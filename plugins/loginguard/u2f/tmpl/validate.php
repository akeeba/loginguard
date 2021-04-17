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

$layoutPath = PluginHelper::getLayoutPath('loginguard', 'u2f', 'error');
include $layoutPath;

?>
<div id="loginguard-u2f-controls">
    <input name="code" value="" id="loginGuardCode" class="form-control input-lg" type="hidden">

	<a class="btn btn-primary btn-lg btn-big"
	   id="loginguard-captive-button-submit">
		<span class="icon icon-lock glyphicon glyphicon-lock"></span>
		<?= Text::_('PLG_LOGINGUARD_U2F_LBL_VALIDATEKEY'); ?>
	</a>
</div>
