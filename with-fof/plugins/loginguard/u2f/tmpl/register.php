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

include PluginHelper::getLayoutPath('loginguard', 'u2f', 'error');

?>
<div id="loginguard-u2f-controls">
    <input class="form-control" id="loginguard-method-code" name="code" value="" placeholder="" type="hidden">

    <div class="akeeba-form-group--pull-right">
        <div class="akeeba-form-group--actions">
            <a class="akeeba-btn--primary--large loginguard_u2f_setup">
                <span class="icon icon-lock glyphicon glyphicon-lock"></span>
				<?= Text::_('PLG_LOGINGUARD_U2F_LBL_REGISTERKEY'); ?>
            </a>
        </div>
    </div>
</div>
