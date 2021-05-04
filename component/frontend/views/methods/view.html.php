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
use Joomla\CMS\MVC\View\HtmlView as HtmlViewAlias;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\User\User;

class LoginGuardViewMethods extends HtmlViewAlias
{
	/**
	 * Is this an administrator page?
	 *
	 * @var   bool
	 */
	public $isAdmin = false;

	/**
	 * The TFA methods available for this user
	 *
	 * @var   array
	 */
	public $methods = [];

	/**
	 * The return URL to use for all links and forms
	 *
	 * @var   string
	 */
	public $returnURL = null;

	/**
	 * Are there any active TFA methods at all?
	 *
	 * @var   bool
	 */
	public $tfaActive = false;

	/**
	 * Which method has the default record?
	 *
	 * @var   string
	 */
	public $defaultMethod = '';

	/**
	 * The user object used to display this page
	 *
	 * @var   User
	 */
	public $user = null;

	/**
	 * Execute and display a template script.
	 *
	 * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
	 *
	 * @return  mixed  A string if successful, otherwise an Error object.
	 *
	 * @see     JViewLegacy::loadTemplate()
	 */
	function display($tpl = null)
	{
		$app = Factory::getApplication();

		if (empty($this->user))
		{
			$this->user = $app->getIdentity() ?: Factory::getUser();
		}

		/** @var LoginGuardModelMethods $model */
		$model = $this->getModel();

		if ($this->getLayout() != 'firsttime')
		{
			$this->setLayout('default');
		}

		$this->methods = $model->getMethods($this->user);
		$this->isAdmin = $app->isClient('administrator');
		$activeRecords = 0;

		foreach ($this->methods as $methodName => $method)
		{
			$methodActiveRecords = count($method['active']);

			if (!$methodActiveRecords)
			{
				continue;
			}

			$activeRecords   += $methodActiveRecords;
			$this->tfaActive = true;

			foreach ($method['active'] as $record)
			{
				if ($record->default)
				{
					$this->defaultMethod = $methodName;

					break;
				}
			}
		}

		// If there are no backup codes yet we should create new ones
		if (!class_exists('LoginGuardModelBackupcodes'))
		{
			require_once JPATH_BASE . '/components/com_loginguard/models/backupcodes.php';
		}

		/** @var LoginGuardModelBackupcodes $model */
		BaseDatabaseModel::addIncludePath(JPATH_ROOT . '/components/com_loginguard/models', 'LoginGuardModel');
		$model       = BaseDatabaseModel::getInstance('Backupcodes', 'LoginGuardModel');
		$backupCodes = $model->getBackupCodes($this->user);

		if ($activeRecords && empty($backupCodes))
		{
			$model->regenerateBackupCodes($this->user);
		}

		$backupCodesRecord = $model->getBackupCodesRecord($this->user);

		if (!is_null($backupCodesRecord))
		{
			$this->methods['backupcodes'] = [
				'name'          => 'backupcodes',
				'display'       => Text::_('COM_LOGINGUARD_LBL_BACKUPCODES'),
				'shortinfo'     => Text::_('COM_LOGINGUARD_LBL_BACKUPCODES_DESCRIPTION'),
				'image'         => 'media/com_loginguard/images/emergency.svg',
				'canDisable'    => false,
				'allowMultiple' => false,
				'active'        => [$backupCodesRecord],
			];
		}

		// Get the media version
		JLoader::register('LoginGuardHelperVersion', JPATH_SITE . '/components/com_loginguard/helpers/version.php');
		$mediaVersion = ApplicationHelper::getHash(LoginGuardHelperVersion::component('com_loginguard'));

		// Include CSS
		HTMLHelper::_('stylesheet', 'com_loginguard/methods.css', [
			'version'       => $mediaVersion,
			'relative'      => true,
			'detectDebug'   => true,
			'pathOnly'      => false,
			'detectBrowser' => true,
		], [
			'type' => 'text/css',
		]);

		if (ComponentHelper::getParams('com_loginguard')->get('dark_mode') != 0)
		{
			HTMLHelper::_('stylesheet', 'com_loginguard/dark.css', [
				'version'       => $mediaVersion,
				'relative'      => true,
				'detectDebug'   => true,
				'pathOnly'      => false,
				'detectBrowser' => true,
			], [
				'type' => 'text/css',
			]);
		}

		// Back-end: always show a title in the 'title' module position, not in the page body
		if ($this->isAdmin)
		{
			ToolbarHelper::title(JText::_('COM_LOGINGUARD') . " <small>" . JText::_('COM_LOGINGUARD_HEAD_LIST_PAGE') . "</small>", 'loginguard');
			$this->title = '';

			ToolbarHelper::back('JTOOLBAR_BACK', Route::_('index.php?option=com_loginguard'));
		}

		// Display the view
		$result = parent::display($tpl);

		Factory::getApplication()->triggerEvent('onComLoginGuardViewMethodsAfterDisplay', [$this]);

		return $result;
	}
}