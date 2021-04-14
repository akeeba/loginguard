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
	 * Objects to handle table pseudo-events.
	 *
	 * This is necessary because Joomla 3 and 4 work entirely differently in that regard. On Joomla 3 we have
	 * Joomla\CMS\Table\Observer\AbstractObserver. On Joomla 4 we have proper Events. This means that we have the
	 * following unsavory options:
	 *
	 * 1. Have separate packages for Joomla 3 and 4. Um, just now.
	 * 2. Duplicate the code for Joomla 3 and 4, just to satisfy completely different interfaces.
	 * 3. Create a superdupermegamassive class
	 * 4. Make the table class a "god object".
	 * 5. Implement our poor man's version of even handling.
	 *
	 * We prefer to go with option #5 because it can be easily refactored into proper Joomla 4 events when the time
	 * comes.
	 *
	 * @var  array
	 */
	private $eventHandlerObjects = [];

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

		$this->eventHandlerObjects[] = new LoginGuardTableObserverEncrypt($this, [
			'columns' => [
				'options'
			]
		]);

		$this->eventHandlerObjects[] = new LoginGuardTableObserverDefault($this);
	}

	public function load($keys = null, $reset = true)
	{
		$this->triggerPseudoEvent('onBeforeLoad', $keys, $reset);

		$result = parent::load($keys, $reset);

		$this->triggerPseudoEvent('onAfterLoad', $result);

		return $result;
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

	public function store($updateNulls = false)
	{
		$k = $this->_tbl_keys;

		$this->triggerPseudoEvent('onBeforeStore', $updateNulls, $k);

		$result = parent::store($updateNulls);

		$this->triggerPseudoEvent('onAfterStore', $updateNulls, $k);

		return $result;
	}

	public function delete($pk = null)
	{
		if (is_null($pk))
		{
			$pk = array();

			foreach ($this->_tbl_keys as $key)
			{
				$pk[$key] = $this->$key;
			}
		}
		elseif (!is_array($pk))
		{
			$pk = array($this->_tbl_key => $pk);
		}

		foreach ($this->_tbl_keys as $key)
		{
			$pk[$key] = is_null($pk[$key]) ? $this->$key : $pk[$key];

			if ($pk[$key] === null)
			{
				throw new \UnexpectedValueException('Null primary key not allowed.');
			}

			$this->$key = $pk[$key];
		}

		// Implement \JObservableInterface: Pre-processing by observers
		$this->triggerPseudoEvent('onBeforeDelete', $pk);

		$result = parent::delete($pk);

		if ($result)
		{
			$this->triggerPseudoEvent('onAfterDelete', $pk);
		}

		return $result;
	}

	private function triggerPseudoEvent($eventName, &...$arguments)
	{
		foreach ($this->eventHandlerObjects as $o)
		{
			if (!method_exists($o, $eventName))
			{
				continue;
			}

			$o->{$eventName}(...$arguments);
		}
	}
}