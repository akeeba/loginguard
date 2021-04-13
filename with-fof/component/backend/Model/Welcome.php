<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\LoginGuard\Admin\Model;

use Exception;
use FOF40\Database\Installer;
use FOF40\Model\Model;
use RuntimeException;

// Protect from unauthorized access
defined('_JEXEC') || die();

/**
 * Model for the Welcome page
 *
 * @since       2.0.0
 */
class Welcome extends Model
{
	/**
	 * Are there any published LoginGuard plugins in the specified folder?
	 *
	 * @return  bool
	 *
	 * @since   1.0.0
	 */
	public function isLoginGuardPluginPublished($folder)
	{
		$db = $this->container->db;
		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->qn('#__extensions'))
			->where($db->qn('type') . ' = ' . $db->q('plugin'))
			->where($db->qn('element') . ' = ' . $db->q('loginguard'))
			->where($db->qn('folder') . ' = ' . $db->q($folder))
			->where($db->qn('enabled') . ' = ' . $db->q(1));

		try
		{
			$result = $db->setQuery($query)->loadResult();

			return !empty($result);
		}
		catch (Exception $e)
		{
			return false;
		}
	}


	/**
	 * Are there any published LoginGuard plugins?
	 *
	 * @return  bool
	 *
	 * @since   1.0.0
	 */
	public function hasPublishedPlugins()
	{
		$db = $this->container->db;
		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->qn('#__extensions'))
			->where($db->qn('type') . ' = ' . $db->q('plugin'))
			->where($db->qn('folder') . ' = ' . $db->q('loginguard'))
			->where($db->qn('enabled') . ' = ' . $db->q(1));

		try
		{
			$result = $db->setQuery($query)->loadResult();

			return !empty($result);
		}
		catch (Exception $e)
		{
			return false;
		}
	}

	/**
	 * Are there any installed LoginGuard plugins?
	 *
	 * @return  bool
	 *
	 * @since   1.0.0
	 */
	public function hasInstalledPlugins()
	{
		$db = $this->container->db;
		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->qn('#__extensions'))
			->where($db->qn('type') . ' = ' . $db->q('plugin'))
			->where($db->qn('folder') . ' = ' . $db->q('loginguard'));

		try
		{
			$result = $db->setQuery($query)->loadResult();

			return !empty($result);
		}
		catch (Exception $e)
		{
			return false;
		}
	}

	/**
	 * Do I need to migrate Joomla Two Factor Authentication information into Akeeba LoginGuard?
	 *
	 * @return  bool
	 *
	 * @since   1.0.0
	 */
	public function needsMigration()
	{
		// There is no TFA in Joomla < 3.2
		if (version_compare(JVERSION, '3.2.0', 'lt'))
		{
			return false;
		}

		// Get the users with Joomla! TFA records
		$db    = $this->container->db;
		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->qn('#__users'))
			->where($db->qn('otpKey') . ' != ' . $db->q(''))
			->where($db->qn('otep') . ' != ' . $db->q(''));
		$result = $db->setQuery($query)->loadResult();

		return !empty($result);
	}

	/**
	 * Checks the database for missing / outdated tables and runs the appropriate SQL scripts if necessary.
	 *
	 * @return  $this
	 * @throws  RuntimeException|Exception
	 */
	public function checkAndFixDatabase()
	{
		// Install or update database
		$dbInstaller = new Installer(
			$this->container->db,
			JPATH_ADMINISTRATOR . '/components/com_loginguard/sql/xml'
		);

		$dbInstaller->updateSchema();

		return $this;
	}

}
