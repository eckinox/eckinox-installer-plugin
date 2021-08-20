<?php

namespace Eckinox\Composer;

use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller;
use Composer\Repository\InstalledRepositoryInterface;

class Installer extends LibraryInstaller
{
	private const FILES_DIRECTORY = 'replicate';
    /**
     * {@inheritDoc}
     */
    public function supports($packageType)
    {
        return 'eckinox-metapackage' === $packageType;
    }

	public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
	{
		parent::install($repo, $package);
		
		$this->copyPackageFiles($package);
	}

	public function copyPackageFiles(PackageInterface $package)
	{
		$packageDir = $this->getInstallPath($package);
		$sourceDir = $packageDir . DIRECTORY_SEPARATOR . self::FILES_DIRECTORY . DIRECTORY_SEPARATOR;

		if (!is_dir($sourceDir)) {
			return;
		}

		$filesToReplicate = $this->getDirContents($sourceDir);

		foreach ($filesToReplicate as $filename) {
			$localFilename = $this->getLocalFilename($sourceDir, $filename);

			if (!file_exists($localFilename) || !is_dir($localFilename)) {
				$this->filesystem->copy($filename, $localFilename);
			}
		}
	}

	protected function getLocalFilename(string $sourceDir, string $packageFilename)
	{
		$vendorDir = realpath($this->vendorDir);
		$rootDir = substr($vendorDir, 0, strlen($vendorDir) - strlen('vendor'));

		return str_replace($sourceDir, $rootDir, $packageFilename);
	}

	protected function getDirContents($dir, &$results = array()) {
		$files = scandir($dir);
	
		foreach ($files as $key => $value) {
			$path = realpath($dir . DIRECTORY_SEPARATOR . $value);
			if (!is_dir($path)) {
				$results[] = $path;
			} else if ($value != "." && $value != "..") {
				$results[] = $path;
				$this->getDirContents($path, $results);
			}
		}
	
		return $results;
	}
}