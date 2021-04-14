<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') || die;

use Joomla\CMS\Factory;
use Joomla\Event\Event;

trait LoginGuardHelperMixinTriggerEvent
{
	/**
	 * Triggers an object-specific event. The event runs both locally –if a suitable method exists– and through the
	 * Joomla! plugin system. A true/false return value is expected. The first false return cancels the event.
	 *
	 * @param   string  $event      The name of the event, typically named onPredicateVerb e.g. onBeforeKick
	 * @param   array   $arguments  The arguments to pass to the event handlers
	 *
	 * @return  bool
	 */
	protected function triggerEvent(string $event, array $arguments = []): bool
	{
		// If there is an object method for this event, call it
		if (method_exists($this, $event))
		{
			if (call_user_func([$this, $event], ...$arguments) === false)
			{
				return false;
			}
		}

		// All other event handlers live outside this object, therefore they need to be passed a reference to this
		// object as the first argument.
		array_unshift($arguments, $this);

		// If we have an "on" prefix for the event (e.g. onFooBar) remove it and stash it for later.
		$prefix = '';

		if (substr($event, 0, 2) == 'on')
		{
			$prefix = 'on';
			$event  = substr($event, 2);
		}

		// Get the component name and object type from the namespace of the caller
		$callers        = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS);
		$parts = $this->getClassParts($callers[1]['class']);
		$className      = $parts['name'];
		$objectType     = $parts['type'];
		$bareComponent  = $parts['component'];

		// Get the component/model prefix for the event
		$prefix .= 'Com' . ucfirst($bareComponent);
		$prefix .= ucfirst($objectType) . ucfirst($className);

		// The event name will be something like onComFoobarControllerItemsBeforeSomething
		$event = $prefix . $event;

		// Call the Joomla! plugins
		$results = self::runPlugins($event, $arguments);

		return !in_array(false, $results, true);
	}

	/**
	 * Figures out the component, object type and object name from a Joomla 3 or 4 class name.
	 *
	 * Example (Joomla 3):
	 * Giving `FoobarControllerItem` returns:
	 * * `name` = 'Item'
	 * * `type` = 'Controller'
	 * * `component` = 'foobar'
	 *
	 * Example (Joomla 4):
	 * Giving `AcmeCorp\Component\FooBar\Administrator\Controller\Item` returns:
	 * * `name` = 'Item'
	 * * `type` = 'Controller'
	 * * `component` = 'foobar'
	 *
	 * @param   string  $className
	 *
	 * @return  string[]
	 */
	private function getClassParts(string $className): array
	{
		$ret = [
			'name'      => '',
			'type'      => '',
			'component' => '',
		];

		// Do we have a namespaced (Joomla 4) class?
		if (strpos($className, '\\') !== false)
		{
			$namespaceParts = explode('\\', $className);
			$ret['name']    = array_pop($namespaceParts);
			$ret['type']    = array_pop($namespaceParts);
			array_pop($namespaceParts);
			$ret['component'] = strtolower(array_pop($namespaceParts));

			return $ret;
		}

		// Joomla 3 uses the convention ComponentTypeName e.g. FoobarControllerItem
		$possibleSeparators = [
			'Controller',
			'Helper',
			'Model',
			'Table',
			'View',
		];

		foreach ($possibleSeparators as $separator)
		{
			if (strpos($className, $separator) === false)
			{
				continue;
			}

			[$component, $name] = explode($separator, $className, 2);
			$ret['type']      = $separator;
			$ret['component'] = strtolower($component);
			$ret['name']      = $name;
		}

		return $ret;
	}

	/**
	 * Execute plugins (system-level triggers) and fetch back an array with
	 * their return values.
	 *
	 * @param   string  $event  The event (trigger) name, e.g. onBeforeScratchMyEar
	 * @param   array   $data   A hash array of data sent to the plugins as part of the trigger
	 *
	 * @return  array  A simple array containing the results of the plugins triggered
	 */
	private static function runPlugins(string $event, array $data = []): array
	{
		if (class_exists('JEventDispatcher'))
		{
			return JEventDispatcher::getInstance()->trigger($event, $data);
		}

		// If there's no JEventDispatcher try getting JApplication
		try
		{
			$app = Factory::getApplication();
		}
		catch (Exception $e)
		{
			// If I can't get JApplication I cannot run the plugins.
			return [];
		}

		// Joomla 3 and 4 have triggerEvent
		if (method_exists($app, 'triggerEvent'))
		{
			return $app->triggerEvent($event, $data);
		}

		// Joomla 5 (and possibly some 4.x versions) don't have triggerEvent. Go through the Events dispatcher.
		if (method_exists($app, 'getDispatcher') && class_exists('Joomla\Event\Event'))
		{
			try
			{
				$dispatcher = $app->getDispatcher();
			}
			catch (\UnexpectedValueException $exception)
			{
				return [];
			}

			if ($data instanceof Event)
			{
				$eventObject = $data;
			}
			elseif (\is_array($data))
			{
				$eventObject = new Event($event, $data);
			}
			else
			{
				throw new InvalidArgumentException('The plugin data must either be an event or an array');
			}

			$result = $dispatcher->dispatch($event, $eventObject);

			return !isset($result['result']) || \is_null($result['result']) ? [] : $result['result'];
		}

		// No viable way to run the plugins :(
		return [];
	}

}