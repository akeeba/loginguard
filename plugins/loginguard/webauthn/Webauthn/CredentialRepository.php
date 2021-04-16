<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\LoginGuard\Webauthn;

// Prevent direct access
defined('_JEXEC') || die;

use Akeeba\LoginGuard\Admin\Model\Tfa;
use JLoader;
use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use LoginGuardHelperTfa;
use LoginGuardTableTfa;
use RuntimeException;
use Webauthn\AttestationStatement\AttestationStatement;
use Webauthn\AttestedCredentialData;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialSourceRepository;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\TrustPath\EmptyTrustPath;

/**
 * Implementation of the credentials repository for the WebAuthn library.
 *
 * Important assumption: interaction with Webauthn through the library is only performed for the currently logged in
 * user. Therefore all methods which take a credential ID work by checking the LoginGuard TFA records of the current
 * user only. This is a necessity. The records are stored encrypted, therefore we cannot do a partial search in the
 * table. We have to load the records, decrypt them and inspect them. We cannot do that for thousands of records but
 * we CAN do that for the few records each user has under their account.
 *
 * This behavior can be changed by passing a user ID in the constructor of the class.
 *
 * @package     Akeeba\LoginGuard\Webauthn
 *
 * @since       3.1.0
 */
class CredentialRepository implements PublicKeyCredentialSourceRepository
{
	/**
	 * The user ID we will operate with
	 *
	 * @var   int
	 * @since 3.1.0
	 */
	private $user_id = 0;

	/**
	 * CredentialRepository constructor.
	 *
	 * @param   int  $user_id  The user ID this repository will be working with.
	 */
	public function __construct(int $user_id = 0)
	{
		if (empty($user_id))
		{
			$user_id = Factory::getUser()->id;
		}

		$this->user_id = $user_id;
	}

	public function findOneByCredentialId(string $publicKeyCredentialId): ?PublicKeyCredentialSource
	{
		$publicKeyCredentialUserEntity = new PublicKeyCredentialUserEntity('', $this->user_id, '', '');
		$credentials                   = $this->findAllForUserEntity($publicKeyCredentialUserEntity);

		foreach ($credentials as $record)
		{
			if ($record->getAttestedCredentialData()->getCredentialId() != $publicKeyCredentialId)
			{
				continue;
			}

			return $record;
		}

		return null;
	}

	public function findAllForUserEntity(PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity): array
	{
		if (empty($publicKeyCredentialUserEntity))
		{
			$user_id = $this->user_id;
		}
		else
		{
			$user_id = $publicKeyCredentialUserEntity->getId();
		}

		$return = [];

		JLoader::register('LoginGuardHelperTfa', JPATH_SITE . '/components/com_loginguard/helpers/tfa.php');
		$results = LoginGuardHelperTfa::getUserTfaRecords($user_id);

		if (count($results) < 1)
		{
			return $return;
		}

		/** @var Tfa $result */
		foreach ($results as $result)
		{
			$options = $result->options;

			if (!is_array($options) || empty($options))
			{
				continue;
			}

			if (!isset($options['attested']) && !isset($options['pubkeysource']))
			{
				continue;
			}

			if (isset($options['attested']) && is_string($options['attested']))
			{
				$options['attested'] = json_decode($options['attested'], true);

				$return[$result->id] = $this->attestedCredentialToPublicKeyCredentialSource(
					AttestedCredentialData::createFromArray($options['attested']), $user_id
				);
			}
			elseif (isset($options['pubkeysource']) && is_string($options['pubkeysource']))
			{
				$options['pubkeysource']  = json_decode($options['pubkeysource'], true);
				$return[$result->id] = PublicKeyCredentialSource::createFromArray($options['pubkeysource']);
			}
			elseif (isset($options['pubkeysource']) && is_array($options['pubkeysource']))
			{
				$return[$result->id] = PublicKeyCredentialSource::createFromArray($options['pubkeysource']);
			}
		}

		return $return;
	}

	public function saveCredentialSource(PublicKeyCredentialSource $publicKeyCredentialSource): void
	{
		// I can only create or update credentials for the user this class was created for
		if ($publicKeyCredentialSource->getUserHandle() != $this->user_id)
		{
			throw new RuntimeException('Cannot create or update WebAuthn credentials for a different user.', 403);
		}

		// Do I have an existing record for this credential?
		$recordId                      = null;
		$publicKeyCredentialUserEntity = new PublicKeyCredentialUserEntity('', $this->user_id, '', '');
		$credentials                   = $this->findAllForUserEntity($publicKeyCredentialUserEntity);

		foreach ($credentials as $id => $record)
		{
			if ($record->getAttestedCredentialData()->getCredentialId() != $publicKeyCredentialSource->getAttestedCredentialData()->getCredentialId())
			{
				continue;
			}

			$recordId = $id;

			break;
		}

		// Create or update a record
		Table::addIncludePath(JPATH_ROOT . '/components/com_loginguard/tables');

		/** @var LoginGuardTableTfa $tfaModel */
		$tfaModel = Table::getInstance('Tfa', 'LoginGuardTable');

		if ($recordId)
		{
			$tfaModel->load($recordId);

			$options = $tfaModel->options;

			if (isset($options['attested']))
			{
				unset($options['attested']);
			}

			$options['pubkeysource'] = $publicKeyCredentialSource;
			$tfaModel->save([
				'options' => $options
			]);
		}
		else
		{
			$tfaModel->reset();
			$tfaModel->save([
				'user_id' => $this->user_id,
				'title'   => 'WebAuthn auto-save',
				'method'  => 'webauthn',
				'default' => 0,
				'options' => ['pubkeysource' => $publicKeyCredentialSource],
			]);
		}
	}

	/**
	 * Converts a legacy AttestedCredentialData object stored in the database into a PublicKeyCredentialSource object.
	 *
	 * This makes several assumptions which can be problematic and the reason why the WebAuthn library version 2 moved
	 * away from attested credentials to public key credential sources:
	 *
	 * - The credential is always of the public key type (that's safe as the only option supported)
	 * - You can access it with any kind of authenticator transport: USB, NFC, Internal or Bluetooth LE (possibly
	 * dangerous)
	 * - There is no attestations (generally safe since browsers don't seem to support attestation yet)
	 * - There is no trust path (generally safe since browsers don't seem to provide one)
	 * - No counter was stored (dangerous since it can lead to replay attacks).
	 *
	 * @param   AttestedCredentialData  $record   Legacy attested credential data object
	 * @param   int                     $user_id  User ID we are getting the credential source for
	 *
	 * @return  PublicKeyCredentialSource
	 */
	private function attestedCredentialToPublicKeyCredentialSource(AttestedCredentialData $record, int $user_id): PublicKeyCredentialSource
	{
		return new PublicKeyCredentialSource(
			$record->getCredentialId(),
			PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
			[
				PublicKeyCredentialDescriptor::AUTHENTICATOR_TRANSPORT_USB,
				PublicKeyCredentialDescriptor::AUTHENTICATOR_TRANSPORT_NFC,
				PublicKeyCredentialDescriptor::AUTHENTICATOR_TRANSPORT_INTERNAL,
				PublicKeyCredentialDescriptor::AUTHENTICATOR_TRANSPORT_BLE,
			],
			AttestationStatement::TYPE_NONE,
			new EmptyTrustPath(),
			$record->getAaguid(),
			$record->getCredentialPublicKey(),
			$user_id,
			0
		);
	}
}
