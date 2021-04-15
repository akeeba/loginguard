<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController as BaseControllerAlias;
use Joomla\CMS\Router\Route;

class LoginGuardController extends BaseControllerAlias
{
	/**
	 * Method to display a view.
	 *
	 * @param   boolean  $cachable   If true, the view output will be cached.
	 * @param   boolean  $urlparams  An array of safe url parameters and their variable types, for valid values see
	 *                               {@link JFilterInput::clean()}.
	 *
	 * @return  JControllerLegacy  This object to support chaining.
	 */
	public function display($cachable = false, $urlparams = false)
	{
		$this->setRedirect(Route::_('index.php?option=com_loginguard&task=methods.display', false));

		// If you're a super user you get to see the Welcome page instead
		if (Factory::getUser()->authorise('core.admin'))
		{
			$this->setRedirect(Route::_('index.php?option=com_loginguard&view=welcome', false));
		}

		return $this;
	}

}