<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\LoginGuard\Admin\Controller;

use Akeeba\LoginGuard\Admin\Model\Convert as ConvertModel;
use FOF40\Container\Container;
use FOF40\Controller\Controller;
use Joomla\CMS\Language\Text as JText;
use RuntimeException;

// Protect from unauthorized access
defined('_JEXEC') || die();

/**
 * Controller for the Convert page in the backend of the site
 *
 * @package     Akeeba\LoginGuard\Admin\Controller
 *
 * @since       2.0.0
 */
class Convert extends Controller
{
	/**
	 * Welcome constructor.
	 *
	 * Sets up the default task.
	 *
	 * @param   Container  $container
	 * @param   array      $config
	 *
	 * @since   2.0.0
	 */
	public function __construct(Container $container, array $config = [])
	{
		if (!isset($config['default_task']))
		{
			$config['default_task'] = 'convert';
		}

		parent::__construct($container, $config);

		$this->cacheableTasks = [];
		$this->userCaching = 2;
	}

	/**
	 * Triggers before executing any task. We use it to limit this view to Super Users only.
	 *
	 * @param   string  $task
	 *
	 * @since   2.0.0
	 */
	protected function onBeforeExecute(&$task)
	{
		$this->assertSuperUser();
	}

	/**
	 * Assert that the user is a Super User
	 *
	 * @return  void
	 *
	 * @since   2.0.0
	 */
	protected function assertSuperUser()
	{
		if ($this->container->platform->authorise('core.admin', null))
		{
			return;
		}

		throw new RuntimeException(JText::_('JERROR_ALERTNOAUTHOR'), 403);
	}

	/**
	 * Performs a conversion step
	 *
	 * @since   2.0.0
	 */
	public function convert()
	{
		// Set up the Model and View
		/** @var ConvertModel $model */
		$model = $this->getModel();
		$view  = $this->getView();

		// Perform the conversion
		$result = $model->convert();

		// Set the correct layout depending on what happened to the conversion
		$view->setLayout('default');

		if (!$result)
		{
			$view->setLayout('done');
			$model->disableTFA();
		}

		$this->display();
	}
}
