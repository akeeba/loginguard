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
use Exception;
use FOF40\Container\Container;
use FOF40\Input\Input;
use Joomla\CMS\Language\Text;
use RuntimeException;

defined('_JEXEC') || die;

trait TfaSaveSetup
{
	/**
	 * Parse the input from the TFA setup page and return the configuration information to be saved to the database. If
	 * the information is invalid throw a RuntimeException to signal the need to display the editor page again. The
	 * message of the exception will be displayed to the user. If the record does not correspond to your plugin return
	 * an empty array.
	 *
	 * @param   Tfa    $record  The #__loginguard_tfa record currently selected by the user.
	 * @param   Input  $input   The user input you are going to take into account.
	 *
	 * @return  array  The configuration data to save to the database
	 *
	 * @throws  RuntimeException  In case the validation fails
	 */
	public function onLoginGuardTfaSaveSetup(Tfa $record, Input $input): array
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

		$code                = $input->get('code', null, 'base64');
		$container           = Container::getInstance('com_loginguard');
		$registrationRequest = $container->platform->getSessionVar('publicKeyCredentialCreationOptions', null, 'plg_loginguard_webauthn');

		// If there was no registration request BUT there is a registration response throw an error
		if (empty($registrationRequest) && !empty($code))
		{
			throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		// If there is no registration request (and there isn't a registration response) we are just saving the title.
		if (empty($registrationRequest))
		{
			return $record->options;
		}

		// In any other case try to authorize the registration
		try
		{
			$publicKeyCredentialSource = Credentials::validateAuthenticationData($code);
		}
		catch (Exception $err)
		{
			throw new RuntimeException($err->getMessage(), 403);
		}
		finally
		{
			// Unset the request data from the session.
			$container->platform->setSessionVar('publicKeyCredentialCreationOptions', null, 'plg_loginguard_webauthn');
			$container->platform->setSessionVar('registration_user_id', null, 'plg_loginguard_webauthn');
		}

		// Return the configuration to be serialized
		return [
			'credentialId' => base64_encode($publicKeyCredentialSource->getAttestedCredentialData()->getCredentialId()),
			'pubkeysource' => json_encode($publicKeyCredentialSource),
			'counter'      => 0,
		];
	}
}
