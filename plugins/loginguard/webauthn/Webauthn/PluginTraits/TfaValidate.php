<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */


namespace Akeeba\LoginGuard\Webauthn\PluginTraits;


// Prevent direct access
use Akeeba\LoginGuard\Admin\Model\Tfa;
use Akeeba\LoginGuard\Webauthn\Helper\Credentials;
use Joomla\CMS\Factory;
use Joomla\CMS\User\User;

defined('_JEXEC') || die;

trait TfaValidate
{
	/**
	 * Validates the Two Factor Authentication code submitted by the user in the captive Two Step Verification page. If
	 * the record does not correspond to your plugin return FALSE.
	 *
	 * @param   Tfa       $record  The TFA method's record you're validating against
	 * @param   User      $user    The user record
	 * @param   string    $code    The submitted code
	 *
	 * @return  bool
	 */
	public function onLoginGuardTfaValidate(Tfa $record, User $user, $code): bool
	{
		// Make sure we are enabled
		if (!$this->enabled)
		{
			return false;
		}

		// Make sure we are actually meant to handle this method
		if ($record->method != $this->tfaMethodName)
		{
			return false;
		}

		// Double check the TFA method is for the correct user
		if ($user->id != $record->user_id)
		{
			return false;
		}

		$this->loadComposerDependencies();

		try
		{
			Credentials::validateChallenge($code);
		}
		catch (\Exception $e)
		{
			try
			{
				Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
			}
			catch (\Exception $e)
			{
			}

			return false;
		}

		return true;
	}
}
