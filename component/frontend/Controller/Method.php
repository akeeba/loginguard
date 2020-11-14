<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\LoginGuard\Site\Controller;

use Akeeba\LoginGuard\Site\Helper\Tfa;
use Akeeba\LoginGuard\Site\Model\BackupCodes;
use Akeeba\LoginGuard\Site\Model\Method as MethodModel;
use Exception;
use FOF30\Container\Container;
use FOF30\Controller\Controller;
use FOF30\Controller\Mixin\PredefinedTaskList;
use Joomla\CMS\Language\Text as JText;
use Joomla\CMS\Router\Route as JRoute;
use Joomla\CMS\User\User;
use Joomla\CMS\User\User as JUser;
use RuntimeException;

// Protect from unauthorized access
defined('_JEXEC') || die();

/**
 * Controller for the method edit page
 *
 * @since       2.0.0
 */
class Method extends Controller
{
	use PredefinedTaskList;

	/**
	 * Method constructor.
	 *
	 * @param Container $container
	 * @param array     $config
	 *
	 * @since   2.0.0
	 */
	public function __construct(Container $container, array $config = [])
	{
		if (!isset($config['default_task']))
		{
			$config['default_task'] = 'add';
		}

		parent::__construct($container, $config);

		$this->setPredefinedTaskList(['add', 'edit', 'regenbackupcodes', 'delete', 'save']);
		$this->cacheableTasks = [];
		$this->userCaching = 2;
	}

	/**
	 * Add a new TFA method
	 *
	 * @return  void
	 * @since   2.0.0
	 */
	public function add()
	{
		$this->assertLoggedInUser();

		// Make sure I am allowed to edit the specified user
		$user_id = $this->input->getInt('user_id', null);
		$user    = $this->container->platform->getUser($user_id);
		$this->_assertCanEdit($user);

		// Also make sure the method really does exist
		$method = $this->input->getCmd('method');
		$this->_assertMethodExists($method);

		/** @var MethodModel $model */
		$model = $this->getModel();
		$model->setState('method', $method);

		// Pass the return URL to the view
		$returnURL       = $this->input->getBase64('returnurl');
		$view            = $this->getView();
		$view->returnURL = $returnURL;
		$view->user      = $user;

		parent::display();
	}

	/**
	 * Edit an existing TFA method
	 *
	 * @return  void
	 * @since   2.0.0
	 */
	public function edit()
	{
		$this->assertLoggedInUser();

		// Make sure I am allowed to edit the specified user
		$user_id = $this->input->getInt('user_id', null);
		$user    = $this->container->platform->getUser($user_id);
		$this->_assertCanEdit($user);

		// Also make sure the method really does exist
		$id     = $this->input->getInt('id');
		$record = $this->_assertValidRecordId($id, $user);

		if ($id <= 0)
		{
			throw new RuntimeException(JText::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		/** @var MethodModel $model */
		$model = $this->getModel();
		$model->setState('id', $id);

		// Pass the return URL to the view
		$returnURL       = $this->input->getBase64('returnurl');
		$view            = $this->getView();
		$view->returnURL = $returnURL;
		$view->user      = $user;

		parent::display();
	}

	/**
	 * Regenerate backup codes
	 *
	 * @return  void
	 * @since   2.0.0
	 *
	 * @throws  Exception
	 */
	public function regenbackupcodes()
	{
		$this->assertLoggedInUser();

		$this->csrfProtection();

		// Make sure I am allowed to edit the specified user
		$user_id = $this->input->getInt('user_id', null);
		$user    = $this->container->platform->getUser($user_id);
		$this->_assertCanEdit($user);

		/** @var BackupCodes $model */
		$model = $this->container->factory->model('BackupCodes');
		$model->regenerateBackupCodes($user);

		$backupCodesRecord = $model->getBackupCodesRecord($user);

		// Redirect
		$redirectUrl = 'index.php?option=com_loginguard&view=Method&task=edit&user_id=' . $user_id . '&id=' . $backupCodesRecord->id;
		$returnURL = $this->input->getBase64('returnurl');

		if (!empty($returnURL))
		{
			$redirectUrl .= '&returnurl=' . $returnURL;
		}

		$this->setRedirect(JRoute::_($redirectUrl, false));
	}

	/**
	 * Delete an existing TFA method
	 *
	 * @return  void
	 * @since   2.0.0
	 *
	 * @throws  Exception
	 */
	public function delete()
	{
		$this->assertLoggedInUser();

		$this->csrfProtection();

		// Make sure I am allowed to edit the specified user
		$user_id = $this->input->getInt('user_id', null);
		$user    = $this->container->platform->getUser($user_id);
		$this->_assertCanEdit($user);

		// Also make sure the method really does exist
		$id     = $this->input->getInt('id');
		$record = $this->_assertValidRecordId($id, $user);

		if ($id <= 0)
		{
			throw new RuntimeException(JText::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		$type    = null;
		$message = null;

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
		$url       = JRoute::_('index.php?option=com_loginguard&view=Methods&user_id=' . $user_id, false);
		$returnURL = $this->input->getBase64('returnurl');

		if (!empty($returnURL))
		{
			$url = base64_decode($returnURL);
		}

		$this->setRedirect($url, $message, $type);
	}

	/**
	 * Save the TFA method
	 *
	 * @return  void
	 * @since   2.0.0
	 *
	 * @throws Exception
	 */
	public function save()
	{
		$this->assertLoggedInUser();

		// CSRF Check
		$this->csrfProtection();

		// Make sure I am allowed to edit the specified user
		$user_id = $this->input->getInt('user_id', null);
		$user    = $this->container->platform->getUser($user_id);
		$this->_assertCanEdit($user);

		// Redirect
		$url       = JRoute::_('index.php?option=com_loginguard&view=Methods&user_id=' . $user_id, false);
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

		/** @var MethodModel $model */
		$model = $this->getModel();

		// Ask the plugin to validate the input by calling onLoginGuardTfaSaveSetup
		$result = [];
		$input  = $this->input;

		try
		{
			$pluginResults = $this->container->platform->runPlugins('onLoginGuardTfaSaveSetup', [$record, $input]);

			foreach ($pluginResults as $pluginResult)
			{
				$result = array_merge($result, $pluginResult);
			}
		}
		catch (RuntimeException $e)
		{
			// Go back to the edit page
			$nonSefUrl = 'index.php?option=com_loginguard&view=Method&task=';

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

			return;
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
		try
		{
			$record->save();
		}
		catch (Exception $e)
		{
			// Go back to the edit page
			$nonSefUrl = 'index.php?option=com_loginguard&view=Method&task=';

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

			$url = JRoute::_($nonSefUrl, false);
			$this->setRedirect($url, $e->getMessage(), 'error');

			return;
		}

		$this->setRedirect($url);
	}

	/**
	 * Assert that the provided ID is a valid record identified for the given user
	 *
	 * @param   int    $id    Record ID
	 * @param   JUser  $user  Which user should I validate for? Leave null for currently logged in user.
	 *
	 * @return  \Akeeba\LoginGuard\Site\Model\Tfa  The loaded record
	 * @since   2.0.0
	 */
	private function _assertValidRecordId($id, JUser $user = null)
	{
		if (is_null($user))
		{
			$user    = $this->container->platform->getUser();
		}

		/** @var MethodModel $model */
		$model = $this->getModel();

		$model->setState('id', $id);

		$record = $model->getRecord($user);

		if (is_null($record) || ($record->id != $id) || ($record->user_id != $user->id))
		{
			throw new RuntimeException(JText::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		return $record;
	}

	/**
	 * Assert that the user is logged in.
	 *
	 * @param   JUser|User  $user  User record. Null to use current user.
	 *
	 * @return  void
	 * @throws  RuntimeException  When the user is a guest (not logged in)
	 * @since   2.0.0
	 */
	private function _assertCanEdit($user = null)
	{
		if (is_null($user))
		{
			$user    = $this->container->platform->getUser();
		}

		if (!Tfa::canEditUser($user))
		{
			throw new RuntimeException(JText::_('JERROR_ALERTNOAUTHOR'), 403);
		}
	}

	/**
	 * Assert that the specified TFA method exists, is activated and enabled for the current user
	 *
	 * @param   string  $method  The method to check
	 *
	 * @return  void
	 * @since   2.0.0
	 *
	 * @throws  RuntimeException  If the TFA method does nto exist
	 */
	private function _assertMethodExists($method)
	{
		/** @var MethodModel $model */
		$model = $this->getModel();

		if (!$model->methodExists($method))
		{
			throw new RuntimeException(JText::_('JERROR_ALERTNOAUTHOR'), 403);
		}
	}

	private function assertLoggedInUser()
	{
		if ($this->container->platform->getUser()->guest)
		{
			throw new RuntimeException(JText::_('JERROR_ALERTNOAUTHOR'), 403);
		}
	}
}
