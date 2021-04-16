<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2017 Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var  LoginGuardViewMethod $this */

HTMLHelper::_('bootstrap.tooltip', '.hasTooltip');

$cancelURL = Route::_('index.php?option=com_loginguard&task=methods.display&user_id=' . $this->user->id);

if (!empty($this->returnURL))
{
	$cancelURL = $this->escape(base64_decode($this->returnURL));
}

if ($this->record->method != 'backupcodes')
{
	throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
}

?>
<h3>
    <?= Text::_('COM_LOGINGUARD_LBL_BACKUPCODES') ?>
</h3>

<div class="alert alert-info">
	<?= Text::_('COM_LOGINGUARD_LBL_BACKUPCODES_INSTRUCTIONS') ?>
</div>

<table class="table table-striped">
	<?php for ($i = 0; $i < (count($this->backupCodes) / 2); $i++): ?>
        <tr>
            <td>
	            <?php if (!empty($this->backupCodes[2 * $i])): ?>
                &#128273;
		            <?= $this->backupCodes[2 * $i] ?>
                <?php endif; ?>
            </td>
            <td>
                <?php if (!empty($this->backupCodes[1 + 2 * $i])): ?>
                &#128273;
	                <?= $this->backupCodes[1 + 2 * $i] ?>
                <?php endif ;?>
            </td>
        </tr>
	<?php endfor; ?>
</table>

<p>
	<?= Text::_('COM_LOGINGUARD_LBL_BACKUPCODES_RESET_INFO'); ?>
</p>

<a class="btn btn-danger" href="<?= Route::_('index.php?option=com_loginguard&task=method.regenbackupcodes&user_id=' . $this->user->id . (empty($this->returnURL) ? '' : '&returnurl=' . $this->returnURL)) ?>">
	<span class="icon icon-refresh"></span>
	<?= Text::_('COM_LOGINGUARD_LBL_BACKUPCODES_RESET'); ?>
</a>

<a href="<?= $cancelURL ?>"
   class="btn btn-secondary">
    <span class="icon icon-cancel-2"></span>
	<?= Text::_('COM_LOGINGUARD_LBL_EDIT_CANCEL'); ?>
</a>