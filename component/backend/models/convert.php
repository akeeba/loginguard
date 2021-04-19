<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
defined('_JEXEC') or die;

use Joomla\CMS\Date\Date;
use Joomla\CMS\Encrypt\Aes;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Table\Table;
use Joomla\Registry\Registry;

JLoader::register('LoginGuardHelperTfa', JPATH_SITE . '/components/com_loginguard/helpers/tfa.php');

Table::addIncludePath(JPATH_ROOT . '/components/com_loginguard/tables');

/**
 * A model responsible for converting Joomla's Two Factor Authenticator entries into Akeeba LoginGuard's Two Step
 * Verification entries.
 *
 * @since 1.2.1
 */
class LoginGuardModelConvert extends BaseDatabaseModel
{
	/**
	 * The site's secret key
	 *
	 * @var   string
	 * @since 1.2.1
	 */
	protected $secret = '';

	/**
	 * @param   int  $limit  How many records to process at once. Around 25 should be safe in most cases.
	 *
	 * @return  bool  True if we converted any users, false if we're done converting users
	 * @since   1.2.1
	 */
	public function convert($limit = 25)
	{
		// Get the users with Joomla! TFA records
		$db    = $this->getDbo();
		$query = $db->getQuery(true)
			->select('*')
			->from($db->qn('#__users'))
			->where($db->qn('otpKey') . ' != ' . $db->q(''))
			->where($db->qn('otep') . ' != ' . $db->q(''));
		$users = $db->setQuery($query, 0, $limit)->loadObjectList();

		// There are no more users with TFA configured, let's stop here
		if (empty($users))
		{
			return false;
		}

		// Get the Secret from Joomla's configuration. It's used as an encryption key in Joomla! 3.6.3 and earlier.
		$secret = $this->getSecret();

		// Loop all users with TFA
		foreach ($users as $user)
		{
			[$otpMethod, $otpKey] = explode(':', $user->otpKey, 2);
			$otpKey     = $this->decryptTFAString($secret, $otpKey);
			$otep       = $this->decryptTFAString($secret, $user->otep);
			$methodName = 'convert' . ucfirst($otpMethod);

			// Make sure I can convert the currently selected method
			if (!method_exists($this, $methodName))
			{
				continue;
			}

			// Convert the TFA method
			call_user_func([$this, $methodName], $otpKey, $user->id);

			// Convert the emergency codes
			$this->convertEmergencyCodes($otep, $user->id);

			// Disable the TFA in the user's record
			$update = (object) [
				'id'     => $user->id,
				'otpKey' => '',
				'otep'   => '',
			];
			$db->updateObject('#__users', $update, ['id']);
		}

		return true;
	}

	/**
	 * Public setter for the secret key.
	 *
	 * @param   string  $secret  The secret key to set in the model.
	 *
	 * @return  void
	 * @since   1.2.1
	 */
	public function setSecret($secret)
	{
		$this->secret = $secret;
	}

	/**
	 * Disable all Two Factor Authentication plugins. Since Akeeba LoginGuard will be implementing Two Step Verification
	 * you don't need them any more.
	 *
	 * @return  void
	 * @since   1.2.1
	 */
	public function disableTFA()
	{
		$db    = $this->getDbo();
		$query = $db->getQuery(true)
			->update($db->qn('#__extensions'))
			->set($db->qn('enabled') . ' = ' . $db->q('0'))
			->where($db->qn('type') . ' = ' . $db->q('plugin'))
			->where($db->qn('folder') . ' = ' . $db->q('twofactorauth'));

		$db->setQuery($query)->execute();
	}

	/**
	 * Private getter for the secret key. If no secret key is set in the model the site's secret key is returned
	 * instead.
	 *
	 * @return  string
	 * @since   1.2.1
	 */
	private function getSecret()
	{
		if (empty($this->secret))
		{
			/** @var Registry $jConfig */
			$this->secret = Factory::getApplication()->get('secret', null);
		}

		return $this->secret;
	}

	/**
	 * Convert the one time emergency codes from Joomla's TFA configuration.
	 *
	 * @param   string  $json     The JSON-encoded list of codes
	 * @param   int     $user_id  The ID of the user for this method
	 *
	 * @return  void
	 * @since   1.2.1
	 */
	private function convertEmergencyCodes($json, $user_id)
	{
		// Try to decode the configuration
		$config = @json_decode($json, true);

		// If the configuration cannot be decoded (corrupt) just give up and don't convert
		if (empty($config))
		{
			return;
		}

		// Delete any other record with the same user_id and method.
		$db    = $this->getDbo();
		$query = $db->getQuery(true)
			->delete($db->qn('#__loginguard_tfa'))
			->where($db->qn('user_id') . ' = ' . $db->q($user_id))
			->where($db->qn('method') . ' = ' . $db->q('emergencycodes'));
		$db->setQuery($query)->execute();

		// Migrate data
		$jDate = Date::getInstance();
		$table = Table::getInstance('Tfa', 'LoginGuardTable');

		$table->save([
			'user_id'    => $user_id,
			'title'      => Text::_('COM_LOGINGUARD_LBL_BACKUPCODES'),
			'method'     => 'backupcodes',
			'default'    => 0,
			'created_on' => $jDate->toSql(),
			'last_used'  => $db->getNullDate(),
			'options'    => json_decode($json, true),
		]);
	}

	/**
	 * Convert from Joomla TFA method 'yubikey'. This method implements TFA with YubiKey only, therefore it's mapped to
	 * our YubiKey plugin.
	 *
	 * @param   string  $json     The JSON-encoded configuration of this method.
	 * @param   int     $user_id  The ID of the user for this method
	 *
	 * @return  void
	 * @since   1.2.1
	 */
	private function convertYubikey($json, $user_id)
	{
		// Try to decode the configuration
		$config = @json_decode($json, true);

		// If the configuration cannot be decoded (corrupt) just give up and don't convert
		if (empty($config))
		{
			return;
		}

		// Delete any other record with the same user_id and method.
		$db    = $this->getDbo();
		$query = $db->getQuery(true)
			->delete($db->qn('#__loginguard_tfa'))
			->where($db->qn('user_id') . ' = ' . $db->q($user_id))
			->where($db->qn('method') . ' = ' . $db->q('yubikey'));
		$db->setQuery($query)->execute();

		// Migrate data
		$jDate = Date::getInstance();
		$table = Table::getInstance('Tfa', 'LoginGuardTable');

		$table->save([
			'user_id'    => $user_id,
			'title'      => 'YubiKey ' . $config['yubikey'],
			'method'     => 'yubikey',
			'default'    => 0,
			'created_on' => $jDate->toSql(),
			'last_used'  => $db->getNullDate(),
			'options'    => ['id' => $config['yubikey']],
		]);
	}

	/**
	 * Convert from Joomla TFA method 'totp'. This method implements TFA with time-based one time passwords, therefore
	 * it's mapped to our TOTP plugin.
	 *
	 * @param   string  $json     The JSON-encoded configuration of this method.
	 * @param   int     $user_id  The ID of the user for this method
	 *
	 * @return  void
	 * @since   1.2.1
	 */
	private function convertTotp($json, $user_id)
	{
		$config = @json_decode($json, true);

		if (empty($config))
		{
			return;
		}

		// Delete any other record with the same user_id and method.
		$db    = $this->getDbo();
		$query = $db->getQuery(true)
			->delete($db->qn('#__loginguard_tfa'))
			->where($db->qn('user_id') . ' = ' . $db->q($user_id))
			->where($db->qn('method') . ' = ' . $db->q('totp'));
		$db->setQuery($query)->execute();

		// Get the TSV object to insert
		$jDate = Date::getInstance();
		$table = Table::getInstance('Tfa', 'LoginGuardTable');

		$table->save([
			'user_id'    => $user_id,
			'title'      => 'Authenticator',
			'method'     => 'totp',
			'default'    => 0,
			'created_on' => $jDate->toSql(),
			'last_used'  => $db->getNullDate(),
			'options'    => ['key' => $config['code']],
		]);
	}

	/**
	 * Convert from Joomla TFA method 'yubikeytotp'. This is a non-standard method, part of the now defunct Akeeba TFA
	 * plugins for Joomla! 3. It implements TFA with TOTP and/or any number of YubiKeys. We map it to both our TOTP and
	 * our YubiKey plugins.
	 *
	 * @param   string  $json     The JSON-encoded configuration of this method.
	 * @param   int     $user_id  The ID of the user for this method
	 *
	 * @return  void
	 * @since   1.2.1
	 */
	private function convertYubikeytotp($json, $user_id)
	{
		$config = @json_decode($json, true);

		if (empty($config))
		{
			return;
		}

		// Handle the TOTP part if there's a non-empty TOTP key code
		if (isset($config['yubikeytotp_code']) && !empty($config['yubikeytotp_code']))
		{
			$fakeConfig = json_encode([
				'code' => $config['yubikeytotp_code'],
			]);

			$this->convertTotp($fakeConfig, $user_id);
		}

		// Handle the YubiKey part if there is an array of YubiKeys in use
		if (isset($config['yubikeytotp']) && is_array($config['yubikeytotp']) && count($config['yubikeytotp']))
		{
			foreach ($config['yubikeytotp'] as $yubikey)
			{
				$fakeConfig = json_encode([
					'yubikey' => $yubikey,
				]);

				$this->convertYubikey($fakeConfig, $user_id);
			}
		}
	}

	/**
	 * Tries to decrypt the TFA configuration, using a different method depending on the Joomla! version.
	 *
	 * @param   string  $secret           Site's secret key
	 * @param   string  $stringToDecrypt  Base64-encoded and encrypted, JSON-encoded information
	 *
	 * @return  string  Decrypted, but JSON-encoded, information
	 *
	 * @see     https://github.com/joomla/joomla-cms/pull/12497
	 * @since   1.2.1
	 */
	private function decryptTFAString(string $secret, string $stringToDecrypt): string
	{
		if (version_compare('JVERSION', '3.999.999', 'lt'))
		{
			return $this->decryptTFAStringJoomla3($secret, $stringToDecrypt);
		}

		// Is this already decrypted?
		try
		{
			$decrypted = json_decode($stringToDecrypt, true);
		}
		catch (Exception $e)
		{
			$decrypted = null;
		}

		if (!empty($decrypted))
		{
			return $stringToDecrypt;
		}

		// No, we need to decrypt the string
		$aes       = new Aes($secret, 256);
		$decrypted = $aes->decryptString($stringToDecrypt);

		if (!is_string($decrypted) || empty($decrypted))
		{
			return '';
		}

		// Remove the null padding added during encryption
		return rtrim($decrypted, "\0");
	}

	/**
	 * Tries to decrypt the TFA configuration. Joomla 3 version.
	 *
	 * @param   string  $secret           Site's secret key
	 * @param   string  $stringToDecrypt  Base64-encoded and encrypted, JSON-encoded information
	 *
	 * @return  string  Decrypted, but JSON-encoded, information
	 *
	 * @see     https://github.com/joomla/joomla-cms/pull/12497
	 * @since   1.2.1
	 */
	private function decryptTFAStringJoomla3(string $secret, string $stringToDecrypt): string
	{
		// Make sure FOF 2.x is loaded (required by Joomla 3's TFA)
		if (!defined('FOF_INCLUDED'))
		{
			require_once JPATH_LIBRARIES . '/fof/include.php';
		}

		// If encryption is not supported we return the original string
		if (!FOFEncryptAes::isSupported())
		{
			return $stringToDecrypt;
		}

		// Joomla 3.6.3 and earlier
		if (version_compare(JVERSION, '3.6.3', 'le') || !class_exists('FOFEncryptAesMcrypt', true))
		{
			$aesDecryptor = new FOFEncryptAes($secret, 256, 'cbc');

			return $aesDecryptor->decryptString($stringToDecrypt);
		}

		// Joomla 3.6.4 or later. If it's raw JSON just return it, otherwise try to decrypt it first.
		$stringToDecrypt = trim($stringToDecrypt, "\0");

		if (!is_null(json_decode($stringToDecrypt, true)))
		{
			return $stringToDecrypt;
		}

		$openssl = new FOFEncryptAes($secret, 256, 'cbc', null, 'openssl');

		if ($openssl->isSupported())
		{
			$decryptedConfig = $openssl->decryptString($stringToDecrypt);
			$decryptedConfig = trim($decryptedConfig, "\0");

			if (!is_null(json_decode($decryptedConfig, true)))
			{
				return $decryptedConfig;
			}
		}

		return '';
	}
}