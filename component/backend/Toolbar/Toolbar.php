<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\LoginGuard\Admin\Toolbar;

use FOF30\Toolbar\Toolbar as BaseToolbar;
use JRoute;
use JText;
use JToolbarHelper;

// Protect from unauthorized access
defined('_JEXEC') or die();

/**
 * Custom toolbar handling.
 *
 * For the views where a custom method does not exist we will simply use the default toolbar per FOF standards.
 *
 * @since       2.0.0
 */
class Toolbar extends BaseToolbar
{
	/**
	 * Render the toolbar for view=Convert
	 *
	 * @since   2.0.0
	 */
	public function onConverts()
	{
		JToolbarHelper::title(JText::_('COM_LOGINGUARD') . ': <small>' . JText::_('COM_LOGINGUARD_HEAD_CONVERT') . '</small>', 'loginguard');
		JToolbarHelper::back('JTOOLBAR_BACK', JRoute::_('index.php?option=com_loginguard'));
	}

	/**
	 * Render the toolbar for view=Welcome
	 *
	 * @since   2.0.0
	 */
	public function onWelcomes()
	{
		JToolbarHelper::title(JText::_('COM_LOGINGUARD') . ': <small>' . JText::_('COM_LOGINGUARD_HEAD_WELCOME') . '</small>', 'loginguard');
		JToolbarHelper::help('', false, 'https://github.com/akeeba/loginguard/wiki');
		JToolbarHelper::preferences('com_loginguard');

		if ($this->container->platform->authorise('loginguard.userlist', 'com_loginguard'))
		{
			$bar = \JToolBar::getInstance('toolbar');
			$bar->appendButton('Link', 'user', JText::_('COM_LOGINGUARD_HEAD_USERS'), 'index.php?option=com_loginguard&view=Users');
		}

	}

	/**
	 * Render the toolbar for view=Users
	 *
	 * @since   3.1.0
	 */
	public function onUsers()
	{
		JToolbarHelper::title(JText::_('COM_LOGINGUARD') . ': <small>' . JText::_('COM_LOGINGUARD_HEAD_USERS') . '</small>', 'loginguard');
		JToolbarHelper::back('JTOOLBAR_BACK', JRoute::_('index.php?option=com_loginguard'));
		//JToolbarHelper::help('', false, 'https://github.com/akeeba/loginguard/wiki');
	}

	/**
	 * Render the toolbar for view=Methods
	 *
	 * @since   2.0.0
	 */
	public function onMethods()
	{
		JToolbarHelper::title(JText::_('COM_LOGINGUARD') . " <small>" . JText::_('COM_LOGINGUARD_HEAD_LIST_PAGE') . "</small>", 'lock');
	}

	/**
	 * Render the toolbar for view=Method
	 *
	 * @since   2.0.0
	 */
	public function onMethod()
	{
		JToolbarHelper::title(JText::_('COM_LOGINGUARD') . " <small>" . $this->title . "</small>", 'lock');
	}

	public function onCaptives()
	{
		JToolbarHelper::title(JText::_('COM_LOGINGUARD_HEAD_TFA_PAGE'), 'lock');
	}
}
