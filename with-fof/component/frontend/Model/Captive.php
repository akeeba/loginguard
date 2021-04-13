<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\LoginGuard\Site\Model;

use Akeeba\LoginGuard\Site\Helper\Tfa;
use Akeeba\LoginGuard\Site\Model\Tfa as TfaRecord;
use Exception;
use FOF40\Model\Model;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Application\CMSApplication as JApplicationCms;
use Joomla\CMS\Factory as JFactory;
use Joomla\CMS\Language\Text as JText;
use Joomla\CMS\User\User;
use Joomla\CMS\User\User as JUser;
use Joomla\Event\Event;
use stdClass;

// Protect from unauthorized access
defined('_JEXEC') || die();

class Captive extends Model
{
	/**
	 * Cache of the names of the currently active TFA methods
	 *
	 * @var   array|null
	 * @since 2.0.0
	 */
	protected $activeTFAMethodNames = null;

	/**
	 * Prevents Joomla from displaying any modules.
	 *
	 * This is implemented with a trick. If you use jdoc tags to load modules the JDocumentRendererHtmlModules
	 * uses JModuleHelper::getModules() to load the list of modules to render. This goes through JModuleHelper::load()
	 * which triggers the onAfterModuleList event after cleaning up the module list from duplicates. By resetting
	 * the list to an empty array we force Joomla to not display any modules.
	 *
	 * Similar code paths are followed by any canonical code which tries to load modules. So even if your template does
	 * not use jdoc tags this code will still work as expected.
	 *
	 * @param   JApplicationCms|CMSApplication  $app  The CMS application to manipulate
	 *
	 * @return  void
	 *
	 * @throws  Exception
	 */
	public function killAllModules($app = null)
	{
		if (is_null($app))
		{
			$app = JFactory::getApplication();
		}

		if (version_compare(JVERSION, '3.99999.99999', 'lt'))
		{
			$app->registerEvent('onAfterModuleList', [$this, 'onAfterModuleListJoomla3']);

			return;
		}

		$app->registerEvent('onAfterModuleList', [$this, 'onAfterModuleListJoomla4']);
	}

	/**
	 * Process the modules list on Joomla! 3.
	 *
	 * Joomla! 3.x is passing the array of modules by reference. We just have to overwrite the array passed as a
	 * parameter.
	 *
	 * @param   array  $modules  The list of modules on the site
	 *
	 * @return  void
	 * @since   2.0.0
	 */
	public function onAfterModuleListJoomla3(&$modules)
	{
		if (empty($modules))
		{
			return;
		}

		$this->filterModules($modules);
	}

	/**
	 * Process the modules list on Joomla! 4.
	 *
	 * Joomla! 3.x is passing an Event object. The first argument of the event object is the array of modules. After
	 * filtering it we have to overwrite the event argument (NOT just return the new list of modules). If a future
	 * version of Joomla! uses immutable events we'll have to use Reflection to do that or Joomla! would have to fix
	 * the way this event is handled, taking its return into account. For now, we just abuse the mutable event
	 * properties - a feature of the event objects we discussed in the Joomla! 4 Working Group back in August 2015.
	 *
	 * @param   Event  $event  The Joomla! event object
	 *
	 * @return  void
	 * @since   2.0.0
	 */
	public function onAfterModuleListJoomla4(Event $event)
	{
		$modules = $event->getArgument(0);

		if (empty($modules))
		{
			return;
		}

		$this->filterModules($modules);

		$event->setArgument(0, $modules);
	}

	/**
	 * Get the TFA records for the user which correspond to active plugins
	 *
	 * @param   JUser|User  $user  The user for which to fetch records. Skip to use the current user.
	 *
	 * @return  \Akeeba\LoginGuard\Site\Model\Tfa[]
	 * @since   2.0.0
	 */
	public function getRecords($user = null, $includeBackupCodes = false)
	{
		if (is_null($user))
		{
			$user = $this->container->platform->getUser();
		}

		// Get the user's TFA records
		/** @var TfaRecord $tfaModel */
		$tfaModel = $this->container->factory->model('Tfa')->tmpInstance();
		$records  = $tfaModel->user_id($user->id)->get(true);

		// No TFA methods? Then we obviously don't need to display a captive login page.
		if ($records->count() < 1)
		{
			return [];
		}

		// Get the enabled TFA methods' names
		$methodNames = $this->getActiveMethodNames();

		// Filter the records based on currently active TFA methods
		$ret = [];

		if ($includeBackupCodes)
		{
			$methodNames[] = 'backupcodes';
			$methodNames   = array_unique($methodNames);
		}
		else
		{
			$pos = array_search('backupcodes', $methodNames);

			if ($pos !== false)
			{
				unset($methodNames[$pos]);
			}
		}

		/** @var TfaRecord $record */
		foreach ($records as $record)
		{
			// Backup codes must not be included in the list. We add them in the View, at the end of the list.
			if (in_array($record->method, $methodNames))
			{
				$ret[$record->getId()] = $record;
			}
		}

		return $ret;
	}

	/**
	 * Get the currently selected TFA record for the current user. If the record ID is empty, it does not correspond to
	 * the currently logged in user or does not correspond to an active plugin null is returned instead.
	 *
	 * @param   JUser|User  $user  The user for which to fetch records. Skip to use the current user.
	 *
	 * @return  \Akeeba\LoginGuard\Site\Model\Tfa
	 * @since   2.0.0
	 */
	public function getRecord($user = null)
	{
		$id = (int) $this->getState('record_id', null);

		if ($id <= 0)
		{
			return null;
		}

		if (is_null($user))
		{
			$user = $this->container->platform->getUser();
		}

		$records = $this->getRecords($user, true);

		if (!isset($records[$id]))
		{
			return null;
		}

		return $records[$id];
	}

	/**
	 * Load the captive login page render options for a specific TFA record
	 *
	 * @param   stdClass  $record  The TFA record to process
	 *
	 * @return  array  The rendering options
	 * @since   2.0.0
	 */
	public function loadCaptiveRenderOptions($record)
	{
		$renderOptions = [
			'pre_message'        => '',
			'field_type'         => 'input',
			'input_type'         => 'text',
			'placeholder'        => '',
			'label'              => '',
			'html'               => '',
			'post_message'       => '',
			'hide_submit'        => false,
			'help_url'           => '',
			'allowEntryBatching' => false,
		];

		if (empty($record))
		{
			return $renderOptions;
		}

		$results = $this->container->platform->runPlugins('onLoginGuardTfaCaptive', [$record]);

		if (empty($results))
		{
			return $renderOptions;
		}

		foreach ($results as $result)
		{
			if (empty($result))
			{
				continue;
			}

			return array_merge($renderOptions, $result);
		}

		return $renderOptions;
	}

	/**
	 * Returns the title to display in the captive login page, or an empty string if no title is to be displayed.
	 *
	 * @return  string
	 * @since   2.0.0
	 */
	public function getPageTitle()
	{
		// In the frontend we can choose if we will display a title
		$showTitle = (bool) $this->container->params->get('frontend_show_title', 1);

		if (!$showTitle)
		{
			return '';
		}

		return JText::_('COM_LOGINGUARD_HEAD_TFA_PAGE');
	}

	/**
	 * Translate a TFA method's name into its human-readable, display name
	 *
	 * @param   string  $name  The internal TFA method name
	 *
	 * @return  string
	 * @since   2.0.0
	 */
	public function translateMethodName($name)
	{
		static $map = null;

		if (!is_array($map))
		{
			$map        = [];
			$tfaMethods = Tfa::getTfaMethods();

			if (!empty($tfaMethods))
			{
				foreach ($tfaMethods as $tfaMethod)
				{
					$map[$tfaMethod['name']] = $tfaMethod['display'];
				}
			}
		}

		if ($name == 'backupcodes')
		{
			return JText::_('COM_LOGINGUARD_LBL_BACKUPCODES_METHOD_NAME');
		}

		return $map[$name] ?? $name;
	}

	/**
	 * Translate a TFA method's name into the relative URL if its logo image
	 *
	 * @param   string  $name  The internal TFA method name
	 *
	 * @return  string
	 * @since   2.0.0
	 */
	public function getMethodImage($name)
	{
		static $map = null;

		if (!is_array($map))
		{
			$map        = [];
			$tfaMethods = Tfa::getTfaMethods();

			if (!empty($tfaMethods))
			{
				foreach ($tfaMethods as $tfaMethod)
				{
					$map[$tfaMethod['name']] = $tfaMethod['image'];
				}
			}
		}

		if ($name == 'backupcodes')
		{
			return 'media/com_loginguard/images/emergency.svg';
		}

		return $map[$name] ?? $name;
	}

	/**
	 * This is the method which actually filters the sites modules based on the allowed module positions specified by
	 * the user.
	 *
	 * @param   array  $modules  The list of the site's modules. Passed by reference.
	 *
	 * @return  void  The by-reference value is modified instead.
	 * @since   2.0.0
	 */
	private function filterModules(&$modules)
	{
		$allowedPositions = $this->getAllowedModulePositions();

		if (empty($allowedPositions))
		{
			$modules = [];

			return;
		}

		$filtered = [];

		foreach ($modules as $module)
		{
			if (in_array($module->position, $allowedPositions))
			{
				$filtered[] = $module;
			}
		}

		$modules = $filtered;
	}

	/**
	 * Get a list of module positions we are allowed to display
	 *
	 * @return  array
	 * @since   2.0.0
	 */
	private function getAllowedModulePositions()
	{
		$isAdmin = $this->container->platform->isBackend();

		// Load the list of allowed module positions from the component's settings. May be different for front- and back-end
		$configKey = 'allowed_positions_' . ($isAdmin ? 'backend' : 'frontend');
		$res       = $this->container->params->get($configKey, []);

		// In the backend we must always add the 'title' module position
		if ($isAdmin)
		{
			$res[] = 'title';
		}

		return $res;
	}

	/**
	 * Return all the active TFA methods' names
	 *
	 * @return  array
	 * @since   2.0.0
	 */
	private function getActiveMethodNames()
	{
		if (is_null($this->activeTFAMethodNames))
		{
			// Let's get a list of all currently active TFA methods
			$tfaMethods = Tfa::getTfaMethods();

			// If not TFA method is active we can't really display a captive login page.
			if (empty($tfaMethods))
			{
				$this->activeTFAMethodNames = [];

				return $this->activeTFAMethodNames;
			}

			// Get a list of just the method names
			$this->activeTFAMethodNames = [];

			foreach ($tfaMethods as $tfaMethod)
			{
				$this->activeTFAMethodNames[] = $tfaMethod['name'];
			}
		}

		return $this->activeTFAMethodNames;
	}

}
