<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;

try
{
	// Initialisation. Wrapped in an anonymous function to prevent local variables leaking into the caller method scope.
	call_user_func(function () {
		// Minimum PHP version check
		define('AKEEBA_COMMON_WRONGPHP', 1);

		$minPHPVersion = '7.2.0';
		$softwareName  = 'Akeeba LoginGuard';

		if (!require_once(JPATH_ADMINISTRATOR . '/components/com_loginguard/views/common/tmpl/wrongphp.php'))
		{
			return;
		}

		$app = Factory::getApplication();

		/**
		 * Defend against double sending of codes in some cases.
		 *
		 * If a system plugin is loaded before LoginGuard and tries to load CSS, JS or image files which do not exists
		 * on the server on a site with URL rewrite code enabled in the .htaccess / web.config / NginX configuration
		 * file these missing files are routed to Joomla's index.php. This causes the LoginGuard system plugin to
		 * internally redirect the application to com_loginguard's Captive view. If the code by email / SMS plugin is
		 * set to be the default method its onLoginGuardTfaCaptive method is called and an email / SMS with the code is
		 * sent out **for each and every missing file handled by Joomla**.
		 *
		 * The workaround here examines the HTTP Accept header. Only those requests with an Accept header that includes
		 * text/html will be processed. Everything else will result in a crude 404, preventing the call to
		 * onLoginGuardTfaCaptive, therefore preventing the multiple sending of the login code by email / SMS.
		 *
		 * There is still one mode of failure. If a third party plugin includes JS which tries to perform an
		 * XMLHttpRequest with a text/html accept header we will process it, triggering another sending of the login
		 * code. There is no way to fix without Joomla adding proper support for captive logins as I had explained back
		 * in 2012 (at JoomlaDay France, IIRC).
		 *
		 * @see   https://www.akeeba.com/support/pre-sales-requests/Ticket/33553:loginguard-support.html
		 *
		 * @since 3.3.3
		 */
		$serverInput = $app->input->server;
		$accept = $serverInput->getString('HTTP_ACCEPT');

		if (strpos($accept, 'text/html') === false)
		{
			@ob_end_clean();

			header('HTTP/1.1 404 Not Found');

			Factory::getApplication()->close();
		}

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

	if (!(include_once JPATH_ADMINISTRATOR . '/components/com_loginguard/views/common/tmpl/errorhandler.php'))
	{
		throw $e;
	}
}