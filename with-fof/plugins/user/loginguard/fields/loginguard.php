<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

// Prevent direct access
defined('_JEXEC') || die;

class JFormFieldLoginguard extends FormField
{
	/**
	 * Element name
	 *
	 * @var   string
	 */
	protected $_name = 'Loginguard';

	function getInput()
	{
		$user_id = $this->form->getData()->get('id', null);

		if (is_null($user_id))
		{
			return Text::_('PLG_USER_LOGINGUARD_ERR_NOUSER');
		}

		try
		{
			// Capture the output instead of pushing it to the browser
			@ob_start();

			// Render the other component's view
			FOF40\Container\Container::getInstance('com_loginguard', [
				'tempInstance' => true,
				'input'        => [
					'view'      => 'Methods',
					'returnurl' => base64_encode(Uri::getInstance()->toString()),
					'user_id'   => $user_id,
				],
			])->dispatcher->dispatch();

			// Get the output...
			$content = ob_get_contents();

			// ...and close the output buffer
			ob_end_clean();
		}
		catch (\Exception $e)
		{
			// Whoops! The component blew up. Close the output buffer...
			ob_end_clean();
			// ...and indicate that we have no content.
			$content = Text::_('PLG_USER_LOGINGUARD_ERR_NOCOMPONENT');
		}

		if (!class_exists('Akeeba\\LoginGuard\\Site\\View\\Methods\\Html'))
		{
			$content = Text::_('PLG_USER_LOGINGUARD_ERR_NOCOMPONENT');
		}

		// Display the content
		return $content;
	}
}
