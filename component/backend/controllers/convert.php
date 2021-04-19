<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\User\User;

class LoginGuardControllerConvert extends BaseController
{
	public function __construct(array $config = [])
	{
		parent::__construct($config);

		$this->registerDefaultTask('convert');
	}

	public function convert($cachable = false, $urlparams = false)
	{
		$this->assertSuperUser();

		// Set up the Model and View
		$model = $this->getConvertModel();
		$view  = $this->getConvertView();

		$view->setModel($model, true);

		// Perform the conversion
		$result = $model->convert();

		// Set the correct layout depending on what happened to the conversion
		$view->setLayout('default');

		if (!$result)
		{
			$view->setLayout('done');
			$model->disableTFA();
		}

		// Render the view
		$view->display();

		return $this;
	}

	/**
	 * Get a reference to the model which performs the conversion from TFA to TSV
	 *
	 * @return  LoginGuardModelConvert
	 */
	protected function getConvertModel()
	{
		/** @var LoginGuardModelConvert $model */
		$model = $this->getModel('Convert');

		return $model;
	}

	/**
	 * Get a reference to the view object for this view
	 *
	 * @return LoginGuardViewConvert
	 */
	protected function getConvertView()
	{
		// Get the view object
		$document   = Factory::getApplication()->getDocument();
		$viewLayout = $this->input->get('layout', 'default', 'string');
		/** @var LoginGuardViewConvert $view */
		$view = $this->getView('convert', 'html', '', [
			'base_path' => $this->basePath,
			'layout'    => $viewLayout,
		]);

		$view->document = $document;

		return $view;
	}

	/**
	 * Assert that the user is a Super User
	 *
	 * @param   User|null  $user  The user to assert. Null to use the currently logged in user
	 *
	 * @return  void
	 * @throws  Exception
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

		throw new RuntimeException(JText::_('JERROR_ALERTNOAUTHOR'), 403);
	}
}