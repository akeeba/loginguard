<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\LoginGuard\Site\View\Method;

// Protect from unauthorized access
use Akeeba\LoginGuard\Site\Model\Method;
use Exception;
use FOF40\View\DataView\Html as BaseView;
use Joomla\CMS\Toolbar\ToolbarHelper as JToolbarHelper;
use Joomla\CMS\User\User as JUser;

defined('_JEXEC') || die();

class Html extends BaseView
{
	/**
	 * Is this an administrator page?
	 *
	 * @var   bool
	 * @since 2.0.0
	 */
	public $isAdmin = false;

	/**
	 * The editor page render options
	 *
	 * @var   array
	 * @since 2.0.0
	 */
	public $renderOptions = [];

	/**
	 * The TFA method record being edited
	 *
	 * @var   object
	 * @since 2.0.0
	 */
	public $record = null;

	/**
	 * The title text for this page
	 *
	 * @var  string
	 * @since 2.0.0
	 */
	public $title = '';

	/**
	 * The return URL to use for all links and forms
	 *
	 * @var   string
	 * @since 2.0.0
	 */
	public $returnURL = null;

	/**
	 * The user object used to display this page
	 *
	 * @var   JUser
	 * @since 2.0.0
	 */
	public $user = null;

	/**
	 * The backup codes for the current user. Only applies when the backup codes record is being "edited"
	 *
	 * @var   array
	 * @since 2.0.0
	 */
	public $backupCodes = [];

	/**
	 * Am I editing an existing method? If it's false then I'm adding a new method.
	 *
	 * @var   bool
	 * @since 2.0.0
	 */
	public $isEditExisting = false;

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
	 */
	function onBeforeDisplay()
	{
		if (empty($this->user))
		{
			$this->user = $this->container->platform->getUser();
		}

		/** @var Method $model */
		$model = $this->getModel();
		$this->setLayout('edit');
		$this->renderOptions = $model->getRenderOptions($this->user);
		$this->record        = $model->getRecord($this->user);
		$this->title         = $model->getPageTitle();
		$this->isAdmin       = $this->container->platform->isBackend();

		// Backup codes are a special case, rendered with a special layout
		if ($this->record->method == 'backupcodes')
		{
			$this->setLayout('backupcodes');

			$backupCodes = $this->record->options;

			if (!is_array($backupCodes))
			{
				$backupCodes = [];
			}

			$backupCodes = array_filter($backupCodes, function ($x) {
				return !empty($x);
			});

			if (count($backupCodes) % 2 != 0)
			{
				$backupCodes[] = '';
			}

			/**
			 * The call to array_merge resets the array indices. This is necessary since array_filter kept the indices,
			 * meaning our elements are completely out of order.
			 */
			$this->backupCodes = array_merge($backupCodes);
		}

		// Set up the isEditExisting property.
		$this->isEditExisting = !empty($this->record->id);

		if ($this->isAdmin)
		{
			$helpUrl     = $this->renderOptions['help_url'];

			if (!empty($helpUrl))
			{
				JToolbarHelper::help('', false, $helpUrl);
			}
		}

		$this->addCssFile('media://com_loginguard/css/methods.min.css', null, 'text/css', null, [
			'relative'    => true,
			'detectDebug' => true
		]);
	}

}
