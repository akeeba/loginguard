<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\LoginGuard\Webauthn\PluginTraits;

// Prevent direct access
defined('_JEXEC') || die;

use Akeeba\LoginGuard\Webauthn\Helper\Credentials;
use Exception;
use JLoader;
use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use LoginGuardHelperVersion;
use LoginGuardTableTfa;
use RuntimeException;
use Webauthn\PublicKeyCredentialRequestOptions;

trait TfaCaptive
{
	/**
	 * Returns the information which allows LoginGuard to render the captive TFA page. This is the page which appears
	 * right after you log in and asks you to validate your login with TFA.
	 *
	 * @param   LoginGuardTableTfa  $record  The #__loginguard_tfa record currently selected by the user.
	 *
	 * @return  array
	 */
	public function onLoginGuardTfaCaptive(LoginGuardTableTfa $record): array
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

		// Get the media version
		JLoader::register('LoginGuardHelperVersion', JPATH_SITE . '/components/com_loginguard/helpers/version.php');
		$mediaVersion = ApplicationHelper::getHash(LoginGuardHelperVersion::component('com_loginguard'));

		/**
		 * The following code looks stupid. An explanation is in order.
		 *
		 * What we normally want to do is save the authentication data returned by getAuthenticateData into the session.
		 * This is what is sent to the U2F key through the Javascript API and signed. The signature is posted back to
		 * the form as the "code" which is read by onLoginGuardTfaValidate. That method will read the authentication
		 * data from the session and pass it along with the key registration data (from the database) and the
		 * authentication response (the "code" submitted in the form) to the U2F library for validation.
		 *
		 * Validation will work as long as the challenge recorded in the encrypted AUTHENTICATION RESPONSE matches, upon
		 * decryption, the challenge recorded in the AUTHENTICATION DATA.
		 *
		 * I observed that for whatever stupid reason the browser was sometimes sending TWO requests to the server's
		 * captive login page but only rendered the FIRST. This meant that the authentication data sent to the key had
		 * already been overwritten in the session by the "invisible" second request. As a result the challenge would
		 * not match and we'd get a validation error.
		 *
		 * The code below will attempt to read the authentication data from the session first. If it exists it will NOT
		 * try to replace it (technically it replaces it with a copy of the same data - same difference!). If nothing
		 * exists in the session, however, it WILL store the (random seeded) result of the getAuthenticateData method.
		 * Therefore the first request to the captive login page will store a new set of authentication data whereas the
		 * second, "invisible", request will just reuse the same data as the first request, fixing the observed issue in
		 * a way that doesn't compromise security.
		 *
		 * In case you are wondering, yes, the data is removed from the session in the onLoginGuardTfaValidate method.
		 * In fact it's the first thing we do after reading it, preventing constant reuse of the same set of challenges.
		 *
		 * That was fun to debug - for "poke your eyes with a rusty fork" values of fun.
		 */

		$session          = $this->app->getSession();
		$pkOptionsEncoded = $session->get('plg_loginguard_webauthn.publicKeyCredentialRequestOptions', null);

		$force = $this->app->input->getInt('force', 0);

		try
		{
			if ($force)
			{
				throw new RuntimeException('Expected exception (good): force a new key request');
			}

			if (empty($pkOptionsEncoded))
			{
				throw new RuntimeException('Expected exception (good): we do not have a pending key request');
			}

			$serializedOptions = base64_decode($pkOptionsEncoded);
			$pkOptions         = unserialize($serializedOptions);

			if (!is_object($pkOptions) || empty($pkOptions) || !($pkOptions instanceof PublicKeyCredentialRequestOptions))
			{
				throw new RuntimeException('The pending key request is corrupt; a new one will be created');
			}

			$pkRequest = json_encode($pkOptions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		}
		catch (Exception $e)
		{
			$pkRequest = Credentials::createChallenge($record->user_id);
		}

		try
		{
			Factory::getDocument()->addScriptOptions('com_loginguard.authData', base64_encode($pkRequest), false);
			$layoutPath = PluginHelper::getLayoutPath('loginguard', 'webauthn', 'validate');
			ob_start();
			include $layoutPath;
			$html = ob_get_clean();
		}
		catch (Exception $e)
		{
			return [];
		}

		// We are going to load a JS file and use custom on-load JS to intercept the loginguard-captive-button-submit button
		HTMLHelper::_('script', 'plg_loginguard_webauthn/dist/webauthn.js', [
			'version'       => $mediaVersion,
			'relative'      => true,
			'detectDebug'   => true,
			'framework'     => true,
			'pathOnly'      => false,
			'detectBrowser' => true,
		], [
			'defer' => true,
			'async' => false,
		]);

		// Load JS translations
		Text::script('PLG_LOGINGUARD_WEBAUTHN_ERR_NOTAVAILABLE_HEAD');
		Text::script('PLG_LOGINGUARD_WEBAUTHN_ERR_NO_STORED_CREDENTIAL');

		$this->app->getDocument()->addScriptOptions('com_loginguard.pagetype', 'validate', false);

		$helpURL = $this->params->get('helpurl', 'https://github.com/akeeba/loginguard/wiki/Webauthn');

		return [
			// Custom HTML to display above the TFA form
			'pre_message'        => Text::_('PLG_LOGINGUARD_WEBAUTHN_LBL_INSTRUCTIONS'),
			// How to render the TFA code field. "input" (HTML input element) or "custom" (custom HTML)
			'field_type'         => 'custom',
			// The type attribute for the HTML input box. Typically "text" or "password". Use any HTML5 input type.
			'input_type'         => '',
			// Placeholder text for the HTML input box. Leave empty if you don't need it.
			'placeholder'        => '',
			// Label to show above the HTML input box. Leave empty if you don't need it.
			'label'              => '',
			// Custom HTML. Only used when field_type = custom.
			'html'               => $html,
			// Custom HTML to display below the TFA form
			'post_message'       => '',
			// Should I hide the submit button? Useful if you need to render your own buttons or use a method which is meant to auto-submit upon doing a certain action.
			'hide_submit'        => true,
			// URL for help content
			'help_url'           => $helpURL,
			// Allow authentication against all entries of this TFA method. Otherwise authentication takes place against a SPECIFIC entry at a time.
			'allowEntryBatching' => true,
		];
	}

}
