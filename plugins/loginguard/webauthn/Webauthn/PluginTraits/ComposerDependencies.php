<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\LoginGuard\Webauthn\PluginTraits;

// Prevent direct access
defined('_JEXEC') || die;

trait ComposerDependencies
{
	protected function loadComposerDependencies()
	{
		if (version_compare(JVERSION, '3.999.999', 'gt'))
		{
			return;
		}

		// Is the library already loaded?
		if (class_exists('Webauthn\CredentialRepository'))
		{
			return;
		}

		require_once JPATH_ADMINISTRATOR . '/components/com_loginguard/vendor/autoload.php';
	}
}
