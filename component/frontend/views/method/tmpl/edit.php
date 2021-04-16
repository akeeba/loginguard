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

/** @var  LoginGuardViewMethod  $this */

HTMLHelper::_('bootstrap.tooltip', '.hasTooltip');

$cancelURL = Route::_('index.php?option=com_loginguard&task=methods.display&user_id=' . $this->user->id);

if (!empty($this->returnURL))
{
	$cancelURL = $this->escape(base64_decode($this->returnURL));
}

$recordId = (int) $this->record->id ?? 0;
$method = $this->record->method ?? $this->getModel()->getState('method');
$userId = (int) $this->user->id ?? 0;
?>
<form action="<?= Route::_(sprintf("index.php?option=com_loginguard&task=method.save&id=%d&method=%s&user_id=%d", $recordId, $method, $userId)) ?>"
	  class="form" id="loginguard-method-edit" method="post">
	<?= HTMLHelper::_('form.token') ?>
	<?php if (!empty($this->returnURL)): ?>
	<input type="hidden" name="returnurl" value="<?= $this->escape($this->returnURL) ?>">
	<?php endif; ?>

	<?php if (!empty($this->renderOptions['hidden_data'])): ?>
	<?php foreach ($this->renderOptions['hidden_data'] as $key => $value): ?>
	<input type="hidden" name="<?= $this->escape($key) ?>" value="<?= $this->escape($value) ?>">
	<?php endforeach; ?>
	<?php endif; ?>

	<?php if (!empty($this->title)): ?>
    <?php if (!empty($this->renderOptions['help_url'])): ?>
    <span class="pull-right float-end">
        <a href="<?= $this->renderOptions['help_url'] ?>"
           class="btn btn-sm btn-small btn-default btn-inverse btn-dark"
           target="_blank"
        >
            <span class="icon icon-question-sign glyphicon glyphicon-question-sign"></span>
        </a>

    </span>
    <?php endif;?>
	<h3 id="loginguard-method-edit-head">
		<?= Text::_($this->title) ?>
	</h3>
	<?php endif; ?>

	<div class="control-group form-group">
		<label class="control-label hasTooltip" for="loginguard-method-edit-title"
			title="<?= $this->escape(Text::_('COM_LOGINGUARD_LBL_EDIT_FIELD_TITLE_DESC')) ?>">
			<?= Text::_('COM_LOGINGUARD_LBL_EDIT_FIELD_TITLE'); ?>
		</label>
		<div class="controls">
			<input type="text" class="form-control input-xxlarge" id="loginguard-method-edit-title"
			       name="title"
			       value="<?= $this->escape($this->record->title) ?>"
			       placeholder="<?= Text::_('COM_LOGINGUARD_LBL_EDIT_FIELD_TITLE_DESC') ?>">
		</div>
	</div>

    <div class="control-group form-group">
        <div class="controls">
            <label class="control-label hasTooltip"
            title="<?= $this->escape(Text::_('COM_LOGINGUARD_LBL_EDIT_FIELD_DEFAULT_DESC')); ?>">
                <input type="checkbox" <?= $this->record->default ? 'checked="checked"' : ''; ?> name="default">
	            <?= Text::_('COM_LOGINGUARD_LBL_EDIT_FIELD_DEFAULT'); ?>
            </label>
        </div>
    </div>

	<?php if (!empty($this->renderOptions['pre_message'])): ?>
	<div class="loginguard-method-edit-pre-message">
		<?= $this->renderOptions['pre_message'] ?>
	</div>
	<?php endif; ?>

	<?php if (!empty($this->renderOptions['tabular_data'])): ?>
	<div class="loginguard-method-edit-tabular-container">
		<?php if (!empty($this->renderOptions['table_heading'])): ?>
		<h4>
			<?= $this->renderOptions['table_heading'] ?>
		</h4>
		<?php endif; ?>
		<table class="table table-striped">
			<tbody>
			<?php foreach ($this->renderOptions['tabular_data'] as $cell1 => $cell2): ?>
			<tr>
				<td>
					<?= $cell1 ?>
				</td>
				<td>
					<?= $cell2 ?>
				</td>
			</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php endif; ?>

	<?php if ($this->renderOptions['field_type'] == 'custom'): ?>
		<?= $this->renderOptions['html']; ?>
	<?php else: ?>
	<div class="control-group form-group">
		<?php if ($this->renderOptions['label']): ?>
		<label class="control-label hasTooltip" for="loginguard-method-edit-code">
			<?= $this->renderOptions['label']; ?>
		</label>
		<?php endif; ?>
		<div class="controls">
			<input type="<?= $this->renderOptions['input_type']; ?>"
			       class="form-control" id="loginguard-method-code"
			       name="code"
			       value="<?= $this->escape($this->renderOptions['input_value']) ?>"
			       placeholder="<?= $this->escape($this->renderOptions['placeholder']) ?>">
		</div>
	</div>
	<?php endif; ?>

	<div class="control-group">
		<div class="controls">
			<?php if ($this->renderOptions['show_submit'] || $this->isEditExisting): ?>
			<button type="submit" class="btn btn-primary"
				<?= $this->renderOptions['submit_onclick'] ? "onclick=\"{$this->renderOptions['submit_onclick']}\"" : '' ?>>
				<span class="icon icon-ok glyphicon glyphicon-ok-circle"></span>
				<?= Text::_('COM_LOGINGUARD_LBL_EDIT_SUBMIT'); ?>
			</button>
			<?php endif; ?>

			<a href="<?= $cancelURL ?>"
			   class="btn btn-small btn-sm btn-danger">
				<span class="icon icon-cancel-2 glyphicon glyphicon-cancel-2"></span>
				<?= Text::_('COM_LOGINGUARD_LBL_EDIT_CANCEL'); ?>
			</a>
		</div>
	</div>

	<?php if (!empty($this->renderOptions['post_message'])): ?>
		<div class="loginguard-method-edit-post-message">
			<?= $this->renderOptions['post_message'] ?>
		</div>
	<?php endif; ?>
</form>