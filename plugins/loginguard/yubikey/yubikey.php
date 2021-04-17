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
use Joomla\CMS\Input\Input;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use LoginGuardTableTfa as Tfa;

/**
 * Akeeba LoginGuard Plugin for Two Step Verification method "Yubikey"
 *
 * Use a YubiKey secure hardware token. Supports both the default, centralized key servers and your own custom key
 * server.
 */
class PlgLoginguardYubikey extends CMSPlugin
{
	/**
	 * @var CMSApplication
	 */
	protected $app;

	/**
	 * The TFA method name handled by this plugin
	 *
	 * @var   string
	 */
	private $tfaMethodName = 'yubikey';

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

		$this->loadLanguage();
	}

	/**
	 * Gets the identity of this TFA method
	 *
	 * @return  array
	 */
	public function onLoginGuardTfaGetMethod(): array
	{
		$helpURL = $this->params->get('helpurl', 'https://github.com/akeeba/loginguard/wiki/YubiKey');

		return [
			// Internal code of this TFA method
			'name'               => $this->tfaMethodName,
			// User-facing name for this TFA method
			'display'            => Text::_('PLG_LOGINGUARD_YUBIKEY_LBL_DISPLAYEDAS'),
			// Short description of this TFA method displayed to the user
			'shortinfo'          => Text::_('PLG_LOGINGUARD_YUBIKEY_LBL_SHORTINFO'),
			// URL to the logo image for this method
			'image'              => 'media/plg_loginguard_yubikey/images/yubikey.svg',
			// Are we allowed to disable it?
			'canDisable'         => true,
			// Are we allowed to have multiple instances of it per user?
			'allowMultiple'      => true,
			// URL for help content
			'help_url'           => $helpURL,
			// Allow authentication against all entries of this TFA method. Otherwise authentication takes place against a SPECIFIC entry at a time.
			'allowEntryBatching' => 1,
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
	public function onLoginGuardTfaCaptive(Tfa $record)
	{
		// Make sure we are actually meant to handle this method
		if ($record->method != $this->tfaMethodName)
		{
			return [];
		}

		$helpURL = $this->params->get('helpurl', 'https://github.com/akeeba/loginguard/wiki/YubiKey');

		return [
			// Custom HTML to display above the TFA form
			'pre_message'        => '',
			// How to render the TFA code field. "input" (HTML input element) or "custom" (custom HTML)
			'field_type'         => 'input',
			// The type attribute for the HTML input box. Typically "text" or "password". Use any HTML5 input type.
			'input_type'         => 'text',
			// Placeholder text for the HTML input box. Leave empty if you don't need it.
			'placeholder'        => '',
			// Label to show above the HTML input box. Leave empty if you don't need it.
			'label'              => Text::_('PLG_LOGINGUARD_YUBIKEY_LBL_LABEL'),
			// Custom HTML. Only used when field_type = custom.
			'html'               => '',
			// Custom HTML to display below the TFA form
			'post_message'       => '',
			// URL for help content
			'help_url'           => $helpURL,
			// Allow authentication against all entries of this TFA method. Otherwise authentication takes place against a SPECIFIC entry at a time.
			'allowEntryBatching' => 1,
		];
	}

	/**
	 * Returns the information which allows LoginGuard to render the TFA setup page. This is the page which allows the
	 * user to add or modify a TFA method for their user account. If the record does not correspond to your plugin
	 * return an empty array.
	 *
	 * @param   LoginGuardTableTfa  $record  The #__loginguard_tfa record currently selected by the user.
	 *
	 * @return  array
	 */
	public function onLoginGuardTfaGetSetup(Tfa $record): array
	{
		// Make sure we are actually meant to handle this method
		if ($record->method != $this->tfaMethodName)
		{
			return [];
		}

		// Load the options from the record (if any)
		$options = $this->_decodeRecordOptions($record);
		$keyID   = $options['id'] ?? '';
		$helpURL = $this->params->get('helpurl', 'https://github.com/akeeba/loginguard/wiki/YubiKey');

		return [
			// Default title if you are setting up this TFA method for the first time
			'default_title'  => Text::_('PLG_LOGINGUARD_YUBIKEY_LBL_DISPLAYEDAS'),
			// Custom HTML to display above the TFA setup form
			'pre_message'    => Text::_('PLG_LOGINGUARD_YUBIKEY_LBL_SETUP_INSTRUCTIONS'),
			// Heading for displayed tabular data. Typically used to display a list of fixed TFA codes, TOTP setup parameters etc
			'table_heading'  => '',
			// Any tabular data to display (label => custom HTML). See above
			'tabular_data'   => [],
			// Hidden fields to include in the form (name => value)
			'hidden_data'    => [],
			// How to render the TFA setup code field. "input" (HTML input element) or "custom" (custom HTML)
			'field_type'     => 'input',
			// The type attribute for the HTML input box. Typically "text" or "password". Use any HTML5 input type.
			'input_type'     => 'text',
			// Pre-filled value for the HTML input box. Typically used for fixed codes, the fixed YubiKey ID etc.
			'input_value'    => $keyID,
			// Placeholder text for the HTML input box. Leave empty if you don't need it.
			'placeholder'    => Text::_('PLG_LOGINGUARD_YUBIKEY_LBL_SETUP_PLACEHOLDER'),
			// Label to show above the HTML input box. Leave empty if you don't need it.
			'label'          => Text::_('PLG_LOGINGUARD_YUBIKEY_LBL_SETUP_LABEL'),
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
		$keyID   = $options['id'] ?? '';

		/**
		 * If the submitted code is 12 characters and identical to our existing key there is no change, perform no
		 * further checks.
		 */
		$code = $input->getString('code');

		if ((strlen($code) == 12) && ($code == $keyID))
		{
			return $options;
		}

		// If an empty code or something other than 44 characters was submitted I'm not having any of this!
		if (empty($code) || (strlen($code) != 44))
		{
			throw new RuntimeException(Text::_('PLG_LOGINGUARD_YUBIKEY_ERR_INVALID_CODE'), 500);
		}

		// Validate the code
		$isValid = $this->validateYubikeyOtp($code);

		if (!$isValid)
		{
			throw new RuntimeException(Text::_('PLG_LOGINGUARD_YUBIKEY_ERR_INVALID_CODE'), 500);
		}

		// The code is valid. Keep the Yubikey ID (first twelve characters)
		$keyID = substr($code, 0, 12);

		// Return the configuration to be serialized
		return [
			'id' => $keyID,
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

		try
		{
			JLoader::register('LoginGuardHelperTfa', JPATH_SITE . '/components/com_loginguard/helpers/tfa.php');
			$records = LoginGuardHelperTfa::getUserTfaRecords($record->user_id);
			$records = array_filter($records, function ($rec) use ($record) {
				return $rec->method === $record->method;
			});
		}
		catch (Exception $e)
		{
			$records = [];
		}

		// Loop all records, stop if at least one matches
		foreach ($records as $aRecord)
		{
			if ($this->validateAgainstRecord($aRecord, $code))
			{
				return true;
			}
		}

		// None of the records succeeded? Return false.
		return false;
	}

	/**
	 * Validates a Yubikey OTP against the Yubikey servers
	 *
	 * @param   string  $otp  The OTP generated by your Yubikey
	 *
	 * @return  boolean  True if it's a valid OTP
	 */
	public function validateYubikeyOtp(string $otp): bool
	{
		// Let the user define a client ID and a secret key in the plugin's configuration
		$clientID     = $this->params->get('client_id', 1);
		$secretKey    = $this->params->get('secret', '');
		$server_queue = trim($this->params->get('servers', ''));

		if (!empty($server_queue))
		{
			$server_queue = explode("\r", $server_queue);
		}

		if (empty($server_queue))
		{
			$server_queue = [
				'https://api.yubico.com/wsapi/2.0/verify',
				'https://api2.yubico.com/wsapi/2.0/verify',
				'https://api3.yubico.com/wsapi/2.0/verify',
				'https://api4.yubico.com/wsapi/2.0/verify',
				'https://api5.yubico.com/wsapi/2.0/verify',
			];
		}

		shuffle($server_queue);

		$gotResponse = false;

		$http     = HttpFactory::getHttp();
		$token    = Session::getFormToken();
		$nonce    = md5($token . uniqid(random_int(0, mt_getrandmax())));
		$response = null;

		while (!$gotResponse && !empty($server_queue))
		{
			$server = array_shift($server_queue);
			$uri    = new Uri($server);

			// The client ID for signing the response
			$uri->setVar('id', $clientID);

			// The OTP we read from the user
			$uri->setVar('otp', $otp);

			// This prevents a REPLAYED_OTP status if the token doesn't change after a user submits an invalid OTP
			$uri->setVar('nonce', $nonce);

			// Minimum service level required: 50% (at least 50% of the YubiCloud servers must reply positively for the
			// OTP to validate)
			$uri->setVar('sl', 50);

			// Timeout waiting for YubiCloud servers to reply: 5 seconds.
			$uri->setVar('timeout', 5);

			// Set up the optional HMAC-SHA1 signature for the request.
			$this->signRequest($uri, $secretKey);

			if ($uri->hasVar('h'))
			{
				$uri->setVar('h', urlencode($uri->getVar('h')));
			}

			try
			{
				$response = $http->get($uri->toString(), [], 6);

				if (!empty($response))
				{
					$gotResponse = true;
				}
				else
				{
					continue;
				}
			}
			catch (Exception $exc)
			{
				// No response, continue with the next server
				continue;
			}
		}

		if (empty($response))
		{
			$gotResponse = false;
		}

		// No server replied; we can't validate this OTP
		if (!$gotResponse)
		{
			return false;
		}

		// Parse response
		$lines = explode("\n", $response->body);
		$data  = [];

		foreach ($lines as $line)
		{
			$line  = trim($line);
			$parts = explode('=', $line, 2);

			if (count($parts) < 2)
			{
				continue;
			}

			$data[$parts[0]] = $parts[1];
		}

		// Validate the signature
		$h       = $data['h'] ?? null;
		$fakeUri = Uri::getInstance('http://www.example.com');
		$fakeUri->setQuery($data);
		$this->signRequest($fakeUri, $secretKey);
		$calculatedH = $fakeUri->getVar('h', null);

		if ($calculatedH != $h)
		{
			return false;
		}

		// Validate the response - We need an OK message reply
		if ($data['status'] !== 'OK')
		{
			return false;
		}

		// Validate the response - We need a confidence level over 50%
		if ($data['sl'] < 50)
		{
			return false;
		}

		// Validate the response - The OTP must match
		if ($data['otp'] != $otp)
		{
			return false;
		}

		// Validate the response - The token must match
		if ($data['nonce'] != $nonce)
		{
			return false;
		}

		return true;
	}

	/**
	 * Sign the request to YubiCloud.
	 *
	 * @see   https://developers.yubico.com/yubikey-val/Validation_Protocol_V2.0.html
	 *
	 * @param   Uri     $uri     The request URI to sign
	 * @param   string  $secret  The secret key to sign with
	 *
	 * @return  void
	 */
	public function signRequest(Uri $uri, string $secret): void
	{
		// Make sure we have an encoding secret
		$secret = trim($secret);

		if (empty($secret))
		{
			return;
		}

		// I will need base64 encoding and decoding
		if (!function_exists('base64_encode') || !function_exists('base64_decode'))
		{
			return;
		}

		// I need HMAC-SHA-1 support. Therefore I check for HMAC and SHA1 support in the PHP 'hash' extension.
		if (!function_exists('hash_hmac') || !function_exists('hash_algos'))
		{
			return;
		}

		$algos = hash_algos();

		if (!in_array('sha1', $algos))
		{
			return;
		}

		// Get the parameters
		/** @var   array $vars I have to explicitly state the type because the Joomla docblock is wrong :( */
		$vars = $uri->getQuery(true);

		// 'h' is the hash and it doesn't participate in the calculation of itself.
		if (isset($vars['h']))
		{
			unset($vars['h']);
		}

		// Alphabetically sort the set of key/value pairs by key order.
		ksort($vars);

		/**
		 * Construct a single line with each ordered key/value pair concatenated using &, and each key and value
		 * concatenated with =. Do not add any line breaks. Do not add whitespace.
		 *
		 * Now, if you thought I can't really write PHP code, a.k.a. why not use http_build_query, read on.
		 *
		 * The way YubiKey expects the query to be built is UTTERLY WRONG. They are doing string concatenation, not
		 * URL query building! Therefore you cannot use http_build_query(). Instead, you need to use dumb string
		 * concatenation. I kid you not. If you want to laugh (or cry) read their Auth_Yubico class. It's 1998 all over
		 * again.
		 */
		$stringToSign = '';

		foreach ($vars as $k => $v)
		{
			$stringToSign .= '&' . $k . '=' . $v;
		}

		$stringToSign = ltrim($stringToSign, '&');

		/**
		 * Apply the HMAC-SHA-1 algorithm on the line as an octet string using the API key as key (remember to
		 * base64decode the API key obtained from Yubico).
		 */
		$decodedKey = base64_decode($secret);
		$hash       = hash_hmac('sha1', $stringToSign, $decodedKey, true);

		/**
		 * Base 64 encode the resulting value according to RFC 4648, for example, t2ZMtKeValdA+H0jVpj3LIichn4=
		 */
		$h = base64_encode($hash);

		/**
		 * Append the value under key h to the message.
		 */
		$uri->setVar('h', $h);
	}

	/**
	 * Decodes the options from a #__loginguard_tfa record into an options object.
	 *
	 * @param   LoginGuardTableTfa  $record
	 *
	 * @return  array
	 */
	private function _decodeRecordOptions(Tfa $record): array
	{
		$options = [
			'id' => '',
		];

		if (!empty($record->options))
		{
			$recordOptions = $record->options;

			$options = array_merge($options, $recordOptions);
		}

		return $options;
	}

	/**
	 * @param   LoginGuardTableTfa  $record
	 * @param   string              $code
	 *
	 * @return  bool
	 */
	private function validateAgainstRecord(Tfa $record, string $code): bool
	{
		// Load the options from the record (if any)
		$options = $this->_decodeRecordOptions($record);
		$keyID   = $options['id'] ?? '';

		// If there is no key in the options throw an error
		if (empty($keyID))
		{
			return false;
		}

		// If the submitted code is empty throw an error
		if (empty($code))
		{
			return false;
		}

		// If the submitted code length is wrong throw an error
		if (strlen($code) != 44)
		{
			return false;
		}

		// If the submitted code's key ID does not match the stored throw an error
		if (substr($code, 0, 12) != $keyID)
		{
			return false;
		}

		// Check the OTP code for validity
		return $this->validateYubikeyOtp($code);
	}
}
