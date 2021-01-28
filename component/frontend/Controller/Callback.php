<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\LoginGuard\Site\Controller;

use FOF40\Container\Container;
use FOF40\Controller\Controller;
use FOF40\Controller\Mixin\PredefinedTaskList;
use Joomla\CMS\Language\Text as JText;
use RuntimeException;

// Protect from unauthorized access
defined('_JEXEC') || die();

/**
 * Controller for remote callbacks, primarily used for OAuth2 authentication with third party providers.
 *
 * @since       2.0.0
 */
class Callback extends Controller
{
	use PredefinedTaskList;

	/**
	 * Callback constructor.
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
			$config['default_task'] = 'callback';
		}

		parent::__construct($container, $config);

		$this->setPredefinedTaskList(['callback']);
		$this->cacheableTasks = [];
		$this->userCaching = 2;
	}

	/**
	 * Implement a callback feature, typically used for OAuth2 authentication
	 *
	 * @return  void
	 * @since  2.0.0
	 */
	public function callback()
	{
		// Only allow logged in users
		if ($this->container->platform->getUser()->guest)
		{
			throw new RuntimeException(JText::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		// Get the method and make sure it's non-empty
		$method = $this->input->getCmd('method', '');

		if (empty($method))
		{
			throw new RuntimeException(JText::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		$this->container->platform->importPlugin('loginguard');
		$results = $this->container->platform->runPlugins('onLoginGuardCallback', [$method]);

		/**
		 * The first plugin to handle the request should either redirect or close the application. If we are still here
		 * no plugin handled the request. So all we can do is close the application, i.e. die gracefully.
		 */
		$this->container->platform->closeApplication();
	}
}
