<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
defined('_JEXEC') or die;

use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

class LoginGuardControllerCaptive extends BaseController
{
	public function __construct(array $config = [])
	{
		parent::__construct($config);

		$this->registerDefaultTask('captive');
	}

	public function captive($cachable = false, $urlparams = false)
	{
		$app  = Factory::getApplication();
		$user = $app->getIdentity() ?: Factory::getUser();

		// Only allow logged in users
		if ($user->guest)
		{
			throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		// Get the view object
		$viewLayout = $this->input->get('layout', 'default', 'string');
		$view       = $this->getView('captive', 'html', '', [
			'base_path' => $this->basePath,
			'layout'    => $viewLayout,
		]);

		$view->document = $app->getDocument();

		// If we're already logged in go to the site's home page
		if ($app->getSession()->get('com_loginguard.tfa_checked', 0) == 1)
		{
			$url = Route::_('index.php?option=com_loginguard&task=methods.display', false);

			$this->setRedirect($url);
		}

		// Pass the model to the view
		/** @var LoginGuardModelCaptive $model */
		$model = $this->getModel('captive');
		$view->setModel($model, true);

		try
		{
			// kill all modules on the page
			$model->killAllModules();
		}
		catch (Exception $e)
		{
			// If we can't kill the modules we can still survive.
		}

		// Pass the TFA record ID to the model
		$record_id = $this->input->getInt('record_id', null);
		$model->setState('record_id', $record_id);

		// Validate by Browser ID
		try
		{
			$browserId = $this->getBrowserId();
		}
		catch (Exception $e)
		{
			$browserId = '';
		}

		/** @var LoginGuardModelRememberme $rememberMeModel */
		$rememberMeModel = $this->getModel('rememberme');

		if (!is_null($browserId) && $rememberMeModel->setBrowserId($browserId)->hasValidCookie())
		{
			// Tell the plugins that we successfully applied 2SV – used by our User Actions Log plugin.
			LoginGuardHelperTfa::runPlugins('onComLoginguardCaptiveValidateSuccess', [
				Text::_('COM_LOGINGUARD_LBL_METHOD_BROWSERID'),
			]);

			// Flag the user as fully logged in
			$app->getSession()->set('com_loginguard.tfa_checked', 1);

			// Get the return URL stored by the plugin in the session
			$return_url = $app->getSession()->get('com_loginguard.return_url', '');

			// If the return URL is not set or not inside this site redirect to the site's front page
			if (empty($return_url) || !Uri::isInternal($return_url))
			{
				$return_url = Uri::base();
			}

			$this->setRedirect($return_url);

			return $this;
		}

		// Do not go through $this->display() because it overrides the model, nullifying the whole concept of MVC.
		$view->display();

		return $this;
	}


	/**
	 * Validate the TFA code entered by the user
	 *
	 * @param   bool   $cachable       Can this view be cached
	 * @param   array  $urlparameters  An array of safe url parameters and their variable types, for valid values see
	 *                                 {@link JFilterInput::clean()}.
	 *
	 * @return  self   The current JControllerLegacy object to support chaining.
	 */
	public function validate($cachable = false, $urlparameters = [])
	{
		// CSRF Check
		$this->checkToken();

		// Get the TFA parameters from the request
		$record_id = $this->input->getInt('record_id', null);
		$code      = $this->input->get('code', null, 'raw');
		/** @var LoginGuardModelCaptive $model */
		$model = $this->getModel('Captive', 'LoginGuardModel');

		// Validate the TFA record
		$model->setState('record_id', $record_id);
		$record = $model->getRecord();

		if (empty($record))
		{
			Factory::getApplication()->triggerEvent('onComLoginguardCaptiveValidateInvalidMethod');

			throw new RuntimeException(Text::_('COM_LOGINGUARD_ERR_INVALID_METHOD'), 500);
		}

		// Validate the code
		$user = JFactory::getUser();

		JLoader::register('LoginGuardHelperTfa', JPATH_SITE . '/components/com_loginguard/helpers/tfa.php');
		$results     = LoginGuardHelperTfa::runPlugins('onLoginGuardTfaValidate', [$record, $user, $code]);
		$isValidCode = false;

		if ($record->method == 'backupcodes')
		{
			if (!class_exists('LoginGuardModelBackupcodes'))
			{
				require_once JPATH_BASE . '/components/com_loginguard/models/backupcodes.php';
			}

			/** @var LoginGuardModelBackupcodes $codesModel */
			$codesModel = BaseDatabaseModel::getInstance('Backupcodes', 'LoginGuardModel');
			$results    = [$codesModel->isBackupCode($code, $user)];
			/**
			 * This is required! Do not remove!
			 *
			 * There is a store() call below. It saves the in-memory TFA record to the database. That includes the
			 * options key which contains the configuration of the method. For backup codes, these are the actual codes
			 * you can use. When we check for a backup code validity we also "burn" it, i.e. we remove it from the
			 * options table and save that to the database. However, this DOES NOT update the $record here. Therefore
			 * the call to saveRecord() would overwrite the database contents with a record that _includes_ the backup
			 * code we had just burned. As a result the single use backup codes end up being multiple use.
			 *
			 * By doing a getRecord() here, right after we have "burned" any correct backup codes, we resolve this
			 * issue. The loaded record will reflect the database contents where the options DO NOT include the code we
			 * just used. Therefore the call to store() will result in the correct database state, i.e. the used backup
			 * code being removed.
			 */
			$record = $model->getRecord();
		}

		if (is_array($results) && !empty($results))
		{
			foreach ($results as $result)
			{
				if ($result)
				{
					$isValidCode = true;

					break;
				}
			}
		}

		if (!$isValidCode)
		{
			// The code is wrong. Display an error and go back.
			$captiveURL = Route::_('index.php?option=com_loginguard&view=captive&record_id=' . $record_id, false);
			$message    = Text::_('COM_LOGINGUARD_ERR_INVALID_CODE');
			$this->setRedirect($captiveURL, $message, 'error');

			Factory::getApplication()->triggerEvent('onComLoginguardCaptiveValidateFailed', [$record->title]);

			return $this;
		}

		// Update the Last Used, UA and IP columns
		JLoader::import('joomla.environment.browser');
		$jNow = Date::getInstance();

		$record->last_used = $jNow->toSql();

		if (!class_exists('LoginGuardModelMethod'))
		{
			JLoader::register('LoginGuardModelMethod', __DIR__ . '/../models/method.php');
		}

		$record->store();

		// Flag the user as fully logged in
		$session = Factory::getApplication()->getSession();
		$session->set('com_loginguard.tfa_checked', 1);

		// Get the return URL stored by the plugin in the session
		$return_url = $session->get('com_loginguard.return_url', '');

		// If the return URL is not set or not inside this site redirect to the site's front page
		if (empty($return_url) || !Uri::isInternal($return_url))
		{
			$return_url = Uri::base();
		}

		$this->setRedirect($return_url);

		Factory::getApplication()->triggerEvent('onComLoginguardCaptiveValidateSuccess', [$record->title]);

		return $this;
	}

	/**
	 * Get the Browser ID from the session or the request
	 *
	 * Checks if there is a valid request trying to set the browser ID. If so, it's saved in the session and returned.
	 * Otherwise we return whatever browser ID we currently have in the session.
	 *
	 * @return string|null
	 * @throws Exception
	 */
	private function getBrowserId(): ?string
	{
		$app     = Factory::getApplication();
		$session = $app->getSession();

		/**
		 * I will only accept a browser ID if it's POSTed with a valid anti-CSRF token and the session flag is set. This
		 * gives me adequate assurances that there's no monkey business going on.
		 */
		try
		{
			$allowedFlag = ($this->input->getMethod() == 'POST') &&
				$session->get('com_loginguard.browserIdCodeLoaded', false);
		}
		catch (Exception $e)
		{
			$allowedFlag = false;
		}

		if ($allowedFlag)
		{
			// IMPORTANT: DO NOT USE checkToken() — IT CAUSES AN INFINITE REDIRECTION!
			$token       = Session::getFormToken();
			$allowedFlag = $app->input->post->getInt($token, 0) == 1;
		}

		// Get the browser ID recorded in the session and in the request
		$browserIdSession = $session->get('com_loginguard.browserId', null);
		$browserIdRequest = $this->input->post->getString('browserId', null);

		// Nobody is trying to set a browser ID in the request. Return the browser ID we stored in the session.
		if (!$allowedFlag && is_null($browserIdRequest))
		{
			return $browserIdSession;
		}

		// Attempt to pass a browser ID from a page other than layout=fingerprint. Pretend fingerprinting failed.
		if (!$allowedFlag)
		{
			$browserIdRequest = '';
		}

		// We already have a browser ID in the session and we're given a different one. That's... strange.
		if (!is_null($browserIdSession) && !empty($browserIdRequest) && ($browserIdRequest != $browserIdSession))
		{
			$browserIdRequest = '';
		}

		// Normalize zero, null and empty string to an empty string that means "fingerprinting failed"
		if (empty($browserIdRequest))
		{
			$browserIdRequest = '';
		}

		// Reset the flag to prevent opportunities to override our browser fingerprinting
		$session->set('com_loginguard.browserIdCodeLoaded', false);
		// Save the browser ID in the session
		$session->set('com_loginguard.browserId', $browserIdRequest);

		// Finally, return the browser ID as requested
		return $browserIdRequest;
	}
}