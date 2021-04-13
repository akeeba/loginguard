<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\LoginGuard\Admin\Model;

// Protect from unauthorized access
defined('_JEXEC') || die();

use Akeeba\LoginGuard\Site\Model\BackupCodes;
use FOF40\Container\Container;
use FOF40\Model\DataModel;
use Joomla\CMS\Language\Text as JText;
use RuntimeException;

/**
 * @package     Akeeba\LoginGuard\Admin\Model
 *
 * @since       3.0.0
 *
 * @property int    $user_id
 * @property string $title
 * @property string $method
 * @property int    $default
 * @property string $created_on
 * @property string $last_used
 * @property array  $options
 *
 * @method   $this  user_id($v)
 * @method   $this  title($v)
 * @method   $this  method($v)
 * @method   $this  default($v)
 * @method   $this  created_on($v)
 * @method   $this  last_used($v)
 * @method   $this  options($v)
 */
class Tfa extends DataModel
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

	public function __construct(Container $container, array $config = [])
	{
		$config['tableName']   = '#__loginguard_tfa';
		$config['idFieldName'] = 'id';
		$config['behaviours'] = ['filters'];

		parent::__construct($container, $config);
	}

	/**
	 * JSON-encode and encrypt the method options before saving to the database
	 *
	 * @param   mixed  $value  The raw options value of the record (should be an array)
	 *
	 * @return  string
	 *
	 * @since   3.0.0
	 */
	protected function setOptionsAttribute($value)
	{
		if (empty($value))
		{
			$value = [];
		}

		$encoded = json_encode($value);

		return $this->container->crypto->encrypt($encoded);
	}

	/**
	 * Decrypt and JSON-decode the method options after loading from the database
	 *
	 * @param   string  $value  The ciphertext
	 *
	 * @return  array  The decrypted, decoded value
	 *
	 * @since   3.0.0
	 */
	protected function getOptionsAttribute($value)
	{
		if (is_string($value))
		{
			$decrypted = @json_decode($this->container->crypto->decrypt($value), true);

			if (!is_array($decrypted))
			{
				$decrypted = @json_decode($this->container->crypto->decrypt($value, true), true);
			}

			$value = $decrypted;
		}

		return empty($value) ? [] : $value;
	}

	/**
	 * Check the validity of the record. Deal with default records.
	 *
	 * @return  static
	 *
	 * @since   3.0.0
	 */
	public function check()
	{
		if (empty($this->user_id))
		{
			throw new \RuntimeException("The user ID of a LoginGuard TFA record cannot be empty.");
		}

		parent::check();

		// If this record is marked as the default we have to unset all other default records
		if ($this->default)
		{
			$this->getClone()->tmpInstance()->reset(true, true)->user_id($this->user_id)
				->get(true)->save([
				'default' => 0
			]);
		}

		return $this;
	}

	/**
	 * We are about to create a new record. If this is the only record for the user let's make it default and raise a
	 * flag that we need to create backup codes.
	 *
	 * @param   \stdClass  $dataObject  The data to be stored to the database. See FOF 3 code.
	 *
	 * @since   3.0.0
	 */
	protected function onBeforeCreate(&$dataObject)
	{
		$numOldRecords = $this->getClone()->tmpInstance()
			->reset(true, true)->user_id($this->user_id)
			->get(true)->count();

		if ($numOldRecords > 0)
		{
			return;
		}

		$this->mustCreateBackupCodes = 1;
		$dataObject->default = 1;
	}

	/**
	 * We have just created a new record. If we have set the flag to create new backup records let's create them.
	 *
	 * @since   3.0.0
	 */
	protected function onAfterCreate()
	{
		if (!$this->mustCreateBackupCodes)
		{
			return;
		}

		/** @var BackupCodes $backupCodes */
		$backupCodes = $this->container->factory->model('BackupCodes');
		$user = $this->container->platform->getUser($this->user_id);
		$backupCodes->regenerateBackupCodes($user);
	}

	protected function onBeforeDelete(&$id)
	{
		$record = $this;

		if ($id != $this->getId())
		{
			try
			{
				$record = $this->getClone()
					->tmpInstance()->reset(true)->findOrFail($id);
			}
			catch (\Exception $e)
			{
				// If the record does not exist I will stomp my feet and deny your request
				throw new RuntimeException(JText::_('JERROR_ALERTNOAUTHOR'), 403);
			}
		}

		$user = $this->container->platform->getUser();

		// The user must be a registered user, not a guest
		if ($user->guest)
		{
			throw new RuntimeException(JText::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		// You can only delete your own records, unless you're a super user or have delete privileges on this component
		if (($record->user_id != $user->id) && !\Akeeba\LoginGuard\Site\Helper\Tfa::canEditUser($user))
		{
			throw new RuntimeException(JText::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		// Save flags used onAfterDelete
		$this->deleteFlags[$record->getId()] = [
			'default'    => $record->default,
			'numRecords' => $record->getClone()->tmpInstance()->reset(true, true)
				->user_id($record->user_id)->get(true)->count(),
			'user_id'    => $record->user_id,
			'method'     => $record->method,
		];
	}

	protected function onAfterDelete($id)
	{
		if (!isset($this->deleteFlags[$id]))
		{
			return;
		}

		if (($this->deleteFlags[$id]['numRecords'] <= 2) && ($this->deleteFlags[$id]['method'] != 'backupcodes'))
		{
			/**
			 * This was the second to last TFA record in the database (the last one is the backupcodes). Therefore we
			 * need to delete the remaining entry and go away. We don't trigger this if the method we are deleting was
			 * the backupcodes because we might just be regenerating the backup codes.
			 */
			$this->getClone()->tmpInstance()->reset(true, true)
				->user_id($this->deleteFlags[$id]['user_id'])
				->get(true)->delete();

			unset($this->deleteFlags[$id]);

			return;
		}

		// This was the default record. Promote the next available record to default.
		if ($this->deleteFlags[$id]['default'])
		{
			$this->getClone()->tmpInstance()
				->reset(true, true)->user_id($this->deleteFlags[$id]['user_id'])
				->get(false, 0, 1)->save([
					'default' => 1
				]);
		}
	}
}
