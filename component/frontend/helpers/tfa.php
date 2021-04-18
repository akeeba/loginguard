<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Table\Table;
use Joomla\CMS\User\User;
use Joomla\Event\Event;

/**
 * Helper functions for TFA handling
 */
abstract class LoginGuardHelperTfa
{
	/**
	 * Cache of all currently active TFAs
	 *
	 * @var   array|null
	 */
	protected static $allTFAs = null;

	/**
	 * Are we inside the administrator application
	 *
	 * @var   bool
	 */
	protected static $isAdmin = null;

	/**
	 * Execute plugins (system-level triggers) and fetch back an array with
	 * their return values.
	 *
	 * @param   string  $event  The event (trigger) name, e.g. onBeforeScratchMyEar
	 * @param   array   $data   A hash array of data sent to the plugins as part of the trigger
	 *
	 * @return  array  A simple array containing the results of the plugins triggered
	 * @since   1.2.0
	 */
	public static function runPlugins(string $event, array $data = []): array
	{
		if (class_exists('JEventDispatcher'))
		{
			return JEventDispatcher::getInstance()->trigger($event, $data);
		}

		// If there's no JEventDispatcher try getting JApplication
		try
		{
			$app = Factory::getApplication();
		}
		catch (Exception $e)
		{
			// If I can't get JApplication I cannot run the plugins.
			return [];
		}

		// Joomla 3 and 4 have triggerEvent
		if (method_exists($app, 'triggerEvent'))
		{
			return $app->triggerEvent($event, $data);
		}

		// Joomla 5 (and possibly some 4.x versions) don't have triggerEvent. Go through the Events dispatcher.
		if (method_exists($app, 'getDispatcher') && class_exists('Joomla\Event\Event'))
		{
			try
			{
				$dispatcher = $app->getDispatcher();
			}
			catch (\UnexpectedValueException $exception)
			{
				return [];
			}

			if ($data instanceof Event)
			{
				$eventObject = $data;
			}
			elseif (\is_array($data))
			{
				$eventObject = new Event($event, $data);
			}
			else
			{
				throw new \InvalidArgumentException('The plugin data must either be an event or an array');
			}

			$result = $dispatcher->dispatch($event, $eventObject);

			return !isset($result['result']) || \is_null($result['result']) ? [] : $result['result'];
		}

		// No viable way to run the plugins :(
		return [];
	}

	/**
	 * Get a list of all of the TFA methods
	 *
	 * @return  array
	 * @since   1.2.0
	 */
	public static function getTfaMethods(): array
	{
		PluginHelper::importPlugin('loginguard');

		if (is_null(self::$allTFAs))
		{
			// Get all the plugin results
			$temp = self::runPlugins('onLoginGuardTfaGetMethod', []);

			// Normalize the results
			self::$allTFAs = [];

			foreach ($temp as $method)
			{
				if (!is_array($method))
				{
					continue;
				}

				$method = array_merge([
					// Internal code of this TFA method
					'name'               => '',
					// User-facing name for this TFA method
					'display'            => '',
					// Short description of this TFA method displayed to the user
					'shortinfo'          => '',
					// URL to the logo image for this method
					'image'              => '',
					// Are we allowed to disable it?
					'canDisable'         => true,
					// Are we allowed to have multiple instances of it per user?
					'allowMultiple'      => false,
					// URL for help content
					'help_url'           => '',
					// Allow authentication against all entries of this TFA method. Otherwise authentication takes place against a SPECIFIC entry at a time.
					'allowEntryBatching' => false,
				], $method);

				if (empty($method['name']))
				{
					continue;
				}

				self::$allTFAs[$method['name']] = $method;
			}
		}

		return self::$allTFAs;
	}

	/**
	 * Is the current user allowed to edit the TFA configuration of $user? To do so I must either be editing my own
	 * account OR I have to be a Super User editing a non-superuser's account. Important to note: nobody can edit the
	 * accounts of Super Users except themselves. Therefore make damn sure you keep those backup codes safe!
	 *
	 * @param   User|null  $user  The user you want to know if we're allowed to edit
	 *
	 * @return  bool
	 * @throws  Exception
	 *
	 * @since   1.2.0
	 */
	public static function canEditUser(?User $user = null): bool
	{
		// I can edit myself
		if (is_null($user))
		{
			return true;
		}

		// Guests can't have TFA
		if ($user->guest)
		{
			return false;
		}

		// Get the currently logged in user
		$myUser = Factory::getApplication()->getIdentity() ?: Factory::getUser();

		// Same user? I can edit myself
		if ($myUser->id === $user->id)
		{
			return true;
		}

		// To edit a different user I must be a Super User myself. If I'm not, I can't edit another user!
		if (!$myUser->authorise('core.admin'))
		{
			return false;
		}

		// Even if I am a Super User I must not be able to edit another Super User.
		if ($user->authorise('core.admin'))
		{
			return false;
		}

		// I am a Super User trying to edit a non-superuser. That's allowed.
		return true;
	}

	/**
	 * Return all TFA records for a specific user
	 *
	 * @param   int|null  $user_id  User ID. NULL for currently logged in user.
	 *
	 * @return  LoginGuardTableTfa[]
	 * @throws  Exception
	 *
	 * @since   1.2.0
	 */
	public static function getUserTfaRecords(?int $user_id): array
	{
		if (empty($user_id))
		{
			$user    = Factory::getApplication()->getIdentity() ?: Factory::getUser();
			$user_id = $user->id ?: 0;
		}

		$db    = Factory::getDbo();
		$query = $db->getQuery(true)
			->select($db->quoteName('id'))
			->from($db->quoteName('#__loginguard_tfa'))
			->where($db->quoteName('user_id') . ' = ' . (int) $user_id);
		try
		{
			$ids = $db->setQuery($query)->loadColumn() ?: [];
		}
		catch (Exception $e)
		{
			$ids = [];
		}

		if (empty($ids))
		{
			return [];
		}

		BaseDatabaseModel::addIncludePath(JPATH_ROOT . '/components/com_loginguard/models', 'LoginGuardModel');
		Table::addIncludePath(JPATH_ROOT . '/components/com_loginguard/tables');

		// Map all results to Tfa table objects
		$records = array_map(function ($id) {
			/** @var LoginGuardTableTfa $record */
			$record = Table::getInstance('Tfa', 'LoginGuardTable');
			$loaded = $record->load($id);

			return $loaded ? $record : null;
		}, $ids);

		// Let's remove methods we couldn't decrypt when reading from the database.
		$hasBackupCodes = false;

		$records = array_filter($records, function ($record) use (&$hasBackupCodes) {
			$isValid = !is_null($record) && (!empty($record->options));

			if ($isValid && ($record->method === 'backupcodes'))
			{
				$hasBackupCodes = true;
			}

			return $isValid;
		});

		// If the only method is backup codes it's as good as having no records
		if ((count($records) === 1) && $hasBackupCodes)
		{
			return [];
		}

		return $records;
	}
}