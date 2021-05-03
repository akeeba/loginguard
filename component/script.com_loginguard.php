<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Protect from unauthorized access
defined('_JEXEC') or die();

use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Log\Log;
use Joomla\Registry\Registry;
use Joomla\CMS\Filesystem\File;

class Com_LoginguardInstallerScript
{
	protected $componentName = 'com_loginguard';

	protected $componentTitle = 'Akeeba LoginGuard';

	protected $minimumPHPVersion = '7.3.0';

	protected $minimumJoomlaVersion = '3.9.0';

	protected $maximumJoomlaVersion = '4.0.999';

	protected $removeFiles = [
		'files'   => [],
		'folders' => [],
	];

	/**
	 * Joomla! pre-flight event. This runs before Joomla! installs or updates the component. This is our last chance to
	 * tell Joomla! if it should abort the installation.
	 *
	 * @param   string                      $type    Installation type (install, update, discover_install)
	 * @param   JInstallerAdapterComponent  $parent  Parent object
	 *
	 * @return  boolean  True to let the installation proceed, false to halt the installation
	 */
	public function preflight($type, $parent)
	{
		// Check the minimum PHP version
		if (!version_compare(PHP_VERSION, $this->minimumPHPVersion, 'ge'))
		{
			$msg = "<p>You need PHP $this->minimumPHPVersion or later to install this component</p>";

			Log::add($msg, Log::WARNING, 'jerror');

			return false;
		}
		
		// Check the minimum Joomla! version
		if (!version_compare(JVERSION, $this->minimumJoomlaVersion, 'ge'))
		{
			$msg = "<p>You need Joomla! $this->minimumJoomlaVersion or later to install this component</p>";

			Log::add($msg, Log::WARNING, 'jerror');

			return false;
		}

		// Check the maximum Joomla! version
		if (!version_compare(JVERSION, $this->maximumJoomlaVersion, 'le'))
		{
			$msg = "<p>You need Joomla! $this->maximumJoomlaVersion or earlier to install this component</p>";

			Log::add($msg, Log::WARNING, 'jerror');

			return false;
		}

		return true;
	}

	/**
	 * Runs after install, update or discover_update. In other words, it executes after Joomla! has finished installing
	 * or updating your component. This is the last chance you've got to perform any additional installations, clean-up,
	 * database updates and similar housekeeping functions.
	 *
	 * @param   string                      $type    install, update or discover_update
	 * @param   JInstallerAdapterComponent  $parent  Parent object
	 */
	function postflight($type, $parent)
	{
		// Remove obsolete files and folders
		$this->removeFilesAndFolders($this->removeFiles);

		// Always reset the OPcache if it's enabled. Otherwise there's a good chance the server will not know we are
		// replacing .php scripts. This is a major concern since PHP 5.5 included and enabled OPcache by default.
		if (function_exists('opcache_reset'))
		{
			opcache_reset();
		}
	}

	/**
	 * Removes obsolete files and folders
	 *
	 * @param   array  $removeList  The files and directories to remove
	 */
	private function removeFilesAndFolders($removeList)
	{
		foreach ($removeList['files'] ?? [] as $file)
		{
			$f = JPATH_ROOT . '/' . $file;

			@is_file($f) && File::delete($f);
		}

		foreach ($removeList['folders'] ?? [] as $folder)
		{
			$f = JPATH_ROOT . '/' . $folder;

			@is_dir($f) && Folder::delete($f);
		}
	}

}