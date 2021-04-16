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
    <input id="loginguard-method-code" name="code" value="" placeholder="" type="hidden">

	<a class="btn btn-primary btn-lg btn-big" id="plg_loginguard_webauthn_register_button">
		<span class="icon icon-lock glyphicon glyphicon-lock"></span>
		<?= Text::_('PLG_LOGINGUARD_WEBAUTHN_LBL_REGISTERKEY'); ?>
	</a>
</div>
