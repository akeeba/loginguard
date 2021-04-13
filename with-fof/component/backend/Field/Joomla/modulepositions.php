<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Joomla\CMS\Form\FormHelper;
use Joomla\CMS\HTML\HTMLHelper;

// Prevent direct access
defined('_JEXEC') || die;

if (class_exists('JFormFieldModulePositions'))
{
	return;
}

HTMLHelper::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_modules/helpers/html');
require_once JPATH_ADMINISTRATOR . '/components/com_modules/helpers/modules.php';

FormHelper::loadFieldClass('groupedlist');

/**
 * ModulePositions Field class for the Joomla Framework.
 *
 * @since   1.0.0
 */
class JFormFieldModulePositions extends JFormFieldGroupedList
{
	/**
	 * The form field type.
	 *
	 * @var    string
	 */
	protected $type = 'ModulePositions';

	/**
	 * Method to get the field option groups.
	 *
	 * @return  array  The field option objects as a nested array in groups.
	 *
	 * @since   1.0.0
	 *
	 * @throws  UnexpectedValueException
	 */
	public function getGroups()
	{
		$groups = [];

		$clientId = is_null($this->element->attributes()->client_id) ? 0 : (int) $this->element->attributes()->client_id;
		$state    = is_null($this->element->attributes()->state) ? 1 : (int) $this->element->attributes()->state;

		// Get all module positions for this client ID
		if (version_compare(JVERSION, '3.99999.99999', 'le'))
		{
			$positions = HTMLHelper::_('modules.positions', $clientId, $state, $this->value);

			// There's a junk position added with no content. Remove it.
			if (isset($positions['']))
			{
				unset($positions['']);
			}

			foreach ($positions as $label => $position)
			{
				if (!isset($position['items']))
				{
					continue;
				}

				$groups[$label] = [];

				foreach ($position['items'] as $item)
				{
					$item             = (array) $item;
					$disable          = $item['disable'] ?? false;
					$groups[$label][] = HTMLHelper::_('select.option', $item['value'], $item['text'], 'value', 'text', $disable);
				}
			}
		}
		else
		{
			/**
			 * In Joomla! 4 we get a list of positions based on what is already present in active modules, not positions
			 * per template. The ModulesHelper::getPositions returns a JHtml::select compatible list of positions. We
			 * have to add it to group 0 which has a special meaning in JHtml -- it shows no groups. We need to remove
			 * the first element, though, which is 'none' i.e. assign to no position.
			 */
			$positions = ModulesHelper::getPositions($clientId, false);

			array_shift($positions);

			$groups = [
				0 => $positions,
			];
		}


		return $groups;
	}
}
