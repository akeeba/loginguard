<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Akeeba\LoginGuard\Admin\Model\Tfa;
use FOF40\Container\Container;
use FOF40\Encrypt\Totp;
use FOF40\Input\Input;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;

// Prevent direct access
defined('_JEXEC') || die;

/**
 * Akeeba LoginGuard Plugin for Two Step Verification method "Authentication Code by PushBullet"
 *
 * Requires entering a 6-digit code sent to the user through email. These codes change automatically every 30
 * seconds.
 */
class PlgLoginguardEmail extends CMSPlugin
{
	/**
	 * Generated OTP length. Constant: 6 numeric digits.
	 */
	private const CODE_LENGTH = 6;

	/**
	 * Length of the secret key used for generating the OTPs. Constant: 10 characters.
	 */
	private const SECRET_KEY_LENGTH = 10;

	/**
	 * The TFA method name handled by this plugin
	 *
	 * @var   string
	 */
	private $tfaMethodName = 'email';

	/**
	 * The component's container object
	 *
	 * @var   Container
	 */
	private $container = null;

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

		// Get a reference to the component's container
		$this->container = Container::getInstance('com_loginguard');

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
		$helpURL = $this->params->get('helpurl', 'https://github.com/akeeba/loginguard/wiki/Email');

		return [
			// Internal code of this TFA method
			'name'          => $this->tfaMethodName,
			// User-facing name for this TFA method
			'display'       => Text::_('PLG_LOGINGUARD_EMAIL_LBL_DISPLAYEDAS'),
			// Short description of this TFA method displayed to the user
			'shortinfo'     => Text::_('PLG_LOGINGUARD_EMAIL_LBL_SHORTINFO'),
			// URL to the logo image for this method
			'image'         => 'media/plg_loginguard_email/images/email.svg',
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
	 * @param   stdClass  $record  The #__loginguard_tfa record currently selected by the user.
	 *
	 * @return  array
	 */
	public function onLoginGuardTfaGetSetup($record)
	{
		$helpURL = $this->params->get('helpurl', 'https://github.com/akeeba/loginguard/wiki/Email');

		// Make sure we are actually meant to handle this method
		if ($record->method != $this->tfaMethodName)
		{
			return [];
		}

		// Load the options from the record (if any)
		$options = $this->_decodeRecordOptions($record);
		$key     = $options['key'] ?? '';

		// If there's a key in the session use that instead.
		$this->container->platform->getSessionVar('emailcode.key', $key, 'com_loginguard');

		// Initialize objects
		$timeStep = min(max((int) $this->params->get('timestep', 120), 30), 900);
		$totp     = new Totp($timeStep, self::CODE_LENGTH, self::SECRET_KEY_LENGTH);

		// If there's still no key in the options, generate one and save it in the session
		if (empty($key))
		{
			$key = $totp->generateSecret();
			$this->container->platform->setSessionVar('emailcode.key', $key, 'com_loginguard');
		}

		$this->container->platform->setSessionVar('emailcode.user_id', $record->user_id, 'com_loginguard');

		// Send an email message with a new code and ask the user to enter it.
		$user = Factory::getUser($record->user_id);
		$this->sendCode($key, $user);

		return [
			// Default title if you are setting up this TFA method for the first time
			'default_title'  => Text::_('PLG_LOGINGUARD_EMAIL_LBL_DISPLAYEDAS'),
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
			'placeholder'    => Text::_('PLG_LOGINGUARD_EMAIL_LBL_SETUP_PLACEHOLDER'),
			// Label to show above the HTML input box. Leave empty if you don't need it.
			'label'          => Text::_('PLG_LOGINGUARD_EMAIL_LBL_SETUP_LABEL'),
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
	 * @param   stdClass  $record  The #__loginguard_tfa record currently selected by the user.
	 * @param   Input     $input   The user input you are going to take into account.
	 *
	 * @return  array  The configuration data to save to the database
	 *
	 * @throws  RuntimeException  In case the validation fails
	 */
	public function onLoginGuardTfaSaveSetup($record, Input $input)
	{
		// Make sure we are actually meant to handle this method
		if ($record->method != $this->tfaMethodName)
		{
			return [];
		}

		// Load the options from the record (if any)
		$options = $this->_decodeRecordOptions($record);
		$key     = $options['key'] ?? '';

		// If there is no key in the options fetch one from the session
		if (empty($key))
		{
			$key = $this->container->platform->getSessionVar('emailcode.key', null, 'com_loginguard');
		}

		// If there is still no key in the options throw an error
		if (empty($key))
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
		$timeStep = min(max((int) $this->params->get('timestep', 120), 30), 900);
		$totp     = new Totp($timeStep, self::CODE_LENGTH, self::SECRET_KEY_LENGTH);
		$isValid  = $totp->checkCode($key, $code);

		if (!$isValid)
		{
			throw new RuntimeException(Text::_('PLG_LOGINGUARD_EMAIL_ERR_INVALID_CODE'), 500);
		}

		// The code is valid. Unset the key from the session.
		$this->container->platform->setSessionVar('totp.key', null, 'com_loginguard');

		// Return the configuration to be serialized
		return [
			'key' => $key,
		];
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
			return [];
		}

		// Load the options from the record (if any)
		$options = $this->_decodeRecordOptions($record);
		$key     = $options['key'] ?? '';
		$helpURL = $this->params->get('helpurl', 'https://github.com/akeeba/loginguard/wiki/Email');

		// Send an email message with a new code and ask the user to enter it.
		$user = Factory::getUser($record->user_id);

		try
		{
			$this->sendCode($key, $user);
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
			'placeholder'  => Text::_('PLG_LOGINGUARD_EMAIL_LBL_SETUP_PLACEHOLDER'),
			// Label to show above the HTML input box. Leave empty if you don't need it.
			'label'        => Text::_('PLG_LOGINGUARD_EMAIL_LBL_SETUP_LABEL'),
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
		$key     = $options['key'] ?? '';

		// If there is no key in the options throw an error
		if (empty($key))
		{
			return false;
		}

		// Check the TFA code for validity
		$timeStep = min(max((int) $this->params->get('timestep', 120), 30), 900);
		$totp     = new Totp($timeStep, self::CODE_LENGTH, self::SECRET_KEY_LENGTH);

		return $totp->checkCode($key, $code);
	}

	/**
	 * Executes before showing the 2SV methods for the user. Used for the Force Enable feature.
	 *
	 * @param   User|null  $user
	 *
	 * @return  void
	 */
	public function onLoginGuardBeforeDisplayMethods(?User $user)
	{
		// Is the forced enable feature activated?
		if ($this->params->get('force_enable', 0) != 1)
		{
			return;
		}

		// Get second factor methods for this user
		/** @var Tfa $model */
		$model          = $this->container->factory->model('Tfa')->tmpInstance();
		$userTfaRecords = $model->user_id($user->id)->get(true);

		// If there are no methods go back
		if ($userTfaRecords->count() < 1)
		{
			return;
		}

		// If the only method is backup codes go back
		if ($userTfaRecords->count() == 1)
		{
			/** @var Tfa $record */
			$record = $userTfaRecords->first();

			if ($record->method == 'backupcodes')
			{
				return;
			}
		}

		// If I already have the email method go back
		$emailRecords = $userTfaRecords->filter(function (Tfa $record) {
			return $record->method == 'email';
		});

		if ($emailRecords->count())
		{
			return;
		}

		// Add the email method
		try
		{
			/** @var \Akeeba\LoginGuard\Site\Model\Method $methodModel */
			$methodModel = $this->container->factory->model('Method')->tmpInstance();
			$timeStep    = min(max((int) $this->params->get('timestep', 120), 30), 900);
			$totp        = new Totp($timeStep, self::CODE_LENGTH, self::SECRET_KEY_LENGTH);
			$methodModel->setState('id', 0);
			$record          = $methodModel->getRecord($user);
			$record->method  = 'email';
			$record->title   = Text::_('PLG_LOGINGUARD_EMAIL_LBL_DISPLAYEDAS');
			$record->options = [
				'key' => ($totp)->generateSecret(),
			];
			$record->default = 0;
			$record->save();
		}
		catch (Exception $e)
		{
			// Fail gracefully
		}
	}

	/**
	 * Creates a new TOTP code based on secret key $key and sends it to the user via email.
	 *
	 * @param   string  $key   The TOTP secret key
	 * @param   User    $user  The Joomla! user to use
	 *
	 * @return  void
	 *
	 * @throws  LoginGuardPushbulletApiException  If something goes wrong
	 */
	public function sendCode($key, User $user = null)
	{
		// Make sure we have a user
		if (!is_object($user) || !($user instanceof User))
		{
			$user = Factory::getUser();
		}

		// Get the API objects
		$timeStep = min(max((int) $this->params->get('timestep', 120), 30), 900);
		$totp     = new Totp($timeStep, self::CODE_LENGTH, self::SECRET_KEY_LENGTH);

		// Create the list of variable replacements
		$code = $totp->getCode($key);

		$replacements = [
			'[CODE]'     => $code,
			'[SITENAME]' => Factory::getConfig()->get('sitename'),
			'[SITEURL]'  => Uri::base(),
			'[USERNAME]' => $user->username,
			'[EMAIL]'    => $user->email,
			'[FULLNAME]' => $user->name,
		];

		// Get the title and body of the e-mail message
		$subject = Text::_('PLG_LOGINGUARD_EMAIL_MESSAGE_SUBJECT');
		$subject = str_ireplace(array_keys($replacements), array_values($replacements), $subject);
		$body    = Text::_('PLG_LOGINGUARD_EMAIL_MESSAGE_BODY');
		$body    = str_ireplace(array_keys($replacements), array_values($replacements), $body);

		// Send email
		try
		{
			$mailer = Factory::getMailer();
			$mailer->setSubject($subject);
			$mailer->setBody($body);
			$mailer->addRecipient($user->email, $user->name);
			$mailer->Send();
		}
		catch (Exception $e)
		{
			return;
		}
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
		$options = [
			'key' => '',
		];

		if (!empty($record->options))
		{
			$recordOptions = $record->options;

			$options = array_merge($options, $recordOptions);
		}

		return $options;
	}
}
