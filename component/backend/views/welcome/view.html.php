<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2017 Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class LoginGuardViewWelcome extends BaseHtmlView
{
	/**
	 * Is the user plugin missing / disabled?
	 *
	 * @var   bool
	 */
	public $noUserPlugin = false;

	/**
	 * Is the system plugin missing / disabled?
	 *
	 * @var   bool
	 */
	public $noSystemPlugin = false;

	/**
	 * Are no published methods detected?
	 *
	 * @var   bool
	 */
	public $noMethods = false;

	/**
	 * Are no loginguard plugins installed?
	 *
	 * @var   bool
	 */
	public $notInstalled = false;

	/**
	 * Do we have to migrate from Joomla's Two Factor Authentication?
	 *
	 * @var   bool
	 */
	public $needsMigration = false;

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
		/** @var LoginGuardModelWelcome $model */
		$model = $this->getModel();

		$this->noMethods         = !$model->hasPublishedPlugins();
		$this->notInstalled      = !$model->hasInstalledPlugins();
		$this->noUserPlugin      = !$model->isLoginGuardPluginPublished('user');
		$this->noSystemPlugin    = !$model->isLoginGuardPluginPublished('system');
		$this->needsMigration    = $model->needsMigration();

		// Show a title and the component's Options button
		ToolbarHelper::title(JText::_('COM_LOGINGUARD') . ': <small>' . JText::_('COM_LOGINGUARD_HEAD_WELCOME') . '</small>', 'loginguard');
		ToolbarHelper::link('index.php?option=com_loginguard&view=users', 'COM_LOGINGUARD_HEAD_USERS', 'users');
		ToolbarHelper::help('', false, 'https://github.com/akeeba/loginguard/wiki');
		ToolbarHelper::preferences('com_loginguard');

		// Display the view
		return parent::display($tpl);
	}
}