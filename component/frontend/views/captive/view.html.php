<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
defined('_JEXEC') or die;

use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class LoginGuardViewCaptive extends BaseHtmlView
{
	/**
	 * The TFA method records for the current user which correspond to enabled plugins
	 *
	 * @var  array
	 */
	public $records = [];

	/**
	 * The currently selected TFA method record against which we'll be authenticating
	 *
	 * @var  null|stdClass
	 */
	public $record = null;

	/**
	 * The captive TFA page's rendering options
	 *
	 * @var   array|null
	 */
	public $renderOptions = null;

	/**
	 * The title to display at the top of the page
	 *
	 * @var   string
	 */
	public $title = '';

	/**
	 * Is this an administrator page?
	 *
	 * @var   bool
	 */
	public $isAdmin = false;

	/**
	 * Does the currently selected method allow authenticating against all of its records?
	 *
	 * @var   bool
	 */
	public $allowEntryBatching = false;

	/**
	 * All enabled TFA methods (plugins)
	 *
	 * @var   array
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
	 * Execute and display a template script.
	 *
	 * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
	 *
	 * @return  mixed  A string if successful, otherwise an Error object.
	 *
	 * @throws  Exception
	 */
	public function display($tpl = null)
	{
		$app  = Factory::getApplication();
		$user = $app->getIdentity() ?: Factory::getUser();

		LoginGuardHelperTfa::runPlugins('onLoginGuardBeforeDisplayMethods', [$user]);

		/** @var LoginGuardModelCaptive $model */
		$model = $this->getModel();

		// Load data from the model
		$this->isAdmin    = $app->isClient('administrator');
		$this->records    = $this->get('records');
		$this->record     = $this->get('record');
		$this->tfaMethods = LoginGuardHelperTfa::getTfaMethods();
		$this->browserId  = $app->getSession()->get('com_loginguard.browserId', null);

		if (!empty($this->records))
		{
			if (!class_exists('LoginGuardModelBackupcodes'))
			{
				require_once JPATH_BASE . '/components/com_loginguard/models/backupcodes.php';
			}

			/** @var LoginGuardModelBackupcodes $codesModel */
			$codesModel        = BaseDatabaseModel::getInstance('Backupcodes', 'LoginGuardModel');
			$backupCodesRecord = $codesModel->getBackupCodesRecord();

			if (!is_null($backupCodesRecord))
			{
				$backupCodesRecord->title = Text::_('COM_LOGINGUARD_LBL_BACKUPCODES');
				$this->records[]          = $backupCodesRecord;
			}
		}

		// If we only have one record there's no point asking the user to select a TFA method
		if (empty($this->record))
		{
			// Default to the first record
			$this->record = reset($this->records);

			// If we have multiple records try to make this record the default
			if (count($this->records) > 1)
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
		$rememberMe = ComponentHelper::getParams('com_loginguard')->get('allow_rememberme', 1);

		$this->renderOptions      = $model->loadCaptiveRenderOptions($this->record);
		$this->allowEntryBatching = isset($this->renderOptions['allowEntryBatching']) ? $this->renderOptions['allowEntryBatching'] : 0;

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

				LoginGuardHelperTfa::runPlugins('onComLoginguardCaptiveShowSelect', []);
				break;

			case 'fingerprint':
				// This flag tells the Captive model that we are sending a new browser ID now
				$app->getSession()->set('com_loginguard.browserIdCodeLoaded', true);
				break;

			case 'default':
			default:
				$this->renderOptions      = $model->loadCaptiveRenderOptions($this->record);
				$this->allowEntryBatching = $this->renderOptions['allowEntryBatching'] ?? 0;

				LoginGuardHelperTfa::runPlugins('onComLoginguardCaptiveShowCaptive', [
					$this->escape($this->record->title),
				]);
				break;
		}


		// Which title should I use for the page?
		$this->title = $this->get('PageTitle');

		// Back-end: always show a title in the 'title' module position, not in the page body
		if ($this->isAdmin)
		{
			ToolbarHelper::title(JText::_('COM_LOGINGUARD_HEAD_TFA_PAGE'), 'lock');
			$this->title = '';
		}

		// Get the media version
		JLoader::register('LoginGuardHelperVersion', JPATH_SITE . '/components/com_loginguard/helpers/version.php');
		$mediaVersion = ApplicationHelper::getHash(LoginGuardHelperVersion::component('com_loginguard'));

		// Include CSS
		HTMLHelper::_('stylesheet', 'com_loginguard/captive.min.css', [
			'version'       => $mediaVersion,
			'relative'      => true,
			'detectDebug'   => true,
			'pathOnly'      => false,
			'detectBrowser' => true,
		], [
			'type' => 'text/css',
		]);

		// Display the view
		return parent::display($tpl);
	}
}