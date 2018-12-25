<?php

class ProjectTask extends Task
{
	protected $action = null;
	protected $name = null;
	protected $release = null;
	protected $dev = null;
	protected $root = null;

	public function setAction($action)
	{
		$this->action = $action;
	}

	public function setName($name)
	{
		$this->name = $name;
	}

	public function setRelease($release): void
	{
		$this->release = $release;
	}

	public function setDev($dev)
	{
		$this->dev = $dev;
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
		echo '==== Phing Info ===' . PHP_EOL
			. 'Name               ' . $this->name . PHP_EOL
			. '[RELEASE] Version  ' . $this->release . PHP_EOL
			. '[RELEASE] Package  ' . $this->getPackageName() . PHP_EOL
			. '[DEV] Version      ' . $this->dev . PHP_EOL
			. '[DEV] Package      ' . $this->getPackageName(true) . PHP_EOL
			. 'Base Directory     ' . realpath($this->base) . PHP_EOL;
	}

	protected function prepareRelease()
	{
		echo '==== Prepare ' . $this->name . ' ' . $this->release . ' Release ===' . PHP_EOL;

		echo 'Change version ....... ';
		echo ($files = $this->changeVersion($this->release)) ? 'OK' : 'ERROR';
		echo PHP_EOL;

		echo 'Set version .......... ';
		echo ($this->setVersion($this->release, $files)) ? 'OK' : 'ERROR';
		echo PHP_EOL;

		$date = date('F Y');
		echo 'Change date .......... ';
		echo ($files = $this->changeDate($date, $files)) ? 'OK' : 'ERROR';
		echo PHP_EOL;

		echo 'Set date ............. ';
		echo ($this->changeDate($date, $files)) ? 'OK' : 'ERROR';
		echo PHP_EOL;
	}

	protected function packageRelease()
	{
		echo '==== Package ' . $this->name . ' ' . $this->release . ' Release ===' . PHP_EOL;
		echo 'Create package ....... ';
		echo ($files = $this->createPackage($this->getPackageName())) ? 'OK' : 'ERROR';
		echo PHP_EOL;
	}

	protected function prepareDev()
	{
		echo '==== Prepare ' . $this->name . ' ' . $this->dev . ' Dev ===' . PHP_EOL;

		echo 'Change version ................... ';
		echo ($files = $this->changeVersion($this->dev)) ? 'OK' : 'ERROR';
		echo PHP_EOL;

		echo 'Change PhpStorm copyrights ....... ';
		echo ($files = $this->changePhpStormCopyrights($this->dev, date('F Y'))) ? 'OK' : 'ERROR';
		echo PHP_EOL;
	}

	protected function packageDev()
	{
		echo '==== Package ' . $this->name . ' ' . $this->dev . ' Dev ===' . PHP_EOL;
		echo 'Create package ....... ';
		echo ($files = $this->createPackage($this->getPackageName(true))) ? 'OK' : 'ERROR';
		echo PHP_EOL;
	}

	protected function createPackage($package = '', $files = array())
	{
		$root      = $this->root . DIRECTORY_SEPARATOR;
		$directory = $root . '.packages/';
		$package   = $directory . $package;
		$files     = (!empty($files)) ? $files
			: $this->getFiles($root, array('.idea/', '.packages/', '.phing/', '.gitignore', 'LICENSE', '*.md'));

		if (empty($files)) return false;

		if (file_exists($package))
		{
			unlink($package);
		}

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

	protected function changeVersion($version = '', $files = array())
	{
		$root  = $this->root . DIRECTORY_SEPARATOR;
		$files = (!empty($files)) ? $files
			: $this->getFiles($root, array('.idea/', '.packages/', '.phing/', '.gitignore', 'LICENSE', '*.md'));

		if (empty($files)) return false;

		foreach ($files as $path)
		{
			$file     = new PhingFile($root, $path);
			$filename = $file->getAbsolutePath();
			$original = file_get_contents($filename);
			$replace  = preg_replace('/@version(\s*)(.?)*/', '@version${1}' . $version, $original);
			$replace  = preg_replace('/\<version\>(.?)*<\/version\>/', '<version>' . $version . '</version>', $replace);

			if ($original != $replace)
			{
				file_put_contents($filename, $replace);
			}
		}

		return $files;
	}

	protected function changePhpStormCopyrights($version = '', $date = '')
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

	protected function setVersion($version = '', $files = array())
	{
		$root  = $this->root . DIRECTORY_SEPARATOR;
		$files = (!empty($files)) ? $files
			: $this->getFiles($root, array('.idea/', '.packages/', '.phing/', '.gitignore', 'LICENSE', '*.md'));

		if (empty($files)) return false;

		foreach ($files as $path)
		{
			$file     = new PhingFile($root, $path);
			$filename = $file->getAbsolutePath();
			$original = file_get_contents($filename);
			$replace  = str_replace('__DEPLOY_VERSION__', $version, $original);

			if ($original != $replace)
			{
				file_put_contents($filename, $replace);
			}
		}

		return $files;
	}

	protected function changeDate($date = '', $files = array())
	{
		$root  = $this->root . DIRECTORY_SEPARATOR;
		$files = (!empty($files)) ? $files
			: $this->getFiles($root, array('.idea/', '.packages/', '.phing/', '.gitignore', 'LICENSE', '*.md'));

		if (empty($files)) return false;

		foreach ($files as $path)
		{
			$file     = new PhingFile($root, $path);
			$filename = $file->getAbsolutePath();
			$original = file_get_contents($filename);
			$replace  = preg_replace('/@date(\s*)(.?)*/', '@date${1}' . $date, $original);
			$replace  = preg_replace('/\<date\>(.?)*<\/date\>/', '<date>' . $date . '</date>', $replace);
			$replace  = preg_replace('/\<creationDate\>(.?)*<\/creationDate\>/', '<creationDate>' . $date . '</creationDate>', $replace);

			if ($original != $replace)
			{
				file_put_contents($filename, $replace);
			}
		}

		return $files;
	}

	protected function setDate($date = '', $files = array())
	{
		$root  = $this->root . DIRECTORY_SEPARATOR;
		$files = (!empty($files)) ? $files
			: $this->getFiles($root, array('.idea/', '.packages/', '.phing/', '.gitignore', 'LICENSE', '*.md'));

		if (empty($files)) return false;

		foreach ($files as $path)
		{
			$file     = new PhingFile($root, $path);
			$filename = $file->getAbsolutePath();
			$original = file_get_contents($filename);
			$replace  = str_replace('__DEPLOY_DATE__', $date, $original);

			if ($original != $replace)
			{
				file_put_contents($filename, $replace);
			}
		}

		return $files;
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
		$version = ($dev) ? $this->dev . '_dev' : $this->release;

		return $this->name . '_' . $version . '.zip';
	}
}