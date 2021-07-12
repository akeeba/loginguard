<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\User\User;

defined('_JEXEC') || die();

if (!class_exists('ActionLogPlugin', true))
{
	JLoader::register('ActionLogPlugin', JPATH_ADMINISTRATOR . '/components/com_actionlogs/libraries/actionlogplugin.php');
	JLoader::register('ActionlogsHelper', JPATH_ADMINISTRATOR . '/components/com_actionlogs/helpers/actionlogs.php');
}

/**
 * LoginGuard integration with Joomla's User Actions Log
 *
 * @since  3.1.2
 */
class PlgActionlogLoginguard extends ActionLogPlugin
{
	/**
	 * The Joomla application object.
	 *
	 * @var   CMSApplication
	 * @since 5.0.0
	 */
	protected $app;

	/**
	 * The name of the component we are logging actions for
	 *
	 * @var   string
	 * @since 5.0.0
	 */
	private $defaultExtension = 'com_loginguard';

	/**
	 * Logs converting from Joomla's TFA
	 *
	 * @return  void
	 */
	public function onComLoginguardControllerConvertAfterConvert()
	{
		$this->logUserAction('', 'PLG_ACTIONLOG_LOGINGUARD_ACTION_CONVERT', 'com_loginguard');
	}

	/**
	 * Logs showing the TSV selection method
	 *
	 * @return  void
	 */
	public function onComLoginguardCaptiveShowSelect()
	{
		$this->logUserAction('', 'PLG_ACTIONLOG_LOGINGUARD_ACTION_CAPTIVE_SELECT', 'com_loginguard');
	}

	/**
	 * Logs showing the captive login page
	 *
	 * @param   string  $methodTitleEscaped
	 *
	 * @return  void
	 */
	public function onComLoginguardCaptiveShowCaptive(string $methodTitleEscaped)
	{
		$this->logUserAction($methodTitleEscaped, 'PLG_ACTIONLOG_LOGINGUARD_ACTION_CAPTIVE_CAPTIVE', 'com_loginguard');
	}

	/**
	 * Log displaying a user's Two Step Verification methods
	 *
	 * @param   LoginGuardViewMethods  $view
	 *
	 * @return  void
	 */
	public function onComLoginGuardViewMethodsAfterDisplay($view)
	{
		$layout = $view->getLayout();
		$key    = 'PLG_ACTIONLOG_LOGINGUARD_ACTION_METHODS_SHOW';

		if ($layout == 'firsttime')
		{
			$key = 'PLG_ACTIONLOG_LOGINGUARD_ACTION_METHODS_FIRSTTIME';
		}

		$this->logUserAction('', $key, 'com_loginguard');
	}

	/**
	 * Log regenerating backup codes
	 *
	 * @return  void
	 */
	public function onComLoginguardControllerMethodAfterRegenbackupcodes()
	{
		$this->logUserAction('', 'PLG_ACTIONLOG_LOGINGUARD_ACTION_METHOD_REGENBACKUPCODES', 'com_loginguard');
	}

	/**
	 * Log adding a new TSV method
	 *
	 * @return  void
	 */
	public function onComLoginguardControllerMethodBeforeAdd(User $user, string $method)
	{
		$this->logUserAction([
			'method'    => $method,
			'user_id'   => $user->id,
			'otheruser' => $user->username,
		], 'PLG_ACTIONLOG_LOGINGUARD_ACTION_METHOD_ADD', 'com_loginguard');
	}

	/**
	 * Log editing a TSV method
	 *
	 * @return  void
	 */
	public function onComLoginguardControllerMethodBeforeEdit(int $id, User $user)
	{
		$this->logUserAction([
			'id'        => $id,
			'user_id'   => $user->id,
			'otheruser' => $user->username,
		], 'PLG_ACTIONLOG_LOGINGUARD_ACTION_METHOD_EDIT', 'com_loginguard');
	}

	/**
	 * Log removing a TSV method
	 *
	 * @return  void
	 */
	public function onComLoginguardControllerMethodBeforeDelete(int $id, User $user)
	{
		$this->logUserAction([
			'id'        => $id,
			'user_id'   => $user->id,
			'otheruser' => $user->username,
		], 'PLG_ACTIONLOG_LOGINGUARD_ACTION_METHOD_DELETE', 'com_loginguard');
	}

	/**
	 * Log saving a TSV method
	 *
	 * @return  void
	 */
	public function onComLoginguardControllerMethodBeforeSave(int $id, User $user)
	{
		$this->logUserAction([
			'id'        => $id,
			'user_id'   => $user->id,
			'otheruser' => $user->username,
		], 'PLG_ACTIONLOG_LOGINGUARD_ACTION_METHOD_SAVE', 'com_loginguard');
	}

	/**
	 * Log completely disabling TSV
	 *
	 * @return  void
	 */
	public function onComLoginguardControllerMethodsBeforeDisable(User $user)
	{
		$this->logUserAction([
			'user_id'   => $user->id,
			'otheruser' => $user->username,
		], 'PLG_ACTIONLOG_LOGINGUARD_ACTION_METHODS_DISABLE', 'com_loginguard');
	}

	/**
	 * Log opting out of TSV
	 *
	 * @return  void
	 */
	public function onComLoginguardControllerMethodsBeforeDontshowthisagain(User $user)
	{
		$this->logUserAction([
			'user_id'   => $user->id,
			'otheruser' => $user->username,
		], 'PLG_ACTIONLOG_LOGINGUARD_ACTION_METHODS_DONTSHOWTHISAGAIN', 'com_loginguard');
	}

	/**
	 * Log TSV failure due to invalid method
	 *
	 * @return  void
	 */
	public function onComLoginguardCaptiveValidateInvalidMethod()
	{
		$this->logUserAction('', 'PLG_ACTIONLOG_LOGINGUARD_ACTION_VALIDATE_INVALID_METHOD', 'com_loginguard');
	}

	/**
	 * Log TSV failure
	 *
	 * @param   string  $methodTitle
	 *
	 * @return  void
	 */
	public function onComLoginguardCaptiveValidateFailed($methodTitle)
	{
		$this->logUserAction(htmlspecialchars($methodTitle), 'PLG_ACTIONLOG_LOGINGUARD_ACTION_VALIDATE_FAILED', 'com_loginguard');
	}

	/**
	 * Log TSV success
	 *
	 * @param   string  $methodTitle
	 *
	 * @return  void
	 */
	public function onComLoginguardCaptiveValidateSuccess($methodTitle)
	{
		$this->logUserAction(htmlspecialchars($methodTitle), 'PLG_ACTIONLOG_LOGINGUARD_ACTION_VALIDATE_SUCCESS', 'com_loginguard');
	}

	/**
	 * Log a user action.
	 *
	 * This is a simple wrapper around self::addLog
	 *
	 * @param   string|array  $title               Language key for title or an array of additional data to record in
	 *                                             the audit log.
	 * @param   string        $logText             Language key describing the user action taken.
	 * @param   string|null   $extension           The name of the extension being logged (default: use
	 *                                             $this->defaultExtension).
	 * @param   User|null     $user                User object taking this action (default: currently logged in user).
	 *
	 * @return  void
	 *
	 * @see     self::addLog
	 * @since   5.0.0
	 */
	private function logUserAction($title, string $logText, ?string $extension = null, ?User $user = null): void
	{
		// Make sure I am in the front- or backend application
		if (empty($this->app) || (!$this->app->isClient('administrator') && !$this->app->isClient('site')))
		{
			return;
		}

		// Get the user if not defined
		$user = $user ?? ($this->app->getIdentity() ?: Factory::getUser());

		// No log for guests
		if (empty($user) || ($user->guest))
		{
			return;
		}

		// Default extension if none defined
		$extension = $extension ?? $this->defaultExtension;

		$message = [
			'username'    => $user->username,
			'accountlink' => 'index.php?option=com_users&task=user.edit&id=' . $user->id,
		];

		if (!is_array($title))
		{
			$title = [
				'title' => $title,
			];
		}

		$message = array_merge($message, $title);

		$this->addLog([$message], $logText, $extension, $user->id);
	}
}
