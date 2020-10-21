<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Akeeba\LoginGuard\Admin\Model\Tfa;
use FOF30\Encrypt\Totp;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use SMSApi\Client;
use SMSApi\Api\SmsFactory;

// Prevent direct access
defined('_JEXEC') or die;

/**
 * Akeeba LoginGuard Plugin for Two Step Verification method "Authentication Code by SMS (SMSAPI.com)"
 *
 * Requires entering a 6-digit code sent to the user through a text message. These codes change automatically every 5 minutes.
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
	 * The TFA method name handled by this plugin
	 *
	 * @var   string
	 */
	private $tfaMethodName = 'smsapi';

	/**
	 * Constructor. Loads the language files as well.
	 *
	 * @param   object  &$subject  The object to observe
	 * @param   array   $config    An optional associative array of configuration settings.
	 *                             Recognized key values include 'name', 'group', 'params', 'language'
	 *                             (this list is not meant to be comprehensive).
	 */
	public function __construct($subject, array $config = array())
	{
		parent::__construct($subject, $config);

		// Load the SMSAPI library
		if (!class_exists('SMSApi\\Client', true))
		{
			# SMS Api
			JLoader::registerNamespace('SMSApi\\', realpath(__DIR__ . '/../plugins/loginguard/smsapi/classes'), false, false, 'psr4');
		}

		// Load the API parameters
		/** @var \Joomla\Registry\Registry $params */
		$params = $this->params;

		$this->username = $params->get('username', null);
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

		return array(
			// Internal code of this TFA method
			'name'          => $this->tfaMethodName,
			// User-facing name for this TFA method
			'display'       => JText::_('PLG_LOGINGUARD_SMSAPI_LBL_DISPLAYEDAS'),
			// Short description of this TFA method displayed to the user
			'shortinfo'     => JText::_('PLG_LOGINGUARD_SMSAPI_LBL_SHORTINFO'),
			// URL to the logo image for this method
			'image'         => 'media/plg_loginguard_smsapi/images/smsapi.svg',
			// Are we allowed to disable it?
			'canDisable'    => true,
			// Are we allowed to have multiple instances of it per user?
			'allowMultiple' => false,
			// URL for help content
			'help_url' => $helpURL,
		);
	}

	/**
	 * Returns the information which allows LoginGuard to render the TFA setup page. This is the page which allows the
	 * user to add or modify a TFA method for their user account. If the record does not correspond to your plugin
	 * return an empty array.
	 *
	 * @param   stdClass  $record  The #__loginguard_tfa record currently selected by the user.
	 *
	 * @return  array
	 */
	public function onLoginGuardTfaGetSetup($record)
	{
		$helpURL  = $this->params->get('helpurl', 'https://github.com/akeeba/loginguard/wiki/SMSAPI');

		// Make sure we are actually meant to handle this method
		if ($record->method != $this->tfaMethodName)
		{
			return array();
		}

		// Load the options from the record (if any)
		$options = $this->_decodeRecordOptions($record);
		$key     = isset($options['key']) ? $options['key'] : '';
		$phone   = isset($options['phone']) ? $options['phone'] : '';

		// If there's a key or phone number in the session use that instead.
		$session = Factory::getSession();
		$key     = $session->get('smsapi.key', $key, 'com_loginguard');
		$phone   = $session->get('smsapi.phone', $phone, 'com_loginguard');

		// Initialize objects
		$totp = new Totp(180, 6, 10);

		// If there's still no key in the options, generate one and save it in the session
		if (empty($key))
		{
			$key = $totp->generateSecret();
			$session->set('smsapi.key', $key, 'com_loginguard');
		}

		$session->set('smsapi.user_id', $record->user_id, 'com_loginguard');

		// If there is no phone we need to show the phone entry page
		if (empty($phone))
		{
			$layoutPath = PluginHelper::getLayoutPath('loginguard', 'smsapi', 'phone');
			ob_start();
			include $layoutPath;
			$html = ob_get_clean();

			return array(
				// Default title if you are setting up this TFA method for the first time
				'default_title'  => JText::_('PLG_LOGINGUARD_SMSAPI_LBL_DISPLAYEDAS'),
				// Custom HTML to display above the TFA setup form
				'pre_message'    => JText::_('PLG_LOGINGUARD_SMSAPI_LBL_SETUP_INSTRUCTIONS'),
				// Heading for displayed tabular data. Typically used to display a list of fixed TFA codes, TOTP setup parameters etc
				'table_heading'  => '',
				// Any tabular data to display (label => custom HTML). See above
				'tabular_data'   => array(),
				// Hidden fields to include in the form (name => value)
				'hidden_data'    => array(),
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
				'help_url' => $helpURL,
			);

		}

		// We have a phone and a key. Send a push message with a new code and ask the user to enter it.
		$this->sendCode($key, $phone);

		return array(
			// Default title if you are setting up this TFA method for the first time
			'default_title'  => JText::_('PLG_LOGINGUARD_SMSAPI_LBL_DISPLAYEDAS'),
			// Custom HTML to display above the TFA setup form
			'pre_message'    => '',
			// Heading for displayed tabular data. Typically used to display a list of fixed TFA codes, TOTP setup parameters etc
			'table_heading'  => '',
			// Any tabular data to display (label => custom HTML). See above
			'tabular_data'   => array(),
			// Hidden fields to include in the form (name => value)
			'hidden_data'    => array(
				'key' => $key,
			),
			// How to render the TFA setup code field. "input" (HTML input element) or "custom" (custom HTML)
			'field_type'     => 'input',
			// The type attribute for the HTML input box. Typically "text" or "password". Use any HTML5 input type.
			'input_type'     => 'number',
			// Pre-filled value for the HTML input box. Typically used for fixed codes, the fixed YubiKey ID etc.
			'input_value'    => '',
			// Placeholder text for the HTML input box. Leave empty if you don't need it.
			'placeholder'    => JText::_('PLG_LOGINGUARD_SMSAPI_LBL_SETUP_PLACEHOLDER'),
			// Label to show above the HTML input box. Leave empty if you don't need it.
			'label'          => JText::_('PLG_LOGINGUARD_SMSAPI_LBL_SETUP_LABEL'),
			// Custom HTML. Only used when field_type = custom.
			'html'           => '',
			// Should I show the submit button (apply the TFA setup)? Only applies in the Add page.
			'show_submit'    => true,
			// onclick handler for the submit button (apply the TFA setup)?
			'submit_onclick' => '',
			// Custom HTML to display below the TFA setup form
			'post_message'   => '',
			// URL for help content
			'help_url' => $helpURL,
		);
	}

	/**
	 * Parse the input from the TFA setup page and return the configuration information to be saved to the database. If
	 * the information is invalid throw a RuntimeException to signal the need to display the editor page again. The
	 * message of the exception will be displayed to the user. If the record does not correspond to your plugin return
	 * an empty array.
	 *
	 * @param   stdClass  $record  The #__loginguard_tfa record currently selected by the user.
	 * @param   JInput    $input   The user input you are going to take into account.
	 *
	 * @return  array  The configuration data to save to the database
	 *
	 * @throws  RuntimeException  In case the validation fails
	 */
	public function onLoginGuardTfaSaveSetup($record, JInput $input)
	{
		// Make sure we are actually meant to handle this method
		if ($record->method != $this->tfaMethodName)
		{
			return array();
		}

		$session = Factory::getSession();

		// Load the options from the record (if any)
		$options = $this->_decodeRecordOptions($record);
		$key     = isset($options['key']) ? $options['key'] : '';
		$phone   = isset($options['phone']) ? $options['phone'] : '';

		// If there is no key in the options fetch one from the session
		if (empty($key))
		{
			$key = $session->get('smsapi.key', null, 'com_loginguard');
		}

		// If there is no key in the options fetch one from the session
		if (empty($phone))
		{
			$phone = $session->get('smsapi.phone', null, 'com_loginguard');
		}

		// If there is still no key in the options throw an error
		if (empty($key))
		{
			throw new RuntimeException(JText::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		// If there is still no phone in the options throw an error
		if (empty($phone))
		{
			throw new RuntimeException(JText::_('JERROR_ALERTNOAUTHOR'), 403);
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
		$totp = new Totp(180, 6, 10);
		$isValid = $totp->checkCode($key, $code);

		if (!$isValid)
		{
			throw new RuntimeException(JText::_('PLG_LOGINGUARD_SMSAPI_ERR_INVALID_CODE'), 500);
		}

		// The code is valid. Unset the key from the session.
		$session->set('totp.key', null, 'com_loginguard');

		// Return the configuration to be serialized
		return array(
			'key'   => $key,
			'phone' => $phone
		);
	}

	/**
	 * Returns the information which allows LoginGuard to render the captive TFA page. This is the page which appears
	 * right after you log in and asks you to validate your login with TFA.
	 *
	 * @param   stdClass  $record  The #__loginguard_tfa record currently selected by the user.
	 *
	 * @return  array
	 */
	public function onLoginGuardTfaCaptive($record)
	{
		// Make sure we are actually meant to handle this method
		if ($record->method != $this->tfaMethodName)
		{
			return array();
		}

		// Load the options from the record (if any)
		$options = $this->_decodeRecordOptions($record);
		$key     = isset($options['key']) ? $options['key'] : '';
		$phone   = isset($options['phone']) ? $options['phone'] : '';
		$helpURL = $this->params->get('helpurl', 'https://github.com/akeeba/loginguard/wiki/SMSAPI');

		// Send a push message with a new code and ask the user to enter it.
		$this->sendCode($key, $phone);

		return array(
			// Custom HTML to display above the TFA form
			'pre_message'  => '',
			// How to render the TFA code field. "input" (HTML input element) or "custom" (custom HTML)
			'field_type'   => 'input',
			// The type attribute for the HTML input box. Typically "text" or "password". Use any HTML5 input type.
			'input_type'   => 'number',
			// Placeholder text for the HTML input box. Leave empty if you don't need it.
			'placeholder'  => JText::_('PLG_LOGINGUARD_SMSAPI_LBL_SETUP_PLACEHOLDER'),
			// Label to show above the HTML input box. Leave empty if you don't need it.
			'label'        => JText::_('PLG_LOGINGUARD_SMSAPI_LBL_SETUP_LABEL'),
			// Custom HTML. Only used when field_type = custom.
			'html'         => '',
			// Custom HTML to display below the TFA form
			'post_message' => '',
			// URL for help content
			'help_url'     => $helpURL,
		);
	}

	/**
	 * Validates the Two Factor Authentication code submitted by the user in the captive Two Step Verification page. If
	 * the record does not correspond to your plugin return FALSE.
	 *
	 * @param   Tfa       $record  The TFA method's record you're validatng against
	 * @param   User      $user    The user record
	 * @param   string    $code    The submitted code
	 *
	 * @return  bool
	 */
	public function onLoginGuardTfaValidate(Tfa $record, User $user, $code)
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
		$key = isset($options['key']) ? $options['key'] : '';

		// If there is no key in the options throw an error
		if (empty($key))
		{
			return false;
		}

		// Check the TFA code for validity
		$totp = new Totp(180, 6, 10);
		return $totp->checkCode($key, $code);
	}

	/**
	 * Decodes the options from a #__loginguard_tfa record into an options object.
	 *
	 * @param   stdClass  $record
	 *
	 * @return  array
	 */
	private function _decodeRecordOptions($record)
	{
		$options = array(
			'key'   => '',
			'phone' => ''
		);

		if (!empty($record->options))
		{
			$recordOptions = $record->options;

			$options = array_merge($options, $recordOptions);
		}

		return $options;
	}

	/**
	 * Creates a new TOTP code based on secret key $key and sends it to the user via SMSAPI to the phone number $token
	 *
	 * @param   string  $key    The TOTP secret key
	 * @param   string  $phone  The phone number with the international prefix
	 * @param   User    $user   The Joomla! user to use
	 *
	 * @return  void
	 *
	 * @throws  Exception  If something goes wrong
	 */
	public function sendCode($key, $phone, User $user = null)
	{
		// Make sure we have a user
		if (!is_object($user) || !($user instanceof User))
		{
			$user = Factory::getUser();
		}

		// Get the API objects
		$totp = new Totp(180, 6, 10);

		$client = new Client($this->username);
		$client->setPasswordHash($this->passwordMD5);
		$smsapi = new SmsFactory;
		$smsapi->setClient($client);

		// Create the list of variable replacements
		$code = $totp->getCode($key);

		$replacements = array(
			'[CODE]'     => $code,
			'[SITENAME]' => Factory::getConfig()->get('sitename'),
			'[SITEURL]'  => Uri::base(),
			'[USERNAME]' => $user->username,
			'[EMAIL]'    => $user->email,
			'[FULLNAME]' => $user->name,
		);

		// Get the title and body of the push message
		$message = JText::_('PLG_LOGINGUARD_SMSAPI_MESSAGE');
		$message = str_ireplace(array_keys($replacements), array_values($replacements), $message);

		// Send the text using the default Sender
		$actionSend = $smsapi->actionSend();
		$actionSend->setTo($phone);
		$actionSend->setText($message);

		$response = $actionSend->execute();
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
	public function onLoginGuardCallback($method)
	{
		if ($method != $this->tfaMethodName)
		{
			return false;
		}

		$app   = Factory::getApplication();
		$input = $app->input;

		// Do I have a phone variable?
		$phone = $input->getString('phone', null);

		if (empty($phone))
		{
			throw new RuntimeException(JText::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		$phone = preg_replace("/[^0-9]/", "", $phone);

		// Set the phone to the session
		$session = Factory::getSession();
		$session->set('smsapi.phone', $phone, 'com_loginguard');

		// Get the User ID for the editor page
		$user_id = $session->get('smsapi.user_id', null, 'com_loginguard');
		$session->set('smsapi.user_id', null, 'com_loginguard');

		// Redirect to the editor page
		$userPart    = empty($user_id) ? '' : ('&user_id=' . $user_id);
		$redirectURL = 'index.php?option=com_loginguard&view=Method&task=add&method=smsapi' . $userPart;

		$app->redirect($redirectURL);

		// Just to make IDEs happy. The application is closed above during the redirection.
		return false;
	}

}
