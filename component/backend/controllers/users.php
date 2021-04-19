<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\AdminController;

/**
 * Users list controller class.
 *
 * @since  5.0.0
 */
class LoginGuardControllerUsers extends AdminController
{
	/**
	 * @var    string  The prefix to use with controller messages.
	 * @since  5.0.0
	 */
	protected $text_prefix = 'COM_USERS_USERS';

	/**
	 * Constructor.
	 *
	 * @param   array  $config  An optional associative array of configuration settings.
	 *
	 * @throws Exception
	 * @since   5.0.0
	 */
	public function __construct($config = [])
	{
		parent::__construct($config);

		// Load com_users' language since we reuse those strings here.
		$jLang = Factory::getApplication()->getLanguage();
		$jLang->load('com_users');
	}

	/**
	 * Proxy for getModel.
	 *
	 * @param   string  $name    The model name. Optional.
	 * @param   string  $prefix  The class prefix. Optional.
	 * @param   array   $config  Configuration array for model. Optional.
	 *
	 * @return  object  The model.
	 *
	 * @since   5.0.0
	 */
	public function getModel($name = 'Users', $prefix = 'LoginGuardModel', $config = ['ignore_request' => true])
	{
		return parent::getModel($name, $prefix, $config);
	}
}
