<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\LoginGuard\Site\Helper;

// Protect from unauthorized access
use FOF30\Container\Container;
use Joomla\CMS\User\User;

defined('_JEXEC') || die();

/**
 * Two Factor Authentication helper class for Akeeba LoginGuard
 *
 * @since       2.0.0
 */
abstract class Tfa
{
	/**
	 * Cache of all currently active TFAs
	 *
	 * @var   array|null
	 * @since 1.0.0
	 */
	protected static $allTFAs = null;

	/**
	 * The LoginGuard's container object
	 *
	 * @var   Container
	 * @since 2.0.0
	 */
	protected static $container = null;

	/**
	 * Get a reference to LoginGuard's container object
	 *
	 * @return  Container
	 *
	 * @since   2.0.0
	 */
	protected static function getContainer()
	{
		if (empty(self::$container))
		{
			self::$container = Container::getInstance('com_loginguard');
		}

		return self::$container;
	}

	/**
	 * Get a list of all of the TFA methods
	 *
	 * @return  array
	 */
	public static function getTfaMethods()
	{
		self::getContainer()->platform->importPlugin('loginguard');

		if (is_null(self::$allTFAs))
		{
			// Get all the plugin results
			$temp = self::getContainer()->platform->runPlugins('onLoginGuardTfaGetMethod', []);

			self::$allTFAs = [];

			foreach ($temp as $method)
			{
				if (!is_array($method))
				{
					continue;
				}

				$method = array_merge([
					// Internal code of this TFA method
					'name'               => '',
					// User-facing name for this TFA method
					'display'            => '',
					// Short description of this TFA method displayed to the user
					'shortinfo'          => '',
					// URL to the logo image for this method
					'image'              => '',
					// Are we allowed to disable it?
					'canDisable'         => true,
					// Are we allowed to have multiple instances of it per user?
					'allowMultiple'      => false,
					// URL for help content
					'help_url'           => '',
					// Allow authentication against all entries of this TFA method. Otherwise authentication takes place against a SPECIFIC entry at a time.
					'allowEntryBatching' => false,
				], $method);

				if (empty($method['name']))
				{
					continue;
				}

				self::$allTFAs[$method['name']] = $method;
			}
		}

		return self::$allTFAs;
	}

	/**
	 * Is the current user allowed to edit the TFA configuration of $user? To do so I must either be editing my own
	 * account OR I have to be a Super User editing a non-superuser's account. Important to note: nobody can edit the
	 * accounts of Super Users except themselves. Therefore make damn sure you keep those backup codes safe!
	 *
	 * @param   User  $user  The user you want to know if we're allowed to edit
	 *
	 * @return  bool
	 */
	public static function canEditUser(User $user = null)
	{
		// I can edit myself
		if (empty($user))
		{
			return true;
		}

		// Guests can't have TFA
		if ($user->guest)
		{
			return false;
		}

		// Get the currently logged in used
		$myUser = self::getContainer()->platform->getUser();

		// Same user? I can edit myself
		if ($myUser->id == $user->id)
		{
			return true;
		}

		// To edit a different user I must be a Super User myself. If I'm not, I can't edit another user!
		if (!$myUser->authorise('core.admin'))
		{
			return false;
		}

		// Even if I am a Super User I must not be able to edit another Super User.
		if ($user->authorise('core.admin'))
		{
			return false;
		}

		// I am a Super User trying to edit a non-superuser. That's allowed.
		return true;
	}

}
