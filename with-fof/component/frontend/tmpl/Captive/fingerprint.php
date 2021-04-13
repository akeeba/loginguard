<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
defined('_JEXEC') || die;

/** @var \Akeeba\LoginGuard\Site\View\Captive\Html $this */

if (!is_null($this->browserId) || !$this->container->session->get('browserIdCodeLoaded', false, 'com_loginguard'))
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
$this->addJavascriptFile('media://com_loginguard/js/magicthingie.min.js', null, 'text/javascript', true, false);
$this->addJavascriptFile('media://com_loginguard/js/security.js', null, 'text/javascript', true, false);

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route; ?>
<div class="akeeba-panel--info">
	<header class="akeeba-block-header">
		<h2>
			<?= Text::_('COM_LOGINGUARD_HEAD_FINGERPRINTING'); ?>
		</h2>
	</header>
	<p id="loginguard-captive-fingeprint-info" style="display: none">
		<?= Text::_('COM_LOGINGUARD_LBL_FINGERPRINTING_MESSAGE'); ?>
	</p>

	<script type="text/javascript">
		document.getElementById('loginguard-captive-fingeprint-info').style.display = 'block';
	</script>

	<form id="akeebaLoginguardForm" method="post" action="<?= Route::_('index.php?option=com_loginguard&view=Captive') ?>">
		<input type="hidden" name="<?= $this->container->platform->getToken(true) ?>" value="1">
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
