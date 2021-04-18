<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Table\Table;
use Joomla\CMS\User\User;
use Joomla\Event\Event;

JLoader::register('LoginGuardHelperTfa', JPATH_SITE . '/components/com_loginguard/helpers/tfa.php');

/**
 * Captive Two Step Verification page's model
 */
class LoginGuardModelCaptive extends BaseDatabaseModel
{
	/**
	 * Cache of the names of the currently active TFA methods
	 *
	 * @var  null
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
	 * @param   CMSApplication|null  $app  The CMS application to manipulate
	 *
	 * @return  void
	 * @throws  Exception
	 *
	 * @since   1.2.0
	 */
	public function killAllModules(CMSApplication $app = null): void
	{
		if (is_null($app))
		{
			$app = Factory::getApplication();
		}

		if (version_compare(JVERSION, '3.99999.99999', 'lt'))
		{
			$app->registerEvent('onAfterModuleList', [$this, 'onAfterModuleListJoomla3']);

			return;
		}

		$app->registerEvent('onAfterModuleList', [$this, 'onAfterModuleListJoomla4']);
	}

	/**
	 * Get the TFA records for the user which correspond to active plugins
	 *
	 * @param   User|null  $user                The user for which to fetch records. Skip to use the current user.
	 * @param   bool       $includeBackupCodes  Should I include the backup codes record?
	 *
	 * @return  array
	 * @throws  Exception
	 *
	 * @since   1.2.0
	 */
	public function getRecords(User $user = null, bool $includeBackupCodes = false): array
	{
		if (is_null($user))
		{
			$user = Factory::getApplication()->getIdentity() ?: Factory::getUser();
		}

		// Get the user's TFA records
		$records = LoginGuardHelperTfa::getUserTfaRecords($user->id);

		// No TFA methods? Then we obviously don't need to display a captive login page.
		if (empty($records))
		{
			return [];
		}

		// Get the enabled TFA methods' names
		$methodNames = $this->getActiveMethodNames();

		// Filter the records based on currently active TFA methods
		$ret = [];

		$methodNames[] = 'backupcodes';
		$methodNames   = array_unique($methodNames);

		if (!$includeBackupCodes)
		{
			$methodNames = array_filter($methodNames, function ($method) {
				return $method != 'backupcodes';
			});
		}

		/** @var LoginGuardTableTfa $record */
		foreach ($records as $record)
		{
			// Backup codes must not be included in the list. We add them in the View, at the end of the list.
			if (in_array($record->method, $methodNames))
			{
				$ret[$record->id] = $record;
			}
		}

		return $ret;
	}

	/**
	 * Get the currently selected TFA record for the current user. If the record ID is empty, it does not correspond to
	 * the currently logged in user or does not correspond to an active plugin null is returned instead.
	 *
	 * @param   User|null  $user  The user for which to fetch records. Skip to use the current user.
	 *
	 * @return  LoginGuardTableTfa|null
	 * @throws  Exception
	 *
	 * @since   1.2.0
	 */
	public function getRecord(?User $user = null): ?LoginGuardTableTfa
	{
		$id = (int) $this->getState('record_id', null);

		if ($id <= 0)
		{
			return null;
		}

		if (is_null($user))
		{
			$user = Factory::getApplication()->getIdentity() ?: Factory::getUser();
		}

		/** @var LoginGuardTableTfa $record */
		$record = Table::getInstance('Tfa', 'LoginGuardTable');
		$loaded = $record->load([
			'user_id' => $user->id,
			'id'      => $id,
		]);

		if (!$loaded)
		{
			return null;
		}

		$methodNames = $this->getActiveMethodNames();

		if (!in_array($record->method, $methodNames) && ($record->method != 'backupcodes'))
		{
			return null;
		}

		return $record;
	}

	/**
	 * Load the captive login page render options for a specific TFA record
	 *
	 * @param   LoginGuardTableTfa  $record  The TFA record to process
	 *
	 * @return  array  The rendering options
	 * @since   1.2.0
	 */
	public function loadCaptiveRenderOptions(?LoginGuardTableTfa $record): array
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

		$results = LoginGuardHelperTfa::runPlugins('onLoginGuardTfaCaptive', [$record]);

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
	 * @since   1.2.0
	 */
	public function getPageTitle(): string
	{
		// In the frontend we can choose if we will display a title
		$showTitle = (bool) ComponentHelper::getParams('com_loginguard')->get('frontend_show_title', 1);

		if (!$showTitle)
		{
			return '';
		}

		return Text::_('COM_LOGINGUARD_HEAD_TFA_PAGE');
	}

	/**
	 * Translate a TFA method's name into its human-readable, display name
	 *
	 * @param   string  $name  The internal TFA method name
	 *
	 * @return  string
	 * @since   1.2.0
	 */
	public function translateMethodName(string $name): string
	{
		static $map = null;

		if (!is_array($map))
		{
			$map        = [];
			$tfaMethods = LoginGuardHelperTfa::getTfaMethods();

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
			return Text::_('COM_LOGINGUARD_LBL_BACKUPCODES_METHOD_NAME');
		}

		return $map[$name] ?? $name;
	}

	/**
	 * Translate a TFA method's name into the relative URL if its logo image
	 *
	 * @param   string  $name  The internal TFA method name
	 *
	 * @return  string
	 * @since   1.2.0
	 */
	public function getMethodImage(string $name): string
	{
		static $map = null;

		if (!is_array($map))
		{
			$map        = [];
			$tfaMethods = LoginGuardHelperTfa::getTfaMethods();

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
	 * Process the modules list on Joomla! 3.
	 *
	 * Joomla! 3.x is passing the array of modules by reference. We just have to overwrite the array passed as a
	 * parameter.
	 *
	 * @param   array  $modules  The list of modules on the site
	 *
	 * @return  void
	 * @throws  Exception
	 *
	 * @since   2.0.0
	 */
	public function onAfterModuleListJoomla3(array &$modules): void
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
	 * @throws  Exception
	 *
	 * @since   2.0.0
	 */
	public function onAfterModuleListJoomla4(Event $event): void
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
	 * Get a list of module positions we are allowed to display
	 *
	 * @return  array
	 * @throws  Exception
	 *
	 * @since   1.2.0
	 */
	private function getAllowedModulePositions(): array
	{
		$isAdmin = Factory::getApplication()->isClient('administrator');

		// Load the list of allowed module positions from the component's settings. May be different for front- and back-end
		$configKey = 'allowed_positions_' . ($isAdmin ? 'backend' : 'frontend');
		$res       = ComponentHelper::getParams('com_loginguard')->get($configKey, []);

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
	 * @since   1.2.0
	 */
	private function getActiveMethodNames(): ?array
	{
		if (!is_null($this->activeTFAMethodNames))
		{
			return $this->activeTFAMethodNames;
		}

		// Let's get a list of all currently active TFA methods
		$tfaMethods = LoginGuardHelperTfa::getTfaMethods();

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

		return $this->activeTFAMethodNames;
	}

	/**
	 * This is the method which actually filters the sites modules based on the allowed module positions specified by
	 * the user.
	 *
	 * @param   array  $modules  The list of the site's modules. Passed by reference.
	 *
	 * @return  void  The by-reference value is modified instead.
	 * @since   2.0.0
	 * @throws  Exception
	 */
	private function filterModules(array &$modules): void
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

}