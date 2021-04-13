<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/**
 * Helper functions for TFA handling
 */
abstract class LoginGuardHelperVersion
{
	/**
	 * A cache with the version numbers of components
	 *
	 * @var   array
	 *
	 * @since 1.2.0
	 */
	private static $version = array();

	/**
	 * Get a component's version. The XML manifest on disk will be tried first. If it's not there or does not have a
	 * version string the manifest cache in the database is tried. If that fails a fake version number will be returned.
	 *
	 * @param   string  $component  The name of the component, e.g. com_foobar
	 *
	 * @return  string  The version string
	 *
	 * @since   1.2.0
	 */
	public static function component(string $component): ?string
	{
		if (!isset(self::$version[$component]))
		{
			self::$version[$component] = null;
		}

		if (is_null(self::$version[$component]))
		{
			self::$version[$component] = self::getVersionFromManifest($component);
		}

		if (is_null(self::$version[$component]))
		{
			self::$version[$component] = self::getVersionFromDatabase($component);
		}

		if (is_null(self::$version[$component]))
		{
			self::$version[$component] = 'dev-' . str_replace(' ', '_', microtime(false));
		}

		return self::$version[$component];
	}

	/**
	 * Get a component's version from the manifest cache in the database
	 *
	 * @param   string  $component  The component's bname
	 *
	 * @return  string  The component version or null if none is defined
	 *
	 * @since   1.2.0
	 */
	private static function getVersionFromDatabase(string $component): ?string
	{
		$db      = Factory::getDbo();
		$query   = $db->getQuery(true)
			->select($db->qn('manifest_cache'))
			->from($db->qn('#__extensions'))
			->where($db->qn('element') . ' = ' . $db->q($component))
			->where($db->qn('type') . ' = ' . $db->q('component'));

		try
		{
			$json = $db->setQuery($query)->loadResult();
		}
		catch (Exception $e)
		{
			return null;
		}

		if (empty($json))
		{
			return null;
		}

		$options = json_decode($json, true);

		if (empty($options))
		{
			return null;
		}

		if (!isset($options['version']))
		{
			return null;
		}

		return $options['version'];
	}

	/**
	 * Get a component's version from the manifest file on disk. IMPORTANT! The manifest for com_something must be named
	 * something.xml.
	 *
	 * @param   string  $component  The component's bname
	 *
	 * @return  string  The component version or null if none is defined
	 *
	 * @since   1.2.0
	 */
	private static function getVersionFromManifest(string $component): ?string
	{
		$bareComponent = str_replace('com_', '', $component);
		$file = JPATH_ADMINISTRATOR . '/components/' . $component . '/' . $bareComponent . '.xml';

		if (!is_file($file) || !is_readable($file))
		{
			return null;
		}

		$data = @file_get_contents($file);

		if (empty($data))
		{
			return null;
		}

		try
		{
			$xml = new SimpleXMLElement($data, LIBXML_COMPACT | LIBXML_NONET | LIBXML_ERR_NONE);
		}
		catch (Exception $e)
		{
			return null;
		}

		$versionNode = $xml->xpath('/extension/version');

		if (empty($versionNode))
		{
			return null;
		}

		return (string)($versionNode[0]);
	}
}