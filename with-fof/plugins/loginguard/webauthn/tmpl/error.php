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

?>
<div id="loginguard-webauthn-missing">
	<div class="alert alert-error">
		<h4>
			<?= Text::_('PLG_LOGINGUARD_WEBAUTHN_ERR_NOTAVAILABLE_HEAD'); ?>
		</h4>
		<p>
			<?= Text::_('PLG_LOGINGUARD_WEBAUTHN_ERR_NOTAVAILABLE_BODY'); ?>
		</p>
	</div>
</div>
