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
			$installer->copyPackageFiles(null, $package);
		};

		$promise = parent::install($repo, $package);

		// Composer v2 might return a promise here
		if ($promise instanceof PromiseInterface) {
			return $promise->then($replicateFiles);
		}

		// If not, execute the code right away as parent::install executed synchronously (composer v1, or v2 without async)
		$replicateFiles();
	}

	public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target)
	{

		$installer = $this;
		$replicateFiles = function () use ($initial, $target, $installer) {
			$installer->removeDeletedReplications($initial, $target);
			$installer->copyPackageFiles($initial, $target);
		};

		$promise = parent::update($repo, $initial, $target);

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

	public function copyPackageFiles(?PackageInterface $currentlyInstalledPackage, PackageInterface $newPackage)
	{
		$newPackageDir = $this->getInstallPath($newPackage);
		$newSourceDir = $newPackageDir . DIRECTORY_SEPARATOR . self::FILES_DIRECTORY . DIRECTORY_SEPARATOR;
		$installedPackageDir = null;
		$installedSourceDir = null;

		if ($currentlyInstalledPackage) {
			$installedPackageDir = $currentlyInstalledPackage ? $this->getInstallPath($currentlyInstalledPackage) : null;
			$installedSourceDir = $installedPackageDir ? ($installedPackageDir . DIRECTORY_SEPARATOR . self::FILES_DIRECTORY . DIRECTORY_SEPARATOR) : null;
		}

		if (!is_dir($newSourceDir)) {
			return;
		}

		$packageHandler = $this->getPackageHandler($newPackage);
		$filesToReplicate = $this->getDirContents($newSourceDir);

		foreach ($filesToReplicate as $filename) {
			$localFilename = $this->getLocalFilename($newSourceDir, $filename);
			$installedFilename = null;

			if ($installedSourceDir) {
				$installedFilename = $this->getLocalFilename($installedSourceDir, $filename);

				if (!is_file($installedFilename)) {
					$installedFilename = null;
				}
			}

			if (!file_exists($localFilename)) {
				$this->filesystem->copy($filename, $localFilename);

				if (!is_dir($filename) && is_executable($filename)) {
					// Fix permissions after copying
					chmod($localFilename, 0755);
				}

				if ($packageHandler !== null) {
					$packageHandler->postFileCreationCallback($localFilename);
				}
			} else if ($packageHandler !== null) {
				if (file_exists($localFilename) && !is_dir($localFilename)) {
					$packageHandler->handleExistingFile($filename, $localFilename, $installedFilename);
				}
			}
		}
	}

	/**
	 * Removes files that were created in a previous version of the package but that isn't
	 * present in the new version of the package.
	 */
	public function removeDeletedReplications(PackageInterface $oldPackage, PackageInterface $newPackage)
	{
		$oldPackageDir = $this->getInstallPath($oldPackage);
		$oldSourceDir = $oldPackageDir . DIRECTORY_SEPARATOR . self::FILES_DIRECTORY . DIRECTORY_SEPARATOR;
		$newPackageDir = $this->getInstallPath($newPackage);
		$newSourceDir = $newPackageDir . DIRECTORY_SEPARATOR . self::FILES_DIRECTORY . DIRECTORY_SEPARATOR;

		if (!is_dir($oldSourceDir) || !is_dir($newSourceDir)) {
			return;
		}

		$oldFiles = $this->getDirContents($oldSourceDir);
		$newFiles = $this->getDirContents($newSourceDir);

		foreach ($oldFiles as $filename) {
			$newFilename = str_replace($oldSourceDir, $newSourceDir, $filename);

			if (!in_array($newFilename, $newFiles)) {
				$localFilename = $this->getLocalFilename($oldSourceDir, $filename);

				if (file_exists($localFilename)) {
					if (md5_file($filename) == md5_file($localFilename)) {
						unlink($localFilename);
					}
				}
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

	protected function getPackageHandler(PackageInterface $package): ?HandlerInterface
	{
		$extra = $package->getExtra();

		if (!isset($extra['class'])) {
			return null;
		}

		$handlerClass = $extra['class'];

		if (!class_exists($handlerClass)) {
			$autoload = $package->getAutoload();
			$packageDir = $this->getInstallPath($package);

			if (isset($autoload['psr-4'])) {
				foreach ($autoload['psr-4'] as $classPart => $startDir) {
					$classTrailingPart = str_replace($classPart, "", $handlerClass);
					$classPath = $packageDir . '/' . $startDir . $classTrailingPart . '.php';

					if (file_exists($classPath)) {
						require_once($classPath);
					}

					break;
				}
			}
		}

		if (!class_exists($handlerClass)) {
			$this->io->writeError(sprintf("Eckinox package handler $handlerClass could not be loaded. Proceeding with partial installation."));
			return null;
		}

		return new $handlerClass($package, $this->filesystem, $this->io);
	}
}
