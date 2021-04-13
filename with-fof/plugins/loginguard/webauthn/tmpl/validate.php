<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;

// Prevent direct access
defined('_JEXEC') || die;

$layoutPath = PluginHelper::getLayoutPath('loginguard', 'webauthn', 'error');
include $layoutPath;

?>
<div id="loginguard-u2f-controls">
    <input name="code" value="" id="loginGuardCode" class="form-control input-lg" type="hidden">

    <div class="akeeba-form-group--pull-right" id="loginguard-webauthn-button">
        <div class="akeeba-form-group--actions">
            <a class="akeeba-btn--primary--large" id="plg_loginguard_webauthn_validate_button">
                <span class="icon icon-lock glyphicon glyphicon-lock"></span>
                <?= Text::_('PLG_LOGINGUARD_WEBAUTHN_LBL_VALIDATEKEY'); ?>
            </a>
        </div>
    </div>
</div>
