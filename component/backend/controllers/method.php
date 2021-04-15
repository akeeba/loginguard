<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
defined('_JEXEC') or die;

// Use the class from the front-end
JLoader::register('LoginGuardControllerMethod', JPATH_SITE . '/components/com_loginguard/controllers/method.php');
