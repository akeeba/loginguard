<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Akeeba\LoginGuard\Site\View\Methods\Html;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

// Prevent direct access
defined('_JEXEC') || die;

/** @var Html $this */

?>
<div id="loginguard-methods-list" class="akeeba-panel--info">
    <header class="akeeba-block-header">
        <h3 id="loginguard-methods-list-head">
		    <?= Text::_('COM_LOGINGUARD_HEAD_LIST_PAGE'); ?>
        </h3>
    </header>

    <div id="loginguard-methods-list-instructions">
        <p>
            <span class="icon icon-info glyphicon glyphicon-info-sign"></span>
			<?= Text::_('COM_LOGINGUARD_LBL_LIST_INSTRUCTIONS'); ?>
        </p>
    </div>

	<div id="loginguard-methods-reset-container" class="akeeba-panel--<?= $this->tfaActive ? 'success' : 'danger' ?>">
        <div class="akeeba-container--75-25">
            <div>
                <span id="loginguard-methods-reset-message">
                    <?= Text::sprintf('COM_LOGINGUARD_LBL_LIST_STATUS', Text::_('COM_LOGINGUARD_LBL_LIST_STATUS_' . ($this->tfaActive ? 'ON' : 'OFF'))) ?>
                </span>
            </div>
            <div class="loginguard-methods-reset-container-removeall-container">
	            <?php if ($this->tfaActive): ?>
                    <a href="<?= Route::_('index.php?option=com_loginguard&view=Methods&task=disable&' . $this->container->platform->getToken() . '=1' . ($this->returnURL ? '&returnurl=' . $this->escape(urlencode($this->returnURL)) : '') . '&user_id=' . $this->user->id) ?>"
                       class="akeeba-btn--red">
			            <?= Text::_('COM_LOGINGUARD_LBL_LIST_REMOVEALL'); ?>
                    </a>
	            <?php endif; ?>
            </div>
        </div>
	</div>

</div>
<?php $this->setLayout('list'); echo $this->loadTemplate(); ?>
