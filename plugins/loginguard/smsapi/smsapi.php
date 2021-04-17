<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
defined('_JEXEC') || die;

JLoader::register('LoginGuardAuthenticator', JPATH_ADMINISTRATOR . '/components/com_loginguard/helpers/authenticator.php');

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Router\Route;
use Joomla\Registry\Registry;
use LoginGuardAuthenticator as Totp;
use LoginGuardTableTfa as Tfa;
use Joomla\CMS\Input\Input;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use SMSApi\Api\SmsFactory;
use SMSApi\Client;
use SMSApi\Exception\ActionException;
use SMSApi\Exception\ClientException;
use SMSApi\Exception\HostException;

/**
 * Akeeba LoginGuard Plugin for Two Step Verification method "Authentication Code by SMS (SMSAPI.com)"
 *
 * Requires entering a 6-digit code sent to the user through a text message. These codes change automatically every 5
 * minutes.
 */
class PlgLoginguardSmsapi extends CMSPlugin
{
	/**
	 * The SMSAPI.com username.
	 *
	 * @var   string
	 */
	public $username;

	/**
	 * The SMSAPI.com API Password in MD5.
	 *
	 * @var   string
	 */
	public $passwordMD5;

	/**
	 * @var CMSApplication
	 */
	protected $app;

	/**
	 * The TFA method name handled by this plugin
	 *
	 * @var   string
	 */
	private $tfaMethodName = 'smsapi';

	/**
	 * Constructor. Loads the language files as well.
	 *
	 * @param   object  &$subject  The object to observe
	 * @param   array    $config   An optional associative array of configuration settings.
	 *                             Recognized key values include 'name', 'group', 'params', 'language'
	 *                             (this list is not meant to be comprehensive).
	 */
	public function __construct($subject, array $config = [])
	{
		parent::__construct($subject, $config);

		// Load the SMSAPI library
		if (!class_exists('SMSApi\\Client', true))
		{
			# SMS Api
			if (version_compare(JVERSION, '3.99999.99999', 'le'))
			{
				JLoader::registerNamespace('SMSApi\\', realpath(__DIR__ . '/classes'), false, false, 'psr4');
			}
			else
			{
				JLoader::registerNamespace('SMSApi\\', realpath(__DIR__ . '/classes'));
			}
		}

		// Load the API parameters
		/** @var Registry $params */
		$params = $this->params;

		$this->username    = $params->get('username', null);
		$this->passwordMD5 = $params->get('password', null);

		// Load the language files
		$this->loadLanguage();
	}

	/**
	 * Gets the identity of this TFA method
	 *
	 * @return  array|false
	 */
	public function onLoginGuardTfaGetMethod()
	{
		// This plugin is disabled if you haven't configured it yet
		if (empty($this->passwordMD5) || empty($this->username))
		{
			return false;
		}

		$helpURL = $this->params->get('helpurl', 'https://github.com/akeeba/loginguard/wiki/SMSAPI');

		return [
			// Internal code of this TFA method
			'name'          => $this->tfaMethodName,
			// User-facing name for this TFA method
			'display'       => Text::_('PLG_LOGINGUARD_SMSAPI_LBL_DISPLAYEDAS'),
			// Short description of this TFA method displayed to the user
			'shortinfo'     => Text::_('PLG_LOGINGUARD_SMSAPI_LBL_SHORTINFO'),
			// URL to the logo image for this method
			'image'         => 'media/plg_loginguard_smsapi/images/smsapi.svg',
			// Are we allowed to disable it?
			'canDisable'    => true,
			// Are we allowed to have multiple instances of it per user?
			'allowMultiple' => false,
			// URL for help content
			'help_url'      => $helpURL,
		];
	}

	/**
	 * Returns the information which allows LoginGuard to render the TFA setup page. This is the page which allows the
	 * user to add or modify a TFA method for their user account. If the record does not correspond to your plugin
	 * return an empty array.
	 *
	 * @param   Tfa  $record  The #__loginguard_tfa record currently selected by the user.
	 *
	 * @return  array
	 */
	public function onLoginGuardTfaGetSetup(Tfa $record): array
	{
		$helpURL = $this->params->get('helpurl', 'https://github.com/akeeba/loginguard/wiki/SMSAPI');

		// Make sure we are actually meant to handle this method
		if ($record->method != $this->tfaMethodName)
		{
			return [];
		}

		// Load the options from the record (if any)
		$options = $this->_decodeRecordOptions($record);
		$key     = $options['key'] ?? '';
		$phone   = $options['phone'] ?? '';
		$session = $this->app->getSession();

		// If there's a key or phone number in the session use that instead.
		$key   = $session->get('com_loginguard.smsapi.key', $key);
		$phone = $session->get('com_loginguard.smsapi.phone', $phone);

		// Initialize objects
		$totp = new Totp(180, 6, 20);

		// If there's still no key in the options, generate one and save it in the session
		if (empty($key))
		{
			$key = $totp->generateSecret();
			$session->set('com_loginguard.smsapi.key', $key);
		}

		$session->set('com_loginguard.smsapi.user_id', $record->user_id);

		// We have a phone and a key. Send an SMS message with a new code and ask the user to enter it.
		try
		{
			if (!empty($phone))
			{
				$this->sendCode($key, $phone);
			}
		}
		catch (Exception $e)
		{
			Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');

			$phone = null;

			$session->set('com_loginguard.smsapi.phone', null);
		}

		// If there is no phone we need to show the phone entry page
		if (empty($phone))
		{
			$layoutPath = PluginHelper::getLayoutPath('loginguard', 'smsapi', 'phone');
			ob_start();
			include $layoutPath;
			$html = ob_get_clean();

			return [
				// Default title if you are setting up this TFA method for the first time
				'default_title'  => Text::_('PLG_LOGINGUARD_SMSAPI_LBL_DISPLAYEDAS'),
				// Custom HTML to display above the TFA setup form
				'pre_message'    => Text::_('PLG_LOGINGUARD_SMSAPI_LBL_SETUP_INSTRUCTIONS'),
				// Heading for displayed tabular data. Typically used to display a list of fixed TFA codes, TOTP setup parameters etc
				'table_heading'  => '',
				// Any tabular data to display (label => custom HTML). See above
				'tabular_data'   => [],
				// Hidden fields to include in the form (name => value)
				'hidden_data'    => [],
				// How to render the TFA setup code field. "input" (HTML input element) or "custom" (custom HTML)
				'field_type'     => 'custom',
				// The type attribute for the HTML input box. Typically "text" or "password". Use any HTML5 input type.
				'input_type'     => '',
				// Pre-filled value for the HTML input box. Typically used for fixed codes, the fixed YubiKey ID etc.
				'input_value'    => '',
				// Placeholder text for the HTML input box. Leave empty if you don't need it.
				'placeholder'    => '',
				// Label to show above the HTML input box. Leave empty if you don't need it.
				'label'          => '',
				// Custom HTML. Only used when field_type = custom.
				'html'           => $html,
				// Should I show the submit button (apply the TFA setup)? Only applies in the Add page.
				'show_submit'    => false,
				// onclick handler for the submit button (apply the TFA setup)?
				'submit_onclick' => '',
				// Custom HTML to display below the TFA setup form
				'post_message'   => '',
				// URL for help content
				'help_url'       => $helpURL,
			];

		}

		return [
			// Default title if you are setting up this TFA method for the first time
			'default_title'  => Text::_('PLG_LOGINGUARD_SMSAPI_LBL_DISPLAYEDAS'),
			// Custom HTML to display above the TFA setup form
			'pre_message'    => '',
			// Heading for displayed tabular data. Typically used to display a list of fixed TFA codes, TOTP setup parameters etc
			'table_heading'  => '',
			// Any tabular data to display (label => custom HTML). See above
			'tabular_data'   => [],
			// Hidden fields to include in the form (name => value)
			'hidden_data'    => [
				'key' => $key,
			],
			// How to render the TFA setup code field. "input" (HTML input element) or "custom" (custom HTML)
			'field_type'     => 'input',
			// The type attribute for the HTML input box. Typically "text" or "password". Use any HTML5 input type.
			'input_type'     => 'number',
			// Pre-filled value for the HTML input box. Typically used for fixed codes, the fixed YubiKey ID etc.
			'input_value'    => '',
			// Placeholder text for the HTML input box. Leave empty if you don't need it.
			'placeholder'    => Text::_('PLG_LOGINGUARD_SMSAPI_LBL_SETUP_PLACEHOLDER'),
			// Label to show above the HTML input box. Leave empty if you don't need it.
			'label'          => Text::_('PLG_LOGINGUARD_SMSAPI_LBL_SETUP_LABEL'),
			// Custom HTML. Only used when field_type = custom.
			'html'           => '',
			// Should I show the submit button (apply the TFA setup)? Only applies in the Add page.
			'show_submit'    => true,
			// onclick handler for the submit button (apply the TFA setup)?
			'submit_onclick' => '',
			// Custom HTML to display below the TFA setup form
			'post_message'   => '',
			// URL for help content
			'help_url'       => $helpURL,
		];
	}

	/**
	 * Parse the input from the TFA setup page and return the configuration information to be saved to the database. If
	 * the information is invalid throw a RuntimeException to signal the need to display the editor page again. The
	 * message of the exception will be displayed to the user. If the record does not correspond to your plugin return
	 * an empty array.
	 *
	 * @param   LoginGuardTableTfa  $record  The #__loginguard_tfa record currently selected by the user.
	 * @param   Input               $input   The user input you are going to take into account.
	 *
	 * @return  array  The configuration data to save to the database
	 *
	 */
	public function onLoginGuardTfaSaveSetup(Tfa $record, Input $input): array
	{
		// Make sure we are actually meant to handle this method
		if ($record->method != $this->tfaMethodName)
		{
			return [];
		}

		// Load the options from the record (if any)
		$options = $this->_decodeRecordOptions($record);
		$key     = $options['key'] ?? '';
		$phone   = $options['phone'] ?? '';
		$session = $this->app->getSession();

		// If there is no key in the options fetch one from the session
		if (empty($key))
		{
			$key = $session->get('com_loginguard.smsapi.key', null);
		}

		// If there is no key in the options fetch one from the session
		if (empty($phone))
		{
			$phone = $session->get('com_loginguard.smsapi.phone', null);
		}

		// If there is still no key in the options throw an error
		if (empty($key))
		{
			throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		// If there is still no phone in the options throw an error
		if (empty($phone))
		{
			throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		/**
		 * If the code is empty but the key already existed in $options someone is simply changing the title / default
		 * method status. We can allow this and stop checking anything else now.
		 */
		$code = $input->getInt('code');

		if (empty($code) && !empty($optionsKey))
		{
			return $options;
		}

		// In any other case validate the submitted code
		$totp    = new Totp(180, 6, 20);
		$isValid = $totp->checkCode($key, $code);

		if (!$isValid)
		{
			throw new RuntimeException(Text::_('PLG_LOGINGUARD_SMSAPI_ERR_INVALID_CODE'), 500);
		}

		// The code is valid. Unset the key from the session.
		$session->set('com_loginguard.totp.key', null);

		// Return the configuration to be serialized
		return [
			'key'   => $key,
			'phone' => $phone,
		];
	}

	/**
	 * Returns the information which allows LoginGuard to render the captive TFA page. This is the page which appears
	 * right after you log in and asks you to validate your login with TFA.
	 *
	 * @param   LoginGuardTableTfa  $record  The #__loginguard_tfa record currently selected by the user.
	 *
	 * @return  array
	 */
	public function onLoginGuardTfaCaptive(Tfa $record): array
	{
		// Make sure we are actually meant to handle this method
		if ($record->method != $this->tfaMethodName)
		{
			return [];
		}

		// Load the options from the record (if any)
		$options = $this->_decodeRecordOptions($record);
		$key     = $options['key'] ?? '';
		$phone   = $options['phone'] ?? '';
		$helpURL = $this->params->get('helpurl', 'https://github.com/akeeba/loginguard/wiki/SMSAPI');

		// Send a push message with a new code and ask the user to enter it.
		try
		{
			$this->sendCode($key, $phone);
		}
		catch (Exception $e)
		{
			return [];
		}

		return [
			// Custom HTML to display above the TFA form
			'pre_message'  => '',
			// How to render the TFA code field. "input" (HTML input element) or "custom" (custom HTML)
			'field_type'   => 'input',
			// The type attribute for the HTML input box. Typically "text" or "password". Use any HTML5 input type.
			'input_type'   => 'number',
			// Placeholder text for the HTML input box. Leave empty if you don't need it.
			'placeholder'  => Text::_('PLG_LOGINGUARD_SMSAPI_LBL_SETUP_PLACEHOLDER'),
			// Label to show above the HTML input box. Leave empty if you don't need it.
			'label'        => Text::_('PLG_LOGINGUARD_SMSAPI_LBL_SETUP_LABEL'),
			// Custom HTML. Only used when field_type = custom.
			'html'         => '',
			// Custom HTML to display below the TFA form
			'post_message' => '',
			// URL for help content
			'help_url'     => $helpURL,
		];
	}

	/**
	 * Validates the Two Factor Authentication code submitted by the user in the captive Two Step Verification page. If
	 * the record does not correspond to your plugin return FALSE.
	 *
	 * @param   Tfa     $record  The TFA method's record you're validatng against
	 * @param   User    $user    The user record
	 * @param   string  $code    The submitted code
	 *
	 * @return  bool
	 */
	public function onLoginGuardTfaValidate(Tfa $record, User $user, string $code): bool
	{
		// Make sure we are actually meant to handle this method
		if ($record->method != $this->tfaMethodName)
		{
			return false;
		}

		// Double check the TFA method is for the correct user
		if ($user->id != $record->user_id)
		{
			return false;
		}

		// Load the options from the record (if any)
		$options = $this->_decodeRecordOptions($record);
		$key     = $options['key'] ?? '';

		// If there is no key in the options throw an error
		if (empty($key))
		{
			return false;
		}

		// Check the TFA code for validity
		$totp = new Totp(180, 6, 20);

		return $totp->checkCode($key, $code);
	}

	/**
	 * Creates a new TOTP code based on secret key $key and sends it to the user via SMSAPI to the phone number $token
	 *
	 * @param   string     $key    The TOTP secret key
	 * @param   string     $phone  The phone number with the international prefix
	 * @param   User|null  $user   The Joomla! user to use
	 *
	 * @return  void
	 *
	 * @throws ActionException
	 * @throws ClientException
	 * @throws HostException
	 */
	public function sendCode(string $key, string $phone, User $user = null): void
	{
		static $alreadySent = false;

		if ($alreadySent)
		{
			return;
		}

		// Make sure we have a user
		if (!is_object($user) || !($user instanceof User))
		{
			$user = Factory::getUser();
		}

		// Get the API objects
		$totp = new Totp(180, 6, 20);

		$client = new Client($this->username);
		$client->setPasswordHash($this->passwordMD5);
		$smsapi = new SmsFactory;
		$smsapi->setClient($client);

		// Create the list of variable replacements
		$code = $totp->getCode($key);

		$replacements = [
			'[CODE]'     => $code,
			'[SITENAME]' => $this->app->get('sitename', 'Joomla! Site'),
			'[SITEURL]'  => Uri::base(),
			'[USERNAME]' => $user->username,
			'[EMAIL]'    => $user->email,
			'[FULLNAME]' => $user->name,
		];

		// Get the title and body of the push message
		$message = Text::_('PLG_LOGINGUARD_SMSAPI_MESSAGE');
		$message = str_ireplace(array_keys($replacements), array_values($replacements), $message);

		// Send the text using the default Sender
		$actionSend = $smsapi->actionSend();
		$actionSend->setTo($phone);
		$actionSend->setText($message);

		$response = $actionSend->execute();

		$alreadySent = true;
	}

	/**
	 * Handle the callback.
	 *
	 * When the user enters their phone number they are redirected to this callback. This callback stores the necessary
	 * parameters to the session and redirects the user back to the setup page.
	 *
	 * @param   string  $method  The 2SV method used during the callback.
	 *
	 * @return  bool  Only returns false when this plugin is not supposed to handle the request. Redirects the
	 *                application otherwise (no return value).
	 */
	public function onLoginGuardCallback(string $method): bool
	{
		if ($method != $this->tfaMethodName)
		{
			return false;
		}

		$input   = $this->app->input;
		$session = $this->app->getSession();

		// Do I have a phone variable?
		$phone = $input->getString('phone', null);

		if (empty($phone))
		{
			throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		$phone = preg_replace("/[^0-9]/", "", $phone);

		// Set the phone to the session
		$session->set('com_loginguard.smsapi.phone', $phone);

		// Get the User ID for the editor page
		$user_id = $session->get('com_loginguard.smsapi.user_id', null);
		$session->set('com_loginguard.smsapi.user_id', null);

		// Redirect to the editor page
		$userPart    = empty($user_id) ? '' : ('&user_id=' . $user_id);
		$redirectURL = Route::_('index.php?option=com_loginguard&view=Method&task=add&method=smsapi' . $userPart);

		$this->app->redirect($redirectURL);

		// Just to make IDEs happy. The application is closed above during the redirection.
		return false;
	}

	/**
	 * Decodes the options from a #__loginguard_tfa record into an options object.
	 *
	 * @param   Tfa  $record
	 *
	 * @return  array
	 */
	private function _decodeRecordOptions(Tfa $record): array
	{
		$options = [
			'key'   => '',
			'phone' => '',
		];

		if (!empty($record->options))
		{
			$recordOptions = $record->options;

			$options = array_merge($options, $recordOptions);
		}

		return $options;
	}

}
