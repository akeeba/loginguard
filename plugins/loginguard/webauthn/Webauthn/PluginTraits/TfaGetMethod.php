<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\LoginGuard\Webauthn\PluginTraits;

// Prevent direct access
defined('_JEXEC') || die;

use Joomla\CMS\Language\Text;

trait TfaGetMethod
{
	/**
	 * Gets the identity of this TFA method
	 *
	 * @return  array
	 *
	 * @since   3.1.0
	 */
	public function onLoginGuardTfaGetMethod(): array
	{
		if (!$this->enabled)
		{
			return array();
		}

		$helpURL = $this->params->get('helpurl', 'https://github.com/akeeba/loginguard/wiki/Webauthn');

		return array(
			// Internal code of this TFA method
			'name'               => $this->tfaMethodName,
			// User-facing name for this TFA method
			'display'            => Text::_('PLG_LOGINGUARD_WEBAUTHN_LBL_DISPLAYEDAS'),
			// Short description of this TFA method displayed to the user
			'shortinfo'          => Text::_('PLG_LOGINGUARD_WEBAUTHN_LBL_SHORTINFO'),
			// URL to the logo image for this method
			'image'              => 'media/plg_loginguard_webauthn/images/final-webauthn-logo-webauthn-color.png',
			// Are we allowed to disable it?
			'canDisable'         => true,
			// Are we allowed to have multiple instances of it per user?
			'allowMultiple'      => true,
			// URL for help content
			'help_url'           => $helpURL,
			// Allow authentication against all entries of this TFA method. Otherwise authentication takes place against a SPECIFIC entry at a time.
			'allowEntryBatching' => true,
		);
	}

}
