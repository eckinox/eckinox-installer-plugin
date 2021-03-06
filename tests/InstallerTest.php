<?php

namespace Eckinox\Composer\Tests;

use Composer\Composer;
use Composer\Config;
use Composer\Package\Package;
use Composer\Package\RootPackage;
use Composer\Util\Filesystem;
use Eckinox\Composer\Installer;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/mock-package/src/ReplicationHandler.php";

class CustomInstallerTest extends TestCase
{
	private function getVendorDir()
	{
		static $dir = null;

		if ($dir !== null) {
			return $dir;
		}

		$composer = new Composer();
		$config = new Config(false, realpath('.'));
		$composer->setConfig($config);

		return $dir = rtrim($composer->getConfig()->get('vendor-dir'), '/');
	}

	private function getMockAuthorDir(): string
	{
		return $this->getVendorDir() . '/eckinox-mock';
	}

	public function setUp(): void
	{
		$mockPackageDir = __DIR__ . '/../tests/mock-package';
		$filesystem = new Filesystem();
		$filesystem->ensureDirectoryExists($this->getMockAuthorDir());
		$filesystem->copy($mockPackageDir, $this->getMockAuthorDir() . '/mock-package');
	}

	public function tearDown(): void
	{
		$filesystem = new Filesystem();
		$filesystem->removeDirectoryPhp($this->getMockAuthorDir());
		$filesystem->removeDirectoryPhp(__DIR__ . '/../dir');
		$filesystem->remove(__DIR__ . '/../test.txt');
		$filesystem->remove(__DIR__ . '/../test2.txt');
		$filesystem->remove(__DIR__ . '/../renamed.txt');
		$filesystem->remove(__DIR__ . '/test.txt');
	}

	private function getInstaller(): Installer
	{
		$composer = new Composer();
		$config = new Config(false, realpath('.'));
		$composer->setConfig($config);

		/** @var \Composer\IO\IOInterface */
		$io = $this->createMock('Composer\IO\IOInterface');

		return new Installer($io, $composer);
	}

	/**
	 * Tests installation path for given package/spec combination.
	 */
	public function testFileReplication()
	{
		$installer = $this->getInstaller();

		// Package installation
		$package = new Package('eckinox-mock/mock-package', '1.0.0', '1.0.0');
		$package->setExtra([
			"class" => "Eckinox\\Composer\\Tests\\MockPackage\\ReplicationHandler"
		]);
		$installer->copyPackageFiles(null, $package);

		$this->assertFileExists(__DIR__ . '/../test.txt', 'Root-level files are replicated.');
		$this->assertDirectoryExists(__DIR__ . '/../dir', 'New directories are replicated.');
		$this->assertFileExists(__DIR__ . '/../dir/test.txt', 'Nested files are replicated.');
		$this->assertFileExists(__DIR__ . '/test.txt', 'Nested files in existing directories are replicated.');
		$this->assertFileExists(__DIR__ . '/../renamed.txt', 'Handler is loaded and executed.');

		// Package update
		$mockPackageDir = __DIR__ . '/../tests/mock-package-v2';
		$filesystem = new Filesystem();
		$filesystem->ensureDirectoryExists($this->getMockAuthorDir());
		$filesystem->copy($mockPackageDir, $this->getMockAuthorDir() . '/mock-package-v2');
		$updatedPackage = new Package('eckinox-mock/mock-package-v2', '2.0.0', '2.0.0');
		$updatedPackage->setExtra([
			"class" => "Eckinox\\Composer\\Tests\\MockPackage\\ReplicationHandler"
		]);
		$installer->removeDeletedReplications($package, $updatedPackage);
		$installer->copyPackageFiles($package, $updatedPackage);
		$this->assertFileDoesNotExist(__DIR__ . '/../test.txt', 'Removed files are removed on.');
		$this->assertFileExists(__DIR__ . '/../test2.txt', 'Root-level files are replicated.');
		$this->assertDirectoryExists(__DIR__ . '/../dir', 'New directories are replicated.');
		$this->assertFileExists(__DIR__ . '/../dir/test.txt', 'Nested files are replicated.');
		$this->assertFileExists(__DIR__ . '/test.txt', 'Nested files in existing directories are replicated.');
		$this->assertFileExists(__DIR__ . '/../renamed.txt', 'Handler is loaded and executed.');
	}
}
