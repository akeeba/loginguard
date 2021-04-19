<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use \Joomla\CMS\MVC\View\HtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\ToolbarHelper;

class LoginGuardViewConvert extends HtmlView
{
	/**
	 * Execute and display a template script.
	 *
	 * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
	 *
	 * @return  mixed  A string if successful, otherwise an Error object.
	 */
	function display($tpl = null)
	{
		// Show a title and the component's Options button
		ToolbarHelper::title(Text::_('COM_LOGINGUARD') . ': <small>' . Text::_('COM_LOGINGUARD_HEAD_CONVERT') . '</small>', 'loginguard');

		if ($this->getLayout() != 'done')
		{
			$js = <<< JS
; // Fix broken third party Javascript...
window.addEventListener("DOMContentLoaded", function() {
    document.forms.adminForm.submit();
});

JS;
			$this->document->addScriptDeclaration($js);
		}
		else
		{
			ToolbarHelper::back('JTOOLBAR_BACK', Route::_('index.php?option=com_loginguard'));
		}

		// Display the view
		return parent::display($tpl);
	}
}