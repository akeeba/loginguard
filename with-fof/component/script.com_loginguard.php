<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Protect from unauthorized access
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\Adapter\ComponentAdapter;

defined('_JEXEC') or die();

// Load FOF if not already loaded
if (!defined('FOF40_INCLUDED') && !@include_once(JPATH_LIBRARIES . '/fof40/include.php'))
{
	throw new RuntimeException('FOF 4.0 is not installed');
}

class Com_LoginguardInstallerScript extends FOF40\InstallScript\Component
{
	/**
	 * The component's name
	 *
	 * @var   string
	 */
	public $componentName = 'com_loginguard';

	/**
	 * The title of the component (printed on installation and uninstallation messages)
	 *
	 * @var   string
	 */
	protected $componentTitle = 'Akeeba LoginGuard';

	/**
	 * The minimum PHP version required to install this extension
	 *
	 * @var   string
	 */
	protected $minimumPHPVersion = '7.2.0';

	/**
	 * The minimum Joomla! version required to install this extension
	 *
	 * @var   string
	 */
	protected $minimumJoomlaVersion = '3.9.0';

	/**
	 * The maximum Joomla! version this extension can be installed on
	 *
	 * @var   string
	 */
	protected $maximumJoomlaVersion = '4.0.99999';

	protected $removeFilesAllVersions = [
	        'files' => [
                // Obsolete Joomla! core MVC files from version 1.x
		        'administrator/components/com_loginguard/controller.php',
		        'components/com_loginguard/controller.php',

                // Obsolete cacert.pem from version 1.x; we are now using the one in FOF
		        'components/com_loginguard/cacert.pem',

		        // Remove all obsolete methods view file, except for the list.xml metadata file
		        'components/com_loginguard/views/method/view.html.php',

				// Moving to FOF 4 / FEF 2
				'media/plg_loginguard_smsapi/css/telinput.css',
				'media/plg_loginguard_smsapi/css/telinput.min.css',
				'media/plg_loginguard_smsapi/js/telinput.js',
				'media/plg_loginguard_smsapi/js/telinput.map',
				'media/plg_loginguard_smsapi/js/telinput.min.js',
				'media/plg_loginguard_smsapi/js/telinput.min.map',
				'media/plg_loginguard_smsapi/js/utils.map',
				'media/plg_loginguard_smsapi/js/utils.min.js',
				'media/plg_loginguard_smsapi/js/utils.min.map',

				'media/plg_loginguard_totp/js/data.min.js',
				'media/plg_loginguard_totp/js/qrcode.map',
				'media/plg_loginguard_totp/js/qrcode.min.js',
				'media/plg_loginguard_totp/js/qrcode.min.map',

            ],
	        'folders' => [
		        // Obsolete Joomla! core MVC files from version 1.x
                'administrator/components/com_loginguard/controllers',
                'administrator/components/com_loginguard/helpers',
                'administrator/components/com_loginguard/models',
                'administrator/components/com_loginguard/views',
		        'administrator/components/com_loginguard/sql/mysql',
		        'administrator/components/com_loginguard/sql/postgresql',
		        'administrator/components/com_loginguard/sql/sqlazure',
                'components/com_loginguard/controllers',
                'components/com_loginguard/helpers',
                'components/com_loginguard/models',
                // Remove all obsolete front-end views, except for the list.xml metadata file
                'components/com_loginguard/views/captive',
                'components/com_loginguard/views/method',
                'components/com_loginguard/views/methods',
                // Obsolete custom renderer
		        'components/com_loginguard/Render',
		        // Common tables (they're installed by FOF)
		        'administrator/components/com_loginguard/sql/common',
				// Obsolete view template directories
		        'administrator/components/com_loginguard/View/Convert/tmpl',
		        'administrator/components/com_loginguard/View/Users/tmpl',
		        'administrator/components/com_loginguard/View/Welcome/tmpl',
		        'components/com_loginguard/View/Captive/tmpl',
		        'components/com_loginguard/View/Method/tmpl',
		        'components/com_loginguard/View/Methods/tmpl',

		        // Rename ViewTemplates to tmpl
		        'administrator/components/com_loginguard/ViewTemplates',
		        'components/com_loginguard/ViewTemplates',
	        ]
    ];

	/**
	 * Runs on installation
	 *
	 * @param   JInstallerAdapterComponent $parent The parent object
	 *
	 * @return  void
	 */
	public function install($parent)
	{
		if (!defined('AKEEBA_THIS_IS_INSTALLATION_FROM_SCRATCH'))
		{
			define('AKEEBA_THIS_IS_INSTALLATION_FROM_SCRATCH', 1);
		}
	}

	/** @inheritDoc */
	public function preflight(string $type, ComponentAdapter $parent): bool
	{
		$ret = parent::preflight($type, $parent);

		if (!$ret)
		{
			return $ret;
		}

		/**
		 * Remove the vendor folder on update.
		 *
		 * The folder will be reinstalled right away. The reason I am removing it is that updated dependencies assume
		 * they are installed on a clean slate (that's how Composer does it). If I kee the old files I cause all sorts
		 * of problems.
		 */
		if ($type == 'update')
		{
			$this->removeFilesAndFolders([
				'files'   => [],
				'folders' => [
					'administrator/components/com_loginguard/vendor',
				],
			]);
		}

		return true;
	}

	/**
	 * Runs after install, update or discover_update. In other words, it executes after Joomla! has finished installing
	 * or updating your component. This is the last chance you've got to perform any additional installations, clean-up,
	 * database updates and similar housekeeping functions.
	 *
	 * @param   string                      $type   install, update or discover_update
	 * @param   \JInstallerAdapterComponent $parent Parent object
	 *
	 * @throws  Exception
	 *
	 * @return  void
	 */
	public function postflight(string $type, ComponentAdapter $parent): void
	{
		// Let's install common tables
		$container = null;
		$model     = null;

		if (class_exists('FOF40\\Container\\Container'))
		{
			try
			{
				$container = \FOF40\Container\Container::getInstance('com_loginguard');
			}
			catch (\Exception $e)
			{
				$container = null;
			}
		}

		if (is_object($container) && class_exists('FOF40\\Container\\Container') && ($container instanceof \FOF40\Container\Container))
		{
			/** @var \Akeeba\LoginGuard\Admin\Model\UsageStatistics $model */
			try
			{
				$model = $container->factory->model('UsageStatistics')->tmpInstance();
			}
			catch (\Exception $e)
			{
				$model = null;
			}
		}

		parent::postflight($type, $parent);

		// Add ourselves to the list of extensions depending on Akeeba FEF
		$this->addDependency('file_fef', $this->componentName);
	}


	/**
	 * Override this method to display a custom component installation message if you so wish
	 *
	 * @param  \JInstallerAdapterComponent  $parent  Parent class calling us
	 */
	protected function renderPostInstallation(ComponentAdapter $parent): void
	{
		try
		{
			$this->warnAboutJSNPowerAdmin();
		}
		catch (Exception $e)
		{
			// Don't sweat if the site's db croaks while I'm checking for 3PD software that causes trouble
		}

		?>
		<h2>Welcome to Akeeba LoginGuard</h2>

		<fieldset>
			<p>
				By installing this component you are implicitly accepting
				<a href="https://www.akeeba.com/license.html">its license (GNU GPLv3)</a> and our
				<a href="https://www.akeeba.com/privacy-policy.html">Terms of Service</a>,
				including our Support Policy.
			</p>
		</fieldset>
	<?php
	}

	/**
	 * Override this method to display a custom component uninstallation message if you so wish
	 *
	 * @param  \JInstallerAdapterComponent  $parent  Parent class calling us
	 */
	protected function renderPostUninstallation(ComponentAdapter $parent): void
	{
		?>
		<h2>Akeeba LoginGuard was uninstalled</h2>
		<p>We are sorry that you decided to uninstall Akeeba LoginGuard. Please let us know why by using the <a
			href="https://www.akeeba.com/contact-us.html" target="_blank">Contact Us form on our site</a>. We
			appreciate your feedback; it helps us develop better software!</p>
		<?php
	}

	/**
	 * The PowerAdmin extension makes menu items disappear. People assume it's our fault. JSN PowerAdmin authors don't
	 * own up to their software's issue. I have no choice but to warn our users about the faulty third party software.
	 */
	private function warnAboutJSNPowerAdmin()
	{
		$db = Factory::getDbo();

		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->qn('#__extensions'))
			->where($db->qn('type') . ' = ' . $db->q('component'))
			->where($db->qn('element') . ' = ' . $db->q('com_poweradmin'))
			->where($db->qn('enabled') . ' = ' . $db->q('1'));
		$hasPowerAdmin = $db->setQuery($query)->loadResult();

		if (!$hasPowerAdmin)
		{
			return;
		}

		$query = $db->getQuery(true)
					->select('manifest_cache')
					->from($db->qn('#__extensions'))
					->where($db->qn('type') . ' = ' . $db->q('component'))
					->where($db->qn('element') . ' = ' . $db->q('com_poweradmin'))
					->where($db->qn('enabled') . ' = ' . $db->q('1'));
		$paramsJson = $db->setQuery($query)->loadResult();

		$className = class_exists('JRegistry') ? 'JRegistry' : '\Joomla\Registry\Registry';

		/** @var \Joomla\Registry\Registry $jsnPAManifest */
		$jsnPAManifest = new $className();
		$jsnPAManifest->loadString($paramsJson, 'JSON');
		$version = $jsnPAManifest->get('version', '0.0.0');

		if (version_compare($version, '2.1.2', 'ge'))
		{
			return;
		}

		echo <<< HTML
<div class="well" style="margin: 2em 0;">
<h1 style="font-size: 32pt; line-height: 120%; color: red; margin-bottom: 1em">WARNING: Menu items for {$this->componentTitle} might not be displayed on your site.</h1>
<p style="font-size: 18pt; line-height: 150%; margin-bottom: 1.5em">
	We have detected that you are using JSN PowerAdmin version $version on your site. This is a very old version which ignores Joomla! standards and
	<b>hides</b> the Component menu items to {$this->componentTitle} in the administrator backend of your site. We have contacted the developer of
	JSN PowerAdmin about this issue and we are told it's been fixed since version 2.1.2 of JSN PowerAdmin. Please update JSN PowerAdmin. If you can
	still not see the menu item to {$this->componentTitle} please contact the developers of JSN PowerAdmin for support regarding this issue; we can
	not offer support for third party software. 
</p>
<p style="font-size: 18pt; line-height: 120%; color: green;">
	Tip: You can disable JSN PowerAdmin to see the menu items to {$this->componentTitle}.
</p>
</div>

HTML;

	}

}
