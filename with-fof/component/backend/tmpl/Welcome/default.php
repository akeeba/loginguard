<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

// Prevent direct access
defined('_JEXEC') || die;

/** @var \Akeeba\LoginGuard\Admin\View\Welcome\Html $this */

?>
<?php
// Obsolete PHP version check
echo $this->loadAnyTemplate('admin:com_loginguard/Welcome/phpversion_warning', [
	'softwareName'  => 'Akeeba LoginGuard',
	'minPHPVersion' => '7.2.0',
]);
?>

<?php if ($this->noMethods || $this->notInstalled || $this->noSystemPlugin): ?>
<div class="akeeba-block--failure">
	<span class="akeeba-label--failure"><?= Text::_('COM_LOGINGUARD_STATUS_NOTREADY'); ?></span>
	<?= Text::_('COM_LOGINGUARD_STATUS_NOTREADY_INFO'); ?>
</div>
<?php elseif ($this->noUserPlugin): ?>
<div class="akeeba-block--warning">
	<span class="akeeba-label--warning"><?= Text::_('COM_LOGINGUARD_STATUS_ALMOSTREADY'); ?></span>
	<?= Text::_('COM_LOGINGUARD_STATUS_ALMOSTREADY_INFO'); ?>
</div>
<?php else: ?>
<div class="akeeba-block--success">
	<span class="akeeba-label--success"><?= Text::_('COM_LOGINGUARD_STATUS_READY'); ?></span>
	<?= Text::_('COM_LOGINGUARD_STATUS_READY_INFO'); ?>
</div>
<?php endif; ?>

<?php if ($this->notInstalled): ?>
<div class="akeeba-panel--danger">
    <header class="akeeba-block-header">
        <h3>
            <span class="akion-power"></span>
            <span>
                <?= Text::_('COM_LOGINGUARD_ERR_NOPLUGINS_HEAD'); ?>
            </span>
        </h3>
    </header>
    <p>
        <?= Text::_('COM_LOGINGUARD_ERR_PLUGINS_INFO_COMMON'); ?>
        <?= Text::_('COM_LOGINGUARD_ERR_NOPLUGINS_INFO'); ?>
    </p>
</div>
<?php elseif ($this->noMethods): ?>
<div class="akeeba-panel--danger">
    <header class="akeeba-block-header">
        <h3>
            <span class="akion-android-warning"></span>
            <span>
                <?= Text::_('COM_LOGINGUARD_ERR_NOTINSTALLEDPLUGINS_HEAD'); ?>
            </span>
        </h3>
    </header>
    <p>
	    <?= Text::_('COM_LOGINGUARD_ERR_PLUGINS_INFO_COMMON'); ?>
	    <?= Text::_('COM_LOGINGUARD_ERR_NOTINSTALLEDPLUGINS_INFO'); ?>
    </p>
</div>
<?php endif; ?>
<?php if ($this->noSystemPlugin): ?>
<div class="akeeba-panel--warning">
    <header class="akeeba-block-header">
        <h3>
            <span class="akion-android-warning"></span>
            <span>
            <?= Text::_('COM_LOGINGUARD_ERR_NOSYSTEM_HEAD'); ?>
        </span>
        </h3>
    </header>
    <p>
        <?= Text::_('COM_LOGINGUARD_ERR_NOSYSTEM_INFO'); ?>
    </p>
</div>
<?php endif; ?>

<?php if ($this->noUserPlugin): ?>
<div class="akeeba-panel--warning">
    <header class="akeeba-block-header">
        <h3>
            <span class="akion-android-warning"></span>
            <span>
        <?= Text::_('COM_LOGINGUARD_ERR_NOUSER_HEAD'); ?>
    </span>
        </h3>
    </header>
    <p>
        <?= Text::_('COM_LOGINGUARD_ERR_NOUSER_INFO'); ?>
    </p>
</div>
<?php endif; ?>

<?php if ($this->needsMigration && !$this->notInstalled && !$this->noMethods && !$this->noSystemPlugin): ?>
<div class="akeeba-panel--info">
    <header class="akeeba-block-header">
        <h3>
            <span class="akion-android-lock"></span>
            <span>
                <?= Text::_('COM_LOGINGUARD_LBL_CONVERT_HEAD'); ?>
            </span>
        </h3>
    </header>
    <p>
		<?= Text::_('COM_LOGINGUARD_LBL_CONVERT_INFO'); ?>
    </p>
    <p>
        <a href="<?=Route::_('index.php?option=com_loginguard&view=Convert&task=convert&' . $this->getContainer()->platform->getToken() . '=1')?>"
           class="akeeba-btn--success--large">
            <span class="akion-play"></span>
		    <?= Text::_('COM_LOGINGUARD_BTN_CONVERT'); ?>
        </a>
    </p>
</div>
<?php endif; ?>

<div class="akeeba-panel--primary">
    <header class="akeeba-block-header">
        <h2>
            <?= Text::_('COM_LOGINGUARD_LBL_MANAGE_HEAD'); ?>
        </h2>
    </header>
    <p>
        <?= Text::_('COM_LOGINGUARD_LBL_MANAGE_BODY'); ?>
    </p>
    <p>
        <a href="<?=Route::_('index.php?option=com_loginguard&view=Methods') ?>" class="akeeba-btn--primary--large">
            <span class="akion-android-lock"></span>
            <?= Text::_('COM_LOGINGUARD_BTN_MANAGE_SELF'); ?>
        </a>
        <a href="<?=Route::_('index.php?option=com_users') ?>" class="akeeba-btn--ghost--small">
            <span class="akion-person-stalker"></span>
            <?= Text::_('COM_LOGINGUARD_BTN_MANAGE_OTHERS'); ?>
        </a>
    </p>
</div>
