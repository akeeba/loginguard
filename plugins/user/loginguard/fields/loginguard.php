<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Uri\Uri;

// Prevent direct access
defined('_JEXEC') || die;

class JFormFieldLoginguard extends FormField
{
	/**
	 * Element name
	 *
	 * @var   string
	 */
	protected $_name = 'Loginguard';

	function getInput()
	{
		JLoader::register('LoginGuardViewMethods', JPATH_ROOT . '/components/com_loginguard/views/methods/view.html.php');

		BaseDatabaseModel::addIncludePath(JPATH_ROOT . '/components/com_loginguard/models', 'LoginGuardModel');
		Table::addIncludePath(JPATH_ROOT . '/components/com_loginguard/tables');

		// Make sure we can load the classes we need
		if (!class_exists('LoginGuardViewMethods', true) || !ComponentHelper::isEnabled('com_loginguard'))
		{
			return Text::_('PLG_USER_LOGINGUARD_ERR_NOCOMPONENT');
		}

		// Load the language files
		$jLang = Factory::getLanguage();
		$jLang->load('com_loginguard', JPATH_ADMINISTRATOR, null, true, true);
		$jLang->load('com_loginguard', JPATH_SITE, null, true, true);

		$user_id = $this->form->getData()->get('id', null);

		if (is_null($user_id))
		{
			return Text::_('PLG_USER_LOGINGUARD_ERR_NOUSER');
		}

		$user = Factory::getUser($user_id);

		// Get a model
		/** @var LoginGuardModelMethods $model */
		$model = BaseDatabaseModel::getInstance('Methods', 'LoginGuardModel');

		// Get a view object
		$view = new LoginGuardViewMethods([
			'base_path' => JPATH_SITE . '/components/com_loginguard',
		]);
		$view->setModel($model, true);
		$view->returnURL = base64_encode(Uri::getInstance()->toString());
		$view->user      = $user;

		return $view->display();
	}
}
