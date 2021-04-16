<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
defined('_JEXEC') or die;

JLoader::register('LoginGuardBase32', __DIR__ . '/base32.php');

/**
 * This class provides an RFC6238-compliant Time-based One Time Passwords,
 * compatible with Google Authenticator (with PassCodeLength = 6 and TimePeriod = 30).
 */
class LoginGuardAuthenticator
{
	/**
	 * Passcode length, in characters
	 *
	 * @var   int
	 */
	private $_passCodeLength = 6;

	/**
	 * The modulo (10 ^ _passCodeLength) for the TOTP
	 *
	 * @var   number
	 */
	private $_pinModulo;

	/**
	 * The length of the base32-encoded key
	 *
	 * @var   int
	 */
	private $_secretLength = 10;

	/**
	 * The time step, in seconds.
	 *
	 * @var int
	 */
	private $_timeStep = 30;

	/**
	 * The base32 helper class
	 *
	 * @var   LoginGuardBase32
	 */
	private $_base32 = null;

	/**
	 * Initialises an RFC6238-compatible TOTP generator. Please note that this class does not implement the constraint
	 * in the last paragraph of §5.2 of RFC6238. It's up to you to ensure that the same user/device does not retry
	 * validation within the same Time Step.
	 *
	 * @param   int               $timeStep            The Time Step (in seconds). Use 30 to be compatible with Google
	 *                                                 Authenticator.
	 * @param   int               $passCodeLength      The generated passcode length. Default: 6 digits.
	 * @param   int               $secretLength        The length of the secret key. Default: 10 bytes (80 bits).
	 * @param   LoginGuardBase32  $base32              The base32 en/decrypter
	 */
	public function __construct($timeStep = 30, $passCodeLength = 6, $secretLength = 10, $base32 = null)
	{
		$this->_timeStep       = $timeStep;
		$this->_passCodeLength = $passCodeLength;
		$this->_secretLength   = $secretLength;
		$this->_pinModulo      = pow(10, $this->_passCodeLength);
		$this->_base32         = $base32;

		if (is_null($base32))
		{
			$this->_base32 = new LoginGuardBase32();
		}
	}

	/**
	 * Get the time period based on the $time timestamp and the Time Step defined. If $time is skipped or set to null
	 * the current timestamp will be used.
	 *
	 * @param   int|null  $time  Timestamp
	 *
	 * @return  int  The time period since the UNIX Epoch
	 */
	public function getPeriod($time = null)
	{
		if (is_null($time))
		{
			$time = time();
		}

		$period = floor($time / $this->_timeStep);

		return $period;
	}

	/**
	 * Check is the given passcode $code is a valid TOTP generated using secret key $secret.
	 *
	 * @param   string  $secret  The Base32-encoded secret key
	 * @param   string  $code    The passcode to check
	 *
	 * @return  boolean True if the code is valid
	 */
	public function checkCode(string $secret, string $code): bool
	{
		$time   = $this->getPeriod();
		$result = false;

		for ($i = -1; $i <= 1; $i++)
		{
			$generatedCode = $this->getCode($secret, ($time + $i) * $this->_timeStep);

			if (hash_equals($generatedCode, $code))
			{
				$result = true;
			}
		}

		return $result;
	}

	/**
	 * Gets the TOTP passcode for a given secret key $secret and a given UNIX timestamp $time
	 *
	 * @param   string  $secret  The Base32-encoded secret key
	 * @param   int     $time    UNIX timestamp
	 *
	 * @return  string
	 */
	public function getCode($secret, $time = null)
	{
		$period              = $this->getPeriod($time);
		$secret              = $this->_base32->decode($secret);
		$this->_secretLength = mb_strlen($secret, '8bit');

		$time = pack("N", $period);
		$time = str_pad($time, 8, chr(0), STR_PAD_LEFT);

		$hash   = hash_hmac('sha1', $time, $secret, true);
		$offset = ord(substr($hash, -1));
		$offset = $offset & 0xF;

		$truncatedHash = $this->hashToInt($hash, $offset) & 0x7FFFFFFF;
		$pinValue      = str_pad($truncatedHash % $this->_pinModulo, $this->_passCodeLength, "0", STR_PAD_LEFT);

		return $pinValue;
	}

	/**
	 * Returns a QR code URL for easy setup of TOTP apps like Google Authenticator
	 *
	 * @param   string  $user      User
	 * @param   string  $hostname  Hostname
	 * @param   string  $secret    Secret string
	 *
	 * @return  string
	 */
	public function getUrl($user, $hostname, $secret)
	{
		$url        = sprintf("otpauth://totp/%s@%s?secret=%s", $user, $hostname, $secret);
		$encoder    = "https://chart.googleapis.com/chart?chs=200x200&chld=Q|2&cht=qr&chl=";
		$encoderURL = $encoder . urlencode($url);

		return $encoderURL;
	}

	/**
	 * Generates a (semi-)random Secret Key for TOTP generation
	 *
	 * @return  string
	 */
	public function generateSecret()
	{
		$secret = random_bytes($this->_secretLength);

		return $this->_base32->encode($secret);
	}

	/**
	 * Returns the length of the secret key, in bytes
	 *
	 * @return int
	 */
	public function getSecretLength(): int
	{
		return $this->_secretLength;
	}

	/**
	 * Extracts a part of a hash as an integer
	 *
	 * @param   string  $bytes  The hash
	 * @param   string  $start  The char to start from (0 = first char)
	 *
	 * @return  string
	 */
	protected function hashToInt($bytes, $start)
	{
		$input = substr($bytes, $start, strlen($bytes) - $start);
		$val2  = unpack("N", substr($input, 0, 4));

		return $val2[1];
	}
}
