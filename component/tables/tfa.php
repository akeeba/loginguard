<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
defined('_JEXEC') or die;

use Joomla\CMS\Table\Table;

/**
 * Class LoginGuardTableTfa
 *
 * @property int    $id         Record ID.
 * @property int    user_id     User ID
 * @property string $title      Record title.
 * @property string $method     TFA method (corresponds to one of the plugins).
 * @property int    $default    Is this the default method?
 * @property array  $options    Configuration options for the TFA method.
 * @property string $created_on Date and time the record was created.
 * @property string $last_used  Date and time the record was las used successfully.
 */
class LoginGuardTableTfa extends Table
{
	/**
	 * Internal flag used to create backup codes when I'm creating the very first TFA record
	 *
	 * @var   bool
	 * @since 3.0.0
	 */
	private $mustCreateBackupCodes = false;

	/**
	 * Delete flags per ID, set up onBeforeDelete and used onAfterDelete
	 *
	 * @var   array
	 * @since 3.0.0
	 */
	private $deleteFlags = [];

	/**
	 * Constructor
	 *
	 * @param   JDatabaseDriver  $db  Database connector object
	 */
	public function __construct(&$db)
	{
		parent::__construct('#__loginguard_tfa', 'id', $db);

		if (!class_exists('LoginGuardTableObserverEncrypt'))
		{
			require_once __DIR__ . '/observers/encrypt.php';
		}

		if (!class_exists('LoginGuardTableObserverDefault'))
		{
			require_once __DIR__ . '/observers/default.php';
		}

		LoginGuardTableObserverEncrypt::createObserver($this, [
			'columns' => [
				'options'
			]
		]);

		LoginGuardTableObserverDefault::createObserver($this);
	}

	public function check(): bool
	{
		if (empty($this->user_id))
		{
			$this->setError("The user ID of a LoginGuard TFA record cannot be empty.");

			return false;
		}

		return parent::check();
	}


}