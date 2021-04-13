<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\LoginGuard\Admin\View\Welcome;

use Akeeba\LoginGuard\Admin\Model\Welcome;
use FOF40\View\DataView\Html as BaseView;

// Protect from unauthorized access
defined('_JEXEC') || die();

class Html extends BaseView
{
	/**
	 * Is the user plugin missing / disabled?
	 *
	 * @var   bool
	 * @since 1.0.0
	 */
	public $noUserPlugin = false;

	/**
	 * Is the system plugin missing / disabled?
	 *
	 * @var   bool
	 * @since 1.0.0
	 */
	public $noSystemPlugin = false;

	/**
	 * Are no published methods detected?
	 *
	 * @var   bool
	 * @since 1.0.0
	 */
	public $noMethods = false;

	/**
	 * Are no loginguard plugins installed?
	 *
	 * @var   bool
	 * @since 1.0.0
	 */
	public $notInstalled = false;

	/**
	 * Do we have to migrate from Joomla's Two Factor Authentication?
	 *
	 * @var   bool
	 * @since 1.0.0
	 */
	public $needsMigration = false;

	public function onBeforeWelcome()
	{
		/** @var Welcome $model */
		$model = $this->getModel();

		$this->noMethods         = !$model->hasPublishedPlugins();
		$this->notInstalled      = !$model->hasInstalledPlugins();
		$this->noUserPlugin      = !$model->isLoginGuardPluginPublished('user');
		$this->noSystemPlugin    = !$model->isLoginGuardPluginPublished('system');
		$this->needsMigration    = $model->needsMigration();
	}
}
