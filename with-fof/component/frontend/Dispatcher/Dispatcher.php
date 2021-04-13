<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\LoginGuard\Site\Dispatcher;

// Protect from unauthorized access
use Akeeba\LoginGuard\Admin\Dispatcher\Dispatcher as AdminDispatcher;
use FOF40\Input\Input;
use Joomla\CMS\Factory;

defined('_JEXEC') || die();

class Dispatcher extends AdminDispatcher
{
	/**
	 * The name of the default view, in case none is specified
	 *
	 * @var   string
	 * @since 2.0.0
	 */
	public $defaultView = 'Captive';

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

		$this->input->set('view', $view);
		$this->input->set('task', $task);
	}

	public function onBeforeDispatch()
	{
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
		$serverInput = new Input('SERVER');
		$accept = $serverInput->getString('HTTP_ACCEPT');

		if (strpos($accept, 'text/html') === false)
		{
			@ob_end_clean();

			header('HTTP/1.1 404 Not Found');

			Factory::getApplication()->close();
		}

		parent::onBeforeDispatch();
	}
}
