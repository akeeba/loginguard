<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\LoginGuard\Admin\Dispatcher;

// Protect from unauthorized access
use FOF30\Dispatcher\Dispatcher as BaseDispatcher;
use FOF30\Utils\ComponentVersion;
use RuntimeException;

defined('_JEXEC') or die();

class Dispatcher extends BaseDispatcher
{
	/**
	 * The name of the default view, in case none is specified
	 *
	 * @var   string
	 * @since 2.0.0
	 */
	public $defaultView = 'Welcome';

	/**
	 * Executes before dispatching the request to the appropriate controller.
	 *
	 * @return  void
	 * @throws  RuntimeException
	 *
	 * @since   2.0.0
	 */
	public function onBeforeDispatch()
	{
		// Does the user have adequate permissions to access our component?
		$this->checkPrivileges();

		// Load the FOF language
		$lang = $this->container->platform->getLanguage();
		$lang->load('lib_fof30', JPATH_SITE, 'en-GB', true, true);
		$lang->load('lib_fof30', JPATH_SITE, null, true, false);
		$lang->load('lib_fof30', JPATH_ADMINISTRATOR, 'en-GB', true, true);
		$lang->load('lib_fof30', JPATH_ADMINISTRATOR, null, true, false);

		// Set the link toolbar style to Classic (Bootstrap tabs).
		$this->container->renderer->setOption('linkbar_style', 'classic');

		// Create a media version which depends on our version but doesn't leak it publicly
		$jSecret = \JFactory::getConfig()->get('secret');
		$this->container->mediaVersion = md5(ComponentVersion::getFor('com_loginguard') . $jSecret);

		// Load common media files
		$this->loadCommonMedia();

		// Make sure we have a view and task already set
		$this->decodeViewAndTask();

		if ($this->input->getCmd('view', '') == '')
		{
			$this->getDefaultView();
		}
	}

	/**
	 * Normalize the view and task. Joomla! has a discrepancy between how it handles views/tasks and how it creates its
	 * menu items. This bit of code normalizes everything to a separate view and task.
	 *
	 * @return  void
	 *
	 * @since   2.0.0
	 */
	protected function decodeViewAndTask()
	{
		$view = $this->input->getCmd('view', '');
		$task = $this->input->getCmd('task', '');

		if (!empty($view))
		{
			if (!empty($task) && (strpos($task, '.') === false))
			{
				$task = $view . '.' . $task;
			}
		}

		if (!empty($task) && (strpos($task, '.') !== false))
		{
			list($view, $task) = explode('.', $task, 2);
		}

		$this->input->set('view', $view);
		$this->input->set('task', $task);
	}

	/**
	 * Get the default view and task which is appropriate for this user.
	 *
	 * @return  void
	 *
	 * @since   2.0.0
	 */
	protected function getDefaultView()
	{
		$view = $this->defaultView;
		$task = 'default';

		// If you're a super user you get to see the Welcome page instead
		if ($this->container->platform->authorise('core.admin', null))
		{
			$view = 'Welcome';
		}

		$this->input->set('view', $view);
		$this->input->set('task', $task);
	}

	/**
	 * Does the user have adequate privileges to access our component?
	 *
	 * @return  void
	 *
	 * @since   2.0.0
	 * @throws  RuntimeException
	 */
	protected function checkPrivileges()
	{
		// Only check in the backend
		if (!$this->container->platform->isBackend())
		{
			return;
		}

		// TODO This should be per view / task, otherwise backend users cannot edit their own TFA from the backend.
		// If we don't have the core.manage privilege for this component throw an error
		if (!$this->container->platform->authorise('core.manage', $this->container->componentName))
		{
			throw new RuntimeException(\JText::_('JERROR_ALERTNOAUTHOR'), 404);
		}
	}

	/**
	 * Load media files which are common to all views of the component
	 *
	 * @since  2.0.0
	 */
	protected function loadCommonMedia()
	{
		$this->container->template->addCSS(
			"media://{$this->container->componentName}/css/backend.min.css",
			$this->container->mediaVersion, 'text/css', null,
			[
				'relative'    => true,
				'detectDebug' => true,
			]
		);
	}
}
