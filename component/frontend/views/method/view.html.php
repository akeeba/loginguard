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
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\User\User;

class LoginGuardViewMethod extends BaseHtmlView
{
	/**
	 * Is this an administrator page?
	 *
	 * @var   bool
	 */
	public $isAdmin = false;

	/**
	 * The editor page render options
	 *
	 * @var   array
	 */
	public $renderOptions = [];

	/**
	 * The TFA method record being edited
	 *
	 * @var   object
	 */
	public $record = null;

	/**
	 * The title text for this page
	 *
	 * @var  string
	 */
	public $title = '';

	/**
	 * The return URL to use for all links and forms
	 *
	 * @var   string
	 */
	public $returnURL = null;

	/**
	 * The user object used to display this page
	 *
	 * @var   User
	 */
	public $user = null;

	/**
	 * The backup codes for the current user. Only applies when the backup codes record is being "edited"
	 *
	 * @var   array
	 */
	public $backupCodes = [];

	/**
	 * Am I editing an existing method? If it's false then I'm adding a new method.
	 *
	 * @var   bool
	 */
	public $isEditExisting = false;

	/**
	 * Execute and display a template script.
	 *
	 * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
	 *
	 * @return  mixed  A string if successful, otherwise an Error object.
	 */
	function display($tpl = null)
	{
		$app = Factory::getApplication();

		if (empty($this->user))
		{
			$this->user = $app->getIdentity() ?: Factory::getUser();
		}

		/** @var LoginGuardModelMethod $model */
		$model = $this->getModel();
		$this->setLayout('edit');
		$this->renderOptions = $model->getRenderOptions($this->user);
		$this->record        = $model->getRecord($this->user);
		$this->title         = $model->getPageTitle();
		$this->isAdmin       = $app->isClient('administrator');

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

		// Back-end: always show a title in the 'title' module position, not in the page body
		if ($this->isAdmin)
		{
			ToolbarHelper::title(Text::_('COM_LOGINGUARD') . " <small>" . $this->title . "</small>", 'loginguard');

			$helpUrl = $this->renderOptions['help_url'];

			if (!empty($helpUrl))
			{
				ToolbarHelper::help('', false, $helpUrl);
			}

			$this->title = '';
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

		// Display the view
		return parent::display($tpl);
	}
}