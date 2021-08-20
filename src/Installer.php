<?php

namespace Eckinox\Composer;

use Composer\Installer\LibraryInstaller;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use React\Promise\PromiseInterface;

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
		$installer = $this;
        $replicateFiles = function () use ($package, $installer) {
            $installer->copyPackageFiles($package);
        };

        $promise = parent::install($repo, $package);

        // Composer v2 might return a promise here
        if ($promise instanceof PromiseInterface) {
            return $promise->then($replicateFiles);
        }

        // If not, execute the code right away as parent::install executed synchronously (composer v1, or v2 without async)
        $replicateFiles();
	}

	public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
	{
		$installPath = $this->getPackageBasePath($package);
        $io = $this->io;
        $outputStatus = function () use ($io, $installPath) {
            $io->write(sprintf('Deleting %s - %s', $installPath, !file_exists($installPath) ? '<comment>deleted</comment>' : '<error>not deleted</error>'));
        };

        $promise = parent::uninstall($repo, $package);

        // Composer v2 might return a promise here
        if ($promise instanceof PromiseInterface) {
            return $promise->then($outputStatus);
        }

        // If not, execute the code right away as parent::uninstall executed synchronously (composer v1, or v2 without async)
        $outputStatus();
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