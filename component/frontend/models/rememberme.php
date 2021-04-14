<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') || die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Crypt\Crypt;
use Joomla\CMS\Factory;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\User\UserHelper;

/**
 * Implements a Remember Me feature for Akeeba LoginGuard
 */
class LoginGuardModelRememberme extends BaseDatabaseModel
{
	private const CONFIG_KEY_ENABLED = 'allow_rememberme';

	private const CONFIG_KEY_LIFETIME = 'cookie_lifetime';

	private const CONFIG_KEY_KEYLENGTH = 'cookie_key_length';

	private const COOKIE_PREFIX = 'loginguard_remember_';

	/**
	 * The Browser ID for which the Remember Me cookie is valid
	 *
	 * @var    string|null
	 * @since  3.3.0
	 */
	private $browserId;

	/**
	 * The Username for which the Remember Me cookie is valid
	 *
	 * @var    string|null
	 * @since  3.3.0
	 */
	private $username;

	/**
	 * Returns the cookie name for LoginGuard's Remember Me feature or null if there's no detected browser ID
	 *
	 * @return  string
	 * @since   3.3.0
	 */
	public function getCookieName(): ?string
	{
		// If this feature is not enabled return null
		$isEnabled = (bool) ComponentHelper::getParams('com_loginguard')->get(self::CONFIG_KEY_ENABLED, true);

		if (!$isEnabled)
		{
			return null;
		}

		// If we don't have a username we can't validate cookies so it's as though we're disabled
		if (empty($this->getUsername()))
		{
			return null;
		}

		// Get the browser ID set in this Model (falls back to the session if none is specified)
		$browserIdSession = $this->getBrowserId();

		if (empty($browserIdSession))
		{
			return null;
		}

		return self::COOKIE_PREFIX . $browserIdSession;
	}

	/**
	 * Sets the Remember Me cookie for 2SV
	 *
	 * @return  void
	 * @since   3.3.0
	 */
	public function setCookie(): void
	{
		// Make sure we should be setting a cookie
		$cookieName = $this->getCookieName();

		if (empty($cookieName))
		{
			return;
		}

		// First, make sure we can get a unique series which will be part of the cookie throughout its lifetime
		$series = $this->getUniqueSeries();

		if (empty($series))
		{
			return;
		}

		// Get the necessary information to create a cookie
		$cParams     = ComponentHelper::getParams('com_loginguard');
		$lifetime    = $cParams->get(self::CONFIG_KEY_LIFETIME, 15) * 24 * 3600;
		$expiration  = time() + $lifetime;
		$length      = $cParams->get(self::CONFIG_KEY_KEYLENGTH, 64);
		$token       = UserHelper::genRandomPassword($length);
		$cookieValue = $token . '.' . $series;

		// Overwrite an existing cookie with the new value
		try
		{
			$app = Factory::getApplication();
		}
		catch (Exception $e)
		{
			return;
		}

		$app->input->cookie->set(
			$cookieName,
			$cookieValue,
			$expiration,
			$app->get('cookie_path', '/'),
			$app->get('cookie_domain', ''),
			$app->isHttpsForced(),
			true
		);

		// Store or refresh the token in the database
		$this->storeToken($series, $token, $expiration);
	}

	/**
	 * Do we have a valid Remember Me cookies for 2SV?
	 *
	 * @return  bool
	 * @since   3.3.0
	 */
	public function hasValidCookie(): bool
	{
		// Make sure the feature is enabled, I have a browser ID and I can determine a cookie name
		$cookieName = $this->getCookieName();

		if (empty($cookieName))
		{
			return false;
		}

		// Get the Joomla application
		try
		{
			$app = Factory::getApplication();
		}
		catch (Exception $e)
		{
			return false;
		}

		// Does the cookie actually exist?
		$cookieValue = $app->input->cookie->get($cookieName);

		if (empty($cookieValue))
		{
			return false;
		}

		// Make sure the cookie contents are valid
		$cookieArray = explode('.', $cookieValue);

		// If the cookie is invalid expire it immediately; someone tried to do something funky
		if (count($cookieArray) !== 2)
		{
			$app->input->cookie->set($cookieName, '', 1, $app->get('cookie_path', '/'), $app->get('cookie_domain', ''));

			return false;
		}

		// Get the cookie series
		$filter = new InputFilter();
		$series = $filter->clean($cookieArray[1], 'ALNUM');

		// Remove expired tokens
		$this->removeExpiredTokens();

		// Find the matching token records
		$results = $this->getTokenRecords($cookieName, $series);

		/**
		 * If we have no valid tokens something is happening:
		 *
		 * - Token got removed after logout or password change
		 * - Joomla's Authentication – Cookie plugin killed our tokens when it reset its own tokens.
		 * - Someone is trying to brute force the series.
		 *
		 * Kill the token at once.
		 */
		if (count($results) !== 1)
		{
			$app->input->cookie->set($cookieName, '', 1, $app->get('cookie_path', '/'), $app->get('cookie_domain', ''));

			return false;
		}

		// Validate the token using a time safe comparison
		$isValidToken = UserHelper::verifyPassword($cookieArray[0], $results[0]->token);
		$isValidUser  = Crypt::timingSafeCompare($this->getUsername(), $results[0]->user_id);

		/**
		 * 1. If the token is invalid the following things might have happened:
		 *
		 * - An attacker calculated the browser ID (easy) and guessed the series (hard)
		 * - An attacker has faked the browser ID (hard) and stolen the user's cookie (moderately easy)
		 * - Our database was rolled back to a previous point in time
		 * - Our database got out of sync e.g. one of the token saves failed
		 *
		 * Joomla wrongly assumes that in this case we have a legitimate attack and wipes all tokens. In my experience,
		 * database issues are far more common. Drastic measures are rarely required.
		 *
		 * The best compromise we can make is remove all tokens for our series, not everything under the sun for the
		 * user. The response to a suspected attack should be proportionate. You don't demolish your house with C4 if
		 * you see scratch marks on the door which could either be a burglar trying to get in or the neighbour's dog
		 * getting excited about your mince pie cooling on your kitchen island. Just saying.
		 *
		 * 2. Does the username match? If not, something's up:
		 *
		 * - An admin changed the user's username. In this case we DO want them to go through 2SV again.
		 * - The user has multiple accounts on our site. Moreover, he didn't log out of the previous account and he is
		 *   not using Joomla's Remember Me. In this case we DO want them to go through 2SV every single time.
		 * - The attacker has the victim's browser under control. The attacker logged in with his own account on our
		 *   site and he's now trying to use the cookie for the victim's account.
		 *
		 * In this case I will also remove the cookie series and destroy the cookie in the browser.
		 */
		if (!$isValidToken || !$isValidUser)
		{
			// Delete this cookie series just in case it's an attack
			$db    = $this->getDbo();
			$query = $db->getQuery(true)
				->delete('#__user_keys')
				->where($db->quoteName('series') . ' = ' . $db->quote($series))
				->where($db->quoteName('uastring') . ' = ' . $db->quote($cookieName));

			try
			{
				$db->setQuery($query)->execute();
			}
			catch (RuntimeException $e)
			{
			}

			// Destroy the cookie in the browser.
			$app->input->cookie->set($cookieName, '', 1, $app->get('cookie_path', '/'), $app->get('cookie_domain', ''));

			return false;
		}

		return true;
	}

	/**
	 * Removes the Remember Me cookie for 2SV for the current user and browser ID.
	 *
	 * This is useful when the user explicitly logs out.
	 *
	 * @return  void
	 * @since   3.3.0
	 */
	public function removeCookie(): void
	{
		// Is this feature enabled and we have collected enough information to handle a Remember Me cookie?
		$cookieName = $this->getCookieName();

		if (empty($cookieName))
		{
			return;
		}

		// Get the Joomla application
		try
		{
			$app = Factory::getApplication();
		}
		catch (Exception $e)
		{
			return;
		}

		// Does the cookie actually exist?
		$cookieValue = $app->input->cookie->get($cookieName);

		if (empty($cookieValue))
		{
			return;
		}

		// Make sure the cookie contents are valid
		$cookieArray = explode('.', $cookieValue);

		// If the cookie is invalid expire it immediately; someone tried to do something funky
		if (count($cookieArray) !== 2)
		{
			return;
		}

		$cookieArray = explode('.', $cookieValue);

		// Filter series since we're going to use it in the query
		$filter = new InputFilter();
		$series = $filter->clean($cookieArray[1], 'ALNUM');

		$db = $this->getDbo();

		// Remove the record from the database
		$query = $db->getQuery(true)
			->delete('#__user_keys')
			->where($db->quoteName('series') . ' = ' . $db->quote($series));

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (RuntimeException $e)
		{
			// Continue even if the query fails
		}

		// Destroy the cookie
		$app->input->cookie->set($cookieName, '', 1, $app->get('cookie_path', '/'), $app->get('cookie_domain', ''));
	}

	/**
	 * Gets the browser ID
	 *
	 * @return  string
	 * @since   3.3.0
	 */
	public function getBrowserId(): string
	{
		// Fallback to the browser ID set in the session
		if (is_null($this->browserId))
		{
			$browserId = Factory::getApplication()->getSession()->get('com_loginguard.browserId', null);
			$this->setBrowserId($browserId ?? '');
		}

		return $this->browserId;
	}

	/**
	 * Sets the browser ID
	 *
	 * @param   string|null  $browserId
	 *
	 * @return  self
	 * @since   3.3.0
	 */
	public function setBrowserId(string $browserId): self
	{
		$this->browserId = $browserId;

		return $this;
	}

	/**
	 * Get the username for which Remember Me cookies are being managed
	 *
	 * @return  string
	 * @since   3.3.0
	 */
	public function getUsername(): string
	{
		if (empty($this->username))
		{
			$user = Factory::getApplication()->getIdentity() ?: Factory::getUser();
			$this->setUsername($user->username ?? '');
		}

		return $this->username;
	}

	/**
	 * Set the username for which Remember Me cookies are being managed
	 *
	 * @param   string  $username
	 *
	 * @return  self
	 * @since   3.3.0
	 */
	public function setUsername(string $username): self
	{
		$this->username = $username;

		return $this;
	}

	/**
	 * Stores or refreshes the token in the database
	 *
	 * This creates or updates a database record for the user, browser ID and unique series with the currently valid
	 * token and its expiration date and time.
	 *
	 * Note that the token expiration is NOT updated if the record already exists. The idea of the Remember Me feature
	 * is that it will not ask you for 2SV for a number of days. After that period you need to go through 2SV again to
	 * make sure that you still have valid access to the 2SV method.
	 *
	 * @param   string  $series      Unique series for the token
	 * @param   string  $token       The token to write to the database
	 * @param   int     $expiration  Token's expiration date; only for the FIRST time we store this device's cookie
	 *
	 * @return  void
	 * @since   3.3.0
	 */
	private function storeToken(string $series, string $token, int $expiration): void
	{
		$cookieName  = $this->getCookieName();
		$username    = $this->getUsername();
		$hashedToken = UserHelper::hashPassword($token);

		if (empty($cookieName))
		{
			return;
		}

		$db     = $this->getDbo();
		$query  = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__user_keys'))
			->where($db->quoteName('user_id') . ' = ' . $db->quote($username))
			->where($db->quoteName('series') . ' = ' . $db->quote($series))
			->where($db->quoteName('uastring') . ' = ' . $db->quote($cookieName));
		$record = $db->setQuery($query)->loadObject();

		if (empty($record))
		{
			// Create a new record
			$query = $db->getQuery(true)
				->insert($db->quoteName('#__user_keys'))
				->set($db->quoteName('user_id') . ' = ' . $db->quote($username))
				->set($db->quoteName('series') . ' = ' . $db->quote($series))
				->set($db->quoteName('uastring') . ' = ' . $db->quote($cookieName))
				->set($db->quoteName('time') . ' = ' . $expiration);
		}
		else
		{
			// Update existing record
			$query = $db->getQuery(true)
				->update($db->quoteName('#__user_keys'))
				->where($db->quoteName('user_id') . ' = ' . $db->quote($username))
				->where($db->quoteName('series') . ' = ' . $db->quote($series))
				->where($db->quoteName('uastring') . ' = ' . $db->quote($cookieName));
		}

		$query->set($db->quoteName('token') . ' = ' . $db->quote($hashedToken));

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (RuntimeException $e)
		{
			// No problem if this failed; the user will just have to go through 2SV again.
		}
	}

	/**
	 * Creates a unique series identifier for the Remember Me cookie. Null if uniqueness cannot be guaranteed.
	 *
	 * This is the part of the cookie which will not change throughout its entire lifetime.
	 *
	 * @return  string|null
	 * @since   3.3.0
	 */
	private function getUniqueSeries(): ?string
	{
		$unique     = false;
		$errorCount = 0;
		$db         = $this->getDbo();

		do
		{
			$series = UserHelper::genRandomPassword(64);
			$query  = $db->getQuery(true)
				->select($db->quoteName('series'))
				->from($db->quoteName('#__user_keys'))
				->where($db->quoteName('series') . ' = ' . $db->quote($series));

			try
			{
				$unique = $db->setQuery($query)->loadResult() === null;
			}
			catch (RuntimeException $e)
			{
				// We'll let this query fail up to 5 times before giving up, there's probably a bigger issue at this point
				if (++$errorCount >= 5)
				{
					$series = null;

					break;
				}
			}
		} while ($unique === false);

		return $series;
	}

	/**
	 * Removes all expired user tokens
	 *
	 * This also removes tokens which have nothing to do with us. That's the same behavior as Joomla itself.
	 *
	 * @return  void
	 * @since   3.3.0
	 */
	private function removeExpiredTokens(): void
	{
		$db    = $this->getDbo();
		$query = $db->getQuery(true)
			->delete('#__user_keys')
			->where($db->quoteName('time') . ' < ' . $db->quote(time()));

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (RuntimeException $e)
		{
			// No problem if it failed
		}
	}

	/**
	 * Returns the matching token records for the specified cookie name and series
	 *
	 * The cookie name includes the browser ID. Therefore we are returning the tokens for the specific browser and
	 * unique series.
	 *
	 * @param   string  $cookieName
	 * @param   string  $series
	 *
	 * @return array
	 */
	private function getTokenRecords(string $cookieName, string $series): array
	{
		$db    = $this->getDbo();
		$query = $db->getQuery(true)
			->select($db->quoteName(['user_id', 'token', 'series', 'time']))
			->from($db->quoteName('#__user_keys'))
			->where($db->quoteName('series') . ' = ' . $db->quote($series))
			->where($db->quoteName('uastring') . ' = ' . $db->quote($cookieName))
			->order($db->quoteName('time') . ' DESC');

		try
		{
			$results = $db->setQuery($query)->loadObjectList();
		}
		catch (RuntimeException $e)
		{
			$results = [];
		}

		return $results ?? [];
	}
}