<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Plugin\PluginHelper;

class LoginGuardModelWelcome extends BaseDatabaseModel
{
	/**
	 * Are there any published LoginGuard plugins in the specified folder?
	 *
	 * @return  bool
	 */
	public function isLoginGuardPluginPublished($folder)
	{
		return PluginHelper::isEnabled($folder, 'loginguard');
	}


	/**
	 * Are there any published LoginGuard plugins?
	 *
	 * @return  bool
	 */
	public function hasPublishedPlugins()
	{
		return !empty(PluginHelper::getPlugin('loginguard'));
	}

	/**
	 * Are there any installed LoginGuard plugins?
	 *
	 * Since Joomla's PLuginHelper only returned published plugins we need to go through the database to find out if
	 * there are installed but unpublished plugins.
	 *
	 * @return  bool
	 */
	public function hasInstalledPlugins()
	{
		$db = $this->getDbo();
		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->qn('#__extensions'))
			->where($db->qn('type') . ' = ' . $db->q('plugin'))
			->where($db->qn('folder') . ' = ' . $db->q('loginguard'));

		try
		{
			return !empty($db->setQuery($query)->loadResult());
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
	 */
	public function needsMigration()
	{
		// Get the users with Joomla! TFA records
		$db    = $this->getDbo();
		$query = $db->getQuery(true)
		            ->select('COUNT(*)')
		            ->from($db->qn('#__users'))
		            ->where($db->qn('otpKey') . ' != ' . $db->q(''))
		            ->where($db->qn('otep') . ' != ' . $db->q(''));
		$result = $db->setQuery($query)->loadResult();

		return !empty($result);
	}
}