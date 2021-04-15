<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2017 Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
defined('_JEXEC') or die;

/**
 * The following code loads the front-end view template to avoid code duplication.
 *
 * If your backend template requires completely different HTML than the provided front-end template you need to copy the
 * (front-end!) view template file:
 * 		components/com_loginguard/views/captive/tmpl/select.php
 * to:
 * 		administrator/templates/YOUR_TEMPLATE/html/com_loginguard/captive/select.php
 * You can customize the latter file.
 */
$this->_setPath('template', JPATH_SITE . '/components/com_loginguard/views/' . $this->getName() . '/tmpl');
$result = $this->loadTemplate();

if ($result instanceof Exception)
{
	return $result;
}

echo $result;