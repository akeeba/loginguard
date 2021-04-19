<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Utilities\ArrayHelper;

class LoginGuardModelUsers extends ListModel
{
	/**
	 * A blacklist of filter variables to not merge into the model's state
	 *
	 * @var    array
	 * @since  5.0.0
	 */
	protected $filterBlacklist = ['groups'];

	/**
	 * Constructor.
	 *
	 * @param   array  $config  An optional associative array of configuration settings.
	 *
	 * @throws  Exception
	 * @since   5.0.0
	 * @see     \Joomla\CMS\MVC\Controller\BaseController
	 */
	public function __construct($config = [])
	{
		if (empty($config['filter_fields']))
		{
			$config['filter_fields'] = [
				'id', 'a.id',
				'name', 'a.name',
				'username', 'a.username',
				'email', 'a.email',
				'group_id',
				'state',
				'has2sv',
			];
		}

		parent::__construct($config);

		$this->populateState();
	}

	/**
	 * Gets the list of users and adds expensive joins to the result set.
	 *
	 * @return  mixed  An array of data items on success, false on failure.
	 *
	 * @since   5.0.0
	 */
	public function getItems()
	{
		// Get a storage key.
		$store = $this->getStoreId();

		if (!empty($this->cache[$store]))
		{
			return $this->cache[$store];
		}

		$groups  = $this->getState('filter.groups');
		$groupId = $this->getState('filter.group_id');

		if (isset($groups) && (empty($groups) || $groupId && !in_array($groupId, $groups)))
		{
			$items = [];
		}
		else
		{
			$items = parent::getItems();
		}

		// Bail out on an error or empty list.
		if (empty($items))
		{
			$this->cache[$store] = $items;

			return $items;
		}

		// Joining the groups with the main query is a performance hog.
		// Find the information only on the result set.

		// First pass: get list of the user id's and reset the counts.
		$userIds = [];

		foreach ($items as $item)
		{
			$userIds[]         = (int) $item->id;
			$item->group_count = 0;
			$item->group_names = '';
			$item->note_count  = 0;
		}

		// Get the counts from the database only for the users in the list.
		$db    = $this->getDbo();
		$query = $db->getQuery(true);

		// Join over the group mapping table.
		$query->select('map.user_id, COUNT(map.group_id) AS group_count')
			->from('#__user_usergroup_map AS map')
			->where('map.user_id IN (' . implode(',', $userIds) . ')')
			->group('map.user_id')
			// Join over the user groups table.
			->join('LEFT', '#__usergroups AS g2 ON g2.id = map.group_id');

		$db->setQuery($query);

		// Load the counts into an array indexed on the user id field.
		try
		{
			$userGroups = $db->loadObjectList('user_id');
		}
		catch (RuntimeException $e)
		{
			$this->setError($e->getMessage());

			return false;
		}

		// Second pass: collect the group counts into the master items array.
		foreach ($items as &$item)
		{
			if (isset($userGroups[$item->id]))
			{
				$item->group_count = $userGroups[$item->id]->group_count;

				// Group_concat in other databases is not supported
				$item->group_names = $this->_getUserDisplayedGroups($item->id);
			}

			if (isset($userNotes[$item->id]))
			{
				$item->note_count = $userNotes[$item->id]->note_count;
			}
		}

		// Add the items to the internal cache.
		$this->cache[$store] = $items;

		return $this->cache[$store];
	}

	/**
	 * Method to auto-populate the model state.
	 *
	 * Note. Calling getState in this method will result in recursion.
	 *
	 * @param   string  $ordering   An optional ordering field.
	 * @param   string  $direction  An optional direction (asc|desc).
	 *
	 * @return  void
	 *
	 * @throws  Exception
	 * @since   5.0.0
	 */
	protected function populateState($ordering = 'a.name', $direction = 'asc')
	{
		$app = Factory::getApplication();

		// Load the filter state.
		$this->setState('filter.search', $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string'));
		$this->setState('filter.has2sv', $this->getUserStateFromRequest($this->context . '.filter.has2sv', 'filter_has2sv', '', 'cmd'));
		$this->setState('filter.group_id', $this->getUserStateFromRequest($this->context . '.filter.group_id', 'filter_group_id', null, 'int'));

		$groups = json_decode(base64_decode($app->input->get('groups', '', 'BASE64')));

		if (isset($groups))
		{
			$groups = ArrayHelper::toInteger($groups);
		}

		$this->setState('filter.groups', $groups);

		// Load the parameters.
		$params = ComponentHelper::getParams('com_users');
		$this->setState('params', $params);

		// List state information.
		parent::populateState($ordering, $direction);
	}

	/**
	 * Method to get a store id based on model configuration state.
	 *
	 * This is necessary because the model is used by the component and
	 * different modules that might need different sets of data or different
	 * ordering requirements.
	 *
	 * @param   string  $id  A prefix for the store id.
	 *
	 * @return  string  A store id.
	 *
	 * @since   5.0.0
	 */
	protected function getStoreId($id = '')
	{
		// Compile the store id.
		$id .= ':' . $this->getState('filter.search');
		$id .= ':' . $this->getState('filter.has2sv');
		$id .= ':' . $this->getState('filter.group_id');

		return parent::getStoreId($id);
	}

	/**
	 * Build an SQL query to load the list data.
	 *
	 * @return JDatabaseQuery
	 *
	 * @since   5.0.0
	 */
	protected function getListQuery()
	{
		$db = $this->getDbo();

		// Sub-query for 2SV status
		$subQuery = $db->getQuery(true)
			->select([
				$db->quoteName('user_id'),
				'COUNT(*) AS ' . $db->quoteName('tfaMethods'),
			])->from($db->quoteName('#__loginguard_tfa'))
			->group([$db->quoteName('user_id')]);

		// Select the required fields from the table.
		$query = $db->getQuery(true)
			->select([
				$db->quoteName('a') . '.*',
				'IF(' . $db->qn('t.tfaMethods') . ' > 0, 1, 0) AS ' . $db->qn('has2SV'),
			])
			->from($db->quoteName('#__users') . ' AS a')
			->leftJoin("($subQuery) AS " . $db->qn('t') . ' ON ' . $db->qn('t.user_id') . ' = ' . $db->qn('a.id'));

		// If the model is set to check item state, add to the query.
		$state = $this->getState('filter.state');

		if (is_numeric($state))
		{
			$query->where('a.block = ' . (int) $state);
		}

		// If the model is set to check 2SV state, add to the query.
		$has2SV = $this->getState('filter.has2sv');

		if (is_numeric($has2SV))
		{
			$condition = $has2SV ? ' > 0 ' : ' IS NULL';
			$query->where($db->qn('t.tfaMethods') . $condition);
		}

		// Filter the items over the group id if set.
		$groupId = $this->getState('filter.group_id');
		$groups  = $this->getState('filter.groups');

		if ($groupId || isset($groups))
		{
			$query->join('LEFT', '#__user_usergroup_map AS map2 ON map2.user_id = a.id')
				->group(
					$db->quoteName(
						[
							'a.id',
							'a.name',
							'a.username',
							'a.password',
							'a.block',
							'a.sendEmail',
							'a.registerDate',
							'a.lastvisitDate',
							'a.activation',
							'a.params',
							'a.email',
						]
					)
				);

			if ($groupId)
			{
				$query->where('map2.group_id = ' . (int) $groupId);
			}

			if (isset($groups))
			{
				$query->where('map2.group_id IN (' . implode(',', $groups) . ')');
			}
		}

		// Filter the items over the search string if set.
		$search = $this->getState('filter.search');

		if (!empty($search))
		{
			if (stripos($search, 'id:') === 0)
			{
				$query->where('a.id = ' . (int) substr($search, 3));
			}
			elseif (stripos($search, 'username:') === 0)
			{
				$search = $db->quote('%' . $db->escape(substr($search, 9), true) . '%');
				$query->where('a.username LIKE ' . $search);
			}
			else
			{
				// Escape the search token.
				$search = $db->quote('%' . str_replace(' ', '%', $db->escape(trim($search), true) . '%'));

				// Compile the different search clauses.
				$searches   = [];
				$searches[] = 'a.name LIKE ' . $search;
				$searches[] = 'a.username LIKE ' . $search;
				$searches[] = 'a.email LIKE ' . $search;

				// Add the clauses to the query.
				$query->where('(' . implode(' OR ', $searches) . ')');
			}
		}

		// Add the list ordering clause.
		$query->order($db->qn($db->escape($this->getState('list.ordering', 'a.name'))) . ' ' . $db->escape($this->getState('list.direction', 'ASC')));

		return $query;
	}

	/**
	 * SQL server change
	 *
	 * @param   integer  $userId  User identifier
	 *
	 * @return  string   Groups titles imploded :$
	 */
	protected function _getUserDisplayedGroups($userId)
	{
		$db    = $this->getDbo();
		$query = $db->getQuery(true)
			->select($db->qn('title'))
			->from($db->qn('#__usergroups', 'ug'))
			->join('LEFT', $db->qn('#__user_usergroup_map', 'map') . ' ON (ug.id = map.group_id)')
			->where($db->qn('map.user_id') . ' = ' . (int) $userId);

		try
		{
			$result = $db->setQuery($query)->loadColumn();
		}
		catch (RunTimeException $e)
		{
			$result = [];
		}

		return implode("\n", $result);
	}
}