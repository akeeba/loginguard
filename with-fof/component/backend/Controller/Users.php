<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\LoginGuard\Admin\Controller;

use FOF40\Container\Container;
use FOF40\Controller\DataController;
use FOF40\Controller\Mixin\PredefinedTaskList;
use Joomla\CMS\Language\Text as JText;
use RuntimeException;

// Protect from unauthorized access
defined('_JEXEC') || die();

/**
 * Controller for the Users page in the backend of the site
 *
 * @package     Akeeba\LoginGuard\Admin\Controller
 *
 * @since       3.1.0
 */
class Users extends DataController
{
	use PredefinedTaskList;

	public function __construct(Container $container, array $config = [])
	{
		parent::__construct($container, $config);

		// Only allow a Browse view.
		$this->predefinedTaskList = ['browse'];
		$this->cacheableTasks = [];
		$this->userCaching = 2;
	}


	/**
	 * Triggers before executing any task. We use it to limit this view to authorized personel only.
	 *
	 * @param   string  $task
	 *
	 * @since   3.1.0
	 */
	protected function onBeforeExecute(&$task)
	{
		$this->assertPrivilege();
	}

	/**
	 * Assert that the user is a Super User
	 *
	 * @return  void
	 *
	 * @since   3.1.0
	 */
	protected function assertPrivilege()
	{
		if ($this->container->platform->authorise('loginguard.userlist', 'com_loginguard'))
		{
			return;
		}

		throw new RuntimeException(JText::_('JERROR_ALERTNOAUTHOR'), 403);
	}
}
