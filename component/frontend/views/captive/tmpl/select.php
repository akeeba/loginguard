<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2017 Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/** @var LoginGuardViewCaptive $this */

$shownMethods = [];

?>
<div id="loginguard-select">
    <h3 id="loginguard-select-heading">
        <?= Text::_('COM_LOGINGUARD_HEAD_SELECT_PAGE'); ?>
    </h3>
    <div id="loginguard-select-information">
        <p>
	        <?= Text::_('COM_LOGINGUARD_LBL_SELECT_INSTRUCTIONS'); ?>
        </p>
    </div>

	<div class="loginguard-select-methods">
		<?php foreach ($this->records as $record):
		if (!array_key_exists($record->method, $this->tfaMethods) && ($record->method != 'backupcodes')) continue;
		$allowEntryBatching = isset($this->tfaMethods[$record->method]) ? $this->tfaMethods[$record->method]['allowEntryBatching'] : false;

		if ($this->allowEntryBatching)
		{
			if ($allowEntryBatching && in_array($record->method, $shownMethods)) continue;
			$shownMethods[] = $record->method;
		}

		$methodName = $this->getModel()->translateMethodName($record->method);
		?>
		<a class="loginguard-method"
		   href="<?= Route::_('index.php?option=com_loginguard&view=captive&record_id=' . $record->id)?>">
			<img src="<?= Uri::root() . $this->getModel()->getMethodImage($record->method) ?>" class="loginguard-method-image" />
			<?php if (!$this->allowEntryBatching || !$allowEntryBatching): ?>
			<span class="loginguard-method-title">
				<?php if ($record->method === 'backupcodes'): ?>
					<?= $record->title ?>
				<?php else: ?>
					<?= $this->escape($record->title) ?>
				<?php endif; ?>
			</span>
			<span class="loginguard-method-name">
				<?= $methodName ?>
			</span>
			<?php else: ?>
				<span class="loginguard-method-title">
				<?= $methodName ?>
			</span>
				<span class="loginguard-method-name">
				<?= $methodName ?>
			</span>
			<?php endif; ?>
		</a>
		<?php endforeach; ?>
	</div>
</div>