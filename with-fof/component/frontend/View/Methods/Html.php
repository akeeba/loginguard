<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\LoginGuard\Site\View\Methods;

// Protect from unauthorized access
use Akeeba\LoginGuard\Site\Model\BackupCodes;
use Akeeba\LoginGuard\Site\Model\Methods;
use Exception;
use FOF40\View\DataView\Html as BaseView;
use Joomla\CMS\Language\Text as JText;
use Joomla\CMS\User\User;

defined('_JEXEC') || die();

class Html extends BaseView
{
	/**
	 * Is this an administrator page?
	 *
	 * @var    bool
	 * @since  2.0.0
	 */
	public $isAdmin = false;

	/**
	 * The TFA methods available for this user
	 *
	 * @var    array
	 * @since  2.0.0
	 */
	public $methods = [];

	/**
	 * The return URL to use for all links and forms
	 *
	 * @var    string
	 * @since  2.0.0
	 */
	public $returnURL = null;

	/**
	 * Are there any active TFA methods at all?
	 *
	 * @var    bool
	 * @since  2.0.0
	 */
	public $tfaActive = false;

	/**
	 * Which method has the default record?
	 *
	 * @var    string
	 * @since  2.0.0
	 */
	public $defaultMethod = '';

	/**
	 * The user object used to display this page
	 *
	 * @var    User
	 * @since  2.0.0
	 */
	public $user = null;

	/**
	 * Overrides the default method to execute and display a template script.
	 *
	 * @param   string $tpl The name of the template file to parse
	 *
	 * @return  boolean  True on success
	 *
	 * @throws  Exception  When the layout file is not found
	 */
	public function display($tpl = null)
	{
		$this->onBeforeDisplay();

		return parent::display($tpl);
	}

	/**
	 * Execute and display a template script.
	 *
	 * @return  void
	 * @since   2.0.0
	 * @throws  Exception
	 */
	public function onBeforeDisplay()
	{
		if (empty($this->user))
		{
			$this->user = $this->container->platform->getUser();
		}

		/** @var Methods $model */
		$model = $this->getModel();

		if ($this->getLayout() != 'firsttime')
		{
			$this->setLayout('default');
		}

		$this->methods = $model->getMethods($this->user);
		$this->isAdmin = $this->container->platform->isBackend();
		$activeRecords = 0;

		if (count($this->methods))
		{
			foreach ($this->methods as $methodName => $method)
			{
				$methodActiveRecords = is_array($method['active']) || $method['active'] instanceof \Countable ? count($method['active']) : 0;

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
		}

		// If there are no backup codes yet we should create new ones

		/** @var BackupCodes $model */
		$model       = $this->container->factory->model('BackupCodes');
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
				'display'       => JText::_('COM_LOGINGUARD_LBL_BACKUPCODES'),
				'shortinfo'     => JText::_('COM_LOGINGUARD_LBL_BACKUPCODES_DESCRIPTION'),
				'image'         => 'media/com_loginguard/images/emergency.svg',
				'canDisable'    => false,
				'allowMultiple' => false,
				'active'        => [$backupCodesRecord],
			];
		}

		// Include CSS
		$this->addCssFile('media://com_loginguard/css/methods.min.css', null, 'text/css', null, [
			'relative'    => true,
			'detectDebug' => true,
		]);
	}

}
