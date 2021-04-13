<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\LoginGuard\Site\View\Captive;

// Protect from unauthorized access
use Akeeba\LoginGuard\Site\Helper\Tfa;
use Akeeba\LoginGuard\Site\Model\BackupCodes;
use Akeeba\LoginGuard\Site\Model\Captive;
use FOF40\View\DataView\Html as BaseView;
use Joomla\CMS\Language\Text as JText;

defined('_JEXEC') || die();

class Html extends BaseView
{
	/**
	 * The TFA method records for the current user which correspond to enabled plugins
	 *
	 * @var   \Akeeba\LoginGuard\Site\Model\Tfa[]
	 * @since 2.0.0
	 */
	public $records = [];

	/**
	 * The currently selected TFA method record against which we'll be authenticating
	 *
	 * @var   \Akeeba\LoginGuard\Site\Model\Tfa
	 * @since 2.0.0
	 */
	public $record = null;

	/**
	 * The captive TFA page's rendering options
	 *
	 * @var   array|null
	 * @since 2.0.0
	 */
	public $renderOptions = null;

	/**
	 * The title to display at the top of the page
	 *
	 * @var   string
	 * @since 2.0.0
	 */
	public $title = '';

	/**
	 * Is this an administrator page?
	 *
	 * @var   bool
	 * @since 2.0.0
	 */
	public $isAdmin = false;

	/**
	 * Does the currently selected method allow authenticating against all of its records?
	 *
	 * @var   bool
	 * @since 2.0.0
	 */
	public $allowEntryBatching = false;

	/**
	 * All enabled TFA methods (plugins)
	 *
	 * @var   array
	 * @since 2.0.0
	 */
	public $tfaMethods;

	/**
	 * Browser identification hash (fingerprint)
	 *
	 * @var   string|null
	 * @since 3.3.0
	 */
	public $browserId;

	/**
	 * Executes before displaying the captive login page
	 *
	 * @return  void
	 * @since   2.0.0
	 */
	function onBeforeCaptive()
	{
		$this->container->platform->runPlugins('onLoginGuardBeforeDisplayMethods', [$this->container->platform->getUser()]);

		/** @var Captive $model */
		$model = $this->getModel();

		// Load data from the model
		$this->isAdmin    = $this->container->platform->isBackend();
		$this->records    = $this->get('records');
		$this->record     = $this->get('record');
		$this->tfaMethods = Tfa::getTfaMethods();
		$this->browserId  = $this->container->session->get('browserId', null, 'com_loginguard');

		if (!empty($this->records))
		{
			/** @var BackupCodes $codesModel */
			$codesModel        = $this->container->factory->model('BackupCodes');
			$backupCodesRecord = $codesModel->getBackupCodesRecord();

			if (!is_null($backupCodesRecord))
			{
				$backupCodesRecord->title = JText::_('COM_LOGINGUARD_LBL_BACKUPCODES');
				$this->records[]          = $backupCodesRecord;
			}
		}

		// If we only have one record there's no point asking the user to select a TFA method
		if (empty($this->record))
		{
			// Default to the first record
			$this->record = reset($this->records);

			// If we have multiple records try to make this record the default
			if ((is_array($this->records) || $this->records instanceof \Countable ? count($this->records) : 0) > 1)
			{
				foreach ($this->records as $record)
				{
					if ($record->default)
					{
						$this->record = $record;

						break;
					}
				}
			}
		}

		// Set the correct layout based on the availability of a TFA record
		$this->setLayout('default');

		// Should I implement the Remember Me feature?
		$rememberMe = $this->container->params->get('allow_rememberme', 1);

		// If we have no record selected or explicitly asked to run the 'select' task use the correct layout
		if (is_null($this->record) || ($model->getState('task') == 'select'))
		{
			$this->setLayout('select');
		}
		// If there's no browser ID try to fingerprint the browser instead of showing the 2SV page
		elseif (is_null($this->browserId) && ($rememberMe == 1))
		{
			$this->setLayout('fingerprint');

		}

		switch ($this->getLayout())
		{
			case 'select':
				$this->allowEntryBatching = 1;

				$this->container->platform->runPlugins('onComLoginguardCaptiveShowSelect', []);
				break;

			case 'fingerprint':
				// This flag tells the Captive model that we are sending a new browser ID now
				$this->container->session->set('browserIdCodeLoaded', true, 'com_loginguard');
				break;

			case 'default':
			default:
				$this->renderOptions      = $model->loadCaptiveRenderOptions($this->record);
				$this->allowEntryBatching = $this->renderOptions['allowEntryBatching'] ?? 0;

				$this->container->platform->runPlugins('onComLoginguardCaptiveShowCaptive', [
					$this->escape($this->record->title),
				]);
				break;
		}

		// Which title should I use for the page?
		$this->title = $this->get('PageTitle');

		// Include CSS
		$this->addCssFile('media://com_loginguard/css/captive.min.css', null, 'text/css', null, [
			'relative'    => true,
			'detectDebug' => true,
		]);
	}
}
