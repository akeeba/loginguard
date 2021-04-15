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
use Joomla\CMS\Uri\Uri;

/** @var LoginGuardViewMethods $this */

HTMLHelper::_('bootstrap.tooltip');

/** @var LoginGuardModelMethods $model */
$model = $this->getModel();
?>
<div id="loginguard-methods-list-container">
	<?php foreach($this->methods as $methodName => $method): ?>
        <div class="loginguard-methods-list-method loginguard-methods-list-method-name-<?= htmlentities($method['name'])?> <?= ($this->defaultMethod == $methodName) ? 'loginguard-methods-list-method-default' : ''?> ">
            <img
                    class="loginguard-methods-list-method-image pull-left float-start" src="<?= Uri::root() . $method['image'] ?>"
            >
            <h4 class="loginguard-methods-list-method-title">
	            <?= $method['display'] ?>
				<?php if ($this->defaultMethod == $methodName): ?>
                    <span id="loginguard-methods-list-method-default-tag" class="badge badge-info">
                    <?= Text::_('COM_LOGINGUARD_LBL_LIST_DEFAULTTAG') ?>
                </span>
				<?php endif; ?>
                <span class="hasTooltip icon icon-info-circle glyphicon glyphicon-info-sign pull-right float-end"
                      title="<?= $this->escape($method['shortinfo']) ?>"></span>
            </h4>

            <div class="clearfix"></div>

            <div class="loginguard-methods-list-method-records-container">
				<?php if (count($method['active'])): ?>
                    <div class="loginguard-methods-list-method-records">
						<?php  foreach($method['active'] as $record): ?>
                            <div class="loginguard-methods-list-method-record">

                                <div class="row-fluid">
                                    <div class="loginguard-methods-list-method-record-title-container span10 col-sm-10">
										<?php if ($record->default): ?>
                                            <span id="loginguard-methods-list-method-default-badge-small" class="badge badge-info hasTooltip" title="<?= $this->escape(Text::_('COM_LOGINGUARD_LBL_LIST_DEFAULTTAG')) ?>">
                                            <span class="icon icon-star glyphicon glyphicon-star"></span>
                                        </span>
										<?php endif; ?>
                                        <span class="loginguard-methods-list-method-record-title">
                                            <?= $this->escape($record->title); ?>
                                        </span>
                                    </div>

                                    <div class="span2 col-sm-2 pull-right float-end" style="margin-left: 0">
                                        <a href="<?= Route::_('index.php?option=com_loginguard&task=method.edit&id=' . (int) $record->id . ($this->returnURL ? '&returnurl=' . $this->escape(urlencode($this->returnURL)) : '') . '&user_id=' . $this->user->id)?>"
                                           class="loginguard-methods-list-method-record-edit btn btn-default"
                                        ><span class="icon icon-pencil glyphicon glyphicon-pencil"></span></a>
                                        <br/>
                                    </div>
                                </div>

                                <?php if ($methodName == 'backupcodes'): ?>
                                <div class="loginguard-methods-list-method-backupcodes-preview row-fluid">
                                    <div class="span10 col-sm-10">
                                        <div class="alert alert-info">
                                            <span class="icon icon-info-circle"></span>
	                                        <?= Text::sprintf('COM_LOGINGUARD_LBL_BACKUPCODES_PRINT_PROMPT', Route::_('index.php?option=com_loginguard&task=method.edit&id=' . (int) $record->id . ($this->returnURL ? '&returnurl=' . $this->escape(urlencode($this->returnURL)) : '') . '&user_id=' . $this->user->id)) ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="clearfix"></div>
                                <?php endif; ?>

                                <div class="clearfix"></div>

                                <div class="loginguard-methods-list-method-record-lastused row-fluid">
                                    <div class="span10 col-sm-10">
                                        <span class="loginguard-methods-list-method-record-createdon">
                                            <?= Text::sprintf('COM_LOGINGUARD_LBL_CREATEDON', $model->formatRelative($record->created_on)) ?>
                                        </span>
                                        <span class="loginguard-methods-list-method-record-lastused-date">
                                            <?= Text::sprintf('COM_LOGINGUARD_LBL_LASTUSED', $model->formatRelative($record->last_used)) ?>
                                        </span>
                                    </div>
									<?php if ($method['canDisable']): ?>
                                        <span class="span2 col-sm-2 pull-right float-end" style="margin-left: 0">
                                        <a href="<?= Route::_('index.php?option=com_loginguard&task=method.delete&id=' . (int) $record->id  . ($this->returnURL ? '&returnurl=' . $this->escape(urlencode($this->returnURL)) : '') . '&user_id=' . $this->user->id)?>"
                                           class="loginguard-methods-list-method-record-delete btn btn-danger"
                                        ><span class="icon icon-trash glyphicon glyphicon-trash"></span></a>
                                    </span>
									<?php endif; ?>
                                </div>

                                <div class="clearfix"></div>
                            </div>
						<?php endforeach; ?>
                    </div>
				<?php endif; ?>

				<?php if (empty($method['active']) || $method['allowMultiple']): ?>
                    <div class="loginguard-methods-list-method-addnew-container">
                        <a href="<?= Route::_('index.php?option=com_loginguard&task=method.add&method=' . $this->escape(urlencode($method['name'])) . ($this->returnURL ? '&returnurl=' . $this->escape(urlencode($this->returnURL)) : '') . '&user_id=' . $this->user->id)?>"
                           class="loginguard-methods-list-method-addnew btn btn-primary"
                        >
	                        <?= Text::sprintf('COM_LOGINGUARD_LBL_LIST_ADD_A', $method['display']) ?>
                        </a>
                    </div>
				<?php endif; ?>
            </div>
        </div>
	<?php endforeach; ?>
</div>