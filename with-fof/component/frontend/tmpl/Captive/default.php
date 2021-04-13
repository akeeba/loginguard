<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

// Prevent direct access
defined('_JEXEC') || die;

/** @var \Akeeba\LoginGuard\Site\View\Captive\Html $this */
/** @var \Akeeba\LoginGuard\Site\Model\Captive $model */

$model           = $this->getModel();
$allowRememberMe = $this->container->params->get('allow_rememberme', 1);
?>
<div class="loginguard-captive akeeba-panel--info">
    <header class="akeeba-block-header">
        <h3 id="loginguard-title">
            <span>
                <?php if (!empty($this->title)): ?>
                    <?= $this->title ?> <small> &ndash;
                <?php endif; ?>
                <?php if (!$this->allowEntryBatching): ?>
                    <?= $this->escape($this->record->title) ?>
                <?php else: ?>
                    <?= $this->escape($model->translateMethodName($this->record->method)) ?>
                <?php endif; ?>
                <?php if (!empty($this->title)): ?>
                </small>
                <?php endif; ?>
            </span>
            <span class="loginguard-captive-head-help">
                <?php if (!empty($this->renderOptions['help_url'])): ?>
                <a href="<?= $this->renderOptions['help_url'] ?>"
                   class="akeeba-btn--dark--mini" target="_blank"
                >
                    <span class="akion-ios-information"></span>
                </a>
                <?php endif;?>
            </span>
        </h3>
    </header>

	<?php if ($this->renderOptions['pre_message']): ?>
        <div class="loginguard-captive-pre-message akeeba-block--info">
			<?= $this->renderOptions['pre_message'] ?>
        </div>
	<?php endif; ?>

    <form action="<?= Uri::base() ?>index.php" method="POST" id="loginguard-captive-form" class="akeeba-form--horizontal">
	    <div id="loginguard-captive-form-method-fields">
		    <?php if ($this->renderOptions['field_type'] == 'custom'): ?>
			    <?= $this->renderOptions['html']; ?>
		    <?php else:
                $js = <<< JS
; // Fix broken third party Javascript...
window.addEventListener("DOMContentLoaded", function() {
    document.getElementById('loginGuardCode').focus();
});

JS;
		        $this->addJavascriptInline($js);

            ?>
                <div class="akeeba-form-group">
                    <label for="loginGuardCode" <?= $this->renderOptions['label'] ? '' : 'class="hidden" aria-hidden="true"'?>>
					    <?= $this->renderOptions['label'] ?>
                    </label>
                    <input type="<?= $this->renderOptions['input_type'] ?>"
                           name="code"
                           value=""
					        <?php if (!empty($this->renderOptions['placeholder'])): ?>
                            placeholder="<?= $this->renderOptions['placeholder'] ?>"
					        <?php endif; ?>
                           id="loginGuardCode"
                           class="form-control input-large"
                    >
                </div>
		    <?php endif;?>

			<?php if (!empty($this->browserId) && $allowRememberMe): ?>
			<div id="loginguard-captive-form-remember-me" class="akeeba-form-group">
				<label for="loginguard-rememberme-yes">
					<?= Text::_('JGLOBAL_REMEMBER_ME') ?>
				</label>
				<div class="akeeba-toggle" id="loginguard-rememberme-container">
					<input id="loginguard-rememberme-yes" type="radio" name="rememberme" value="1" checked />
					<label for="loginguard-rememberme-yes" class="green"><?= Text::_('JYES') ?></label>
					<input id="loginguard-rememberme-no" type="radio" name="rememberme" value="0" />
					<label for="loginguard-rememberme-no" class="red"><?= Text::_('JNO') ?></label>
				</div>
			</div>
			<?php endif; ?>
        </div>

        <div id="loginguard-captive-form-standard-buttons" class="akeeba-form-group--pull-right">
            <div class="akeeba-form-group--actions">
                <button type="submit" class="akeeba-btn--large--primary" id="loginguard-captive-button-submit"
                        style="<?= $this->renderOptions['hide_submit'] ? 'display: none' : '' ?>">
                    <span class="akion-chevron-right"></span>
		            <?= Text::_('COM_LOGINGUARD_LBL_VALIDATE'); ?>
                </button>

	            <?php if ($this->isAdmin): ?>
                <a href="<?= Route::_('index.php?option=com_login&task=logout&' . $this->getContainer()->platform->getToken(true) . '=1') ?>"
                   class="akeeba-btn--red" id="loginguard-captive-button-logout">
                    <span class="akion-power"></span>
                    <?= Text::_('COM_LOGINGUARD_LBL_LOGOUT'); ?>
                </a>
	            <?php else: ?>
                <a href="<?= Route::_('index.php?option=com_users&task=user.logout&' . $this->getContainer()->platform->getToken(true) . '=1') ?>"
                   class="akeeba-btn--red" id="loginguard-captive-button-logout">
                    <span class="akion-ios-locked"></span>
                    <?= Text::_('COM_LOGINGUARD_LBL_LOGOUT'); ?>
                </a>
	            <?php endif; ?>
            </div>
        </div>

	    <?php if ((is_array($this->records) || $this->records instanceof \Countable ? count($this->records) : 0) > 1): ?>
            <div id="loginguard-captive-form-choose-another" class="akeeba-form-group--pull-right">
                <a href="<?= Route::_('index.php?option=com_loginguard&view=Captive&task=select') ?>">
				    <?= Text::_('COM_LOGINGUARD_LBL_USEDIFFERENTMETHOD'); ?>
                </a>
            </div>
	    <?php endif; ?>


        <?php if (!empty($this->renderOptions['post_message'])): ?>
            <div class="loginguard-method-edit-post-message akeeba-panel--info">
			    <?= $this->renderOptions['post_message'] ?>
            </div>
	    <?php endif; ?>

        <div>
            <input type="hidden" name="option" value="com_loginguard">
            <input type="hidden" name="view" value="Captive">
            <input type="hidden" name="task" value="validate">
            <input type="hidden" name="record_id" value="<?= $this->record->id ?>">
            <input type="hidden" name="<?= $this->getContainer()->platform->getToken() ?>" value="1">
        </div>
    </form>

</div>
