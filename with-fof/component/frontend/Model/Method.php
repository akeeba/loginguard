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
use Joomla\CMS\Language\Text as JText;
use Joomla\CMS\User\User;

// Protect from unauthorized access
defined('_JEXEC') || die();

/**
 * Two Step Verification method management model
 *
 * @since       2.0.0
 */
class Method extends Model
{
	/**
	 * List of TFA methods
	 *
	 * @var   array
	 * @since 2.0.0
	 */
	protected $tfaMethods = null;

	/**
	 * Is the specified TFA method available?
	 *
	 * @param   string  $method      The method to check.
	 *
	 * @return  bool
	 * @since   2.0.0
	 */
	public function methodExists($method)
	{
		if (!is_array($this->tfaMethods))
		{
			$this->populateTfaMethods();
		}

		return isset($this->tfaMethods[$method]);
	}

	/**
	 * Get the specified TFA method's record
	 *
	 * @param   string  $method      The method to retrieve.
	 *
	 * @return  array
	 * @since   2.0.0
	 */
	public function getMethod($method)
	{
		if (!$this->methodExists($method))
		{
			return [
				'name'          => $method,
				'display'       => '',
				'shortinfo'     => '',
				'image'         => '',
				'canDisable'    => true,
				'allowMultiple' => true,
			];
		}

		return $this->tfaMethods[$method];
	}

	/**
	 * Get the specified TFA record. It will return a fake default record when no record ID is specified.
	 *
	 * @param   User  $user  The user record. Null to use the currently logged in user.
	 *
	 * @return  \Akeeba\LoginGuard\Site\Model\Tfa
	 * @since   2.0.0
	 */
	public function getRecord($user = null)
	{
		if (is_null($user))
		{
			$user = $this->container->platform->getUser();
		}

		$defaultRecord = $this->getDefaultRecord($user);
		$id            = (int) $this->getState('id', 0);

		if ($id <= 0)
		{
			return $defaultRecord;
		}

		/** @var TfaRecord $tfaModel */
		$tfaModel = $this->container->factory->model('Tfa')->tmpInstance();

		try
		{
			$record = $tfaModel->findOrFail($id);
		}
		catch (Exception $e)
		{
			return $defaultRecord;
		}

		if (!$this->methodExists($record->method))
		{
			return $defaultRecord;
		}

		return $record;
	}

	/**
	 * @param   User   $user        The user record. Null to use the currently logged in user.
	 *
	 * @return  array
	 * @since   2.0.0
	 */
	public function getRenderOptions(User $user = null)
	{
		if (is_null($user))
		{
			$user = $this->container->platform->getUser();
		}

		$renderOptions = [
			// Default title if you are setting up this TFA method for the first time
			'default_title'  => '',
			// Custom HTML to display above the TFA setup form
			'pre_message'    => '',
			// Heading for displayed tabular data. Typically used to display a list of fixed TFA codes, TOTP setup parameters etc
			'table_heading'  => '',
			// Any tabular data to display (label => custom HTML). See above
			'tabular_data'   => [],
			// Hidden fields to include in the form (name => value)
			'hidden_data'    => [],
			// How to render the TFA setup code field. "input" (HTML input element) or "custom" (custom HTML)
			'field_type'     => 'input',
			// The type attribute for the HTML input box. Typically "text" or "password". Use any HTML5 input type.
			'input_type'     => 'text',
			// Pre-filled value for the HTML input box. Typically used for fixed codes, the fixed YubiKey ID etc.
			'input_value'    => '',
			// Placeholder text for the HTML input box. Leave empty if you don't need it.
			'placeholder'    => '',
			// Label to show above the HTML input box. Leave empty if you don't need it.
			'label'          => '',
			// Custom HTML. Only used when field_type = custom.
			'html'           => '',
			// Should I show the submit button (apply the TFA setup)?
			'show_submit'    => true,
			// onclick handler for the submit button (apply the TFA setup)
			'submit_onclick' => '',
			// Additional CSS classes for the submit button (apply the TFA setup)
			'submit_class' => '',
			// Custom HTML to display below the TFA setup form
			'post_message'   => '',
			// A URL with help content for this method to display to the user
			'help_url'       => '',
		];

		$record  = $this->getRecord($user);
		$results = $this->container->platform->runPlugins('onLoginGuardTfaGetSetup', [$record]);

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
	 * Return the title to use for the page
	 *
	 * @return  string
	 * @since   2.0.0
	 */
	public function getPageTitle()
	{
		$task    = $this->getState('task', 'edit');
		$langKey = "COM_LOGINGUARD_HEAD_{$task}_PAGE";

		return JText::_($langKey);
	}

	/**
	 * Populate the list of TFA methods
	 *
	 * @since   2.0.0
	 */
	private function populateTfaMethods()
	{
		$this->tfaMethods = [];
		$tfaMethods       = Tfa::getTfaMethods();

		if (empty($tfaMethods))
		{
			return;
		}

		foreach ($tfaMethods as $method)
		{
			$this->tfaMethods[$method['name']] = $method;
		}

		// We also need to add the backup codes method
		$this->tfaMethods['backupcodes'] = [
			'name'          => 'backupcodes',
			'display'       => JText::_('COM_LOGINGUARD_LBL_BACKUPCODES'),
			'shortinfo'     => JText::_('COM_LOGINGUARD_LBL_BACKUPCODES_DESCRIPTION'),
			'image'         => 'media/com_loginguard/images/emergency.svg',
			'canDisable'    => false,
			'allowMultiple' => false,
		];
	}

	/**
	 * Get the default TFA method for the user
	 *
	 * @param   User  $user  The user record. Null to use the current user.
	 *
	 * @return  \Akeeba\LoginGuard\Site\Model\Tfa
	 * @since   2.0.0
	 */
	protected function getDefaultRecord($user = null)
	{
		if (is_null($user))
		{
			$user = $this->container->platform->getUser();
		}

		$method = $this->getState('method');
		$title  = '';

		if (is_null($this->tfaMethods))
		{
			$this->populateTfaMethods();
		}

		if ($method && isset($this->tfaMethods[$method]))
		{
			$title = $this->tfaMethods[$method]['display'];
		}

		$recordData = [
			'id'      => null,
			'user_id' => $user->id,
			'title'   => $title,
			'method'  => $method,
			'default' => 0,
			'options' => [],
		];

		/** @var \Akeeba\LoginGuard\Site\Model\Tfa $record */
		$record = $this->container->factory->model('Tfa');

		return $record->bind($recordData);
	}

}
