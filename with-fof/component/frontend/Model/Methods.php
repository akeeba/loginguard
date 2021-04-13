<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\LoginGuard\Site\Model;

use Akeeba\LoginGuard\Site\Helper\Tfa;
use Akeeba\LoginGuard\Site\Model\Tfa as TfaRecord;
use DateInterval;
use DateTimeZone;
use Exception;
use FOF40\Model\Model;
use Joomla\CMS\Language\Text as JText;
use Joomla\CMS\User\User;
use RuntimeException;

// Protect from unauthorized access
defined('_JEXEC') || die();

/**
 * Two Step Verification methods list page's model
 *
 * @since       2.0.0
 */
class Methods extends Model
{
	/**
	 * Returns a list of all available and their currently active records for given user.
	 *
	 * @param   User  $user  The user object. Skip to use the current user.
	 *
	 * @return  array
	 * @since   2.0.0
	 */
	public function getMethods($user = null)
	{
		if (!is_object($user) || !($user instanceof User))
		{
			$user = $this->container->platform->getUser();
		}

		if ($user->guest)
		{
			return [];
		}

		// Get an associative array of TFA methods
		$rawMethods = Tfa::getTfaMethods();
		$methods    = [];

		foreach ($rawMethods as $method)
		{
			$method['active']         = [];
			$methods[$method['name']] = $method;
		}

		// Put the user TFA records into the methods array
		/** @var TfaRecord $tfaModel */
		$tfaModel       = $this->container->factory->model('Tfa')->tmpInstance();
		$userTfaRecords = $tfaModel->user_id($user->id)->get(true);

		if ($userTfaRecords->count() >= 1)
		{
			foreach ($userTfaRecords as $record)
			{
				if (!isset($methods[$record->method]))
				{
					continue;
				}

				$methods[$record->method]['active'][$record->id] = $record;
			}
		}

		return $methods;
	}

	/**
	 * Delete all Two Step Verification methods for the given user.
	 *
	 * @param   User  $user  The user object to reset TSV for. Null to use the current user.
	 *
	 * @return  void
	 *
	 * @throws  RuntimeException  When the user is invalid or a database error has occurred.
	 * @since   2.0.0
	 */
	public function deleteAll($user = null)
	{
		// Make sure we have a user object
		if (is_null($user))
		{
			$user = $this->container->platform->getUser();
		}

		// If the user object is a guest (who can't have TSV) we abort with an error
		if ($user->guest)
		{
			throw new RuntimeException(JText::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		/** @var TfaRecord $tfaModel */
		$tfaModel = $this->container->factory->model('Tfa')->tmpInstance();
		$tfaModel->user_id($user->id)->get(true)->delete();
	}

	/**
	 * Format a relative timestamp. It deals with timestamps today and yesterday in a special manner. Example returns:
	 * Yesterday, 13:12
	 * Today, 08:33
	 * January 1, 2015
	 *
	 * @param   string  $dateTimeText  The database time string to use, e.g. "2017-01-13 13:25:36"
	 *
	 * @return  string  The formatted, human-readable date
	 */
	public function formatRelative($dateTimeText)
	{
		if (empty($dateTimeText))
		{
			return '&ndash;';
		}

		// The timestamp is given in UTC. Make sure Joomla! parses it as such.
		$utcTimeZone = new DateTimeZone('UTC');
		$jDate       = $this->container->platform->getDate($dateTimeText, $utcTimeZone);
		$unixStamp   = $jDate->toUnix();

		// I'm pretty sure we didn't have TFA in Joomla back in 1970 ;)
		if ($unixStamp < 0)
		{
			return '&ndash;';
		}

		// I need to display the date in the user's local timezone. That's how you do it.
		$user   = $this->container->platform->getUser();
		$userTZ = $user->getParam('timezone', 'UTC');
		$tz     = new DateTimeZone($userTZ);
		$jDate->setTimezone($tz);

		// Default format string: way in the past, the time of the day is not important
		$formatString    = JText::_('COM_LOGINGUARD_LBL_DATE_FORMAT_PAST');
		$containerString = JText::_('COM_LOGINGUARD_LBL_PAST');

		// If the timestamp is within the last 72 hours we may need a special format
		if ($unixStamp > (time() - (72 * 3600)))
		{
			// Is this timestamp today?
			$jNow = $this->container->platform->getDate();
			$jNow->setTimezone($tz);
			$checkNow  = $jNow->format('Ymd', true);
			$checkDate = $jDate->format('Ymd', true);

			if ($checkDate == $checkNow)
			{
				$formatString    = JText::_('COM_LOGINGUARD_LBL_DATE_FORMAT_TODAY');
				$containerString = JText::_('COM_LOGINGUARD_LBL_TODAY');
			}
			else
			{
				// Is this timestamp yesterday?
				$jYesterday = clone $jNow;
				$jYesterday->setTime(0, 0, 0);
				$oneSecond = new DateInterval('PT1S');
				$jYesterday->sub($oneSecond);
				$checkYesterday = $jYesterday->format('Ymd', true);

				if ($checkDate == $checkYesterday)
				{
					$formatString    = JText::_('COM_LOGINGUARD_LBL_DATE_FORMAT_YESTERDAY');
					$containerString = JText::_('COM_LOGINGUARD_LBL_YESTERDAY');
				}
			}
		}

		return sprintf($containerString, $jDate->format($formatString, true));
	}

	/**
	 * Set the user's "don't show this again" flag.
	 *
	 * @param   User   $user  The user to check
	 * @param   bool   $flag  True to set the flag, false to unset it (it will be set to 0, actually)
	 *
	 * @return  void
	 * @since   2.0.0
	 */
	public function setFlag(User $user, $flag = true)
	{
		$db    = $this->container->db;
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
			return;
		}

		$exists = !is_null($result);

		$object = (object) [
			'user_id'       => $user->id,
			'profile_key'   => 'loginguard.dontshow',
			'profile_value' => ($flag ? 1 : 0),
			'ordering'      => 1,
		];

		if (!$exists)
		{
			$db->insertObject('#__user_profiles', $object);
		}
		else
		{
			$db->updateObject('#__user_profiles', $object, ['user_id', 'profile_key']);
		}
	}
}
