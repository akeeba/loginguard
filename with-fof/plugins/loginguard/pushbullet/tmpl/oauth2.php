<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

// Prevent direct access
defined('_JEXEC') || die;

$baseURL     = Uri::base();
$backend     = 0;

if (substr($baseURL, -14) == 'administrator/')
{
	$baseURL = substr($baseURL, 0, -14);
	$backend = 1;
}

$redirectURL = urlencode($baseURL . 'index.php?option=com_loginguard&view=Callback&task=callback&method=pushbullet');
$oauth2URL = "https://www.pushbullet.com/authorize?client_id={$this->clientId}&redirect_uri=$redirectURL&response_type=code&state=$backend"

?>
<div id="loginguard-pushbullet-controls" class="akeeba-form-group--pull-right">
    <div class="akeeba-form-group--actions">
        <a class="akeeba-btn--primary--large" href="<?= $oauth2URL ?>">
            <span class="akion-locked"></span>
			<?= Text::_('PLG_LOGINGUARD_PUSHBULLET_LBL_OAUTH2BUTTON'); ?>
        </a>
    </div>
</div>
