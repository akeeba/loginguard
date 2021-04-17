<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Joomla\CMS\Application\CliApplication;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;

// Prevent direct access
defined('_JEXEC') || die;

/**
 * Akeeba LoginGuard System Plugin
 *
 * Implements the captive Two Step Verification page
 */
class PlgSystemLoginguard extends CMSPlugin
{
	/**
	 * Are we enabled, all requirements met etc?
	 *
	 * @var   bool
	 */
	public $enabled = true;

	/**
	 * Application object.
	 *
	 * @var    CMSApplication
	 * @since  5.0.0
	 */
	protected $app;

	/**
	 * Database object.
	 *
	 * @var    JDatabaseDriver
	 * @since  5.0.0
	 */
	protected $db;

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
	 * @param   array    $config   An optional associative array of configuration settings.
	 *                             Recognized key values include 'name', 'group', 'params', 'language'
	 *                             (this list is not meant to be comprehensive).
	 */
	public function __construct($subject, array $config = [])
	{
		parent::__construct($subject, $config);

		// Make sure Akeeba LoginGuard is installed
		if (
			!file_exists(JPATH_ADMINISTRATOR . '/components/com_loginguard') ||
			!ComponentHelper::isInstalled('com_loginguard') ||
			!ComponentHelper::isEnabled('com_loginguard')
		)
		{
			$this->enabled = false;

			return;
		}

		// PHP version check
		$this->enabled = version_compare(PHP_VERSION, '7.2.0', 'ge');

		$cParams = ComponentHelper::getParams('com_loginguard');

		// Parse settings
		$this->neverTSVUserGroups = $cParams->get('neverTSVUserGroups', []);

		if (!is_array($this->neverTSVUserGroups))
		{
			$this->neverTSVUserGroups = [];
		}

		$this->forceTSVUserGroups = $cParams->get('forceTSVUserGroups', []);

		if (!is_array($this->forceTSVUserGroups))
		{
			$this->forceTSVUserGroups = [];
		}

		JLoader::register('LoginGuardHelperTfa', JPATH_SITE . '/components/com_loginguard/helpers/tfa.php');
	}

	/**
	 * MAGIC TRICK. If you have enabled Joomla's Privacy Consent you'd end up with an infinite redirection loop. That's
	 * because Joomla! did a partial copy of my original research code on captive Joomla! logins. They did no implement
	 * configurable exceptions since they do not know or care about third party extensions -- even when it's the same
	 * extensions they copied code from.
	 *
	 * On Joomla 3 we can snuff out the onAfterRoute event handler for the privacy plugin. This is what we do here.
	 *
	 * On Joomla 4 all event handlers are Closure objects which cannot be inspected with Reflection (because screw you,
	 * PHP developer, that's why!) so we have to instead disable the redirection IF we are on Joomla 4 AND the privacy
	 * plugin is enabled AND the user hasn't consented yet.
	 *
	 * @throws  Exception
	 * @since   3.0.3
	 */
	public function onAfterInitialise()
	{
		// This onyl works on Joomla 3
		if (version_compare(JVERSION, '3.9999.9999', 'ge'))
		{
			return;
		}

		$option = $this->app->input->getCmd('option', null);

		/**
		 * If we're going to need to perform a redirection and Joomla's privacy consent is also enabled we will snuff it
		 * so it doesn't cause an infinite redirection loop. The correct solution would be Joomla! allowing users to
		 * specify exceptions to the captive login but having its developers think of that requires them to use the CMS
		 * in the real world which, as we know, is not the case. No problem. I've made a career working around the
		 * Joomla! core, haven't I?
		 */
		if ($this->willNeedRedirect() || ($option == 'com_loginguard'))
		{
			$this->snuffJoomlaPrivacyConsent();
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
		if (!$this->willNeedRedirect())
		{
			return;
		}

		// Make sure we are logged in
		try
		{
			// Joomla! 3: make sure the user identity is loaded. This MUST NOT be called in Joomla! 4, though.
			if (version_compare(JVERSION, '3.99999.99999', 'lt'))
			{
				$this->app->loadIdentity();
			}

			$user = $this->app->getIdentity();
		}
		catch (Exception $e)
		{
			// This would happen if we are in CLI or under an old Joomla! version. Either case is not supported.
			return;
		}

		/**
		 * If you have enabled Joomla's Privacy Consent you'd end up with an infinite redirection loop. That's because
		 * Joomla! did a partial copy of my original research code on captive Joomla! logins. They did not implement
		 * configurable exceptions since they do not know or care about third party extensions -- even when it's the
		 * same extensions they copied code from.
		 *
		 * While on Joomla 3 we can snuff out the onAfterRoute event handler for the privacy plugin, this is not
		 * possible on Joomla 4 because all event handlers are Closure objects which cannot be inspected with Reflection
		 * (because screw you, PHP developer, that's why!) so we have to instead disable the redirection IF we are on
		 * Joomla 4 AND the privacy plugin is enabled AND the user hasn't consented yet.
		 */
		if (
			version_compare(JVERSION, '3.999.999', 'gt')
			&& PluginHelper::isEnabled('system', 'privacyconsent')
			&& !$this->isUserConsented((int) $user->id)
		)
		{
			return;
		}

		$session = $this->app->getSession();

		// We only kick in when the user has actually set up TFA or must definitely enable TFA.
		$needsTFA     = $this->needsTFA($user);
		$disabledTSV  = $this->disabledTSV($user);
		$mandatoryTSV = $this->mandatoryTSV($user);

		if ($needsTFA && !$disabledTSV)
		{
			// Save the current URL, but only if we haven't saved a URL or if the saved URL is NOT internal to the site.
			$return_url = $session->get('com_loginguard.return_url', '');

			if (empty($return_url) || !Uri::isInternal($return_url))
			{
				$session->set('com_loginguard.return_url', Uri::getInstance()->toString([
					'scheme', 'user', 'pass', 'host', 'port', 'path', 'query', 'fragment',
				]));
			}

			// Redirect
			$url = Route::_('index.php?option=com_loginguard&view=captive', false);
			$this->app->redirect($url, 307);

			return;
		}

		// If we're here someone just logged in but does not have TFA set up. Just flag him as logged in and continue.
		$session->set('com_loginguard.tfa_checked', 1);

		// If we don't have TFA set up yet AND the user plugin had set up a redirection we will honour it
		$redirectionUrl = $session->get('com_loginguard.postloginredirect', null);

		// If the user is in a group that requires TFA we will redirect them to the setup page
		if (!$needsTFA && $mandatoryTSV)
		{
			// First unset the flag to make sure the redirection will apply until they conform to the mandatory TFA
			$session->set('com_loginguard.tfa_checked', 0);

			// Now set a flag which forces rechecking TSV for this user
			$session->set('com_loginguard.recheck_mandatory_tsv', 1);

			// Then redirect them to the setup page
			$this->redirectToTSVSetup();
		}

		if (!$needsTFA && $redirectionUrl && !$disabledTSV)
		{
			$session->set('com_loginguard.postloginredirect', null);

			Factory::getApplication()->redirect($redirectionUrl);
		}
	}

	/**
	 * Hooks on the Joomla! login event. Detects silent logins and disables the Two Step Verification captive page in
	 * this case.
	 *
	 * @param   array  $options  Passed by Joomla. user: a User object; responseType: string, authentication response
	 *                           type.
	 */
	public function onUserAfterLogin($options)
	{
		$session = $this->app->getSession();

		// Always reset the browser ID to avoid session poisoning attacks
		$session->set('com_loginguard.browserId', null);
		$session->set('com_loginguard.browserIdCodeLoaded', false);

		// Should I show 2SV even on silent logins? Default: 1 (yes, show)
		$switch = $this->params->get('2svonsilent', 1);

		if ($switch == 1)
		{
			return;
		}

		// Make sure I have a valid user
		/** @var User $user */
		$user = $options['user'];

		if (!is_object($user) || !($user instanceof User))
		{
			return;
		}

		// Make sure this is a silent login
		if (!$this->isSilentLogin($user, $options['responseType']))
		{
			return;
		}

		// Set the flag indicating that 2SV is already checked.
		$session->set('com_loginguard.tfa_checked', 1);
	}

	/**
	 * Checks if we are running under a CLI script or inside an administrator session
	 *
	 * @return  array
	 *
	 * @throws  Exception
	 */
	protected function isCliAdmin()
	{
		$isAdmin = false;

		try
		{
			if (is_null(Factory::$application))
			{
				$isCLI = true;
			}
			else
			{
				$isCLI = $this->app instanceof Exception || $this->app instanceof CliApplication;
			}
		}
		catch (Exception $e)
		{
			$isCLI = true;
		}

		if (!$isCLI && Factory::$application)
		{
			$isAdmin = Factory::getApplication()->isClient('administrator');
		}

		return [$isCLI, $isAdmin];
	}

	/**
	 * Does the current user need to complete TFA authentication before being allowed to access the site?
	 *
	 * @param   User  $user  The user object
	 *
	 * @return  bool
	 */
	private function needsTFA(User $user)
	{
		// Get the user's TFA records
		$records = LoginGuardHelperTfa::getUserTfaRecords($user->id);

		// No TFA methods? Then we obviously don't need to display a captive login page.
		if (count($records) < 1)
		{
			return false;
		}

		// Let's get a list of all currently active TFA methods
		$tfaMethods = LoginGuardHelperTfa::getTfaMethods();

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
	 * Does the user belong in a group indicating TSV should be disabled for them?
	 *
	 * @param   User  $user
	 *
	 * @return  bool
	 */
	private function disabledTSV(User $user)
	{
		// If the user belongs to a "never check for TSV" user group they are exempt from TSV
		$userGroups             = $user->getAuthorisedGroups();
		$belongsToTSVUserGroups = array_intersect($this->neverTSVUserGroups, $userGroups);

		return !empty($belongsToTSVUserGroups);
	}

	/**
	 * Does the user belong in a group indicating TSV is required for them?
	 *
	 * @param   User  $user
	 *
	 * @return  bool
	 */
	private function mandatoryTSV(User $user)
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
		// If we are in a LoginGuard page do not redirect
		$option = strtolower($this->app->input->getCmd('option'));

		if ($option == 'com_loginguard')
		{
			return;
		}

		// Otherwise redirect to the LoginGuard TSV setup page after enqueueing a message
		$url = Route::_('index.php?option=com_loginguard&view=Methods');
		$this->app->redirect($url, 307);
	}

	/**
	 * Check whether we'll need to do a redirection to the captive page.
	 *
	 * @return  bool
	 *
	 * @throws  Exception
	 * @since   3.0.4
	 *
	 */
	private function willNeedRedirect()
	{
		// If the requirements are not met do not proceed
		if (!$this->enabled)
		{
			return false;
		}

		$session = $this->app->getSession();

		/**
		 * We only kick in if the session flag is not set AND the user is not flagged for monitoring of their TSV status
		 *
		 * In case a user belongs to a group which requires TSV to be always enabled and they logged in without having
		 * TSV enabled we have the recheck flag. This prevents the user from enabling and immediately disabling TSV,
		 * circumventing the requirement for TSV.
		 */
		$tfaChecked = $session->get('com_loginguard.tfa_checked', 0) != 0;
		$tfaRecheck = $session->get('com_loginguard.recheck_mandatory_tsv', 0) != 0;

		if ($tfaChecked && !$tfaRecheck)
		{
			return false;
		}

		// Make sure we are logged in
		try
		{
			// Joomla! 3: make sure the user identity is loaded. This MUST NOT be called in Joomla! 4, though.
			if (version_compare(JVERSION, '3.99999.99999', 'lt'))
			{
				$this->app->loadIdentity();
			}

			$user = $this->app->getIdentity() ?: Factory::getUser();
		}
		catch (Exception $e)
		{
			// This would happen if we are in CLI or under an old Joomla! version. Either case is not supported.
			return false;
		}

		// The plugin only needs to kick in when you have logged in
		if ($user->get('guest'))
		{
			return false;
		}

		/**
		 * Special handling when the requireReset flag is set on the user account.
		 *
		 * Joomla checks the requireReset flag on the user account in the application's doExecute method. If it is set
		 * it will call CMSApplication::checkUserRequireReset() which issues a redirection for the user to reset their
		 * password.
		 *
		 * One easy option here is to say "if the user must reset their password don't show the 2SV captive page"
		 * Unfortunately, that would be a bad idea because of the naive and insecure manner Joomla goes about the forced
		 * password reset. Instead of going through the actual password reset (“Forgot your password?”) page it instead
		 * redirects the user the user profile editor page! This allows the logged in user to view and change everything
		 * in the user profile, including disabling and changing the 2SV options. Considering that forced password reset
		 * is meant to be primarily used when we suspect that the user's account has been compromised this creates a
		 * grave security risk. The attacker in possession of the username and password can trick a Super User into
		 * forcing a password reset, thereby allowing them to bypass Two Step Verification and take over the user
		 * account.
		 *
		 * Instead, we unset the requireReset user flag for the duration of the page load when this method here is
		 * called. This prevents Joomla from redirecting. As a result you need to go through Two Step Verification as
		 * per usual. Once you do that the tfa_checked flag is set in the session and this method never reaches this
		 * point of execution where we unset the requireReset flag. Therefore Joomla now sees the requireReset flag and
		 * shows you the user profile edit page. Now it's safe to do so since you have already proven your identity by
		 * means of Two Step Verification i.e. there's no doubt we should let you make any kind of user account change.
		 *
		 * @see \Joomla\CMS\Application\SiteApplication::doExecute()
		 * @see \Joomla\CMS\Application\CMSApplication::checkUserRequireReset()
		 */
		if ($user->get('requireReset', 0))
		{
			$user->set('requireReset', 0);
		}

		[$isCLI, $isAdmin] = $this->isCliAdmin();

		// TFA is not applicable under CLI
		if ($isCLI)
		{
			return false;
		}

		// If we are in the administrator section we only kick in when the user has backend access privileges
		if ($isAdmin && !$user->authorise('core.login.admin'))
		{
			return false;
		}

		$needsTFA = $this->needsTFA($user);

		if ($tfaChecked && $tfaRecheck && $needsTFA)
		{
			return false;
		}

		// We only kick in if the option and task are not the ones of the captive page
		$fallbackView = version_compare(JVERSION, '3.999.999', 'ge')
			? $this->app->input->getCmd('controller', '')
			: '';
		$option       = strtolower($this->app->input->getCmd('option'));
		$task         = strtolower($this->app->input->getCmd('task'));
		$view         = strtolower($this->app->input->getCmd('view', $fallbackView));

		if (strpos($task, '.') !== false)
		{
			$parts = explode('.', $task);
			$view  = ($parts[0] ?? $view) ?: $view;
			$task  = ($parts[1] ?? $task) ?: $task;
		}

		if ($option == 'com_loginguard')
		{
			// In case someone gets any funny ideas...
			$this->app->input->set('tmpl', 'index');
			$this->app->input->set('format', 'html');
			$this->app->input->set('layout', null);

			if (empty($view) && (strpos($task, '.') !== false))
			{
				[$view, $task] = explode('.', $task, 2);
			}

			// The captive login page is always allowed
			if ($view === 'captive')
			{
				return false;
			}

			// These views are only allowed if you do not have 2SV enabled *or* if you have already logged in.
			if (!$needsTFA && in_array($view, ['ajax', 'method', 'methods']))
			{
				return false;
			}
		}

		// Allow the frontend user to log out (in case they forgot their TFA code or something)
		if (!$isAdmin && ($option == 'com_users') && ($view == 'user') && ($task == 'logout'))
		{
			return false;
		}

		// Allow the backend user to log out (in case they forgot their TFA code or something)
		if ($isAdmin && ($option == 'com_login') && ($task == 'logout'))
		{
			return false;
		}

		/**
		 * Allow com_ajax. This is required for cookie acceptance in the following scenario. Your session has expired,
		 * therefore you need to re-apply TFA. Moreover, your cookie acceptance cookie has also expired and you need to
		 * accept the site's cookies again.
		 */
		if ($option == 'com_ajax')
		{
			return false;
		}

		return true;
	}

	/**
	 * Kills the Joomla Privacy Consent plugin when we are showing the Two Step Verification.
	 *
	 * JPC uses captive login code copied from our DataCompliance component. However, they removed the exceptions we
	 * have for other captive logins. As a result the JPC captive login interfered with LoginGuard's captive login,
	 * causing an infinite redirection.
	 *
	 * Due to complete lack of support for exceptions, this method here does something evil. It hunts down the observer
	 * (plugin hook) installed by the JPC plugin and removes it from the loaded plugins. This prevents the redirection
	 * of the captive login. THIS IS NOT THE BEST WAY TO DO THINGS. You should NOT ever, EVER!!!! copy this code. I am
	 * someone who has spent 15+ years dealing with Joomla's core code and I know what I'm doing, why I'm doing it and,
	 * most importantly, how it can possibly break. don't go about merrily copying this code if you do not understand
	 * how Joomla event dispatching works. You'll break shit and I'm not to blame. Thank you!
	 *
	 * @throws ReflectionException
	 * @since  3.0.4
	 */
	private function snuffJoomlaPrivacyConsent()
	{
		// The broken Joomla! consent plugin is not activated
		if (!PluginHelper::isEnabled('system', 'privacyconsent'))
		{
			return;
		}

		// Get the events dispatcher and find which observer is the offending plugin
		$dispatcher    = JEventDispatcher::getInstance();
		$refDispatcher = new ReflectionObject($dispatcher);
		$refObservers  = $refDispatcher->getProperty('_observers');
		$refObservers->setAccessible(true);
		$observers = $refObservers->getValue($dispatcher);

		$jConsentObserverId = 0;

		foreach ($observers as $id => $o)
		{
			if (!is_object($o))
			{
				continue;
			}

			if ($o instanceof PlgSystemPrivacyconsent)
			{
				$jConsentObserverId = $id;

				break;
			}
		}

		// Nope. Cannot find the offending plugin.
		if ($jConsentObserverId == 0)
		{
			return;
		}

		// Now we need to remove the offending plugin from the onAfterRoute event.
		$refMethods = $refDispatcher->getProperty('_methods');
		$refMethods->setAccessible(true);
		$methods = $refMethods->getValue($dispatcher);

		$methods['onafterroute'] = array_filter($methods['onafterroute'], function ($id) use ($jConsentObserverId) {
			return $id != $jConsentObserverId;
		});
		$refMethods->setValue($dispatcher, $methods);
	}

	/**
	 * Suppress Two Step Verification when Joomla performs a silent login (cookie, social login / single sign-on, GMail,
	 * LDAP). In these cases the login risk has been managed externally.
	 *
	 * For your reference, the Joomla authentication response types are as follows:
	 *
	 * - Joomla: username and password login. We recommend using 2SV with it.
	 * - Cookie: "Remember Me" cookie with a secure, single use token and other safeguards for the user session.
	 * - GMail: login with GMail credentials (probably no longer works)
	 * - LDAP: Joomla's LDAP plugin
	 * - SocialLogin: Akeeba Social Login (login with Facebook etc)
	 *
	 * @param   User    $user
	 * @param   string  $responseType
	 *
	 * @return  bool
	 *
	 * @since   3.1.0
	 */
	private function isSilentLogin(User $user, $responseType)
	{
		// Fail early if the user is not properly logged in.
		if (!is_object($user) || $user->guest)
		{
			return false;
		}

		// Get the custom Joomla login responses we will consider "silent"
		$rawCustomResponses = $this->params->get('silentresponses', '');
		$customResponses    = explode(',', $rawCustomResponses);
		$customResponses    = array_map('trim', $customResponses);
		$customResponses    = array_filter($customResponses, function ($x) {
			return !empty($x);
		});
		$silentResponses    = array_unique($customResponses);

		// If all else fails, use our default list (Joomla's Remember Me cookie and Akeeba SocialLogin)
		if (empty($silentResponses))
		{
			$silentResponses = ['cookie', 'sociallogin', 'passwordless'];
		}

		// Is it a silent login after all?
		if (is_string($responseType) && !empty($responseType) && in_array(strtolower($responseType), $silentResponses))
		{
			return true;
		}

		return false;
	}

	/**
	 * Method to check if the given user has consented yet
	 *
	 * @param   integer  $userId  ID of uer to check
	 *
	 * @return  boolean
	 *
	 * @since   3.9.0
	 */
	private function isUserConsented(int $userId): bool
	{
		// Guest user? WTF!
		if (empty($userId))
		{
			return true;
		}

		$userId = (int) $userId;
		$db     = $this->db;
		$query  = $db->getQuery(true);

		$query->select('COUNT(*)')
			->from($db->quoteName('#__privacy_consents'))
			->where($db->quoteName('user_id') . ' = :userid')
			->where($db->quoteName('subject') . ' = ' . $db->quote('PLG_SYSTEM_PRIVACYCONSENT_SUBJECT'))
			->where($db->quoteName('state') . ' = 1')
			->bind(':userid', $userId, \Joomla\Database\ParameterType::INTEGER);
		$db->setQuery($query);

		return (int) $db->loadResult() > 0;
	}
}
