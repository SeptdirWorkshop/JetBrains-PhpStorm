<?php

use Phing\Io\File;
use Phing\Task;
use Phing\Type\FileSet;

class ProjectTask extends Task
{
	protected ?string $root = null;
	protected ?string $action = null;
	protected ?string $name = null;
	protected ?string $version = null;
	protected ?string $devVersion = null;
	protected ?string $date = null;
	protected array $filesExcludes = [
		'**/.idea/**',
		'**/.packages/**',
		'**/.phing/**',
		'**/node_modules/**',
		'**/vendor/**',
		'**/.gitignore',
		'**/LICENSE',
		'**/*.md,'
	];
	protected array $packageExcludes = [
		'**/.idea/**',
		'**/.packages/**',
		'**/.phing/**',
		'**/node_modules/**',
		'**/vendor/**',
		'**/build/**',
		'**/.gitignore',
		'**/LICENSE',
		'**/*.md,'
	];

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
		$version       = explode('.', $version);
		$count         = count($version);
		$devVersion    = [];
		foreach ($version as $i => $number)
		{
			$number       = (int) $number;
			$devVersion[] = (++$i == $count) ? ++$number . '-dev' : $number;
		}

		$this->devVersion = implode('.', $devVersion);
		$this->date       = date('F Y');
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

	public function info()
	{
		echo implode(PHP_EOL, [
			'==== Project Info ===',
			'Name               ' . $this->name,
			'Date               ' . $this->date,
			'[RELEASE] Version  ' . $this->version,
			'[RELEASE] Package  ' . $this->getPackageName(),
			'[DEV] Version      ' . $this->devVersion,
			'[DEV] Package      ' . $this->getPackageName(true),
			'Base Directory     ' . realpath($this->root),
		]);
	}

	protected function getPackageName($dev = false)
	{
		$version = ($dev) ? $this->devVersion : $this->version;

		return $this->name . '_' . $version . '.zip';
	}

	protected function prepareRelease()
	{
		echo '==== Prepare ' . $this->name . ' ' . $this->version . ' Release ===' . PHP_EOL;

		echo 'Replace version ....... ';
		echo ($files = $this->replaceVersion($this->version)) ? 'OK' : 'ERROR';
		echo PHP_EOL;

		echo 'Replace date .......... ';
		echo ($this->replaceDate($files)) ? 'OK' : 'ERROR';
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
		$files = (!empty($files)) ? $files : $this->getFiles($root, $this->filesExcludes);
		echo ($files) ? 'OK' : 'ERROR' . PHP_EOL;
		echo PHP_EOL;

		echo 'Replace since ....... ';
		try
		{
			foreach ($files as $path)
			{
				$file     = new File($root, $path);
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
		catch (Exception $e)
		{
			echo 'ERROR: ' . $e->getMessage();
		}
		echo PHP_EOL;
	}

	protected function prepareDev()
	{
		echo '==== Prepare ' . $this->name . ' ' . $this->devVersion . ' Dev ===' . PHP_EOL;

		echo 'Replace version ................... ';
		echo ($files = $this->replaceVersion($this->devVersion)) ? 'OK' : 'ERROR';
		echo PHP_EOL;

		echo 'Replace date .......... ';
		echo ($files = $this->replaceDate($files)) ? 'OK' : 'ERROR';
		echo PHP_EOL;

		echo 'Check PhpStorm copyrights ....... ';
		echo ($files = $this->checkPhpStormCopyrights('__DEPLOY_VERSION__', date('F Y'))) ? 'OK' : 'ERROR';
		echo PHP_EOL;
	}

	protected function packageDev()
	{
		echo '==== Package ' . $this->name . ' ' . $this->devVersion . ' Dev ===' . PHP_EOL;
		echo 'Create package ....... ';
		echo ($this->createPackage($this->getPackageName(true))) ? 'OK' : 'ERROR';
		echo PHP_EOL;
	}

	protected function replaceVersion($version = '', $files = [])
	{
		$root  = $this->root . DIRECTORY_SEPARATOR;
		$files = (!empty($files)) ? $files : $this->getFiles($root, $this->filesExcludes);
		if (empty($files))
		{
			return false;
		}

		$dev           = ($version == $this->devVersion);
		$docVersion    = ($dev) ? '__DEPLOY_VERSION__' : $version;
		$deployVersion = ($dev) ? '__DEPLOY_VERSION__' : $version;
		foreach ($files as $path)
		{
			$file     = new File($root, $path);
			$filename = $file->getAbsolutePath();
			$original = file_get_contents($filename);
			$replace  = preg_replace('/@version(\s*)(.?)*/', '@version${1}' . $docVersion, $original);
			$replace  = preg_replace('/\<version\>(.?)*<\/version\>/', '<version>' . $version . '</version>', $replace);
			$replace  = preg_replace('/\*Version:(\s*)(.?)*/', '* Version:${1}' . $version, $replace);
			$replace  = str_replace('__DEPLOY_VERSION__', $deployVersion, $replace);
			if (strpos($path, '.json') !== false)
			{
				$json = json_decode($replace);
				if (!empty($json->version))
				{
					$replace = str_replace('"version": "' . $json->version . '",',
						'"version": "' . $version . '",', $replace);
				}
			}
			if ($original != $replace)
			{
				file_put_contents($filename, $replace);
			}
		}

		return $files;
	}

	protected function checkPhpStormCopyrights($version = '', $date = '')
	{
		$root = $this->root . DIRECTORY_SEPARATOR . '.idea/copyright';
		if (!$files = $this->getFiles($root, ['profiles_settings.xml'])) return false;

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

	protected function replaceDate($files = [])
	{
		$root  = $this->root . DIRECTORY_SEPARATOR;
		$files = (!empty($files)) ? $files
			: $this->getFiles($root, ['.idea/', '.packages/', '.phing/', 'node_modules/', '.gitignore', 'LICENSE', '*.md']);

		if (empty($files))
		{
			return false;
		}

		foreach ($files as $path)
		{
			$file     = new File($root, $path);
			$filename = $file->getAbsolutePath();
			$original = file_get_contents($filename);
			$replace  = preg_replace('/@date(\s*)(.?)*/', '@date${1}' . $this->date, $original);
			$replace  = preg_replace('/\<date\>(.?)*<\/date\>/', '<date>' . $this->date . '</date>', $replace);
			$replace  = preg_replace('/\<creationDate\>(.?)*<\/creationDate\>/', '<creationDate>' . $this->date . '</creationDate>', $replace);
			$replace  = str_replace('__DEPLOY_DATE__', $this->date, $replace);
			if ($original != $replace)
			{
				file_put_contents($filename, $replace);
			}
		}

		return $files;
	}

	protected function createPackage($package = '', $files = [])
	{
		$root      = $this->root . DIRECTORY_SEPARATOR;
		$directory = $root . '.packages/';
		if (file_exists($directory . $package))
		{
			return (unlink($directory . $package)) ? $this->createPackage($package, $files) : false;
		}
		$package = $directory . $package;

		$files = (!empty($files)) ? $files : $this->getFiles($root, $this->packageExcludes);
		if (empty($files))
		{
			return false;
		}

		$zip = new ZipArchive();
		if ($zip->open($package, ZIPARCHIVE::CREATE) !== true)
		{
			return false;
		}

		foreach ($files as $path)
		{
			$file  = new File($root, $path);
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
}