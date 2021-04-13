<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

// Prevent direct access
defined('_JEXEC') || die;

// Load media
HTMLHelper::_('stylesheet', 'plg_loginguard_smsapi/intlTelInput.min.css', [
	'version'     => 'auto',
	'relative'    => true,
	'detectDebug' => false,
]);
HTMLHelper::_('script', 'plg_loginguard_smsapi/intlTelInput.min.js', [
	'version'     => 'auto',
	'relative'    => true,
	'detectDebug' => false,
], [
	'defer' => true,
]);

$utilsScript = HTMLHelper::_('script', 'plg_loginguard_smsapi/utils.js', [
	'version'     => 'auto',
	'relative'    => true,
	'detectDebug' => false,
	'pathOnly'    => true,
]);

$utilsScript = (is_array($utilsScript) ? array_shift($utilsScript) : $utilsScript) ?: '';

$token     = Factory::getApplication()->getSession()->getFormToken();
$actionURL = Uri::base() . 'index.php?option=com_loginguard&view=Callback&task=callback&method=smsapi&' . $token . '=1';

$container = \FOF40\Container\Container::getInstance('com_loginguard', [
		'tempInstance' => true
]);
$container->platform->addScriptOptions('loginguard.smsapi.utilsScript', $utilsScript);
$container->platform->addScriptOptions('loginguard.smsapi.actionUrl', $actionURL);

$js        = /** @lang JavaScript */
	<<< JS

;; // Defense against broken scripts
var loginguardSmsapiTelinput;

akeeba.Loader.add(['intlTelInput', 'akeeba.System', 'akeeba.Ajax'], function(){
	var input = document.querySelector("#loginGuardSMSAPIPhone");
	loginguardSmsapiTelinput = window.intlTelInput(input, {
	    allowDropdown: true,
		nationalMode: true,
		separateDialCode: true,
		initialCountry: "us",
		placeholderNumberType: "MOBILE",
		utilsScript: akeeba.System.getOptions('loginguard.smsapi.utilsScript', '')
	});
	
	akeeba.System.addEventListener('loginguardSmsapiSendCode', 'click', function(e) {
	   e.preventDefault();
	   
	   var phone = loginguardSmsapiTelinput.getNumber();
	   window.location = akeeba.System.getOptions('loginguard.smsapi.actionUrl', '') 
	    + '&phone=' + encodeURIComponent(phone);
	   
	   return false;
	});
})

JS;

Factory::getApplication()->getDocument()->addScriptDeclaration($js);
?>
<div class="akeeba-form--horizontal" id="loginGuardSMSAPIForm">
    <div class="akeeba-form-group">
        <label for="loginGuardSMSAPIPhone">
			<?= Text::_('PLG_LOGINGUARD_SMSAPI_LBL_PHONE') ?>
        </label>
        <input type="text" name="phone-entry-field" id="loginGuardSMSAPIPhone" value="" class="input-large" />
    </div>
    <div class="akeeba-form-group--pull-right">
        <div class="akeeba-form-group--actions">
            <button type="button" class="akeeba-btn--primary" id="loginguardSmsapiSendCode">
                <span class="icon icon-phone glyphicon glyphicon-phone"></span>
				<?= Text::_('PLG_LOGINGUARD_SMSAPI_LBL_SENDCODEBUTTON'); ?>
            </button>
        </div>
    </div>
</div>
