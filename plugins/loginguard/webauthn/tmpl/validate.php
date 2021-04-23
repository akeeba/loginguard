<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
defined('_JEXEC') || die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;

$layoutPath = PluginHelper::getLayoutPath('loginguard', 'webauthn', 'error');
include $layoutPath;

?>
<div id="loginguard-webauthn-controls">
    <input name="code" value="" id="loginGuardCode" class="form-control input-lg" type="hidden">

	<a class="btn btn-primary btn-lg btn-big" id="plg_loginguard_webauthn_validate_button">
		<span class="icon icon-lock glyphicon glyphicon-lock"></span>
		<?= Text::_('PLG_LOGINGUARD_WEBAUTHN_LBL_VALIDATEKEY'); ?>
	</a>
</div>
