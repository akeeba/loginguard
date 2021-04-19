<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Table\Observer\AbstractObserver;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Table\TableInterface;
use Joomla\Utilities\ArrayHelper;

defined('_JEXEC') || die;

/**
 * Manage the default 2SV method and the automatic generation of backup codes.
 *
 * @since 5.0.0
 */
class LoginGuardTableObserverDefault
{
	/**
	 * The observed table
	 *
	 * @var    Table
	 */
	protected $table;

	/**
	 * Delete flags per ID, set up onBeforeDelete and used onAfterDelete
	 *
	 * @var   array
	 * @since 3.0.0
	 */
	private $deleteFlags = [];

	/**
	 * @var int
	 */
	private $mustCreateBackupCodes = 0;

	public function __construct(Table $table, array $params = [])
	{
		$this->table = $table;
	}

	public function onBeforeStore($updateNulls, $tableKey)
	{
		$this->mustCreateBackupCodes = 0;

		// We only care about new records, i.e. those with an ID that's NULL or 0
		if (!empty($this->table->id))
		{
			return;
		}

		// This does not apply to backup codes (otherwise we'd enter an infinite loop)
		if ($this->table->method == 'backupcodes')
		{
			return;
		}

		$records = LoginGuardHelperTfa::getUserTfaRecords($this->table->user_id);

		if ($this->table->id)
		{
			// Existing record. Remove it from the list of records.
			$records = array_filter($records, function ($rec)  {
				return $rec->id != $this->table->id;
			});
		}
		else
		{
			// New record. Update the created_on column
			$this->table->created_on = Date::getInstance()->toSql();
		}

		// Do I need to mark this record as the default?
		if ($this->table->default == 0)
		{
			$hasDefaultRecord = array_reduce($records, function ($carry, $record) {
				return $carry || ($record->default == 1);
			}, false);

			if (!$hasDefaultRecord)
			{
				$this->table->default = 1;
			}
		}

		// Get the number of records this user_id has, except the current record.
		$numOldRecords = count($records);

		if ($numOldRecords == 0)
		{
			$this->mustCreateBackupCodes = 1;
			$this->table->default = 1;
		}
	}

	public function onAfterStore(&$result)
	{
		$this->switchDefaultRecord();

		$this->conditionallyRegenerateBackupCodes();

		$this->mustCreateBackupCodes = 0;
	}

	public function onBeforeDelete($pk)
	{
		$record = $this->table;

		if ($pk != $this->table->id)
		{
			$record = Table::getInstance('Tfa', 'LoginGuardTable');
			$result = $record->load($pk);

			if (!$result)
			{
				// If the record does not exist I will stomp my feet and deny your request
				throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
			}
		}

		$user = Factory::getApplication()->getIdentity() ?: Factory::getUser();

		// The user must be a registered user, not a guest
		if ($user->guest)
		{
			throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		// You can only delete your own records, unless you're a super user or have delete privileges on this component
		if (($record->user_id != $user->id) && !LoginGuardHelperTfa::canEditUser($user))
		{
			throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		// Save flags used onAfterDelete
		$this->deleteFlags[$record->id] = [
			'default'    => $record->default,
			'numRecords' => $this->getNumRecords($record->user_id),
			'user_id'    => $record->user_id,
			'method'     => $record->method,
		];
	}

	public function onAfterDelete($pk)
	{
		if (is_array($pk))
		{
			$pk = array_shift($pk);
		}

		if (!isset($this->deleteFlags[$pk]))
		{
			return;
		}

		if (($this->deleteFlags[$pk]['numRecords'] <= 2) && ($this->deleteFlags[$pk]['method'] != 'backupcodes'))
		{
			/**
			 * This was the second to last TFA record in the database (the last one is the backupcodes). Therefore we
			 * need to delete the remaining entry and go away. We don't trigger this if the method we are deleting was
			 * the backupcodes because we might just be regenerating the backup codes.
			 */
			$db = Factory::getDbo();
			$query = $db->getQuery(true)
				->delete($db->quoteName('#__loginguard_tfa'))
				->where($db->quoteName('user_id') . ' = ' . $db->quote($this->deleteFlags[$pk]['user_id']));
			$db->setQuery($query)->execute();

			unset($this->deleteFlags[$pk]);

			return;
		}

		// This was the default record. Promote the next available record to default.
		if ($this->deleteFlags[$pk]['default'])
		{
			$db = Factory::getDbo();
			$query = $db->getQuery(true)
				->select($db->quoteName('id'))
				->from($db->quoteName('#__loginguard_tfa'))
				->where($db->quoteName('user_id') . ' = ' . $db->quote($this->deleteFlags[$pk]['user_id']));
			$ids = $db->setQuery($query)->loadColumn();

			if (empty($ids))
			{
				return;
			}

			$id = array_shift($ids);
			$query = $db->getQuery(true)
				->update($db->quoteName('#__loginguard_tfa'))
				->set($db->qn('default') . ' = 1')
				->where($db->quoteName('id') . ' = ' . $db->quote($id));
			$db->setQuery($query)->execute();
		}
	}

	private function switchDefaultRecord(): void
	{
		if (!$this->table->default)
		{
			return;
		}

		/**
		 * This record is marked as default, therefore we need to unset the default flag from all other records for this
		 * user.
		 */
		$db    = Factory::getDbo();
		$query = $db->getQuery(true)
			->update($db->quoteName('#__loginguard_tfa'))
			->set($db->quoteName('default') . ' = 0')
			->where($db->quoteName('user_id') . ' = ' . $db->quote($this->table->user_id))
			->where($db->quoteName('id') . ' != ' . $db->quote($this->table->id));
		$db->setQuery($query)->execute();
	}

	private function conditionallyRegenerateBackupCodes(): void
	{
		if (!$this->mustCreateBackupCodes)
		{
			return;
		}

		/** @var LoginGuardModelBackupcodes $backupCodes */
		$backupCodes = BaseDatabaseModel::getInstance('Backupcodes', 'LoginGuardModel');
		$user        = Factory::getUser($this->table->user_id);
		$backupCodes->regenerateBackupCodes($user);
	}

	private function getNumRecords(int $user_id): int
	{
		$db            = Factory::getDbo();
		$query         = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->quoteName('#__loginguard_tfa'))
			->where($db->quoteName('user_id') . ' = ' . $db->quote($user_id));
		$numOldRecords = $db->setQuery($query)->loadResult();

		return (int) $numOldRecords;
	}
}