<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;

defined('_JEXEC') or die;

class Pkg_LoginguardInstallerScript
{
	protected $packageName = 'pkg_loginguard';

	protected $minimumPHPVersion = '7.2.0';

	protected $minimumJoomlaVersion = '3.9.0';

	protected $maximumJoomlaVersion = '4.0.999';

	protected $extensionsToEnable = [
		// System plugins
		['plugin', 'loginguard', 'administrator', 'system'],
		// User plugins
		['plugin', 'loginguard', 'administrator', 'user'],
		// ActionLog plugins
		['plugin', 'loginguard', 'administrator', 'actionlog'],
		// LoginGuard plugins
		['plugin', 'email', 'administrator', 'loginguard'],
		['plugin', 'totp', 'administrator', 'loginguard'],
		['plugin', 'u2f', 'administrator', 'loginguard'],
		['plugin', 'webauthn', 'administrator', 'loginguard'],
		['plugin', 'yubikey', 'administrator', 'loginguard'],
	];

	/**
	 * =================================================================================================================
	 * DO NOT EDIT BELOW THIS LINE
	 * =================================================================================================================
	 */

	/**
	 * Joomla! pre-flight event. This runs before Joomla! installs or updates the package. This is our last chance to
	 * tell Joomla! if it should abort the installation.
	 *
	 * In here we'll try to install FOF. We have to do that before installing the component since it's using an
	 * installation script extending FOF's InstallScript class. We can't use a <file> tag in the manifest to install FOF
	 * since the FOF installation is expected to fail if a newer version of FOF is already installed on the site.
	 *
	 * @param   string                    $type    Installation type (install, update, discover_install)
	 * @param   JInstallerAdapterPackage  $parent  Parent object
	 *
	 * @return  boolean  True to let the installation proceed, false to halt the installation
	 */
	public function preflight($type, $parent)
	{
		// Check the minimum PHP version
		if (!version_compare(PHP_VERSION, $this->minimumPHPVersion, 'ge'))
		{
			$msg = "<p>You need PHP $this->minimumPHPVersion or later to install this package</p>";
			Log::add($msg, Log::WARNING, 'jerror');

			return false;
		}

		// Check the minimum Joomla! version
		if (!version_compare(JVERSION, $this->minimumJoomlaVersion, 'ge'))
		{
			$msg = "<p>You need Joomla! $this->minimumJoomlaVersion or later to install this package</p>";
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
	 * Tuns on installation (but not on upgrade). This happens in install and discover_install installation routes.
	 *
	 * @param   JInstallerAdapterPackage  $parent  Parent object
	 *
	 * @return  bool
	 */
	public function install($parent)
	{
		// Enable the extensions we need to install
		$this->enableExtensions();

		return true;
	}

	/**
	 * Enable modules and plugins after installing them
	 */
	private function enableExtensions()
	{
		foreach ($this->extensionsToEnable as $ext)
		{
			$this->enableExtension($ext[0], $ext[1], $ext[2], $ext[3]);
		}
	}

	/**
	 * Enable an extension
	 *
	 * @param   string   $type    The extension type.
	 * @param   string   $name    The name of the extension (the element field).
	 * @param   integer  $client  The application id (0: Joomla CMS site; 1: Joomla CMS administrator).
	 * @param   string   $group   The extension group (for plugins).
	 */
	private function enableExtension($type, $name, $client = 1, $group = null)
	{
		try
		{
			$db    = Factory::getDbo();
			$query = $db->getQuery(true)
				->update('#__extensions')
				->set($db->qn('enabled') . ' = ' . $db->q(1))
				->where('type = ' . $db->quote($type))
				->where('element = ' . $db->quote($name));
		}
		catch (Exception $e)
		{
			return;
		}


		switch ($type)
		{
			case 'plugin':
				// Plugins have a folder but not a client
				$query->where('folder = ' . $db->quote($group));
				break;

			case 'language':
			case 'module':
			case 'template':
				// Languages, modules and templates have a client but not a folder
				$client = ApplicationHelper::getClientInfo($client, true);
				$query->where('client_id = ' . (int) $client->id);
				break;

			default:
			case 'library':
			case 'package':
			case 'component':
				// Components, packages and libraries don't have a folder or client.
				// Included for completeness.
				break;
		}

		try
		{
			$db->setQuery($query);
			$db->execute();
		}
		catch (Exception $e)
		{
		}
	}

}
