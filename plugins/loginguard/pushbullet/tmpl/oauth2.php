<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
defined('_JEXEC') || die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

$baseURL = Uri::root();
$backend = Factory::getApplication()->isClient('administrator') ? 1 : 0;

$redirectURL = urlencode($baseURL . 'index.php?option=com_loginguard&view=Callback&task=callback&method=pushbullet');
$oauth2URL   = "https://www.pushbullet.com/authorize?client_id={$this->clientId}&redirect_uri=$redirectURL&response_type=code&state=$backend"

?>
<div id="loginguard-pushbullet-controls" style="margin: 0.5em 0">
	<a class="btn btn-primary btn-lg btn-big" href="<?= $oauth2URL ?>">
		<span class="icon icon-lock"></span>
		<?= Text::_('PLG_LOGINGUARD_PUSHBULLET_LBL_OAUTH2BUTTON'); ?>
	</a>
</div>