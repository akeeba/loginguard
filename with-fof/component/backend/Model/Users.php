<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\LoginGuard\Admin\Model;

use FOF40\Container\Container;
use FOF40\Model\DataModel;

// Protect from unauthorized access
defined('_JEXEC') || die();


/**
 * Model for querying the 2SV status of Joomla! users
 *
 * Fields:
 *
 * @property  int     $id
 * @property  string  $name
 * @property  string  $username
 * @property  string  $email
 * @property  string  $password
 * @property  bool    $block
 * @property  bool    $sendEmail
 * @property  string  $registerDate
 * @property  string  $lastvisitDate
 * @property  string  $activation
 * @property  string  $params
 * @property  string  $lastResetTime
 * @property  int     $resetCount
 * @property  string  $otpKey
 * @property  string  $otep
 * @property  bool    $requireReset
 * @property  bool    $has2SV
 *
 * Filters:
 *
 * @method  $this  id()             id(int $v)
 * @method  $this  name()           name(string $v)
 * @method  $this  username()       username(string $v)
 * @method  $this  email()          email(string $v)
 * @method  $this  password()       password(string $v)
 * @method  $this  block()          block(bool $v)
 * @method  $this  sendEmail()      sendEmail(bool $v)
 * @method  $this  registerDate()   registerDate(string $v)
 * @method  $this  lastvisitDate()  lastvisitDate(string $v)
 * @method  $this  activation()     activation(string $v)
 * @method  $this  lastResetTime()  lastResetTime(string $v)
 * @method  $this  resetCount()     resetCount(int $v)
 * @method  $this  otpKey()         otpKey(string $v)
 * @method  $this  otep()           otep(string $v)
 * @method  $this  requireReset()   requireReset(bool $v)
 * @method  $this  has2SV()         has2SV(int $v)
 * @method  $this  search()         search(string $userInfoToSearch)
 *
 * @since   3.1.0
 **/
class Users extends DataModel
{
	/**
	 * Override the constructor since I need to attach to a core table and add the Filters behaviour
	 *
	 * @param Container $container
	 * @param array     $config
	 */
	public function __construct(Container $container, array $config = [])
	{
		$config['tableName'] = '#__users';
		$config['idFieldName'] = 'id';

		parent::__construct($container, $config);

		$this->addKnownField('has2SV', 0, 'bool');

		// If we're on backend load the behaviors
		if ($this->container->platform->isBackend())
		{
			$this->addBehaviour('Filters');
		}
	}

	public function buildQuery($overrideLimits = false)
	{
		$db = $this->getDbo();

		$subQuery = $db->getQuery(true)
			->select([
				$db->qn('user_id'),
				'COUNT(*) AS ' . $db->qn('tfaMethods'),
			])->from($db->qn('#__loginguard_tfa'))
			->group([$db->qn('user_id')]);

		$query = $db->getQuery(true)
			->select([
				$db->qn('u') . '.*',
				'IF(' . $db->qn('t.tfaMethods') . ' > 0, 1, 0) AS ' . $db->qn('has2SV'),
			])
			->from($db->qn('#__users') . ' AS ' . $db->qn('u'))
			->leftJoin("($subQuery) AS " . $db->qn('t') . ' ON ' . $db->qn('t.user_id') . ' = ' . $db->qn('u.id'));

		// Run the "before build query" hook and behaviours
		$this->triggerEvent('onBeforeBuildQuery', [&$query, $overrideLimits]);

		// Apply custom WHERE clauses
		if (is_array($this->whereClauses) || $this->whereClauses instanceof \Countable ? count($this->whereClauses) : 0)
		{
			foreach ($this->whereClauses as $clause)
			{
				$query->where($clause);
			}
		}

		$filter_has2SV = $this->getState('has2SV', null);

		if ($filter_has2SV !== '')
		{
			$operator = $filter_has2SV ? '> 0' : ' IS NULL';
			$query->where($db->qn('tfaMethods') . " $operator");
		}

		if ($search = $this->getState('search', null))
		{
			$query->where(
				'(' .
				'(' . $db->qn('username') . ' LIKE ' . $db->q('%' . $db->escape($search) . '%', false) . ') OR ' .
				'(' . $db->qn('name') . ' LIKE ' . $db->q('%' . $db->escape($search) . '%', false) . ') OR ' .
				'(' . $db->qn('email') . ' LIKE ' . $db->q('%' . $db->escape($search) . '%', false) . ') ' .
				')'
			);
		}

		$filter_group = $this->getState('group', null, 'int');

		if (!is_null($filter_group) && ($filter_group > 0))
		{
			$subQueryGroup = $db->getQuery(true)
				->select([
					$db->qn('user_id'),
				])->from($db->qn('#__user_usergroup_map'))
				->where($db->qn('group_id') . ' = ' . (int) $filter_group);
			$query->where($db->qn('id') . ' IN(' . $subQueryGroup . ')');
		}


		$order = $this->getState('filter_order', 'id', 'cmd');

		if (!in_array($order, array_keys($this->knownFields)))
		{
			$order = 'id';
		}

		$dir = $this->getState('filter_order_Dir', 'DESC', 'cmd');
		$query->order($order . ' ' . $dir);

		// Run the "before after query" hook and behaviours
		$this->triggerEvent('onAfterBuildQuery', [&$query, $overrideLimits]);

		return $query;
	}
}
