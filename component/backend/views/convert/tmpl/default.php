<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
defined('_JEXEC') or die;

/** @var LoginGuardViewConvert $this */

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

?>
<h3>
	<?= Text::_('COM_LOGINGUARD_HEAD_CONVERT'); ?>
</h3>

<div class="alert alert-info">
	<span class="icon icon-info glyphicon glyphicon-info-sign"></span>
	<?= Text::_('COM_LOGINGUARD_CONVERT_INFO'); ?>
</div>

<p>
	<?= Text::_('COM_LOGINGUARD_CONVERT_MOREINFO'); ?>
</p>

<form action="<?= \Joomla\CMS\Router\Route::_('index.php?option=com_loginguard&task=convert.convert') ?>"
	  name="adminForm" id="adminForm" method="post">
	<?= HTMLHelper::_('form.token') ?>
	<input type="submit" class="btn btn-primary" value="<?= $this->escape(Text::_('COM_LOGINGUARD_CONVERT_BUTTON')) ?>">
</form>