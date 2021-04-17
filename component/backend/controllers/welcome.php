<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\User\User;

class LoginGuardControllerWelcome extends BaseController
{
	public function __construct(array $config = array())
	{
		parent::__construct($config);

		$this->registerDefaultTask('welcome');
	}

	public function welcome($cachable = false, $urlparams = false)
	{
		$this->assertSuperUser();

		return $this->display(false, false);
	}

	/**
	 * Assert that the user is a Super User
	 *
	 * @param   User|null  $user  The user to assert. Null to use the currently logged in user
	 *
	 * @return  void
	 */
	protected function assertSuperUser(User $user = null)
	{
		if (empty($user))
		{
			$user = Factory::getApplication()->getIdentity() ?: Factory::getUser();
		}

		if ($user->authorise('core.admin'))
		{
			return;
		}

		throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
	}
}