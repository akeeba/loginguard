<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Akeeba\LoginGuard\Site\Helper\Tfa;
use Akeeba\LoginGuard\Site\Model\RememberMe;
use FOF40\Container\Container;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Table\Menu;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;

// Prevent direct access
defined('_JEXEC') || die;

/**
 * LoginGuard User Plugin
 *
 * Renders a button linking to the Two Step Verification setup page
 */
class plgUserLoginguard extends CMSPlugin
{
	/**
	 * The component's container object
	 *
	 * @var   Container
	 * @since 2.0.0
	 */
	private $container = null;

	/**
	 * Should this plugin do anything?
	 *
	 * @var   bool
	 * @since 3.1.0
	 */
	private $enabled = true;

	/**
	 * Constructor
	 *
	 * @param   object  &$subject  The object to observe
	 * @param   array    $config   An optional associative array of configuration settings.
	 *                             Recognized key values include 'name', 'group', 'params', 'language'
	 *                             (this list is not meant to be comprehensive).
	 */
	public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);

		// Load FOF
		if (!defined('FOF40_INCLUDED') && !@include_once(JPATH_LIBRARIES . '/fof40/include.php'))
		{
			$this->enabled = false;

			return;
		}

		// Make sure Akeeba LoginGuard is installed
		try
		{
			if (
				!file_exists(JPATH_ADMINISTRATOR . '/components/com_loginguard') ||
				!ComponentHelper::isInstalled('com_loginguard') ||
				!ComponentHelper::isEnabled('com_loginguard')
			)
			{
				throw new RuntimeException('Akeeba LoginGuard is not installed');
			}

			$this->container = Container::getInstance('com_loginguard');
		}
		catch (Exception $e)
		{
			$this->enabled = false;
		}

		// Get a reference to the component's container
		$this->container = Container::getInstance('com_loginguard');

		// PHP version check
		$this->enabled = version_compare(PHP_VERSION, '7.2.0', 'ge');

		$this->loadLanguage();
	}

	/**
	 * Adds additional fields to the user editing form
	 *
	 * @param   Form   $form  The form to be altered.
	 * @param   mixed  $data  The associated data for the form.
	 *
	 * @return  boolean
	 *
	 * @throws  Exception
	 */
	public function onContentPrepareForm($form, $data)
	{
		if (!$this->enabled)
		{
			return true;
		}

		if (!($form instanceof Form))
		{
			throw new InvalidArgumentException('JERROR_NOT_A_FORM');
		}

		// Check we are manipulating a valid form.
		$name = $form->getName();

		if (!in_array($name, ['com_admin.profile', 'com_users.user', 'com_users.profile', 'com_users.registration']))
		{
			return true;
		}

		$layout = Factory::getApplication()->input->getCmd('layout', null);

		/**
		 * Joomla is kinda brain-dead. When we have a menu item to the Edit Profile page it does not push the layout
		 * into the Input (as opposed with option and view) so I have to go in and dig it out myself. Yikes!
		 */
		$itemId = Factory::getApplication()->input->getInt('Itemid');

		if ($itemId)
		{
			try
			{
				/** @var Menu $menuItem */
				$menuItem = Table::getInstance('Menu');
				$menuItem->load($itemId);
				$uri    = new Uri($menuItem->link);
				$layout = $uri->getVar('layout', $layout);
			}
			catch (Exception $e)
			{
			}
		}

		if (!$this->container->platform->isBackend() && !in_array($layout, ['edit', 'default']))
		{
			return true;
		}

		// Get the user ID
		$id = null;

		if (is_array($data))
		{
			$id = $data['id'] ?? null;
		}
		elseif (is_object($data) && is_null($data) && ($data instanceof Registry))
		{
			$id = $data->get('id');
		}
		elseif (is_object($data) && !is_null($data))
		{
			$id = $data->id ?? null;
		}

		$user = Factory::getUser($id);

		// Make sure the loaded user is the correct one
		if ($user->id != $id)
		{
			return true;
		}

		// Make sure I am either editing myself OR I am a Super User AND I'm not editing another Super User
		if (!Tfa::canEditUser($user))
		{
			return true;
		}

		// Add the fields to the form.
		Form::addFormPath(dirname(__FILE__) . '/loginguard');

		// Special handling for profile overview page
		if ($layout == 'default')
		{
			/** @var \Akeeba\LoginGuard\Site\Model\Tfa $tfaModel */
			$tfaModel   = $this->container->factory->model('Tfa')->tmpInstance();
			$tfaMethods = $tfaModel->user_id($id)->get(true);

			/**
			 * We cannot pass a boolean or integer; if it's false/0 Joomla! will display "No information entered". We
			 * cannot use a list field to display it in a human readable format, Joomla! just dumps the raw value if you
			 * use such a field. So all I can do is pass raw text. Um, whatever.
			 */
			$data->loginguard = [
				'hastfa' => ($tfaMethods->count() > 0) ? Text::_('PLG_USER_LOGINGUARD_FIELD_HASTFA_ENABLED') : Text::_('PLG_USER_LOGINGUARD_FIELD_HASTFA_DISABLED'),
			];

			$form->loadFile('list', false);

			return true;
		}

		// Profile edit page
		$form->loadFile('loginguard', false);

		return true;
	}

	/**
	 * Runs after successful login of the user. Used to redirect the user to a page where they can set up their Two Step
	 * Verification after logging in.
	 *
	 * @param   array  $options  Passed by Joomla. user: a User object; responseType: string, authentication response
	 *                           type.
	 */
	public function onUserAfterLogin($options)
	{
		if (!$this->enabled)
		{
			return;
		}

		// Make sure the option to redirect is set
		if (!$this->params->get('redirectonlogin', 1))
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

		/**
		 * If the user already has 2SV enabled and we need to show the captive page we won't redirect them to the 2SV
		 * setup page, of course.
		 */
		if ($this->needsCaptivePage($user, $options['responseType']))
		{
			return;
		}

		/**
		 * If the user has already asked us to not show him the 2SV setup page we have to honour their wish.
		 */
		if ($this->hasDoNotShowAgainFlag($user))
		{
			return;
		}

		// Get the redirection URL to the 2SV setup page or custom redirection per plugin configuration
		$url           = Route::_('index.php?option=com_loginguard&view=Methods&task=display&layout=firsttime', false);
		$configuredUrl = $this->params->get('redirecturl', null);

		if ($configuredUrl)
		{
			$url = $configuredUrl;
		}

		// Prepare to redirect
		Factory::getSession()->set('postloginredirect', $url, 'com_loginguard');
	}

	/**
	 * Fires after the user has logged out
	 *
	 * Used to remove the 2SV Remember Me cookie from the browser.
	 *
	 * @param   array|null  $options
	 *
	 * @return  bool  Always true
	 */
	public function onUserAfterLogout(?array $options): bool
	{
		// Is the Remember Me feature enabled?
		$allowRememberMe = $this->container->params->get('allow_rememberme', 1);

		if ($allowRememberMe)
		{
			return true;
		}

		// Make sure I can get an non-empty username
		if (!is_array($options) || !array_key_exists('username', $options))
		{
			return true;
		}

		$userName = $options['username'] ?? '';

		if (empty($userName))
		{
			return true;
		}

		// Finally, remove the Remember Me cookie
		/** @var RememberMe $rememberModel */
		$rememberModel = $this->container->factory->model('RememberMe');
		$rememberModel->setUsername($userName)->removeCookie();

		return true;
	}

	/**
	 * Remove all user profile information for the given user ID
	 *
	 * Method is called after user data is deleted from the database
	 *
	 * @param   array   $user     Holds the user data
	 * @param   bool    $success  True if user was successfully stored in the database
	 * @param   string  $msg      Message
	 *
	 * @return  bool
	 *
	 * @throws  Exception
	 */
	public function onUserAfterDelete($user, $success, $msg)
	{
		if (!$this->enabled)
		{
			return true;
		}

		if (!$success)
		{
			return false;
		}

		$userId = ArrayHelper::getValue($user, 'id', 0, 'int');

		if (!$userId)
		{
			return true;
		}

		$db = Factory::getDbo();

		// Delete user profile records
		$query = $db->getQuery(true)
			->delete($db->qn('#__user_profiles'))
			->where($db->qn('user_id') . ' = ' . $db->q($userId))
			->where($db->qn('profile_key') . ' LIKE ' . $db->q('loginguard.%', false));

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (Exception $e)
		{
			// No sweat if it failed
		}

		// Delete LoginGuard records
		try
		{
			$query = $db->getQuery(true)
				->delete($db->qn('#__loginguard_tfa'))
				->where($db->qn('user_id') . ' = ' . $db->q($userId));

			$db->setQuery($query)->execute();
		}
		catch (Exception $e)
		{
			// No sweat if it failed
		}

		return true;
	}

	/**
	 * Does the current user need to complete 2FA authentication before allowed to access the site?
	 *
	 * @param   User    $user          The user object we are checking
	 * @param   string  $responseType  The login response type (optional)
	 *
	 * @return  bool
	 */
	private function needsCaptivePage(User $user, $responseType = null)
	{
		// Get the user's 2SV records
		/** @var \Akeeba\LoginGuard\Site\Model\Tfa $tfaModel */
		$tfaModel = $this->container->factory->model('Tfa')->tmpInstance();
		$records  = $tfaModel->user_id($user->id)->get(true);

		// No 2SV methods? Then we obviously don't need to display a captive login page.
		if ($records->count() < 1)
		{
			return false;
		}

		// Let's get a list of all currently active 2SV methods
		$tfaMethods = Tfa::getTfaMethods();

		// If not 2SV method is active we can't really display a captive login page.
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

		// Filter the records based on currently active 2SV methods
		foreach ($records as $record)
		{
			if (in_array($record->method, $methodNames))
			{
				// We found an active method. Show the captive page.
				return true;
			}
		}

		// No viable 2SV method found. We won't show the captive page.
		return false;
	}

	/**
	 * Does the user have a "don't show this again" flag?
	 *
	 * @param   User  $user  The user to check
	 *
	 * @return  bool
	 */
	private function hasDoNotShowAgainFlag(User $user)
	{
		$db    = Factory::getDbo();
		$query = $db->getQuery(true)
			->select($db->qn('profile_value'))
			->from($db->qn('#__user_profiles'))
			->where($db->qn('user_id') . ' = ' . $db->q($user->id))
			->where($db->qn('profile_key') . ' = ' . $db->q('loginguard.dontshow'));

		try
		{
			$result = $db->setQuery($query)->loadResult();
		}
		catch (Exception $e)
		{
			$result = 1;
		}

		return is_null($result) ? false : ($result == 1);
	}
}
