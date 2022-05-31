<?php

class ProjectTask extends Task
{
	protected $action = null;
	protected $name = null;
	protected $version = null;
	protected $devVersion = null;
	protected $root = null;

	public function setAction($action)
	{
		$this->action = $action;
	}

	public function setName($name)
	{
		$this->name = $name;
	}

	public function setVersion($version)
	{
		$this->version = $version;

		$version    = explode('.', $version);
		$count      = count($version);
		$devVersion = array();
		foreach ($version as $i => $number)
		{
			$number       = (int) $number;
			$devVersion[] = (++$i == $count) ? ++$number . '-dev' : $number;
		}

		$this->devVersion = implode('.', $devVersion);
	}

	public function setRoot($root)
	{
		$this->root = $root;
	}

	public function main()
	{
		$action = $this->action;

		return $this->$action();
	}

	protected function info()
	{
		echo '==== Project Info ===' . PHP_EOL
			. 'Name               ' . $this->name . PHP_EOL
			. '[RELEASE] Version  ' . $this->release . PHP_EOL
			. '[RELEASE] Package  ' . $this->getPackageName() . PHP_EOL
			. '[DEV] Version      ' . $this->devVersion . PHP_EOL
			. '[DEV] Package      ' . $this->getPackageName(true) . PHP_EOL
			. 'Base Directory     ' . realpath($this->root) . PHP_EOL;
	}

	protected function prepareRelease()
	{
		echo '==== Prepare ' . $this->name . ' ' . $this->version . ' Release ===' . PHP_EOL;

		echo 'Replace version ....... ';
		echo ($files = $this->replaceVersion($this->version)) ? 'OK' : 'ERROR';
		echo PHP_EOL;

		$date = date('F Y');
		echo 'Replace date .......... ';
		echo ($files = $this->replaceDate($date, $files)) ? 'OK' : 'ERROR';
		echo PHP_EOL;
	}

	protected function packageRelease()
	{
		echo '==== Package ' . $this->name . ' ' . $this->version . ' Release ===' . PHP_EOL;
		echo 'Create package ....... ';
		echo ($files = $this->createPackage($this->getPackageName())) ? 'OK' : 'ERROR';
		echo PHP_EOL;
	}

	protected function resetSince()
	{
		echo '==== Reset @since to  __DEPLOY_VERSION__' . PHP_EOL;

		echo 'Find Files ....... ';
		$root  = $this->root . DIRECTORY_SEPARATOR;
		$files = (!empty($files)) ? $files
			: $this->getFiles($root, array('.idea/', '.packages/', '.phing/', 'build/', '.gitignore', 'LICENSE', '*.md'));
		echo ($files) ? 'OK' : 'ERROR' . PHP_EOL;
		echo PHP_EOL;

		echo 'Replace since ....... ';
		try
		{
			foreach ($files as $path)
			{
				$file     = new PhingFile($root, $path);
				$filename = $file->getAbsolutePath();
				$original = file_get_contents($filename);
				$replace  = preg_replace('/@since(\s*)(.?)*/', '@since${1}' . '__DEPLOY_VERSION__', $original);
				if ($original != $replace)
				{
					file_put_contents($filename, $replace);
				}
			}
			echo 'OK';
		}
		catch (Exception $exception)
		{
			echo 'ERROR';
		}
		echo PHP_EOL;
	}

	protected function prepareDev()
	{
		echo '==== Prepare ' . $this->name . ' ' . $this->devVersion . ' Dev ===' . PHP_EOL;

		echo 'Replace version ................... ';
		echo ($files = $this->replaceVersion($this->devVersion)) ? 'OK' : 'ERROR';
		echo PHP_EOL;

		echo 'Check PhpStorm copyrights ....... ';
		echo ($files = $this->checkPhpStormCopyrights('__DEPLOY_VERSION__', date('F Y'))) ? 'OK' : 'ERROR';
		echo PHP_EOL;
	}

	protected function packageDev()
	{
		echo '==== Package ' . $this->name . ' ' . $this->devVersion . ' Dev ===' . PHP_EOL;
		echo 'Create package ....... ';
		echo ($files = $this->createPackage($this->getPackageName(true))) ? 'OK' : 'ERROR';
		echo PHP_EOL;
	}

	protected function replaceVersion($version = '', $files = array())
	{
		$root  = $this->root . DIRECTORY_SEPARATOR;
		$files = (!empty($files)) ? $files
			: $this->getFiles($root, array('.idea/', '.packages/', '.phing/', 'build/node_modules', '.gitignore', 'LICENSE', '*.md'));

		if (empty($files)) return false;

		$dev           = ($version == $this->devVersion);
		$docVersion    = ($dev) ? '__DEPLOY_VERSION__' : $version;
		$deployVersion = ($dev) ? '__DEPLOY_VERSION__' : $version;
		foreach ($files as $path)
		{
			$file     = new PhingFile($root, $path);
			$filename = $file->getAbsolutePath();
			$original = file_get_contents($filename);
			$replace  = preg_replace('/@version(\s*)(.?)*/', '@version${1}' . $docVersion, $original);
			$replace  = preg_replace('/\<version\>(.?)*<\/version\>/', '<version>' . $version . '</version>', $replace);
			$replace  = preg_replace('/\*Version:(\s*)(.?)*/', '* Version:${1}' . $version, $replace);
			$replace  = str_replace('__DEPLOY_VERSION__', $deployVersion, $replace);
			if ($original != $replace)
			{
				file_put_contents($filename, $replace);
			}
		}

		$package = $root . '/build/package.json';
		if ($context = @file_get_contents($package))
		{
			$context = preg_replace('/"version": "(.?)*"/', '"version": "' . $version . '"', $context);
			file_put_contents($package, $context);
		}

		return $files;
	}

	protected function checkPhpStormCopyrights($version = '', $date = '')
	{
		$root = $this->root . DIRECTORY_SEPARATOR . '.idea/copyright';
		if (!$files = $this->getFiles($root, array('profiles_settings.xml'))) return false;

		foreach ($files as $path)
		{
			$filename = '../.idea/copyright/' . $path;
			$original = file_get_contents($filename);
			$replace  = preg_replace('/@version(\s*).+?\&#10/', '@version${1}' . $version . '&#10', $original);
			$replace  = preg_replace('/@date(\s*).+?\&#10/', '@date${1}' . $date . '&#10;', $replace);

			if ($original != $replace)
			{
				file_put_contents($filename, $replace);
			}
		}

		return true;
	}

	protected function replaceDate($date = '', $files = array())
	{
		$root  = $this->root . DIRECTORY_SEPARATOR;
		$files = (!empty($files)) ? $files
			: $this->getFiles($root, array('.idea/', '.packages/', '.phing/', 'build/', '.gitignore', 'LICENSE', '*.md'));

		if (empty($files)) return false;

		foreach ($files as $path)
		{
			$file     = new PhingFile($root, $path);
			$filename = $file->getAbsolutePath();
			$original = file_get_contents($filename);
			$replace  = preg_replace('/@date(\s*)(.?)*/', '@date${1}' . $date, $original);
			$replace  = preg_replace('/\<date\>(.?)*<\/date\>/', '<date>' . $date . '</date>', $replace);
			$replace  = preg_replace('/\<creationDate\>(.?)*<\/creationDate\>/', '<creationDate>' . $date . '</creationDate>', $replace);
			$replace  = str_replace('__DEPLOY_DATE__', $date, $replace);
			if ($original != $replace)
			{
				file_put_contents($filename, $replace);
			}
		}

		return $files;
	}

	protected function createPackage($package = '', $files = array())
	{
		$root      = $this->root . DIRECTORY_SEPARATOR;
		$directory = $root . '.packages/';
		if (file_exists($directory . $package))
		{
			return (unlink($directory . $package)) ? $this->createPackage($package, $files) : false;
		}
		$package = $directory . $package;
		$files   = (!empty($files)) ? $files
			: $this->getFiles($root, array('.idea/', '.packages/', '.phing/', 'build/', '.gitignore', 'LICENSE', '*.md'));

		if (empty($files)) return false;

		$zip = new ZipArchive();
		if ($zip->open($package, ZIPARCHIVE::CREATE) !== true)
		{
			return false;
		}

		foreach ($files as $path)
		{
			$file  = new PhingFile($root, $path);
			$clear = $file->getPathWithoutBase($root);
			$clear = str_replace('\\', '/', $clear);

			if ($file->isDirectory() && $clear != '.')
			{
				$zip->addEmptyDir($clear);
			}
			else
			{
				$zip->addFile($file->getAbsolutePath(), $clear);
			}
		}

		$zip->close();

		return true;
	}

	protected function getFiles($directory = '../', $exclude = '')
	{
		$fileset = new FileSet();
		$fileset->setDir($directory);
		$fileset->setIncludes('**');
		$fileset->setExcludes(implode(',', $exclude));

		$scanner = $fileset->getDirectoryScanner($this->project);

		return $scanner->getIncludedFiles();
	}

	protected function getPackageName($dev = false)
	{
		$version = ($dev) ? $this->devVersion : $this->version;

		return $this->name . '_' . $version . '.zip';
	}
}