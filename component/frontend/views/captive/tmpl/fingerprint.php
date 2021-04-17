<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
defined('_JEXEC') || die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var LoginGuardViewCaptive $this */

$session = Factory::getApplication()->getSession();

if (!is_null($this->browserId) || !$session->get('com_loginguard.browserIdCodeLoaded', false))
{
	die('Someone is being naughty.');
}

/**
 * We now load the FingerprintJS2 v.2.1.0 and the MurmurHash3 library  from a local file with a stupid name because
 * Firefox blocks anything with "fingerprint" in the name. Moreover when CloudFlare's CDN went down it was impossible to
 * access any site using this fingerprinting code.
 *
 * The magicthingie.min.js file contains the following files:
 *   https://cdnjs.cloudflare.com/ajax/libs/fingerprintjs2/2.1.0/fingerprint2.min.js
 *   https://cdnjs.cloudflare.com/ajax/libs/murmurhash3js/3.0.1/murmurHash3js.js
 */
//
HTMLHelper::_('script', 'com_loginguard/magicthingie.min.js', [
	'version'     => 'auto',
	'relative'    => true,
	'detectDebug' => false,
], ['defer' => true]);
HTMLHelper::_('script', 'com_loginguard/security.min.js', [
	'version'     => 'auto',
	'relative'    => true,
	'detectDebug' => false,
], ['defer' => true]);

$js = <<< JS
; // Fix broken third party Javascript...
window.addEventListener("DOMContentLoaded", function() {
    document.getElementById('loginguard-captive-fingeprint-info').style.display = '';
});

JS;

$this->document->addScriptDeclaration($js);

?>
<div class="well card">
	<div class="card-header">
		<h2>
			<?= Text::_('COM_LOGINGUARD_HEAD_FINGERPRINTING'); ?>
		</h2>
	</div>
	<div class="card-body">
		<p id="loginguard-captive-fingeprint-info" style="display: none">
			<?= Text::_('COM_LOGINGUARD_LBL_FINGERPRINTING_MESSAGE'); ?>
		</p>
		<form action="<?= Route::_('index.php?option=com_loginguard&view=Captive') ?>"
			  id="akeebaLoginguardForm"
			  method="post">
			<?= HTMLHelper::_('form.token') ?>
			<input type="hidden" id="akeebaLoginguardFormBrowserId" name="browserId" value="">

			<noscript>
				<h3>
					<?= Text::_('COM_LOGINGUARD_LBL_FINGERPRINTING_NOSCRIPT_HEAD') ?>
				</h3>
				<p>
					<?= Text::_('COM_LOGINGUARD_LBL_FINGERPRINTING_NOSCRIPT_BODY') ?>
				</p>

				<input type="submit">
			</noscript>
		</form>
	</div>
</div>
