<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Akeeba\LoginGuard\Site\Model\Methods;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

// Prevent direct access
defined('_JEXEC') || die;

/** @var \Akeeba\LoginGuard\Site\View\Methods\Html $this */

HTMLHelper::_('bootstrap.tooltip');

/** @var Methods $model */
$model = $this->getModel();
$token = $this->getContainer()->platform->getToken();

?>
<div id="loginguard-methods-list-container" class="akeeba-panel--primary">
	<?php foreach($this->methods as $methodName => $method): ?>
        <div class="loginguard-methods-list-method loginguard-methods-list-method-name-<?= htmlentities($method['name'])?> <?= ($this->defaultMethod == $methodName) ? 'loginguard-methods-list-method-default' : ''?> ">
            <h4 class="loginguard-methods-list-method-title akeeba-container--75-25">
                <span>
		            <?= $method['display'] ?>
	                <?php if ($this->defaultMethod == $methodName): ?>
                        <sup>
                            <small>
                                <span id="loginguard-methods-list-method-default-tag" class="akeeba-label--green--small">
                                <?= Text::_('COM_LOGINGUARD_LBL_LIST_DEFAULTTAG') ?>
                                </span>
                            </small>
                        </sup>
	                <?php endif; ?>
                </span>
                <span class="loginguard-methods-list-method-info">
                    <span class="hasTooltip akion-ios-information"
                          title="<?= $this->escape($method['shortinfo']) ?>"></span>
                </span>
            </h4>

            <div class="akeeba-container--33-66">
                <div>
                    <img class="loginguard-methods-list-method-image pull-left"
                         src="<?= Uri::root() . $method['image'] ?>">
                </div>
                <div class="loginguard-methods-list-method-records-container">
		            <?php if (is_array($method['active']) || $method['active'] instanceof \Countable ? count($method['active']) : 0): ?>
                        <div class="loginguard-methods-list-method-records">
				            <?php  foreach($method['active'] as $record): ?>
                                <div class="loginguard-methods-list-method-record">

                                    <div class="akeeba-container--75-25">
                                        <div class="loginguard-methods-list-method-record-title-container">
                                            <h5 class="loginguard-methods-list-method-record-title">
	                                            <?php if ($record->default): ?>
                                                <small>
                                                    <span id="loginguard-methods-list-method-default-badge-small" class="akeeba-label--small--green hasTooltip" title="<?= $this->escape(Text::_('COM_LOGINGUARD_LBL_LIST_DEFAULTTAG')) ?>">
                                                        <span class="akion-ios-star"></span>
                                                    </span>
                                                </small>
	                                            <?php endif; ?>
                                                <?= $this->escape($record->title); ?>
                                            </h5>
                                        </div>

                                        <div class="loginguard-methods-list-method-record-edit-container">
                                            <a href="<?= Route::_('index.php?option=com_loginguard&view=Method&task=edit&id=' . (int) $record->id . ($this->returnURL ? '&returnurl=' . $this->escape(urlencode($this->returnURL)) : '') . '&user_id=' . $this->user->id)?>"
                                               class="loginguard-methods-list-method-record-edit akeeba-btn--teal--small"
                                            >
                                                <span class="akion-edit"></span>
                                            </a>

                                        </div>
                                    </div>

                                    <div class="loginguard-methods-list-method-record-lastused akeeba-container--75-25">
                                        <div>
	                                        <?php if ($methodName == 'backupcodes'): ?>
                                                <div class="loginguard-methods-list-method-backupcodes-preview akeeba-block--info">
                                                    <?= Text::sprintf('COM_LOGINGUARD_LBL_BACKUPCODES_PRINT_PROMPT', Route::_('index.php?option=com_loginguard&view=Method&task=edit&id=' . (int) $record->id . ($this->returnURL ? '&returnurl=' . $this->escape(urlencode($this->returnURL)) : '') . '&user_id=' . $this->user->id)) ?>
                                                </div>
	                                        <?php endif; ?>
                                            <div>
                                                <span class="loginguard-methods-list-method-record-createdon">
                                                    <?= Text::sprintf('COM_LOGINGUARD_LBL_CREATEDON', $model->formatRelative($record->created_on)) ?>
                                                </span>
                                                <span class="loginguard-methods-list-method-record-lastused-date">
                                                    <?= Text::sprintf('COM_LOGINGUARD_LBL_LASTUSED', $model->formatRelative($record->last_used)) ?>
                                                </span>
                                            </div>
                                        </div>

                                        <div class="loginguard-methods-list-method-record-delete-container">
	                                        <?php if ($method['canDisable']): ?>
                                                <a href="<?= Route::_('index.php?option=com_loginguard&view=Method&task=delete&id=' . (int) $record->id  . ($this->returnURL ? '&returnurl=' . $this->escape(urlencode($this->returnURL)) : '') . '&user_id=' . $this->user->id . '&' . $token . '=1')?>"
                                                   class="loginguard-methods-list-method-record-delete akeeba-btn--red--small"
                                                >
                                                    <span class="akion-trash-b"></span>
                                                </a>
	                                        <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
				            <?php endforeach; ?>
                        </div>
		            <?php endif; ?>

		            <?php if (empty($method['active']) || $method['allowMultiple']): ?>
                        <div class="loginguard-methods-list-method-addnew-container">
                            <a href="<?= Route::_('index.php?option=com_loginguard&view=Method&task=add&method=' . $this->escape(urlencode($method['name'])) . ($this->returnURL ? '&returnurl=' . $this->escape(urlencode($this->returnURL)) : '') . '&user_id=' . $this->user->id)?>"
                               class="loginguard-methods-list-method-addnew akeeba-btn--grey"
                            >
                                <span class="akion-android-add-circle"></span>
					            <?= Text::sprintf('COM_LOGINGUARD_LBL_LIST_ADD_A', $method['display']) ?>
                            </a>
                        </div>
		            <?php endif; ?>
                </div>
            </div>


        </div>
	<?php endforeach; ?>
</div>
