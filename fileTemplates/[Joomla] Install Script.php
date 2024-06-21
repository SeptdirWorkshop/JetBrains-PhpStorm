#set($addToArray = "[]")
#set($path_src = "$path['src']")
#set($path_dest = "$path['dest']")
#set($path_type = "$path['type']")
<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Version;
use Joomla\Database\DatabaseDriver;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class () implements ServiceProviderInterface {
	public function register(Container \$container)
	{
		\$container->set(InstallerScriptInterface::class,
			new class (\$container->get(AdministratorApplication::class)) implements InstallerScriptInterface {
				/**
				 * The application object
				 *
				 * @var  AdministratorApplication
				 *
				 * @since  __DEPLOY_VERSION__
				 */
				protected AdministratorApplication \$app;

				/**
				 * The Database object.
				 *
				 * @var   DatabaseDriver
				 *
				 * @since  __DEPLOY_VERSION__
				 */
				protected DatabaseDriver \$db;

				#if (${minimumJoomla})
				/**
				 * Minimum Joomla version required to install the extension.
				 *
				 * @var  string
				 *
				 * @since  __DEPLOY_VERSION__
				 */
				protected string \$minimumJoomla = '${minimumJoomla}';

				#end
				#if (${minimumPhp})
				/**
				 * Minimum PHP version required to install the extension.
				 *
				 * @var  string
				 *
				 * @since  __DEPLOY_VERSION__
				 */
				protected string \$minimumPhp = '${minimumPhp}';

				#end
				#if (${minimumMySQL})
				/**
				 * Minimum MySQL version required to install the extension.
				 *
				 * @var  string
				 *
				 * @since  __DEPLOY_VERSION__
				 */
				protected string \$minimumMySQL = '${minimumMySQL}';

				#end
				#if (${minimumMariaDb})
				/**
				 * Minimum MariaDb version required to install the extension.
				 *
				 * @var  string
				 *
				 * @since  __DEPLOY_VERSION__
				 */
				protected string \$minimumMariaDb = '${minimumMariaDb}';

				#end
				/**
				 * Language constant for errors.
				 *
				 * @var string
				 *
				 * @since __DEPLOY_VERSION__
				 */
				protected string \$constant = "${languageConstant}";

				#if (${checkExtensionParams})
				/**
				 * Extension params for check.
				 *
				 * @var  array
				 *
				 * @since  __DEPLOY_VERSION__
				 */
				protected array \$extensionParams = [];

				#end
				/**
				 * Update methods.
				 *
				 * @var  array
				 *
				 * @since  __DEPLOY_VERSION__
				 */
				protected array \$updateMethods = [];

				/**
				 * Constructor.
				 *
				 * @param   AdministratorApplication  \$app  The application object.
				 *
				 * @since __DEPLOY_VERSION__
				 */
				public function __construct(AdministratorApplication \$app)
				{
					\$this->app = \$app;
					\$this->db  = Factory::getContainer()->get('DatabaseDriver');
				}

				/**
				 * Function called after the extension is installed.
				 *
				 * @param   InstallerAdapter  \$adapter  The adapter calling this method
				 *
				 * @return  boolean  True on success
				 *
				 * @since   __DEPLOY_VERSION__
				 */
				public function install(InstallerAdapter \$adapter): bool
				{	
					#if (${enablePlugin})				
					\$this->enablePlugin(\$adapter);
					#end

					return true;
				}

				/**
				 * Function called after the extension is updated.
				 *
				 * @param   InstallerAdapter  \$adapter  The adapter calling this method
				 *
				 * @return  boolean  True on success
				 *
				 * @since   __DEPLOY_VERSION__
				 */
				public function update(InstallerAdapter \$adapter): bool
				{
					// Refresh media version
					(new Version())->refreshMediaVersion();

					return true;
				}

				/**
				 * Function called after the extension is uninstalled.
				 *
				 * @param   InstallerAdapter  \$adapter  The adapter calling this method
				 *
				 * @return  boolean  True on success
				 *
				 * @since   __DEPLOY_VERSION__
				 */
				public function uninstall(InstallerAdapter \$adapter): bool
				{
					return true;
				}

				/**
				 * Function called before extension installation/update/removal procedure commences.
				 *
				 * @param   string            \$type     The type of change (install or discover_install, update, uninstall)
				 * @param   InstallerAdapter  \$adapter  The adapter calling this method
				 *
				 * @return  boolean  True on success
				 *
				 * @since   __DEPLOY_VERSION__
				 */
				public function preflight(string \$type, InstallerAdapter \$adapter): bool
				{
					#if(${minimumJoomla} || ${minimumPhp} || ${minimumMySQL} || ${minimumMariaDb})
					// Check compatible
					if (!\$this->checkCompatible())
					{
						return false;
					}

					#end
					return true;
				}

				/**
				 * Function called after extension installation/update/removal procedure commences.
				 *
				 * @param   string            \$type     The type of change (install or discover_install, update, uninstall)
				 * @param   InstallerAdapter  \$adapter  The adapter calling this method
				 *
				 * @return  boolean  True on success
				 *
				 * @since   __DEPLOY_VERSION__
				 */
				public function postflight(string \$type, InstallerAdapter \$adapter): bool
				{
					if (\$type !== 'uninstall')
					{
						#if (${parseLayouts})
						// Parse layouts
						\$this->parseLayouts(\$installer->getManifest()->layouts, \$installer);
						
						#end
						#if (${checkTables})
						// Check databases
						\$this->checkTables(\$adapter);

						#end
						#if (${checkRootRecord})
						// Check root record
						\$this->checkRootRecord('${checkRootRecord}');
						
						#end
						#if (${checkExtensionParams})
						// Check extension params
						\$this->checkExtensionParams(\$adapter);
						
						#end
						// Run updates script
						if (\$type === 'update')
						{
							foreach (\$this->updateMethods as \$method)
							{
								if (method_exists(\$this, \$method))
								{
									\$this->\$method(\$adapter);
								}
							}
						}
					}

					return true;
				}

				#if (${enablePlugin})
				/**
				 * Enable plugin after installation.
				 *
				 * @param   InstallerAdapter  \$adapter  Parent object calling object.
				 *
				 * @since  __DEPLOY_VERSION__
				 */
				protected function enablePlugin(InstallerAdapter \$adapter)
				{
					// Prepare plugin object
					\$plugin          = new \stdClass();
					\$plugin->type    = 'plugin';
					\$plugin->element = \$adapter->getElement();
					\$plugin->folder  = (string) \$adapter->getParent()->manifest->attributes()['group'];
					\$plugin->enabled = 1;

					// Update record
					\$this->db->updateObject('#__extensions', \$plugin, ['type', 'element', 'folder']);
				}
				
				#end
				#if(${minimumJoomla} || ${minimumPhp} || ${minimumMySQL} || ${minimumMariaDb})
				/**
				 * Method to check compatible.
				 *
				 * @throws  \Exception
				 *
				 * @return  bool True on success, False on failure.
				 *
				 * @since  __DEPLOY_VERSION__
				 */
				protected function checkCompatible(): bool
				{
					\$app = Factory::getApplication();
					
					#if (${minimumJoomla})
					// Check joomla version
					if (!(new Version())->isCompatible(\$this->minimumJoomla))
					{
						\$app->enqueueMessage(Text::sprintf(\$constant . '_ERROR_COMPATIBLE_JOOMLA', \$this->minimumJoomla),
							'error');

						return false;
					}
					
					#end
					#if (${minimumPhp})
					// Check PHP
					if (!(version_compare(PHP_VERSION, \$this->minimumPhp) >= 0))
					{
						\$app->enqueueMessage(Text::sprintf(\$constant . '_ERROR_COMPATIBLE_PHP', \$this->minimumPhp),
							'error');

						return false;
					}
					
					#end
					#if (${minimumMySQL} || ${minimumMariaDb})
					// Check database version
					\$db            = \$this->db;
					\$serverType    = \$db->getServerType();
					\$serverVersion = \$db->getVersion();
					#end
					#if (${minimumMySQL})
					if (\$serverType == 'mysql' && stripos(\$serverVersion, 'mariadb') !== false)
					{
						\$serverVersion = preg_replace('/^5\.5\.5-/', '', \$serverVersion);

						if (!(version_compare(\$serverVersion, \$this->minimumMariaDb) >= 0))
						{
							\$app->enqueueMessage(Text::sprintf(\$constant . '_ERROR_COMPATIBLE_DATABASE',
								\$this->minimumMySQL, \$this->minimumMariaDb), 'error');

							return false;
						}
					}
					
					#end
					#if (${minimumMariaDb})
					if (\$serverType == 'mysql' && !(version_compare(\$serverVersion, \$this->minimumMySQL) >= 0))
					{
						\$app->enqueueMessage(Text::sprintf(\$constant . '_ERROR_COMPATIBLE_DATABASE',
							\$this->minimumMySQL, \$this->minimumMariaDb), 'error');

						return false;
					}
					
					#end
					return true;
				}

				#end
				#if (${parseLayouts})
				/**
				 * Method to parse through a layouts element of the installation manifest and take appropriate action.
				 *
				 * @param   SimpleXMLElement|null  \$element    The XML node to process.
				 * @param   Installer|null         \$installer  Installer calling object.
				 *
				 * @return  bool  True on success.
				 *
				 * @since  __DEPLOY_VERSION__
				 */
				public function parseLayouts(SimpleXMLElement \$element = null, Installer \$installer = null): bool
				{
					if (!\$element || !count(\$element->children()))
					{
						return false;
					}

					// Get destination
					\$folder      = ((string) \$element->attributes()->destination) ? '/' . \$element->attributes()->destination : null;
					\$destination = Path::clean(JPATH_ROOT . '/layouts' . \$folder);

					// Get source
					\$folder = (string) \$element->attributes()->folder;
					\$source = (\$folder && file_exists(\$installer->getPath('source') . '/' . \$folder))
						? \$installer->getPath('source') . '/' . \$folder : \$installer->getPath('source');

					// Prepare files
					\$copyFiles = [];
					foreach (\$element->children() as \$file)
					{
						$path_src  = Path::clean(\$source . '/' . \$file);
						$path_dest = Path::clean(\$destination . '/' . \$file);

						// Is this path a file or folder?
						$path_type = \$file->getName() === 'folder' ? 'folder' : 'file';
						if (basename($path_dest) !== $path_dest)
						{
							\$newdir = dirname($path_dest);
							if (!Folder::create(\$newdir))
							{
								Log::add(Text::sprintf('JLIB_INSTALLER_ERROR_CREATE_DIRECTORY', \$newdir), Log::WARNING, 'jerror');

								return false;
							}
						}

						\$copyFiles$addToArray = \$path;
					}

					return \$installer->copyFiles(\$copyFiles, true);
				}

				#end
				#if (${checkTables})
				/**
				 * Method to create database tables in not exist.
				 *
				 * @param   InstallerAdapter  \$adapter  Parent object calling object.
				 *
				 * @since  __DEPLOY_VERSION__
				 */
				protected function checkTables(InstallerAdapter \$adapter)
				{
					if (\$sql = file_get_contents(\$adapter->getParent()->getPath('extension_administrator')
						. '/sql/install.mysql.utf8.sql'))
					{
						\$db = \$this->db;
						foreach (\$db->splitSql(\$sql) as \$query)
						{
							\$db->setQuery(\$db->convertUtf8mb4QueryToUtf8(\$query));
							try
							{
								\$db->execute();
							}
							catch (JDataBaseExceptionExecuting \$e)
							{
								Log::add(Text::sprintf('JLIB_INSTALLER_ERROR_SQL_ERROR', \$e->getMessage()), Log::WARNING, 'jerror');
							}
						}
					}
				}
				
				#end
				#if (${checkRootRecord})
				/**
				 * Method to create root record if don't exist.
				 *
				 * @param   string|null  \$table  Table name.
				 *
				 * @since  __DEPLOY_VERSION__
				 */
				protected function checkRootRecord(string \$table = null)
				{
					\$db = \$this->db;

					// Get base categories
					\$query = \$db->getQuery(true)
						->select('id')
						->from(\$table)
						->where('id = 1');
					\$db->setQuery(\$query);

					// Add root in not found
					if (empty(\$db->loadResult()))
					{
						\$root            = new \stdClass();
						\$root->id        = 1;
						\$root->parent_id = 0;
						\$root->lft       = 0;
						\$root->rgt       = 1;
						\$root->level     = 0;
						\$root->path      = '';
						\$root->alias     = 'root';
						\$root->type      = 'category';
						\$root->state     = 1;

						\$db->insertObject(\$table, \$root);
					}
				}
								
				#end
				#if (${checkExtensionParams})
				/**
				 * Method to check extension params and set if needed.
				 *
				 * @param   InstallerAdapter  \$adapter  Parent object calling object.
				 *
				 * @since  __DEPLOY_VERSION__
				 */
				protected function checkExtensionParams(InstallerAdapter \$adapter)
				{
					if (!empty(\$this->extensionParams))
					{
						\$element = \$adapter->getElement();
						\$folder  = (string) \$adapter->getParent()->manifest->attributes()['group'];

						// Get extension
						\$db    = \$this->db;
						\$query = \$db->getQuery(true)
							->select(['extension_id', 'params'])
							->from(\$db->quoteName('#__extensions'))
							->where(\$db->quoteName('element') . ' = :element')
							->bind(':element', \$element);
						if (!empty(\$folder))
						{
							\$query->where(\$db->quoteName('folder') . ' = :folder')
								->bind(':folder', \$folder);
						}
						if (\$extension = \$db->setQuery(\$query)->loadObject())
						{
							\$extension->params = new Registry(\$extension->params);

							// Check params
							\$needUpdate = false;
							foreach (\$this->extensionParams as \$path => \$value)
							{
								if (!\$extension->params->exists(\$path))
								{
									\$needUpdate = true;
									\$extension->params->set(\$path, \$value);
								}
							}

							// Update
							if (\$needUpdate)
							{
								\$extension->params = (string) \$extension->params;
								\$db->updateObject('#__extensions', \$extension, 'extension_id');
							}
						}
					}
				}

				#end
				#if (${parseLayouts})
				/**
				 * Method to parse through a layouts element of the installation manifest and remove the files that were installed.
				 *
				 * @param   SimpleXMLElement|null  \$element  The XML node to process.
				 *
				 * @return  bool  True on success.
				 *
				 * @since  __DEPLOY_VERSION__
				 */
				protected function removeLayouts(SimpleXMLElement \$element = null): bool
				{
					if (!\$element || !count(\$element->children()))
					{
						return false;
					}

					// Get the array of file nodes to process
					\$files = \$element->children();

					// Get source
					\$folder = ((string) \$element->attributes()->destination) ? '/' . \$element->attributes()->destination : null;
					\$source = Path::clean(JPATH_ROOT . '/layouts' . \$folder);

					// Process each file in the \$files array (children of \$tagName).
					foreach (\$files as \$file)
					{
						\$path = Path::clean(\$source . '/' . \$file);

						// Actually delete the files/folders
						if (is_dir(\$path))
						{
							\$val = Folder::delete(\$path);
						}
						else
						{
							\$val = File::delete(\$path);
						}

						if (\$val === false)
						{
							Log::add('Failed to delete ' . \$path, Log::WARNING, 'jerror');

							return false;
						}
					}

					if (!empty(\$folder))
					{
						Folder::delete(\$source);
					}

					return true;
				}		
				#end
			});
	}
};