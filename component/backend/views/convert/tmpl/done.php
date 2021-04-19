<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
defined('_JEXEC') or die;

/** @var LoginGuardViewConvert $this */

use Joomla\CMS\Language\Text;
?>
<h3><?= Text::_('COM_LOGINGUARD_CONVERT_DONE_HEAD'); ?></h3>

<div class="alert alert-success">
	<p>
		<?= Text::_('COM_LOGINGUARD_CONVERT_DONE_INFO'); ?>
	</p>
</div>