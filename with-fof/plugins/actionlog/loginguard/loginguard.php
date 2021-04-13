<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use FOF40\Container\Container;
use FOF40\Controller\Controller;
use FOF40\View\View;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Plugin\CMSPlugin;

defined('_JEXEC') || die();

/**
 * LoginGuard integration with Joomla's User Actions Log
 *
 * @since  3.1.2
 */
class plgActionlogLoginguard extends CMSPlugin
{
	/** @var Container */
	private $container;

	/**
	 * Constructor
	 *
	 * @param       object $subject The object to observe
	 * @param       array  $config  An array that holds the plugin configuration
	 *
	 * @since       6.4.0
	 */
	public function __construct(& $subject, $config)
	{
		// Make sure LoginGuard is installed
		if (!file_exists(JPATH_ADMINISTRATOR . '/components/com_loginguard'))
		{
			return;
		}

		// Make sure LoginGuard is enabled
		if ( !ComponentHelper::isEnabled('com_loginguard'))
		{
			return;
		}

		// Load FOF
		if (!defined('FOF40_INCLUDED') && !@include_once(JPATH_LIBRARIES . '/fof40/include.php'))
		{
			return;
		}

		$this->container = Container::getInstance('com_loginguard');

		// No point in logging guest actions
		if ($this->container->platform->getUser()->guest)
		{
			return;
		}

		// If any of the above statement returned, our plugin is not attached to the subject, so it's basically disabled
		parent::__construct($subject, $config);
	}

	/**
	 * Logs converting from Joomla's TFA
	 *
	 * @param   Controller  $controller  The controller we are called from
	 *
	 * @return  void
	 */
	public function onComLoginguardControllerConvertAfterConvert(Controller $controller)
	{
		$this->container->platform->logUserAction('', 'PLG_ACTIONLOG_LOGINGUARD_ACTION_CONVERT', 'com_loginguard');
	}

	/**
	 * Logs showing the TSV selection method
	 *
	 * @return  void
	 */
	public function onComLoginguardCaptiveShowSelect()
	{
		$this->container->platform->logUserAction('', 'PLG_ACTIONLOG_LOGINGUARD_ACTION_CAPTIVE_SELECT', 'com_loginguard');
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
		$this->container->platform->logUserAction($methodTitleEscaped, 'PLG_ACTIONLOG_LOGINGUARD_ACTION_CAPTIVE_CAPTIVE', 'com_loginguard');
	}

	/**
	 * Log displaying a user's Two Step Verification methods
	 *
	 * @param   View  $view
	 *
	 * @return  void
	 */
	public function onComLoginGuardViewMethodsAfterDisplay(View $view)
	{
		$layout = $view->getLayout();
		$key    = 'PLG_ACTIONLOG_LOGINGUARD_ACTION_METHODS_SHOW';

		if ($layout == 'firsttime')
		{
			$key    = 'PLG_ACTIONLOG_LOGINGUARD_ACTION_METHODS_FIRSTTIME';
		}

		$this->container->platform->logUserAction('', $key, 'com_loginguard');
	}

	/**
	 * Log regenerating backup codes
	 *
	 * @param   Controller  $controller
	 *
	 * @return  void
	 */
	public function onComLoginguardControllerMethodAfterRegenbackupcodes(Controller $controller)
	{
		$this->container->platform->logUserAction('', 'PLG_ACTIONLOG_LOGINGUARD_ACTION_METHOD_REGENBACKUPCODES', 'com_loginguard');
	}

	/**
	 * Log adding a new TSV method
	 *
	 * @param   Controller  $controller
	 *
	 * @return  void
	 */
	public function onComLoginguardControllerMethodBeforeAdd(Controller $controller)
	{
		$method = $controller->input->getCmd('method');
		$userId = $controller->input->getInt('user_id', null);
		$user   = $this->container->platform->getUser($userId);

		$this->container->platform->logUserAction([
			'method'   => $method,
			'user_id'  => $userId,
			'otheruser' => $user->username,
		], 'PLG_ACTIONLOG_LOGINGUARD_ACTION_METHOD_ADD', 'com_loginguard');
	}

	/**
	 * Log editing a TSV method
	 *
	 * @param   Controller  $controller
	 *
	 * @return  void
	 */
	public function onComLoginguardControllerMethodBeforeEdit(Controller $controller)
	{
		$id     = $controller->input->getCmd('id');
		$userId = $controller->input->getInt('user_id', null);
		$user   = $this->container->platform->getUser($userId);

		$this->container->platform->logUserAction([
			'id'       => $id,
			'user_id'  => $userId,
			'otheruser' => $user->username,
		], 'PLG_ACTIONLOG_LOGINGUARD_ACTION_METHOD_EDIT', 'com_loginguard');
	}

	/**
	 * Log removing a TSV method
	 *
	 * @param   Controller  $controller
	 *
	 * @return  void
	 */
	public function onComLoginguardControllerMethodBeforeDelete(Controller $controller)
	{
		$id     = $controller->input->getCmd('id');
		$userId = $controller->input->getInt('user_id', null);
		$user   = $this->container->platform->getUser($userId);

		$this->container->platform->logUserAction([
			'id'       => $id,
			'user_id'  => $userId,
			'otheruser' => $user->username,
		], 'PLG_ACTIONLOG_LOGINGUARD_ACTION_METHOD_DELETE', 'com_loginguard');
	}

	/**
	 * Log saving a TSV method
	 *
	 * @param   Controller  $controller
	 *
	 * @return  void
	 */
	public function onComLoginguardControllerMethodBeforeSave(Controller $controller)
	{
		$id     = $controller->input->getCmd('id');
		$userId = $controller->input->getInt('user_id', null);
		$user   = $this->container->platform->getUser($userId);

		$this->container->platform->logUserAction([
			'id'       => $id,
			'user_id'  => $userId,
			'otheruser' => $user->username,
		], 'PLG_ACTIONLOG_LOGINGUARD_ACTION_METHOD_SAVE', 'com_loginguard');
	}

	/**
	 * Log completely disabling TSV
	 *
	 * @param   Controller  $controller
	 *
	 * @return  void
	 */
	public function onComLoginguardControllerMethodsBeforeDisable(Controller $controller)
	{
		$userId = $controller->input->getInt('user_id', null);
		$user   = $this->container->platform->getUser($userId);

		$this->container->platform->logUserAction([
			'user_id'  => $userId,
			'otheruser' => $user->username,
		], 'PLG_ACTIONLOG_LOGINGUARD_ACTION_METHODS_DISABLE', 'com_loginguard');
	}

	/**
	 * Log opting out of TSV
	 *
	 * @param   Controller  $controller
	 *
	 * @return  void
	 */
	public function onComLoginguardControllerMethodsBeforeDontshowthisagain(Controller $controller)
	{
		$userId = $controller->input->getInt('user_id', null);
		$user   = $this->container->platform->getUser($userId);

		$this->container->platform->logUserAction([
			'user_id'  => $userId,
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
		$this->container->platform->logUserAction('', 'PLG_ACTIONLOG_LOGINGUARD_ACTION_VALIDATE_INVALID_METHOD', 'com_loginguard');
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
		$this->container->platform->logUserAction(htmlspecialchars($methodTitle), 'PLG_ACTIONLOG_LOGINGUARD_ACTION_VALIDATE_FAILED', 'com_loginguard');
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
		$this->container->platform->logUserAction(htmlspecialchars($methodTitle), 'PLG_ACTIONLOG_LOGINGUARD_ACTION_VALIDATE_SUCCESS', 'com_loginguard');
	}
}
