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
use Joomla\CMS\MVC\Controller\BaseController as BaseControllerAlias;
use Joomla\CMS\Router\Route;
use Joomla\CMS\User\User;

class LoginGuardControllerMethod extends BaseControllerAlias
{
	/**
	 * The default model for this MVC triad. Something that Joomla fails to take into account...
	 *
	 * @var   JModelLegacy
	 */
	protected $default_model = null;

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
		$config['default_view'] = 'Method';
		$config['default_task'] = 'add';

		parent::__construct($config);
	}

	/**
	 * Execute a task by triggering a method in the derived class.
	 *
	 * @param   string  $task  The task to perform. If no matching task is found, the '__default' task is executed, if
	 *                         defined.
	 *
	 * @return  mixed   The value returned by the called method.
	 *
	 * @throws  Exception
	 * @since   3.0
	 */
	public function execute($task)
	{
		// I wish I could use a single controller for plural and singular views - but I can't.
		if ($task == 'display')
		{
			$task = 'add';
		}

		return parent::execute($task);
	}

	/**
	 * Add a new TFA method
	 *
	 * @param   bool   $cachable   Can this view be cached
	 * @param   array  $urlparams  An array of safe url parameters and their variable types, for valid values see
	 *                             {@link JFilterInput::clean()}.
	 *
	 * @return  self   The current JControllerLegacy object to support chaining.
	 */
	public function add($cachable = false, $urlparams = [])
	{
		$this->assertLoggedInUser();

		// Make sure I am allowed to edit the specified user
		$user_id = $this->input->getInt('user_id', null);
		$user    = Factory::getUser($user_id);
		$this->_assertCanEdit($user);

		// Also make sure the method really does exist
		$method = $this->input->getCmd('method');
		$this->_assertMethodExists($method);

		/** @var LoginGuardModelMethod $model */
		$model = $this->getModel();
		$model->setState('method', $method);

		// Pass the return URL to the view
		$returnURL       = $this->input->getBase64('returnurl');
		$viewLayout      = $this->input->get('layout', 'default', 'string');
		$view            = $this->getView('Method', 'html', '', [
			'base_path' => $this->basePath,
			'layout'    => $viewLayout,
		]);
		$view->returnURL = $returnURL;
		$view->user      = $user;
		$view->document  = Factory::getApplication()->getDocument();

		$view->setModel($model, true);

		Factory::getApplication()->triggerEvent('onComLoginguardControllerMethodBeforeAdd', [$user, $method]);

		return $view->display();
	}

	/**
	 * Edit an existing TFA method
	 *
	 * @param   bool   $cachable   Can this view be cached
	 * @param   array  $urlparams  An array of safe url parameters and their variable types, for valid values see
	 *                             {@link JFilterInput::clean()}.
	 *
	 * @return  self   The current JControllerLegacy object to support chaining.
	 */
	public function edit($cachable = false, $urlparams = [])
	{
		$this->assertLoggedInUser();

		// Make sure I am allowed to edit the specified user
		$user_id = $this->input->getInt('user_id', null);
		$user    = Factory::getUser($user_id);
		$this->_assertCanEdit($user);

		// Also make sure the method really does exist
		$id     = $this->input->getInt('id');
		$record = $this->_assertValidRecordId($id, $user);

		if ($id <= 0)
		{
			throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		/** @var LoginGuardModelMethod $model */
		$model = $this->getModel();
		$model->setState('id', $id);

		// Pass the return URL to the view
		$returnURL       = $this->input->getBase64('returnurl');
		$viewLayout      = $this->input->get('layout', 'default', 'string');
		$view            = $this->getView('Method', 'html', '', [
			'base_path' => $this->basePath,
			'layout'    => $viewLayout,
		]);
		$view->returnURL = $returnURL;
		$view->user      = $user;
		$view->document  = Factory::getApplication()->getDocument();

		$view->setModel($model, true);

		Factory::getApplication()->triggerEvent('onComLoginguardControllerMethodBeforeEdit', [$id, $user]);

		return $view->display();
	}

	/**
	 * Regenerate backup codes
	 *
	 * @param   bool   $cachable   Can this view be cached
	 * @param   array  $urlparams  An array of safe url parameters and their variable types, for valid values see
	 *                             {@link JFilterInput::clean()}.
	 *
	 * @return  self   The current JControllerLegacy object to support chaining.
	 */
	public function regenbackupcodes($cachable = false, $urlparams = [])
	{
		$this->assertLoggedInUser();

		$this->checkToken('get');

		// Make sure I am allowed to edit the specified user
		$user_id = $this->input->getInt('user_id', null);
		$user    = Factory::getUser($user_id);
		$this->_assertCanEdit($user);

		/** @var LoginGuardModelBackupcodes $model */
		if (!class_exists('LoginGuardModelBackupcodes'))
		{
			require_once JPATH_BASE . '/components/com_loginguard/models/backupcodes.php';
		}

		$model = $this->getModel('Backupcodes', 'LoginGuardModel');
		$model->regenerateBackupCodes($user);

		$backupCodesRecord = $model->getBackupCodesRecord($user);

		// Redirect
		$redirectUrl = 'index.php?option=com_loginguard&task=method.edit&user_id=' . $user_id . '&id=' . $backupCodesRecord->id;
		$returnURL   = $this->input->getBase64('returnurl');

		if (!empty($returnURL))
		{
			$redirectUrl .= '&returnurl=' . $returnURL;
		}

		$this->setRedirect(Route::_($redirectUrl, false));

		Factory::getApplication()->triggerEvent('onComLoginguardControllerMethodAfterRegenbackupcodes');

		return $this;
	}

	/**
	 * Delete an existing TFA method
	 *
	 * @param   bool   $cachable   Can this view be cached
	 * @param   array  $urlparams  An array of safe url parameters and their variable types, for valid values see
	 *                             {@link JFilterInput::clean()}.
	 *
	 * @return  self   The current JControllerLegacy object to support chaining.
	 */
	public function delete($cachable = false, $urlparams = [])
	{
		$this->assertLoggedInUser();

		$this->checkToken($this->input->getMethod());

		// Make sure I am allowed to edit the specified user
		$user_id = $this->input->getInt('user_id', null);
		$user    = Factory::getUser($user_id);
		$this->_assertCanEdit($user);

		// Also make sure the method really does exist
		$id     = $this->input->getInt('id');
		$record = $this->_assertValidRecordId($id, $user);

		if ($id <= 0)
		{
			throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		/** @var LoginGuardModelMethod $model */
		$model = $this->getModel();

		$type    = null;
		$message = null;

		Factory::getApplication()->triggerEvent('onComLoginguardControllerMethodBeforeDelete', [$id, $user]);

		try
		{
			$record->delete();
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
	 * Save the TFA method
	 *
	 * @param   bool   $cachable   Can this view be cached
	 * @param   array  $urlparams  An array of safe url parameters and their variable types, for valid values see
	 *                             {@link JFilterInput::clean()}.
	 *
	 * @return  self   The current JControllerLegacy object to support chaining.
	 */
	public function save($cachable = false, $urlparams = [])
	{
		$this->assertLoggedInUser();

		$this->checkToken();

		// Make sure I am allowed to edit the specified user
		$user_id = $this->input->getInt('user_id', null);
		$user    = Factory::getUser($user_id);
		$this->_assertCanEdit($user);

		// Redirect
		$url       = Route::_('index.php?option=com_loginguard&task=methods.display&user_id=' . $user_id, false);
		$returnURL = $this->input->getBase64('returnurl');

		if (!empty($returnURL))
		{
			$url = base64_decode($returnURL);
		}

		// The record must either be new (ID zero) or exist
		$id     = $this->input->getInt('id', 0);
		$record = $this->_assertValidRecordId($id, $user);

		// If it's a new record we need to read the method from the request and update the (not yet created) record.
		if ($record->id == 0)
		{
			$methodName = $this->input->getCmd('method');
			$this->_assertMethodExists($methodName);
			$record->method = $methodName;
		}

		/** @var LoginGuardModelMethod $model */
		$model = $this->getModel();

		// Ask the plugin to validate the input by calling onLoginGuardTfaSaveSetup
		$result = [];
		$input  = Factory::getApplication()->input;

		Factory::getApplication()->triggerEvent('onComLoginguardControllerMethodBeforeSave', [$id, $user]);

		try
		{
			$pluginResults = LoginGuardHelperTfa::runPlugins('onLoginGuardTfaSaveSetup', [$record, $input]);

			foreach ($pluginResults as $pluginResult)
			{
				$result = array_merge($result, $pluginResult);
			}
		}
		catch (RuntimeException $e)
		{
			// Go back to the edit page
			$nonSefUrl = 'index.php?option=com_loginguard&task=method.';

			if ($id)
			{
				$nonSefUrl .= 'edit&id=' . (int) $id;
			}
			else
			{
				$nonSefUrl .= 'add&method=' . $record->method;
			}

			$nonSefUrl .= '&user_id=' . $user_id;

			if (!empty($returnURL))
			{
				$nonSefUrl .= '&returnurl=' . urlencode($returnURL);
			}

			$url = JRoute::_($nonSefUrl, false);
			$this->setRedirect($url, $e->getMessage(), 'error');

			return $this;
		}

		// Update the record's options with the plugin response
		$title = $this->input->getString('title', null);
		$title = trim($title);

		if (empty($title))
		{
			$method = $model->getMethod($record->method);
			$title  = $method['display'];
		}

		// Update the record's "default" flag
		$default         = $this->input->getBool('default', false);
		$record->title   = $title;
		$record->options = $result;
		$record->default = $default ? 1 : 0;

		// Ask the model to save the record
		$saved = $record->store();

		if (!$saved)
		{
			// Go back to the edit page
			$nonSefUrl = 'index.php?option=com_loginguard&task=method.';

			if ($id)
			{
				$nonSefUrl .= 'edit&id=' . (int) $id;
			}
			else
			{
				$nonSefUrl .= 'add';
			}

			$nonSefUrl .= '&user_id=' . $user_id;

			if (!empty($returnURL))
			{
				$nonSefUrl .= '&returnurl=' . urlencode($returnURL);
			}

			$url = Route::_($nonSefUrl, false);
			$this->setRedirect($url, $e->getMessage(), 'error');

			return $this;
		}

		$this->setRedirect($url);

		return $this;
	}

	/**
	 * Method to get a model object, loading it ONLY if required.
	 *
	 * Unlike core Joomla, only one instance of the default model will be created. This lets us pass Model state from
	 * the Controller, i.e. implement ARCHITECTURALLY CORRECT MVC for a change.
	 *
	 * @param   string  $name    The model name. Optional.
	 * @param   string  $prefix  The class prefix. Optional.
	 * @param   array   $config  Configuration array for model. Optional.
	 *
	 * @return  JModelLegacy|boolean  Model object on success; otherwise false on failure.
	 */
	public function getModel($name = '', $prefix = '', $config = [])
	{
		$isDefaultModel = (empty($name) || ($name == 'Method')) && empty($prefix);

		if ($isDefaultModel)
		{
			if (is_null($this->default_model))
			{
				$this->default_model = parent::getModel('Method', $prefix, $config);
			}

			return $this->default_model;
		}

		return parent::getModel($name, $prefix, $config);
	}

	/**
	 * Assert that the provided ID is a valid record identified for the given user
	 *
	 * @param   int        $id    Record ID to check
	 * @param   User|null  $user  User record. Null to use current user.
	 *
	 * @return  LoginGuardTableTfa  The loaded record
	 *
	 */
	private function _assertValidRecordId($id, ?User $user = null)
	{
		if (is_null($user))
		{
			$user = Factory::getApplication()->getIdentity() ?: Factory::getUser();
		}

		/** @var LoginGuardModelMethod $model */
		$model = $this->getModel();

		$model->setState('id', $id);

		$record = $model->getRecord($user);

		if (is_null($record) || ($record->id != $id) || ($record->user_id != $user->id))
		{
			throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		return $record;
	}

	/**
	 * Assert that the user is logged in.
	 *
	 * @param   User  $user  User record. Null to use current user.
	 *
	 * @throws  RuntimeException  When the user is a guest (not logged in)
	 */
	private function _assertCanEdit(User $user = null)
	{
		if (is_null($user))
		{
			$user = Factory::getApplication()->getIdentity() ?: Factory::getUser();
		}

		if (!LoginGuardHelperTfa::canEditUser($user))
		{
			throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}
	}

	/**
	 * Assert that the specified TFA method exists, is activated and enabled for the current user
	 *
	 * @param   string  $method  The method to check
	 *
	 * @return  void
	 *
	 * @throws  RuntimeException  If the TFA method does nto exist
	 */
	private function _assertMethodExists($method)
	{
		/** @var LoginGuardModelMethod $model */
		$model = $this->getModel();

		if (!$model->methodExists($method))
		{
			throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}
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