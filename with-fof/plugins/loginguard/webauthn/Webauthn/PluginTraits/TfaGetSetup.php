<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\LoginGuard\Webauthn\PluginTraits;

// Prevent direct access
use Akeeba\LoginGuard\Admin\Model\Tfa;
use Akeeba\LoginGuard\Webauthn\Helper\Credentials;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;

defined('_JEXEC') || die;

trait TfaGetSetup
{
	/**
	 * Returns the information which allows LoginGuard to render the TFA setup page. This is the page which allows the
	 * user to add or modify a TFA method for their user account. If the record does not correspond to your plugin
	 * return an empty array.
	 *
	 * @param   Tfa  $record  The #__loginguard_tfa record currently selected by the user.
	 *
	 * @return  array
	 */
	public function onLoginGuardTfaGetSetup(Tfa $record): array
	{
		// Make sure we are enabled
		if (!$this->enabled)
		{
			return [];
		}

		// Make sure we are actually meant to handle this method
		if ($record->method != $this->tfaMethodName)
		{
			return [];
		}

		$this->loadComposerDependencies();

		// Get some values assuming that we are NOT setting up U2F (the key is already registered)
		$submitClass = '';
		$preMessage  = Text::_('PLG_LOGINGUARD_WEBAUTHN_LBL_CONFIGURED');
		$type        = 'input';
		$html        = '';
		$helpURL     = $this->params->get('helpurl', 'https://github.com/akeeba/loginguard/wiki/Webauthn');
		$hiddenData  = [];

		/**
		 * If there are no authenticators set up yet I need to show a different message and take a different action when
		 * my user clicks the submit button.
		 */
		if (!is_array($record->options) || empty($record->options) || !isset($record->options['credentialId']) || empty($record->options['credentialId']))
		{
			// Load Javascript
			HTMLHelper::_('script', 'plg_loginguard_webauthn/dist/webauthn.js', [
				'version'       => 'auto',
				'relative'      => true,
				'detectDebug'   => true,
				'framework'     => true,
				'pathOnly'      => false,
				'detectBrowser' => true,
			], [
				'defer' => true,
				'async' => false,
			]);

			$layoutPath = PluginHelper::getLayoutPath('loginguard', 'webauthn', 'register');
			ob_start();
			include $layoutPath;
			$html = ob_get_clean();
			$type = 'custom';

			// Load JS translations
			Text::script('PLG_LOGINGUARD_WEBAUTHN_ERR_NOTAVAILABLE_HEAD');

			Factory::getDocument()->addScriptOptions('com_loginguard.pagetype', 'setup', false);

			// Save the U2F request to the session
			$user                    = Factory::getUser();
			$hiddenData['pkRequest'] = base64_encode(Credentials::createPublicKey($user));

			// Special button handling
			$submitClass = "loginguard_webauthn_setup";

			// Message to display
			$preMessage = Text::_('PLG_LOGINGUARD_WEBAUTHN_LBL_INSTRUCTIONS');
		}

		return [
			// Default title if you are setting up this TFA method for the first time
			'default_title' => Text::_('PLG_LOGINGUARD_WEBAUTHN_LBL_DISPLAYEDAS'),
			// Custom HTML to display above the TFA setup form
			'pre_message'   => $preMessage,
			// Heading for displayed tabular data. Typically used to display a list of fixed TFA codes, TOTP setup parameters etc
			'table_heading' => '',
			// Any tabular data to display (label => custom HTML). See above
			'tabular_data'  => [],
			// Hidden fields to include in the form (name => value)
			'hidden_data'   => $hiddenData,
			// How to render the TFA setup code field. "input" (HTML input element) or "custom" (custom HTML)
			'field_type'    => $type,
			// The type attribute for the HTML input box. Typically "text" or "password". Use any HTML5 input type.
			'input_type'    => 'hidden',
			// Pre-filled value for the HTML input box. Typically used for fixed codes, the fixed YubiKey ID etc.
			'input_value'   => '',
			// Placeholder text for the HTML input box. Leave empty if you don't need it.
			'placeholder'   => '',
			// Label to show above the HTML input box. Leave empty if you don't need it.
			'label'         => '',
			// Custom HTML. Only used when field_type = custom.
			'html'          => $html,
			// Should I show the submit button (apply the TFA setup)? Only applies in the Add page.
			'show_submit'   => false,
			// Additional CSS classes for the submit button (apply the TFA setup)
			'submit_class'  => $submitClass,
			// Custom HTML to display below the TFA setup form
			'post_message'  => '',
			// URL for help content
			'help_url'      => $helpURL,
		];
	}

}
