<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Akeeba\LoginGuard\Site\Helper\Tfa;
use FOF30\Container\Container;
use Joomla\CMS\User\User;

// Prevent direct access
defined('_JEXEC') or die;

// Minimum PHP version check
if (!version_compare(PHP_VERSION, '5.4.0', '>='))
{
	return;
}

/**
 * Work around the very broken and completely defunct eAccelerator on PHP 5.4 (or, worse, later versions).
 */
if (function_exists('eaccelerator_info'))
{
	$isBrokenCachingEnabled = true;

	if (function_exists('ini_get') && !ini_get('eaccelerator.enable'))
	{
		$isBrokenCachingEnabled = false;
	}

	if ($isBrokenCachingEnabled)
	{
		/**
		 * I know that this define seems pointless since I am returning. This means that we are exiting the file and
		 * the plugin class isn't defined, so Joomla cannot possibly use it.
		 *
		 * Well, that is how PHP works. Unfortunately, eAccelerator has some "novel" ideas about how to go about it.
		 * For very broken values of "novel". What does it do? It ignores the return and parses the plugin class below.
		 *
		 * You read that right. It ignores ALL THE CODE between here and the class declaration and parses the
		 * class declaration. Therefore the only way to actually NOT load the  plugin when you are using it on a
		 * server where an irresponsible sysadmin has installed and enabled eAccelerator (IT'S END OF LIFE AND BROKEN
		 * PER ITS CREATORS FOR CRYING OUT LOUD) is to define a constant and use it to return from the constructor
		 * method, therefore forcing PHP to return null instead of an object. This prompts Joomla to not do anything
		 * with the plugin.
		 */
		if (!defined('AKEEBA_EACCELERATOR_IS_SO_BORKED_IT_DOES_NOT_EVEN_RETURN'))
		{
			define('AKEEBA_EACCELERATOR_IS_SO_BORKED_IT_DOES_NOT_EVEN_RETURN', 3245);
		}

		return;
	}
}

// Make sure Akeeba LoginGuard is installed
if (!file_exists(JPATH_ADMINISTRATOR . '/components/com_loginguard'))
{
	return;
}

// Load FOF
if (!defined('FOF30_INCLUDED') && !@include_once(JPATH_LIBRARIES . '/fof30/include.php'))
{
	return;
}

/**
 * Akeeba LoginGuard System Plugin
 *
 * Implements the captive Two Step Verification page
 */
class PlgSystemLoginguard extends JPlugin
{
	/**
	 * Are we enabled, all requirements met etc?
	 *
	 * @var   bool
	 */
	public $enabled = true;

	/**
	 * The component's container
	 *
	 * @var   Container
	 * @since 2.0.0
	 */
	private $container = null;

	/**
	 * User groups for which Two Step Verification is never applied
	 *
	 * @var   array
	 * @since 3.0.1
	 */
	private $neverTSVUserGroups = [];

	/**
	 * User groups for which Two Step Verification is mandatory
	 *
	 * @var   array
	 * @since 3.0.1
	 */
	private $forceTSVUserGroups = [];

	/**
	 * Constructor
	 *
	 * @param   object  &$subject  The object to observe
	 * @param   array   $config    An optional associative array of configuration settings.
	 *                             Recognized key values include 'name', 'group', 'params', 'language'
	 *                             (this list is not meant to be comprehensive).
	 */
	public function __construct($subject, array $config = array())
	{
		/**
		 * Required to work around eAccelerator on PHP 5.4 and later.
		 *
		 * PUBLIC SERVICE ANNOUNCEMENT: eAccelerator IS DEFUNCT AND INCOMPATIBLE WITH PHP 5.4 AND ANY LATER VERSION. If
		 * you have it enabled on your server go ahead and uninstall it NOW. It's officially dead since 2012. Thanks.
		 */
		if (defined('AKEEBA_EACCELERATOR_IS_SO_BORKED_IT_DOES_NOT_EVEN_RETURN'))
		{
			return;
		}

		parent::__construct($subject, $config);

		try
		{
			if (!JComponentHelper::isInstalled('com_loginguard') || !JComponentHelper::isEnabled('com_loginguard'))
			{
				$this->enabled = false;
			}
			else
			{
				$this->container = Container::getInstance('com_loginguard');
			}
		}
		catch (Exception $e)
		{
			$this->enabled = false;
		}

		$this->neverTSVUserGroups = $this->container->params->get('neverTSVUserGroups', []);

		if (!is_array($this->neverTSVUserGroups))
		{
			$this->neverTSVUserGroups = [];
		}

		$this->forceTSVUserGroups = $this->container->params->get('forceTSVUserGroups', []);

		if (!is_array($this->forceTSVUserGroups))
		{
			$this->forceTSVUserGroups = [];
		}
	}

	/**
	 * Gets triggered right after Joomla has finished with the SEF routing and before it has the chance to dispatch the
	 * application (load any components).
	 *
	 * @return  void
	 *
	 * @throws  Exception
	 */
	public function onAfterRoute()
	{
		// If the requirements are not met do not proceed
		if (!$this->enabled)
		{
			return;
		}

		// Get the session objects
		try
		{
			$session = JFactory::getSession();
		}
		catch (Exception $e)
		{
			// Can't get access to the session? Must be under CLI which is not supported.
			return;
		}

		/**
		 * We only kick in if the session flag is not set AND the user is not flagged for monitoring of their TSV status
		 *
		 * In case a user belongs to a group which requires TSV to be always enabled and they logged in without having
		 * TSV enabled we have the recheck flag. This prevents the user from enabling and immediately disabling TSV,
		 * circumventing the requirement for TSV.
		 */
		$tfaChecked = $session->get('tfa_checked', 0, 'com_loginguard');
		$tfaRecheck = $session->get('recheck_mandatory_tsv', 0, 'com_loginguard');

		if ($tfaChecked && !$tfaRecheck)
		{
			return;
		}

		// Make sure we are logged in
		try
		{
			$app = JFactory::getApplication();

			// Joomla! 3: make sure the user identity is loaded. This MUST NOT be called in Joomla! 4, though.
			if (version_compare(JVERSION, '3.99999.99999', 'lt'))
			{
				$app->loadIdentity();
			}

			$user = $app->getIdentity();
		}
		catch (\Exception $e)
		{
			// This would happen if we are in CLI or under an old Joomla! version. Either case is not supported.
			return;
		}

		// The plugin only needs to kick in when you have logged in
		if ($user->get('guest'))
		{
			return;
		}
		// Joomla 3.9.0 : leave Joomla handle Privacy Rules, otherwise infinite loop
		if ((version_compare(JVERSION, '3.8.99999', 'gt'))
			&& (!$this->isUserConsented($user->get('id'))) )
		{ // avoid infinite loop
			return;
		} 

		list($isCLI, $isAdmin) = $this->isCliAdmin();

		// TFA is not applicable under CLI
		if ($isCLI)
		{
			return;
		}

		// If we are in the administrator section we only kick in when the user has backend access privileges
		if ($isAdmin && !$user->authorise('core.login.admin'))
		{
			return;
		}

        $needsTFA     = $this->needsTFA($user);

		if ($tfaChecked && $tfaRecheck && $needsTFA)
		{
			return;
		}

		// We only kick in if the option and task are not the ones of the captive page
		$option = strtolower($app->input->getCmd('option'));
		$task = strtolower($app->input->getCmd('task'));
		$view = strtolower($app->input->getCmd('view'));

		if ($option == 'com_loginguard')
		{
			// In case someone gets any funny ideas...
			$app->input->set('tmpl', 'index');
			$app->input->set('format', 'html');
			$app->input->set('layout', null);

			if (empty($view) && (strpos($task, '.') !== false))
			{
				list($view, $task) = explode('.', $task, 2);
			}

			// The captive login page is always allowed
			if ($view === 'captive')
            {
                return;
            }

            // These views are only allowed if you do not have 2SV enabled *or* if you have already logged in.
			if (!$needsTFA && in_array($view, array('ajax', 'method', 'methods')))
			{
				return;
			}
		}

		// Allow the frontend user to log out (in case they forgot their TFA code or something)
		if (!$isAdmin && ($option == 'com_users') && ($task == 'user.logout'))
		{
			return;
		}

		// Allow the backend user to log out (in case they forgot their TFA code or something)
		if ($isAdmin && ($option == 'com_login') && ($task == 'logout'))
		{
			return;
		}

		/**
		 * Allow com_ajax. This is required for cookie acceptance in the following scenario. Your session has expired,
		 * therefore you need to re-apply TFA. Moreover, your cookie acceptance cookie has also expired and you need to
		 * accept the site's cookies again.
		 */
		if ($option == 'com_ajax')
		{
			return;
		}

		// We only kick in when the user has actually set up TFA or must definitely enable TFA.
		$disabledTSV  = $this->disabledTSV($user);
		$mandatoryTSV = $this->mandatoryTSV($user);

		if ($needsTFA && !$disabledTSV)
		{
			// Save the current URL, but only if we haven't saved a URL or if the saved URL is NOT internal to the site.
			$return_url = $session->get('return_url', '', 'com_loginguard');

			if (empty($return_url) || !JUri::isInternal($return_url))
			{
				$session->set('return_url', JUri::getInstance()->toString(array('scheme', 'user', 'pass', 'host', 'port', 'path', 'query', 'fragment')), 'com_loginguard');
			}

			// Redirect
			$url = JRoute::_('index.php?option=com_loginguard&view=captive', false);
			$app->redirect($url, 307);

			return;
		}

		// If we're here someone just logged in but does not have TFA set up. Just flag him as logged in and continue.
		$session->set('tfa_checked', 1, 'com_loginguard');

		// If we don't have TFA set up yet AND the user plugin had set up a redirection we will honour it
		$redirectionUrl = $session->get('postloginredirect', null, 'com_loginguard');

		// If the user is in a group that requires TFA we will redirect them to the setup page
		if (!$needsTFA && $mandatoryTSV)
		{
			// First unset the flag to make sure the redirection will apply until they conform to the mandatory TFA
			$session->set('tfa_checked', 0, 'com_loginguard');

			// Now set a flag which forces rechecking TSV for this user
			$session->set('recheck_mandatory_tsv', 1, 'com_loginguard');

			// Then redirect them to the setup page
			$this->redirectToTSVSetup();
		}

		if (!$needsTFA && $redirectionUrl && !$disabledTSV)
		{
			$session->set('postloginredirect', null, 'com_loginguard');

			JFactory::getApplication()->redirect($redirectionUrl);
		}
	}

	/**
	 * Does the current user need to complete TFA authentication before being allowed to access the site?
	 *
	 * @return  bool
	 */
	private function needsTFA(JUser $user)
	{
		/** @var \Akeeba\LoginGuard\Site\Model\Tfa $tfaModel */
		$tfaModel = $this->container->factory->model('Tfa')->tmpInstance();

		// Get the user's TFA records
		$records = $tfaModel->user_id($user->id)->get(true);

		// No TFA methods? Then we obviously don't need to display a captive login page.
		if ($records->count() < 1)
		{
			return false;
		}

		// Let's get a list of all currently active TFA methods
		$tfaMethods = Tfa::getTfaMethods();

		// If not TFA method is active we can't really display a captive login page.
		if (empty($tfaMethods))
		{
			return false;
		}

		// Get a list of just the method names
		$methodNames = [];

		foreach ($tfaMethods as $tfaMethod)
		{
			$methodNames[] = $tfaMethod['name'];
		}

		// Filter the records based on currently active TFA methods
		foreach ($records as $record)
		{
			if (in_array($record->method, $methodNames))
			{
				// We found an active method. Show the captive page.
				return true;
			}
		}

		// No viable TFA method found. We won't show the captive page.
		return false;
	}

	/**
	 * Checks if we are running under a CLI script or inside an administrator session
	 *
	 * @return  array
	 */
	protected function isCliAdmin()
	{
		$isCLI = false;
		$isAdmin = false;

		try
		{
			if (is_null(\JFactory::$application))
			{
				$isCLI = true;
			}
			else
			{
				$app   = \JFactory::getApplication();
				$isCLI = $app instanceof \Exception || $app instanceof \JApplicationCli;
			}
		}
		catch (\Exception $e)
		{
			$isCLI = true;
		}

		if (!$isCLI && \JFactory::$application)
		{
			if (version_compare(JVERSION, '3.7.0', 'ge'))
			{
				$isAdmin = JFactory::getApplication()->isClient('administrator');
			}
			else
			{
				$isAdmin = JFactory::getApplication()->isAdmin();
			}
		}

		return array($isCLI, $isAdmin);
	}

	/**
	 * Does the user belong in a group indicating TSV should be disabled for them?
	 *
	 * @param   JUser|User $user
	 *
	 * @return  bool
	 */
	private function disabledTSV($user)
	{
		// If the user belongs to a "never check for TSV" user group they are exempt from TSV
		$userGroups             = $user->getAuthorisedGroups();
		$belongsToTSVUserGroups = array_intersect($this->neverTSVUserGroups, $userGroups);

		return !empty($belongsToTSVUserGroups);
	}

	/**
	 * Does the user belong in a group indicating TSV is required for them?
	 *
	 * @param   JUser|User $user
	 *
	 * @return  bool
	 */
	private function mandatoryTSV($user)
	{
		// If the user belongs to a "never check for TSV" user group they are exempt from TSV
		$userGroups             = $user->getAuthorisedGroups();
		$belongsToTSVUserGroups = array_intersect($this->forceTSVUserGroups, $userGroups);

		return !empty($belongsToTSVUserGroups);
	}

	/**
	 * Redirect the user to the Two Step Verification method setup page.
	 *
	 * @return  void
	 *
	 * @since   3.0.1
	 */
	private function redirectToTSVSetup()
	{
		try
		{
			$app = JFactory::getApplication();
		}
		catch (\Exception $e)
		{
			// This would happen if we are in CLI or under an old Joomla! version. Either case is not supported.
			return;
		}

		// If we are in a LoginGuard page do not redirect
		$option = strtolower($app->input->getCmd('option'));

		if ($option == 'com_loginguard')
		{
			return;
		}

		// Otherwise redirect to the LoginGuard TSV setup page after enqueueing a message
		$url = 'index.php?option=com_loginguard&view=Methods';
		$app->redirect($url, 307);
	}
        /* Joomla 3.9.0 : check if User Consented Privacy Rules */
	private function isUserConsented($userId)
	{
            $db = JFactory::getDBo();
		$query = $db->getQuery(true);
		$query->select('COUNT(*)')
			->from('#__privacy_consents')
			->where('user_id = ' . (int) $userId)
			->where('subject = ' . $db->quote('PLG_SYSTEM_PRIVACYCONSENT_SUBJECT'))
			->where('state = 1');
		$db->setQuery($query);

		return (int) $db->loadResult() > 0;
	}
	
}
