<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') || die;

if (!class_exists('LoginGuardHelperMixinTriggerEvent', true))
{
	JLoader::register('LoginGuardHelperMixinTriggerEvent',
		__DIR__ . '/triggerevent.php');
}

trait LoginGuardHelperMixinTaskBasedViewEvents
{
	use LoginGuardHelperMixinTriggerEvent;

	public function display($tpl = null)
	{
		/** @var \Joomla\CMS\MVC\View\HtmlView $this */

		$task = $this->getModel()->getState('task');

		$eventName = 'onBefore' . ucfirst($task);
		$this->triggerEvent($eventName, [&$tpl]);

		parent::display($tpl);

		$eventName = 'onAfter' . ucfirst($task);
		$this->triggerEvent($eventName, [&$tpl]);
	}
}