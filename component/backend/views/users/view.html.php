<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_users
 *
 * @copyright   Copyright (C) 2005 - 2020 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Helper\ContentHelper as JContentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView;
use Joomla\CMS\Object\CMSObject;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * View class for a list of users.
 *
 * @since  5.0.0
 */
class LoginGuardViewUsers extends HtmlView
{
	/**
	 * A JForm instance with filter fields.
	 *
	 * @var    Form
	 * @since  5.0.0
	 */
	public $filterForm;

	/**
	 * An array with active filters.
	 *
	 * @var    array
	 * @since  5.0.0
	 */
	public $activeFilters;

	/**
	 * The item data.
	 *
	 * @var   object
	 * @since 5.0.0
	 */
	protected $items;

	/**
	 * The pagination object.
	 *
	 * @var   Pagination
	 * @since 5.0.0
	 */
	protected $pagination;

	/**
	 * The model state.
	 *
	 * @var   CMSObject
	 * @since 5.0.0
	 */
	protected $state;

	/**
	 * An instance of JDatabaseDriver.
	 *
	 * @var    JDatabaseDriver
	 * @since  5.0.0
	 */
	protected $db;

	/**
	 * An ACL object to verify user rights.
	 *
	 * @var    JObject
	 * @since  5.0.0
	 */
	protected $canDo;

	/**
	 * Display the view
	 *
	 * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
	 *
	 * @return  void
	 * @throws  Exception
	 * @since   5.0.0
	 */
	public function display($tpl = null)
	{
		$this->items         = $this->get('Items');
		$this->pagination    = $this->get('Pagination');
		$this->state         = $this->get('State');
		$this->filterForm    = $this->get('FilterForm');
		$this->activeFilters = $this->get('ActiveFilters');
		$this->canDo         = JContentHelper::getActions('com_users');
		$this->db            = Factory::getDbo();
		$isJ4                = version_compare(JVERSION, '3.999.999', 'gt');

		if (!$isJ4)
		{
			JLoader::register('UsersHelper', JPATH_ADMINISTRATOR . '/components/com_users/helpers/users.php');
			UsersHelper::addSubmenu('users');
		}
		else
		{
			\Joomla\Component\Users\Administrator\Helper\UsersHelper::addSubmenu('users');
		}

		// Check for errors.
		if (count($errors = $this->get('Errors')))
		{
			throw new Exception(implode("\n", $errors), 500);
		}

		// Include the component HTML helpers.
		HTMLHelper::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_users/helpers/html');

		$this->addToolbar();

		parent::display($tpl);
	}

	/**
	 * Add the page title and toolbar.
	 *
	 * @return  void
	 *
	 * @since   5.0.0
	 */
	protected function addToolbar()
	{
		ToolbarHelper::title(JText::_('COM_LOGINGUARD') . ': <small>' . JText::_('COM_LOGINGUARD_HEAD_USERS') . '</small>', 'loginguard');

		ToolbarHelper::back('COM_LOGINGUARD_HEAD_WELCOME', 'index.php?option=com_loginguard');
	}

	/**
	 * Returns an array of fields the table can be sorted by
	 *
	 * @return  array  Array containing the field name to sort by as the key and display text as value
	 *
	 * @since   5.0.0
	 */
	protected function getSortFields()
	{
		return [
			'a.name'     => Text::_('COM_USERS_HEADING_NAME'),
			'a.username' => Text::_('JGLOBAL_USERNAME'),
			'a.email'    => Text::_('JGLOBAL_EMAIL'),
			'a.id'       => Text::_('JGRID_HEADING_ID'),
			'has2SV'     => Text::_('COM_LOGINGUARD_USER_FIELD_HAS2SV'),
		];
	}
}
