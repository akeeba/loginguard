<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2017 Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var LoginGuardViewWelcome $this */

$softwareName          = 'Akeeba LoginGuard';
$minPHPVersion         = '7.2.0';
$class_priority_low    = 'alert alert-info';
$class_priority_medium = 'alert alert-warning';
$class_priority_high   = 'alert alert-danger';

require_once JPATH_COMPONENT_ADMINISTRATOR . '/views/common/tmpl/wrongphp.php';

$label = version_compare(JVERSION, '3.999.999', 'le') ? 'label label-' : 'badge bg-'

?>

<?php if ($this->noMethods || $this->notInstalled || $this->noSystemPlugin): ?>
	<div class="alert alert-error">
	<span class="<?= $label ?>important bg-danger">
		<?= Text::_('COM_LOGINGUARD_STATUS_NOTREADY'); ?>
	</span>
		<?= Text::_('COM_LOGINGUARD_STATUS_NOTREADY_INFO'); ?>
	</div>
<?php elseif ($this->noUserPlugin): ?>
	<div class="alert alert-warning">
	<span class="<?= $label ?>warning">
		<?= Text::_('COM_LOGINGUARD_STATUS_ALMOSTREADY'); ?>
	</span>
		<?= Text::_('COM_LOGINGUARD_STATUS_ALMOSTREADY_INFO'); ?>
	</div>
<?php else: ?>
	<div class="alert alert-success">
	<span class="<?= $label ?>success">
		<?= Text::_('COM_LOGINGUARD_STATUS_READY'); ?>
	</span>
		<?= Text::_('COM_LOGINGUARD_STATUS_READY_INFO'); ?>
	</div>
<?php endif; ?>

<?php if ($this->notInstalled): ?>
	<div class="alert alert-warning">
		<h2>
			<span class="icon icon-power-cord"></span> <span>
			<?= Text::_('COM_LOGINGUARD_ERR_NOPLUGINS_HEAD'); ?>
		</span>
		</h2>
		<p>
			<?= Text::_('COM_LOGINGUARD_ERR_PLUGINS_INFO_COMMON'); ?>
			<?= Text::_('COM_LOGINGUARD_ERR_NOPLUGINS_INFO'); ?>
		</p>
	</div>
<?php elseif ($this->noMethods): ?>
	<div class="alert alert-warning">
		<h2>
			<span class="icon icon-warning-2"></span> <span>
			<?= Text::_('COM_LOGINGUARD_ERR_NOTINSTALLEDPLUGINS_HEAD'); ?>
		</span>
		</h2>
		<p>
			<?= Text::_('COM_LOGINGUARD_ERR_PLUGINS_INFO_COMMON'); ?>
			<?= Text::_('COM_LOGINGUARD_ERR_NOTINSTALLEDPLUGINS_INFO'); ?>
		</p>
	</div>
<?php endif; ?>

<?php if ($this->noSystemPlugin): ?>
	<div class="alert alert-warning">
		<h2>
			<span class="icon icon-warning-2"></span> <span>
			<?= Text::_('COM_LOGINGUARD_ERR_NOSYSTEM_HEAD'); ?>
		</span>
		</h2>
		<p>
			<?= Text::_('COM_LOGINGUARD_ERR_NOSYSTEM_INFO'); ?>
		</p>
	</div>
<?php endif; ?>

<?php if ($this->noUserPlugin): ?>
	<div class="alert alert-warning">
		<h2>
			<span class="icon icon-warning-2"></span> <span>
			<?= Text::_('COM_LOGINGUARD_ERR_NOUSER_HEAD'); ?>
		</span>
		</h2>
		<p>
			<?= Text::_('COM_LOGINGUARD_ERR_NOUSER_INFO'); ?>
		</p>
	</div>
<?php endif; ?>

<?php if ($this->needsMigration && !$this->notInstalled && !$this->noMethods && !$this->noSystemPlugin): ?>
	<div class="alert alert-info">
		<h2>
			<span class="icon icon-lock"></span> <span>
			<?= Text::_('COM_LOGINGUARD_LBL_CONVERT_HEAD'); ?>
		</span>
		</h2>
		<p>
			<?= Text::_('COM_LOGINGUARD_LBL_CONVERT_INFO'); ?>
			<br />
			<a class="btn btn-success btn-large btn-lg"
			   href="<?= Route::_('index.php?option=com_loginguard&task=convert.convert&' . Factory::getApplication()->getSession()->getToken() . '=1') ?>">
				<span class="icon icon-apply"></span>
				<?= Text::_('COM_LOGINGUARD_BTN_CONVERT'); ?>
			</a>
		</p>
	</div>
<?php endif; ?>

<div class="card">
	<div class="card-header">
		<h2>
			<?= Text::_('COM_LOGINGUARD_LBL_MANAGE_HEAD'); ?>
		</h2>
	</div>
	<div class="card-body">
		<p>
			<?= Text::_('COM_LOGINGUARD_LBL_MANAGE_BODY'); ?>
		</p>
		<p>
			<a class="btn btn-primary btn-large btn-lg"
			   href="<?= Route::_('index.php?option=com_loginguard&task=methods.display') ?>">
				<span class="icon icon-lock"></span>
				<?= Text::_('COM_LOGINGUARD_BTN_MANAGE_SELF'); ?>
			</a>
			<a class="btn btn-default btn-outline-secondary" href="<?= Route::_('index.php?option=com_users') ?>">
				<span class="icon icon-users"></span>
				<?= Text::_('COM_LOGINGUARD_BTN_MANAGE_OTHERS'); ?>
			</a>
		</p>
	</div>
</div>