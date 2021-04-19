<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_users
 *
 * @copyright   Copyright (C) 2005 - 2020 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Access\Access;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\String\PunycodeHelper;

HTMLHelper::_('bootstrap.tooltip');
HTMLHelper::_('behavior.multiselect');
HTMLHelper::_('formbehavior.chosen', 'select');

$listOrder  = $this->escape($this->state->get('list.ordering'));
$listDirn   = $this->escape($this->state->get('list.direction'));
$loggeduser = Factory::getUser();

$isJ4 = version_compare(JVERSION, '3.999.999', 'gt');
?>
<form action="<?= Route::_('index.php?option=com_loginguard&view=users'); ?>" method="post" name="adminForm" id="adminForm">
	<div class="row">
		<div class="col-md-12">
			<div id="j-main-container" class="j-main-container">
				<?php
				// Search tools bar
				echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]);
				?>
				<?php if (empty($this->items)) : ?>
					<div class="alert <?= $isJ4 ? 'alert-info' : 'alert-no-items' ?>">
						<?php if ($isJ4): ?>
						<span class="icon-info-circle" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
						<?php endif ?>
						<?= Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
					</div>
				<?php else : ?>
					<table class="table table-striped" id="userList">
						<?php if ($isJ4): ?>
						<caption class="visually-hidden">
							<?php echo Text::_('COM_USERS_USERS_TABLE_CAPTION'); ?>,
							<span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?> </span>,
							<span id="filteredBy"><?php echo Text::_('JGLOBAL_FILTERED_BY'); ?></span>
						</caption>
						<?php endif ?>
						<thead>
						<tr>
							<th class="nowrap" scope="col">
								<?= HTMLHelper::_('searchtools.sort', 'COM_USERS_HEADING_NAME', 'a.name', $listDirn, $listOrder); ?>
							</th>
							<th width="10%" class="nowrap">
								<?= HTMLHelper::_('searchtools.sort', 'JGLOBAL_USERNAME', 'a.username', $listDirn, $listOrder); ?>
							</th>
							<th width="5%" class="nowrap center hidden-phone text-center d-none d-md-table-cell">
								<?= Text::_('COM_USERS_HEADING_ENABLED') ?>
							</th>
							<th width="5%" class="nowrap center text-center">
								<?= Text::_('COM_LOGINGUARD_USER_FIELD_HAS2SV') ?>
							</th>
							<th width="10%" class="nowrap">
								<?= Text::_('COM_USERS_HEADING_GROUPS'); ?>
							</th>
							<th width="15%" class="nowrap hidden-phone hidden-tablet text-center d-none d-md-table-cell">
								<?= HTMLHelper::_('searchtools.sort', 'JGLOBAL_EMAIL', 'a.email', $listDirn, $listOrder); ?>
							</th>
							<th width="1%" class="nowrap hidden-phone w-5 d-none d-md-table-cell">
								<?= HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>
							</th>
						</tr>
						</thead>
						<?php if (!$isJ4): ?>
						<tfoot>
						<tr>
							<td colspan="10">
								<?= $this->pagination->getListFooter(); ?>
							</td>
						</tr>
						</tfoot>
						<?php endif ?>
						<tbody>
						<?php foreach ($this->items as $i => $item) :
							$canEdit   = $this->canDo->get('core.edit');

							// If this group is super admin and this user is not super admin, $canEdit is false
							if ((!$loggeduser->authorise('core.admin')) && Access::check($item->id, 'core.admin'))
							{
								$canEdit   = false;
							}
							?>
							<tr class="row<?= $i % 2; ?>">
								<td>
									<div class="name break-word">
										<?php if ($canEdit) : ?>
											<a href="<?= Route::_('index.php?option=com_users&task=user.edit&id=' . (int) $item->id); ?>" title="<?= Text::sprintf('COM_USERS_EDIT_USER', $this->escape($item->name)); ?>">
												<?= $this->escape($item->name); ?></a>
										<?php else : ?>
											<?= $this->escape($item->name); ?>
										<?php endif; ?>
									</div>
								</td>
								<td class="break-word">
									<?= $this->escape($item->username); ?>
								</td>
								<td class="center hidden-phone d-none d-md-table-cell">
									<?= HTMLHelper::_('jgrid.published', $item->block ? 0 : 1, $i, 'users.', false) ?>
								</td>
								<td class="center">
									<?= HTMLHelper::_('jgrid.published', $item->has2SV, $i, 'users.', false) ?>
								</td>
								<td>
									<?php if (substr_count($item->group_names, "\n") > 1) : ?>
										<span class="hasTooltip" title="<?= HTMLHelper::_('tooltipText', Text::_('COM_USERS_HEADING_GROUPS'), nl2br($item->group_names), 0); ?>"><?= Text::_('COM_USERS_USERS_MULTIPLE_GROUPS'); ?></span>
									<?php else : ?>
										<?= nl2br($item->group_names); ?>
									<?php endif; ?>
								</td>
								<td class="hidden-phone break-word hidden-tablet d-none d-md-table-cell">
									<?= PunycodeHelper::emailToUTF8($this->escape($item->email)); ?>
								</td>
								<td class="hidden-phone d-none d-md-table-cell">
									<?= (int) $item->id; ?>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>

					<?php if ($isJ4): ?>
						<?= $this->pagination->getListFooter(); ?>
					<?php endif ?>
				<?php endif; ?>

				<input type="hidden" name="task" value="" />
				<input type="hidden" name="boxchecked" value="0" />
				<?= HTMLHelper::_('form.token'); ?>
			</div>
		</div>
	</div>
</form>
