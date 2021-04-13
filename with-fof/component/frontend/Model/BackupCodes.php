<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\LoginGuard\Site\Model;

use Exception;
use FOF40\Model\Model;
use Joomla\CMS\Crypt\Crypt as JCrypt;
use Joomla\CMS\User\User;

// Protect from unauthorized access
defined('_JEXEC') || die();

/**
 * A model to handle the emergency backup code
 *
 * @since       2.0.0
 */
class BackupCodes extends Model
{
	/**
	 * Caches the backup codes per user ID
	 *
	 * @var   array
	 * @since 2.0.0
	 */
	protected $cache = [];

	/**
	 * Get the backup codes record for the specified user
	 *
	 * @param   User  $user  The user in question. Use null for the currently logged in user.
	 *
	 * @return  Tfa  Record object or null if none is found
	 *
	 * @since   2.0.0
	 */
	public function getBackupCodesRecord($user = null)
	{
		// Make sure I have a user
		if (empty($user))
		{
			$user = $this->container->platform->getUser();
		}

		// Try to load the record
		/** @var Tfa $tfa */
		$tfa = $this->getContainer()->factory->model('Tfa')->tmpInstance();
		$tfa
			->user_id($user->id)
			->method('backupcodes');

		try
		{
			$record = $tfa->firstOrFail();
		}
		catch (Exception $e)
		{
			// Any db issue is equivalent to "no such record exists"
			$record = null;
		}

		return $record;
	}

	/**
	 * Returns the backup codes for the specified user. Cached values will be preferentially returned, therefore you
	 * MUST go through this model's methods ONLY when dealing with backup codes.
	 *
	 * @param   User  $user  The user for which you want the backup codes
	 *
	 * @return  array|null  The backup codes, or null if they do not exist
	 *
	 * @since   2.0.0
	 */
	public function getBackupCodes($user = null)
	{
		// Make sure I have a user
		if (empty($user))
		{
			$user = $this->container->platform->getUser();
		}

		// If there is no cached record load it from the database
		if (!isset($this->cache[$user->id]))
		{
			// Intiialize (null = no record exists)
			$this->cache[$user->id] = null;
			$json                   = null;

			// Try to load the record
			try
			{
				$record = $this->getBackupCodesRecord($user);

				if (!is_object($record))
				{
					throw new \RuntimeException('Could not load record - this is OK for new users and is caught in the exception handler below.');
				}
			}
			catch (Exception $e)
			{
				// Any db issue is equivalent to "no such record exists"
			}

			if (isset($record) && is_object($record) && !empty($record->options))
			{
				$this->cache[$user->id] = $record->options;
			}
		}

		return $this->cache[$user->id];
	}

	/**
	 * Generate a new set of backup codes for the specified user. The generated codes are immediately saved to the
	 * database and the internal cache is updated.
	 *
	 * @param   User  $user  Which user to generate codes for?
	 *
	 * @since   2.0.0
	 *
	 * @throws  Exception
	 */
	public function regenerateBackupCodes($user = null)
	{
		// Make sure I have a user
		if (empty($user))
		{
			$user = $this->container->platform->getUser();
		}

		// Generate backup codes
		$backupCodes = [];

		for ($i = 0; $i < 10; $i++)
		{
			// Each backup code is 2 groups of 4 digits
			$backupCodes[$i] = sprintf('%04u%04u', $this->getRandomInteger(0, 9999), $this->getRandomInteger(0, 9999));
		}

		// Save the backup codes to the database and update the cache
		$this->saveBackupCodes($backupCodes, $user);
	}

	/**
	 * Generate a crypto-safe (true random) integer with the given range.
	 *
	 * Adapted from PHP-CryptLib by Anthony Ferrara (@ircmaxell) to use Joomla's random bytes generator in JCrypt,
	 * which is in fact ported from the prototypical work done by Anthony that ended up being part of his PHP-CryptLib.
	 *
	 * @param   int  $min  The lower bound of the range to generate
	 * @param   int  $max  The upper bound of the range to generate
	 *
	 * @return  int  The generated random number within the range
	 *
	 * @see  https://github.com/ircmaxell/PHP-CryptLib/blob/master/lib/CryptLib/Random/Generator.php
	 * @see  http://blog.ircmaxell.com/2011/07/random-number-generation-in-php.html
	 *
	 * @since   2.0.0
	 */
	protected function getRandomInteger($min = 0, $max = PHP_INT_MAX)
	{
		$tmp   = (int) max($max, $min);
		$min   = (int) min($max, $min);
		$max   = $tmp;
		$range = $max - $min;

		if ($range == 0)
		{
			return $max;
		}

		if ($range > PHP_INT_MAX || is_float($range))
		{
			/**
			 * This works, because PHP will auto-convert it to a float at this point,
			 * But on 64 bit systems, the float won't have enough precision to
			 * actually store the difference, so we need to check if it's a float
			 * and hence auto-converted...
			 */
			$min   = 0;
			$max   = PHP_INT_MAX;
			$range = $max - $min;
		}

		$bits  = (int) floor(log($range, 2) + 1);
		$bytes = (int) max(ceil($bits / 8), 1);
		$mask  = (int) (2 ** $bits - 1);

		/**
		 * The mask is a better way of dropping unused bits.  Basically what it does
		 * is to set all the bits in the mask to 1 that we may need.  Since the max
		 * range is PHP_INT_MAX, we will never need negative numbers (which would
		 * have the MSB set on the max int possible to generate).  Therefore we
		 * can just mask that away.  Since pow returns a float, we need to cast
		 * it back to an int so the mask will work.
		 *
		 * On a 64 bit platform, that means that PHP_INT_MAX is 2^63 - 1.  Which
		 * is also the mask if 63 bits are needed (by the log(range, 2) call).
		 * So if the computed result is negative (meaning the 64th bit is set), the
		 * mask will correct that.
		 *
		 * This turns out to be slightly better than the shift as we don't need to
		 * worry about "fixing" negative values.
		 */

		do
		{
			$test   = random_bytes($bytes);
			$result = hexdec(bin2hex($test)) & $mask;
		} while ($result > $range);

		return $result + $min;
	}

	/**
	 * Check if the provided string is a backup code. If it is, it will be removed from the list (replaced with an empty
	 * string) and the codes will be saved to the database. All comparisons are performed in a timing safe manner.
	 *
	 * @param   string  $code  The code to check
	 * @param   User    $user  The user to check against
	 *
	 * @return  bool
	 *
	 * @since   2.0.0
	 *
	 * @throws  Exception
	 */
	public function isBackupCode($code, User $user = null)
	{
		// Create a fake array
		$temp1 = ['', '', '', '', '', '', '', '', '', ''];
		// Load the backup codes
		$temp2 = $this->getBackupCodes($user);

		// Keep only the numbers in the provided $code
		filter_var($code, FILTER_SANITIZE_NUMBER_INT);
		$code = trim($code);

		// If the backup codes is not an array or an empty array we use our fake array of 10 elements.
		$codes = empty($temp2) ? $temp1 : $temp2;

		// Check if the code is in the array. We always check against ten codes to prevent timing attacks which
		// determine the amount of codes.
		$result = false;

		// The two arrays let us always add an element to an array, therefore having PHP expend the same amount of time
		// for the correct code, the incorrect codes and the fake codes.
		$newArray   = [];
		$dummyArray = [];

		$realLength = count($codes);
		$restLength = 10 - $realLength;

		for ($i = 0; $i < $realLength; $i++)
		{
			if (JCrypt::timingSafeCompare($codes[$i], $code))
			{
				// This may seem redundant but makes sure both branches of the if-block are isochronous
				$result       = $result || true;
				$newArray[]   = '';
				$dummyArray[] = $codes[$i];
			}
			else
			{
				// This may seem redundant but makes sure both branches of the if-block are isochronous
				$result       = $result || false;
				$dummyArray[] = '';
				$newArray[]   = $codes[$i];
			}
		}

		// This is am intentional waste of time, symmetrical to the code above, making sure evaluating each of the total
		// of ten elements takes the same time. This code should never run UNLESS someone messed up with our backup
		// codes array and it no longer contains 10 elements.
		$otherResult = false;

		for ($i = 0; $i < $restLength; $i++)
		{
			if (JCrypt::timingSafeCompare($temp1[$i], $code))
			{
				$otherResult  = $otherResult || true;
				$newArray[]   = '';
				$dummyArray[] = $temp1[$i];
			}
			else
			{
				$otherResult  = $otherResult || false;
				$newArray[]   = '';
				$dummyArray[] = $temp1[$i];
			}
		}

		// This last check makes sure than an empty code does not validate
		$result = $result && !JCrypt::timingSafeCompare('', $code);

		// Save the backup codes
		$this->saveBackupCodes($newArray, $user);

		// Finally return the result
		return $result;
	}

	/**
	 * Saves the backup codes to the database
	 *
	 * @param   array  $codes  An array of exactly 10 elements
	 * @param   User   $user   The user for which to save the backup codes
	 *
	 * @return  bool
	 *
	 * @since   2.0.0
	 * @throws  Exception
	 */
	public function saveBackupCodes(array $codes, User $user = null)
	{
		// Make sure I have a user
		if (empty($user))
		{
			$user = $this->container->platform->getUser();
		}

		$record = $this->getBackupCodesRecord($user);

		if (is_null($record))
		{
			/** @var Tfa $record */
			$record = $this->container->factory->model('Tfa')->tmpInstance();
			$record->bind([
				'user_id' => $user->id,
				'title'   => 'Backup Codes',
				'method'  => 'backupcodes',
				'default' => 0,
			]);
		}

		$record->save([
			'options' => $codes,
		]);

		// Finally, update the cache
		$this->cache[$user->id] = $codes;

		return true;
	}

}
