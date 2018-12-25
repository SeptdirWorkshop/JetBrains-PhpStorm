<?php
/**
 * @package    Phing Joomla Release Task
 * @version    1.0.0
 * @author     Igor Berdicheskiy - septdir.ru
 * @copyright  Copyright (c) 2013 - 2018 Igor Berdicheskiy. All rights reserved.
 * @license    GNU/GPL license: http://www.gnu.org/copyleft/gpl.html
 * @link       https://septdir.ru
 */

class releaseTask extends Task
{
	/**
	 * The action in the buildfile.
	 *
	 * @var string
	 */
	protected $action = null;

	/**
	 * The name passed in the buildfile.
	 *
	 * @var string
	 */
	protected $name = null;

	/**
	 * The version passed in the buildfile.
	 *
	 * @var string
	 */
	protected $version = null;

	/**
	 * The package passed in the buildfile.
	 *
	 * @var string
	 */
	protected $package = null;

	/**
	 * Set arction for the attribute "action"
	 *
	 * @param string $action Action name
	 */
	public function setAction($action)
	{
		$this->action = $action;
	}

	/**
	 * Set Name for the attribute "name"
	 *
	 * @param string $name Project name
	 */
	public function setName($name)
	{
		$this->name = $name;
	}

	/**
	 * Set version for the attribute "version"
	 *
	 * @param string $version Version number
	 */
	public function setVersion($version)
	{
		$this->version = $version;
	}

	/**
	 * Set package for the attribute "package"
	 *
	 * @param string $package package file name
	 */
	public function setPackage($package)
	{
		$this->package = $package;
	}

	/**
	 * Main function
	 */
	public function main()
	{
		$action = $this->action;
		$this->$action();
	}

	/**
	 * Method to show project information
	 *
	 * @return void
	 */
	public function showInfo()
	{
		echo
			'@name     ' . $this->name . PHP_EOL .
			'@version  ' . $this->version . PHP_EOL .
			'@package  ' . $this->package . PHP_EOL;
	}

	/**
	 * Method to change project version in files
	 *
	 * @return void
	 */
	protected function changeVersion()
	{
		$baseDir = '..' . DIRECTORY_SEPARATOR;

		// Change version in project files
		$files = $this->getFiles($baseDir, array('.idea/', '.packages/', '.phing/', '.gitignore', 'LICENSE', '*.md'));
		foreach ($files as $path)
		{
			$file     = new PhingFile($baseDir, $path);
			$filename = $file->getAbsolutePath();

			$original = file_get_contents($filename);
			$replace  = preg_replace('/@version(.?)*/', '@version    ' . $this->version, $original);
			$replace  = preg_replace('/\<version\>(.?)*<\/version\>/',
				'<version>' . $this->version . '</version>', $replace);
			$replace  = preg_replace('/\<creationDate>(.?)*<\/creationDate\>/',
				'<creationDate>' . date('F Y') . '</creationDate>', $replace);

			if ($original != $replace)
			{
				file_put_contents($filename, $replace);
			}
		}

		// Change version in idea copyrights
		$files = $this->getFiles('../.idea/copyright', array('profiles_settings.xml'));
		foreach ($files as $path)
		{
			$filename = '../.idea/copyright/' . $path;
			$original = file_get_contents($filename);
			$replace  = preg_replace('/@version.+?\&#10;/', '@version    ' . $this->version . '&#10;', $original);

			if ($original != $replace)
			{
				file_put_contents($filename, $replace);
			}
		}
		echo 'Version changed to ' . $this->version . PHP_EOL;

		return;
	}

	/**
	 * Method to change project since in files
	 *
	 * @return void
	 */
	protected function changeSince()
	{
		$baseDir = '..' . DIRECTORY_SEPARATOR;

		// Change version in project files
		$files = $this->getFiles($baseDir, array('.idea/', '.packages/', '.phing/', '.gitignore', 'LICENSE', '*.md'));
		foreach ($files as $path)
		{
			$file     = new PhingFile($baseDir, $path);
			$filename = $file->getAbsolutePath();

			$original = file_get_contents($filename);
			$replace  = preg_replace('/@since(.?)*/', '@since ' . $this->version, $original);

			if ($original != $replace)
			{
				file_put_contents($filename, $replace);
			}
		}

		echo 'Version changed to ' . $this->version . PHP_EOL;

		return;
	}

	/**
	 * Method to  create package archive file
	 *
	 * @return void
	 */
	protected function createPackage()
	{
		$baseDir            = '..' . DIRECTORY_SEPARATOR;
		$packages_directory = $baseDir . '.packages/';
		$package_file       = $packages_directory . $this->package;

		// Remove old archive
		if (file_exists($package_file))
		{
			unlink($package_file);
		}

		// Create new archive
		$zip = new ZipArchive();
		if ($zip->open($package_file, ZIPARCHIVE::CREATE) !== true)
		{
			echo '! Package ' . $this->package . ' error: Error while creating archive file ' . PHP_EOL;

			return;
		}

		// Add files to archive
		$files = $this->getFiles($baseDir, array('.idea/', '.packages/', '.phing/', '.gitignore', 'LICENSE', '*.md'));
		foreach ($files as $path)
		{
			$file = new PhingFile($baseDir, $path);

			$clearPath = $file->getPathWithoutBase($baseDir);
			$clearPath = str_replace('\\', '/', $clearPath);

			if ($file->isDirectory() && $clearPath != '.')
			{
				$zip->addEmptyDir($clearPath);
			}
			else
			{
				$zip->addFile($file->getAbsolutePath(), $clearPath);
			}
		}

		// Close archive
		$zip->close();

		echo 'Package ' . $this->package . ' was created ' . PHP_EOL;

		return;
	}

	/**
	 * Method to get Files
	 *
	 * @param string $directory path to directory
	 * @param string $exclude   files and directory files
	 *
	 * @return array Files paths
	 */
	protected function getFiles($directory = '../', $exclude = '')
	{
		$FileSet = new FileSet();
		$FileSet->setDir($directory);
		$FileSet->setIncludes('**');
		$FileSet->setExcludes(implode(',', $exclude));

		$directoryScanner = $FileSet->getDirectoryScanner($this->project);

		return $directoryScanner->getIncludedFiles();
	}
}