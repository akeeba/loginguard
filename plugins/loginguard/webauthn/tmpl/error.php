<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
defined('_JEXEC') || die;

use Joomla\CMS\Language\Text;

?>
<div id="loginguard-webauthn-missing" style="margin: 0.5em 0">
	<div class="alert alert-danger">
		<h4>
			<?= Text::_('PLG_LOGINGUARD_WEBAUTHN_ERR_NOTAVAILABLE_HEAD'); ?>
		</h4>
		<p>
			<?= Text::_('PLG_LOGINGUARD_WEBAUTHN_ERR_NOTAVAILABLE_BODY'); ?>
		</p>
	</div>
</div>
