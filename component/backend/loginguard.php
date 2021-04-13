<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
defined('_JEXEC') or die;

use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\MVC\Controller\BaseController;

try
{
	// Initialisation. Wrapped in an anonymous function to prevent local variables leaking into the caller method scope.
	call_user_func(function () {
		// Minimum PHP version check
		define('AKEEBA_COMMON_WRONGPHP', 1);

		$minPHPVersion = '7.2.0';
		$softwareName  = 'Akeeba LoginGuard';

		if (!require_once(__DIR__ . '/views/common/tmpl/wrongphp.php'))
		{
			return;
		}

		$app = Factory::getApplication();

		// Load both frontend and backend languages for this component
		$app->getLanguage()->load('com_loginguard', JPATH_SITE, null, true, true);
		$app->getLanguage()->load('com_loginguard', JPATH_ADMINISTRATOR, null, true, true);

		/**
		 * I have to do some special handling to accommodate for the discrepancies between how Joomla creates menu items and how
		 * Joomla handles component controllers. Ugh!
		 */
		$view = $app->input->getCmd('view');
		$task = $app->input->getCmd('task');

		if (!empty($view))
		{
			if (strpos($task, '.') === false)
			{
				$task = $view . '.' . $task;
			}
			else
			{
				[$view, $task2] = explode('.', $task, 2);
			}

			$app->input->set('view', $view);
			$app->input->set('task', $task);
		}

		// Get the media version
		JLoader::register('LoginGuardHelperVersion', JPATH_SITE . '/components/com_loginguard/helpers/version.php');
		$mediaVersion = ApplicationHelper::getHash(LoginGuardHelperVersion::component('com_loginguard'));

		// Include CSS
		HTMLHelper::_('stylesheet', 'com_loginguard/backend.min.css', [
			'version'       => $mediaVersion,
			'relative'      => true,
			'detectDebug'   => true,
			'pathOnly'      => false,
			'detectBrowser' => true,
		], [
			'type' => 'text/css',
		]);
	});


	// Get an instance of the LoginGuard controller
	$controller = BaseController::getInstance('LoginGuard');

	// Get and execute the requested task
	$controller->execute(Factory::getApplication()->input->getCmd('task'));

	// Apply any redirection set in the Controller
	$controller->redirect();
}
catch (Throwable $e)
{
	// DO NOT REMOVE -- They are used by errorhandler.php below.
	$title = 'Akeeba LoginGuard';
	$isPro = false;

	if (!(include_once __DIR__ . '/views/common/tmpl/errorhandler.php'))
	{
		throw $e;
	}
}