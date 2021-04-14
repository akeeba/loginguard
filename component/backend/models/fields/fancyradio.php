<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') || die();

use Joomla\CMS\Form\FormHelper;

// Prevent PHP fatal errors if this somehow gets accidentally loaded multiple times
if (class_exists('JFormFieldFancyradio'))
{
	return;
}

// Load the base form field class
FormHelper::loadFieldClass('radio');

/**
 * Yes/No switcher, compatible with Joomla 3 and 4
 *
 * @noinspection PhpUnused
 */
class JFormFieldFancyradio extends JFormFieldRadio
{
	public function __construct($form = null)
	{
		// Joomla 3.x. Yes, 3.10 does have the layout but I am playing it safe.
		$this->layout = 'joomla.form.field.radio';

		// Joomla 4.0 and later.
		if (version_compare(JVERSION, '3.999.999', 'gt'))
		{
			$this->layout = 'joomla.form.field.radio.switcher';
		}

		parent::__construct($form);
	}
}