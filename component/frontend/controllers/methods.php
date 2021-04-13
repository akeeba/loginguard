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
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

class LoginGuardControllerMethods extends BaseController
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
		// We have to tell Joomla what is the name of the view, otherwise it defaults to the name of the *component*.
		$config['default_view'] = 'Methods';

		parent::__construct($config);
	}

	/**
	 * List all available Two Step Validation methods available and guide the user to setting them up
	 *
	 * @param   bool   $cachable   Can this view be cached
	 * @param   array  $urlparams  An array of safe url parameters and their variable types, for valid values see
	 *                             {@link JFilterInput::clean()}.
	 *
	 * @return  self   The current JControllerLegacy object to support chaining.
	 */
	public function display($cachable = false, $urlparams = [])
	{
		$this->assertLoggedInUser();

		// Make sure I am allowed to edit the specified user
		$user_id = $this->input->getInt('user_id', null);
		$user    = Factory::getUser($user_id);

		if (!LoginGuardHelperTfa::canEditUser($user))
		{
			throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		$returnURL       = $this->input->getBase64('returnurl');
		$viewLayout      = $this->input->get('layout', 'default', 'string');
		$view            = $this->getView('Methods', 'html', '', [
			'base_path' => $this->basePath,
			'layout'    => $viewLayout,
		]);
		$view->returnURL = $returnURL;
		$view->user      = $user;

		parent::display($cachable, $urlparams);

		return $this;
	}

	/**
	 * Disable Two Step Verification for the current user
	 *
	 * @param   bool   $cachable   Can this view be cached
	 * @param   array  $urlparams  An array of safe url parameters and their variable types, for valid values see
	 *                             {@link JFilterInput::clean()}.
	 *
	 * @return  self   The current JControllerLegacy object to support chaining.
	 */
	public function disable($cachable = false, $urlparams = [])
	{
		$this->assertLoggedInUser();

		$this->checkToken();

		// Make sure I am allowed to edit the specified user
		$user_id = $this->input->getInt('user_id', null);
		$user    = Factory::getUser($user_id);

		if (!LoginGuardHelperTfa::canEditUser($user))
		{
			throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		// Delete all TSV methods for the user
		/** @var LoginGuardModelMethods $model */
		$model   = $this->getModel('Methods');
		$type    = null;
		$message = null;

		try
		{
			$model->deleteAll($user);
		}
		catch (Exception $e)
		{
			$message = $e->getMessage();
			$type    = 'error';
		}

		// Redirect
		$url       = Route::_('index.php?option=com_loginguard&task=methods.display&user_id=' . $user_id, false);
		$returnURL = $this->input->getBase64('returnurl');

		if (!empty($returnURL))
		{
			$url = base64_decode($returnURL);
		}

		$this->setRedirect($url, $message, $type);

		return $this;
	}

	/**
	 * Disable Two Step Verification for the current user
	 *
	 * @param   bool   $cachable   Can this view be cached
	 * @param   array  $urlparams  An array of safe url parameters and their variable types, for valid values see
	 *                             {@link JFilterInput::clean()}.
	 *
	 * @return  self   The current JControllerLegacy object to support chaining.
	 */
	public function dontshowthisagain($cachable = false, $urlparams = [])
	{
		$this->assertLoggedInUser();

		$this->checkToken();

		// Make sure I am allowed to edit the specified user
		$user_id = $this->input->getInt('user_id', null);
		$user    = Factory::getUser($user_id);

		if (!LoginGuardHelperTfa::canEditUser($user))
		{
			throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		/** @var LoginGuardModelMethods $model */
		$model = $this->getModel('Methods');
		$model->setFlag($user, true);

		// Redirect
		$url       = Uri::base();
		$returnURL = $this->input->getBase64('returnurl');

		if (!empty($returnURL))
		{
			$url = base64_decode($returnURL);
		}

		$this->setRedirect($url);

		return $this;
	}

	private function assertLoggedInUser()
	{
		$user = Factory::getApplication()->getIdentity() ?? Factory::getUser();

		if ($user->guest)
		{
			throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}
	}

}