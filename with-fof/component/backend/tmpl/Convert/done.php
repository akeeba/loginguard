<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
use Joomla\CMS\Language\Text;

defined('_JEXEC') || die;

/** @var \Akeeba\LoginGuard\Admin\View\Convert\Html $this */

?>
<div class="akeeba-panel--success">
    <header class="akeeba-block-header">
        <h2>
			<?=Text::_('COM_LOGINGUARD_CONVERT_DONE_HEAD'); ?>
        </h2>
    </header>
    <p>
		<?=Text::_('COM_LOGINGUARD_CONVERT_DONE_INFO'); ?>
    </p>
</div>
