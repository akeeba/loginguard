<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

// Prevent direct access
defined('_JEXEC') || die;

$js = <<< JS
;; // Defense against broken scripts

akeeba.Loader.add(['akeeba.System'], function(){
    akeeba.System.documentReady(function() {
		document.getElementById('loginguard-u2f-missing').style.display = 'none';        

		if (typeof(window.u2f) == 'undefined')
		{
			document.getElementById('loginguard-u2f-missing').style.display = 'block';
			document.getElementById('loginguard-u2f-controls').style.display = 'none';
		}
    });
});

JS;

Factory::getDocument()->addScriptDeclaration($js);

?>
<div id="loginguard-u2f-missing">
	<div class="alert alert-error">
		<h4>
			<?= Text::_('PLG_LOGINGUARD_U2F_ERR_NOTAVAILABLE_HEAD'); ?>
		</h4>
		<p>
			<?= Text::_('PLG_LOGINGUARD_U2F_ERR_NOTAVAILABLE_BODY'); ?>
		</p>
	</div>
</div>
