<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Joomla\CMS\Plugin\PluginHelper;

// Prevent direct access
defined('_JEXEC') or die;

$layoutPath = PluginHelper::getLayoutPath('loginguard', 'u2f', 'error');
include $layoutPath;

?>
<div id="loginguard-u2f-controls">
    <input class="form-control" id="loginguard-method-code" name="code" value="" placeholder="" type="hidden">

    <div class="akeeba-form-group--pull-right">
        <div class="akeeba-form-group--actions">
            <a class="akeeba-btn--primary--large"
               onclick="akeeba.LoginGuard.u2f.setUp();">
                <span class="icon icon-lock glyphicon glyphicon-lock"></span>
				<?php echo JText::_('PLG_LOGINGUARD_U2F_LBL_REGISTERKEY'); ?>
            </a>
        </div>
    </div>
</div>
