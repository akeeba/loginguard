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
<div class="akeeba-block--info">
	<?=Text::_('COM_LOGINGUARD_CONVERT_INFO'); ?>
</div>

<div class="akeeba-panel--primary">
    <header class="akeeba-block-header">
        <h2>
	        <?=Text::_('COM_LOGINGUARD_HEAD_CONVERT'); ?>
        </h2>
    </header>
    <p>
		<?=Text::_('COM_LOGINGUARD_CONVERT_MOREINFO'); ?>
    </p>
</div>

<form action="index.php" name="adminForm" id="adminForm" method="get">
    <input type="hidden" name="option" value="com_loginguard"/>
    <input type="hidden" name="view" value="Convert">
    <input type="hidden" name="task" value="convert"/>
    <input type="hidden" name="<?=$this->getContainer()->platform->getToken() ?>" value="1"/>
    <input type="submit" class="btn btn-default" value="<?=$this->escape(Text::_('COM_LOGINGUARD_CONVERT_BUTTON'))?>">
</form>
