<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Plugin\PluginHelper;

class LoginGuardControllerAjax extends BaseController
{
	/**
	 * Constructor.
	 *
	 * @param   array  $config  An optional associative array of configuration settings.
	 *                          Recognized key values include 'name', 'default_task', 'model_path', and
	 *                          'view_path' (this list is not meant to be comprehensive).
	 */
	public function __construct(array $config = [])
	{
		parent::__construct($config);

		$this->registerDefaultTask('json');
	}

	/**
	 * Implement an AJAX feature. Results are returned as JSON. In case of no response the JSON string literal "null"
	 * is returned.
	 *
	 * @param   bool        $cachable   Can this view be cached
	 * @param   bool|array  $urlparams  An array of safe url parameters and their variable types, for valid values see
	 *                                  {@link JFilterInput::clean()}.
	 *
	 * @return  BaseController   The current BaseController object to support chaining.
	 */
	public function json($cachable = false, $urlparams = false): BaseController
	{
		$app = Factory::getApplication();

		// Only allow logged in users
		if (($app->getIdentity() ?: Factory::getUser())->guest)
		{
			throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		$result = $this->getResult();

		echo json_encode($result);

		// Immediately close the application
		$app->close();

		// This is a useless line which never runs. It's here just to make code analyzers happy.
		return $this;
	}

	/**
	 * Implement an AJAX feature. Results are returned as JSON surrounded by triple hashes. In case of no response the
	 * JSON string literal "null" surrounded by triple hashes is returned, i.e.:
	 * ###null###
	 *
	 * The triple-hash-surrounded-JSON format has proven to be the best way to work around brain-dead plugins and hosts
	 * which forcibly inject HTML or other crap to the output _even when the format query string parameter is
	 * explicitly
	 * set to raw_. This solution has proven itself since Joomla! 1.0, all the way back in 2005.
	 *
	 * @param   bool        $cachable   Can this view be cached
	 * @param   array|bool  $urlparams  An array of safe url parameters and their variable types, for valid values see
	 *                                  {@link JFilterInput::clean()}.
	 *
	 * @return  BaseController   The current JControllerLegacy object to support chaining.
	 */
	public function hashjson($cachable = false, $urlparams = false): BaseController
	{
		$app = Factory::getApplication();

		// Only allow logged in users
		if (($app->getIdentity() ?: Factory::getUser())->guest)
		{
			throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		$result = $this->getResult();

		echo '###' . json_encode($result) . '###';

		// Immediately close the application
		$app->close();

		// This is a useless line which never runs. It's here just to make code analyzers happy.
		return $this;

	}

	/**
	 * Implement an AJAX feature. The first plugin handling the request is responsible of returning the results in
	 * whatever format is best for the application. If no plugin handles the request the application closes without
	 * returning a response.
	 *
	 * @param   bool        $cachable   Can this view be cached
	 * @param   array|bool  $urlparams  An array of safe url parameters and their variable types, for valid values see
	 *                                  {@link JFilterInput::clean()}.
	 *
	 * @return  BaseController   The current JControllerLegacy object to support chaining.
	 */
	public function raw($cachable = false, $urlparams = false)
	{
		$app = Factory::getApplication();

		// Only allow logged in users
		if (($app->getIdentity() ?: Factory::getUser())->guest)
		{
			throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		// Note: we return no result. The first plugin which handles the request is supposed to do that.
		$result = $this->getResult();

		// Immediately close the application
		$app->close();

		// This is a useless line which never runs. It's here just to make code analyzers happy.
		return $this;
	}

	/**
	 * Common part of request handling across all tasks. Makes sure the request is a valid AJAX requests, triggers the
	 * plugin event and returns the first non-empty result.
	 *
	 * @return   mixed  Null if no plugin handled the event. Otherwise the first non-false plugin result.
	 */
	private function getResult()
	{
		// Make sure format=raw
		$format = $this->input->getCmd('format', 'html');

		if ($format !== 'raw')
		{
			throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		// Get the method and make sure it's non-empty
		$method = $this->input->getCmd('method', '');
		$action = $this->input->getCmd('action', '');

		if (empty($method) || empty($action))
		{
			throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		// Trigger the onLoginGuardAjax plugin event
		if (!class_exists('LoginGuardHelperTfa', true))
		{
			JLoader::register('LoginGuardHelperTfa', JPATH_SITE . '/components/com_loginguard/helpers/tfa.php');
		}

		PluginHelper::importPlugin('loginguard');
		$results = LoginGuardHelperTfa::runPlugins('onLoginGuardAjax', [$method, $action]);
		$result  = null;

		foreach ($results as $aResult)
		{
			if ($aResult !== false)
			{
				$result = $aResult;

				break;
			}
		}

		return $result;
	}
}