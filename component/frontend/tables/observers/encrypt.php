<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
defined('_JEXEC') or die;

use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Table\Table;
use Joomla\CMS\User\UserHelper;

/**
 * Automatically manage encryption of the TFA records' options
 *
 * @since 5.0.0
 */
class LoginGuardTableObserverEncrypt
{
	/**
	 * The observed table
	 *
	 * @var    Table
	 */
	protected $table;

	/**
	 * The columns to encrypt / decrypt automatically
	 *
	 * @var array
	 */
	private $columns = [];

	/**
	 * The name of the component we are encrypting data for. Determined from the DB table name if not specified.
	 *
	 * @var string
	 */
	private $componentName = '';

	/**
	 * The name of the password file in the component's root, without the .php extensions. Default: encrypt_service_key
	 *
	 * Note that the default is chosen for seamless upgrade from FOF.
	 *
	 * @var string
	 */
	private $passwordFile = '';

	/**
	 * The constant name for the encryption key. Default: <COMPONENT NAME>_FOF_ENCRYPT_SERVICE_SECRETKEY
	 *
	 * Note that the default is chosen for seamless upgrade from FOF.
	 *
	 * @var string
	 */
	private $keyConstant = '';

	/**
	 * The encryption method to use
	 *
	 * @var  string
	 */
	private $method = 'aes-128-cbc';

	/**
	 * The OpenSSL options for encryption / decryption
	 *
	 * @var  int
	 */
	private $openSSLOptions = OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING;

	/**
	 * The cipher key.
	 *
	 * @var   string
	 */
	private $key = '';

	public function __construct(Table $table, array $params = [])
	{
		$this->table = $table;

		if (isset($params['method']))
		{
			$this->setMethod($params['method']);
		}

		if (isset($params['componentName']))
		{
			$this->setComponentName($params['componentName']);
		}
		else
		{
			$name = str_replace('#__', '', $table->getTableName());
			[$component, $tableType] = explode('_', $name, 2);
			$this->setComponentName('com_' . strtolower($component));
		}

		if (isset($params['password']))
		{
			$this->setPassword($params['password']);
		}
		else
		{
			$this->setUpPasswordFromKeyFile();
		}

		if (isset($params['columns']))
		{
			$this->setColumns($params['columns']);
		}
	}

	public function onAfterLoad(&$result)
	{
		// Only tun on successful table load
		if (!$result)
		{
			return;
		}

		// Set up new column bindings
		$bindings = [];

		// Decrypt each column specified in the observer
		foreach ($this->columns as $column)
		{
			$value     = $this->table->{$column} ?? '';

			$decrypted = @json_decode($this->decrypt($value), true);

			if (is_string($decrypted))
			{
				$decrypted = @json_decode($decrypted, true);
			}

			if (!is_array($decrypted))
			{
				$decrypted = @json_decode($this->decrypt($value, true), true);
			}

			if (is_string($decrypted))
			{
				$decrypted = @json_decode($decrypted, true);
			}

			$bindings[$column] = empty($decrypted) ? [] : $decrypted;
		}

		// Apply any updated bindings to the table.
		if (!empty($bindings))
		{
			$this->table->bind($bindings);
		}
	}

	public function onBeforeStore($updateNulls, $tableKey)
	{
		// Set up new column bindings
		$bindings = [];

		// Encrypt each column specified in the observer
		foreach ($this->columns as $column)
		{
			$value = $this->table->{$column};

			if (empty($value))
			{
				$value = [];
			}

			$encoded = json_encode($value);

			$bindings[$column] = $this->encrypt($encoded);
		}

		// Apply any updated bindings to the table.
		if (!empty($bindings))
		{
			$this->table->bind($bindings);
		}
	}

	public function onAfterStore(&$result)
	{
		// After we have finished storing the table we need to decrypt the columns we encrypted onBeforeStore.
		$fakeResult = true;
		$this->onAfterLoad($fakeResult);
	}

	protected function setColumns(array $columns): void
	{
		$this->columns = $columns;
	}

	protected function setUpPasswordFromKeyFile(): void
	{
		// This will also create a key file if none already exists
		$this->key = $this->getPassword();
	}

	protected function setComponentName(string $componentName): void
	{
		$this->componentName = $componentName;
	}

	protected function setMethod(string $method): void
	{
		$this->method = $method;
	}

	protected function setPassword(string $password)
	{
		$this->key = $password;
	}

	private function isSupported(): bool
	{
		if (!function_exists('openssl_get_cipher_methods'))
		{
			return false;
		}

		if (!function_exists('openssl_random_pseudo_bytes'))
		{
			return false;
		}

		if (!function_exists('openssl_cipher_iv_length'))
		{
			return false;
		}

		if (!function_exists('openssl_encrypt'))
		{
			return false;
		}

		if (!function_exists('openssl_decrypt'))
		{
			return false;
		}

		if (!function_exists('hash'))
		{
			return false;
		}

		if (!function_exists('hash_algos'))
		{
			return false;
		}

		$algorithms = openssl_get_cipher_methods();

		if (!in_array('aes-128-cbc', $algorithms))
		{
			return false;
		}

		$algorithms = hash_algos();

		return in_array('sha256', $algorithms);
	}

	private function encrypt(string $plainText): string
	{
		if (!$this->isSupported())
		{
			return $plainText;
		}

		$blockSize = $this->getBlockSize();
		$iv        = random_bytes($blockSize);

		$key     = $this->getExpandedKey($blockSize, $iv);
		$iv_size = $this->getBlockSize();
		$key     = $this->resizeKey($key, $iv_size);
		$iv      = $this->resizeKey($iv, $iv_size);

		if (empty($iv))
		{
			$iv = random_bytes($iv_size);
		}

		$plainText  .= $this->getZeroPadding($plainText, $iv_size);
		$cipherText = $iv . openssl_encrypt($plainText, $this->method, $key, $this->openSSLOptions, $iv);
		$cipherText = base64_encode($cipherText);

		// Return the result
		return '###AES128###' . $cipherText;
	}

	private function decrypt(string $cipherText, bool $legacy = false): string
	{
		if (substr($cipherText, 0, 12) != '###AES128###')
		{
			return $cipherText;
		}

		$cipherText = substr($cipherText, 12);
		$cipherText = base64_decode($cipherText);

		// Extract IV
		$iv_size = $this->getBlockSize();
		$strLen  = function_exists('mb_strlen') ? mb_strlen($cipherText, 'ASCII') : strlen($cipherText);

		// If the string is not big enough to have an Initialization Vector in front then, clearly, it is not encrypted.
		if ($strLen < $iv_size)
		{
			return '';
		}

		// Get the IV, the key and decrypt the string
		$iv  = substr($cipherText, 0, $iv_size);
		$key = $this->getExpandedKey($iv_size, $iv, $legacy);

		$iv_size    = $this->getBlockSize();
		$key        = $this->resizeKey($key, $iv_size);
		$iv         = substr($cipherText, 0, $iv_size);
		$cipherText = substr($cipherText, $iv_size);

		$decrypted = openssl_decrypt($cipherText, $this->method, $key, $this->openSSLOptions, $iv);

		if ($decrypted === false)
		{
			$decrypted = openssl_decrypt($cipherText, $this->method, $key, OPENSSL_RAW_DATA, $iv);
		}

		// Decrypted data is null byte padded. We have to remove the padding before proceeding.
		return rtrim($decrypted, "\0");
	}

	private function getBlockSize(): int
	{
		return openssl_cipher_iv_length($this->method);
	}

	private function getExpandedKey(int $blockSize, string $iv, bool $legacy = false): string
	{
		$key        = $legacy ? $this->legacyKey($this->key) : $this->key;
		$passLength = strlen($key);

		if (function_exists('mb_strlen'))
		{
			$passLength = mb_strlen($key, 'ASCII');
		}

		if ($passLength !== $blockSize)
		{
			$iterations = 1000;
			$salt       = $this->resizeKey($iv, 16);
			$key        = hash_pbkdf2('sha256', $this->key, $salt, $iterations, $blockSize, true);
		}

		return $key;
	}

	private function legacyKey($password): string
	{
		$passLength = strlen($password);

		if (function_exists('mb_strlen'))
		{
			$passLength = mb_strlen($password, 'ASCII');
		}

		if ($passLength === 32)
		{
			return $password;
		}

		// Legacy mode was doing something stupid, requiring a key of 32 bytes. DO NOT USE LEGACY MODE!
		// Legacy mode: use the sha256 of the password
		$key = hash('sha256', $password, true);
		// We have to trim or zero pad the password (we end up throwing half of it away in Rijndael-128 / AES...)
		$key = $this->resizeKey($key, $this->getBlockSize());

		return $key;
	}

	private function resizeKey(string $key, int $size): ?string
	{
		if (empty($key))
		{
			return null;
		}

		$keyLength = strlen($key);

		if (function_exists('mb_strlen'))
		{
			$keyLength = mb_strlen($key, 'ASCII');
		}

		if ($keyLength === $size)
		{
			return $key;
		}

		if ($keyLength > $size)
		{
			if (function_exists('mb_substr'))
			{
				return mb_substr($key, 0, $size, 'ASCII');
			}

			return substr($key, 0, $size);
		}

		return $key . str_repeat("\0", ($size - $keyLength));
	}

	private function getZeroPadding(string $string, int $blockSize): string
	{
		$stringSize = strlen($string);

		if (function_exists('mb_strlen'))
		{
			$stringSize = mb_strlen($string, 'ASCII');
		}

		if ($stringSize === $blockSize)
		{
			return '';
		}

		if ($stringSize < $blockSize)
		{
			return str_repeat("\0", $blockSize - $stringSize);
		}

		$paddingBytes = $stringSize % $blockSize;

		return str_repeat("\0", $blockSize - $paddingBytes);
	}

	private function getPasswordFilePath(): string
	{
		$baseName = $this->passwordFile ?: 'encrypt_service_key';

		return JPATH_ADMINISTRATOR . '/components/' . $this->componentName . '/' . $baseName . '.php';
	}

	private function getConstantName(): string
	{
		return $this->keyConstant ?:
			strtoupper(substr($this->componentName, 4)) . '_FOF_ENCRYPT_SERVICE_SECRETKEY';
	}

	private function getPassword(): string
	{
		$constantName = $this->getConstantName();

		// If we have already read the file just return the key
		if (defined($constantName))
		{
			return constant($constantName);
		}

		// Do I have a secret key file?
		$filePath = $this->getPasswordFilePath();

		// I can't get the path to the file. Cut our losses and assume we can get no key.
		if (empty($filePath))
		{
			define($constantName, '');

			return '';
		}

		// If not, try to create one.
		if (!file_exists($filePath))
		{
			$this->makePasswordFile();
		}

		// We failed to create a new file? Cut our losses and assume we can get no key.
		if (!file_exists($filePath) || !is_readable($filePath))
		{
			define($constantName, '');

			return '';
		}

		// Try to include the key file
		include_once $filePath;

		// The key file contains garbage. Treason! Cut our losses and assume we can get no key.
		if (!defined($constantName))
		{
			define($constantName, '');

			return '';
		}

		// Finally, return the key which was defined in the file (happy path).
		return constant($constantName);
	}

	private function makePasswordFile(): void
	{
		// Get the path to the new secret key file.
		$filePath = $this->getPasswordFilePath();

		// I can't get the path to the file. Sorry.
		if (empty($filePath))
		{
			return;
		}

		$secretKey    = UserHelper::genRandomPassword(64);
		$constantName = $this->getConstantName();

		$fileContent = "<?" . 'ph' . "p\n\n";
		$fileContent .= <<< END
defined('_JEXEC') or die;

/**
 * This file is automatically generated. It contains a secret key used for encrypting data by the component. Please do
 * not remove, edit or manually replace this file. It will render your existing encrypted data unreadable forever.
 */
 
define('$constantName', '$secretKey');

END;

		File::write($filePath, $fileContent);
	}
}