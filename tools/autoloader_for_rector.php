<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Define ourselves as a parent file
use FOF40\Autoloader\Autoloader;

// Try to get the path to the Joomla! installation
$joomlaPath = $_SERVER['HOME'] . '/Sites/dev3';

if (isset($_SERVER['JOOMLA_SITE']) && is_dir($_SERVER['JOOMLA_SITE']))
{
	$joomlaPath = $_SERVER['JOOMLA_SITE'];
}

if (!is_dir($joomlaPath))
{
	echo <<< TEXT


CONFIGURATION ERROR

Your configured path to the Joomla site does not exist. Rector requires loading
core Joomla classes to operate properly.

Please set the JOOMLA_SITE environment variable before running Rector.

Example:

JOOMLA_SITE=/var/www/joomla rector process $(pwd) --config rector.yaml \
  --dry-run

I will now error out. Bye-bye!

TEXT;

	throw new InvalidArgumentException("Invalid Joomla site root folder.");
}

// Required to run the boilerplate FOF CLI code
$originalDirectory = getcwd();
chdir($joomlaPath . '/cli');

// Setup and import the base CLI script
$minphp = '7.2.0';

// Boilerplate -- START
define('_JEXEC', 1);

foreach ([__DIR__, getcwd()] as $curdir)
{
	if (file_exists($curdir . '/defines.php'))
	{
		define('JPATH_BASE', realpath($curdir . '/..'));
		require_once $curdir . '/defines.php';

		break;
	}

	if (file_exists($curdir . '/../includes/defines.php'))
	{
		define('JPATH_BASE', realpath($curdir . '/..'));
		require_once $curdir . '/../includes/defines.php';

		break;
	}
}

defined('JPATH_LIBRARIES') || die ('This script must be placed in or run from the cli folder of your site.');

require_once JPATH_LIBRARIES . '/fof40/Cli/Application.php';
// Boilerplate -- END

// Undo the temporary change for the FOF CLI boilerplate code
chdir($originalDirectory);

// Load FOF 3
if (!defined('FOF40_INCLUDED') && !@include_once(JPATH_LIBRARIES . '/fof40/include.php'))
{
	throw new RuntimeException('FOF 4.0 is not installed', 500);
}

// Load Akeeba LoginGuard's autoloader through FOF 3
$container = FOF40\Container\Container::getInstance('com_loginguard');


// Other classes
$autoloader = include(__DIR__ . '/../component/backend/vendor/autoload.php');
$autoloader->addClassMap([
	# Form fields
	'JFormFieldModulePositions'              => __DIR__ . '/../component/backend/Field/Joomla/modulepositions.php',
	'JFormFieldLoginguard'                   => __DIR__ . '/../plugins/user/loginguard/fields/loginguard.php',
	# Post-installation scripts, package and component
	'Pkg_LoginguardInstallerScript'          => __DIR__ . '/../component/script.akeeba.php',
	'Com_LoginguardInstallerScript'          => __DIR__ . '/../component/script.com_loginguard.php',
	# Plugins
	'plgActionlogLoginguard'                 => __DIR__ . '/../plugins/actionlog/loginguard/loginguard.php',
	'plgActionlogLoginguardInstallerScript'  => __DIR__ . '/../plugins/actionlog/loginguard/script.php',
	'PlgLoginguardEmail'                     => __DIR__ . '/../plugins/loginguard/email/email.php',
	'PlgLoginguardEmailInstallerScript'      => __DIR__ . '/../plugins/loginguard/email/script.php',
	'PlgLoginguardFixed'                     => __DIR__ . '/../plugins/loginguard/fixed/fixed.php',
	'PlgLoginguardFixedInstallerScript'      => __DIR__ . '/../plugins/loginguard/fixed/script.php',
	'PlgLoginguardPushbullet'                => __DIR__ . '/../plugins/loginguard/pushbullet/pushbullet.php',
	'PlgLoginguardPushbulletInstallerScript' => __DIR__ . '/../plugins/loginguard/pushbullet/script.php',
	'PlgLoginguardSmsapi'                    => __DIR__ . '/../plugins/loginguard/smsapi/smsapi.php',
	'PlgLoginguardSmsapiInstallerScript'     => __DIR__ . '/../plugins/loginguard/smsapi/script.php',
	'PlgLoginguardTotp'                      => __DIR__ . '/../plugins/loginguard/totp/totp.php',
	'PlgLoginguardTotpInstallerScript'       => __DIR__ . '/../plugins/loginguard/totp/script.php',
	'PlgLoginguardU2f'                       => __DIR__ . '/../plugins/loginguard/u2f/u2f.php',
	'PlgLoginguardU2fInstallerScript'        => __DIR__ . '/../plugins/loginguard/u2f/script.php',
	'PlgLoginguardWebauthn'                  => __DIR__ . '/../plugins/loginguard/webauthn/webauthn.php',
	'PlgLoginguardWebauthnInstallerScript'   => __DIR__ . '/../plugins/loginguard/webauthn/script.php',
	'PlgLoginguardYubikey'                   => __DIR__ . '/../plugins/loginguard/yubikey/yubikey.php',
	'PlgLoginguardYubikeyInstallerScript'    => __DIR__ . '/../plugins/loginguard/yubikey/script.php',
	'PlgSystemLoginguard'                    => __DIR__ . '/../plugins/system/loginguard/loginguard.php',
	'PlgSystemLoginguardInstallerScript'     => __DIR__ . '/../plugins/system/loginguard/script.php',
	'plgUserLoginguard'                      => __DIR__ . '/../plugins/user/loginguard/loginguard.php',
	'PlgUserLoginguardInstallerScript'       => __DIR__ . '/../plugins/user/loginguard/script.php',
	# PushBullet API
	'LoginGuardPushbulletApi'                => __DIR__ . '/../plugins/loginguard/pushbullet/classes/pushbullet.php',
	# U2F API
	'u2flib_server\\U2F'                     => __DIR__ . '/../plugins/loginguard/u2f/classes/u2f.php',
	# Akeeba Usage Stats
	'AkeebaUsagestats'                       => __DIR__ . '/../../usagestats/lib/usagestats.php',
]);

# SMS Api
JLoader::registerNamespace('SMSApi\\', realpath(__DIR__ . '/../plugins/loginguard/smsapi/classes'), false, false, 'psr4');

# WebAuthn
Autoloader::getInstance()->addMap('Akeeba\\LoginGuard\\Webauthn\\', [realpath(__DIR__ . '/../plugins/loginguard/webauthn/Webauthn')]);
