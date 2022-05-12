<?php

namespace Eckinox\Composer;

use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Util\Filesystem;

interface HandlerInterface
{
	public function __construct(PackageInterface $package, Filesystem $filesystem, IOInterface $io);

	/**
	 * Defines what happens when after a file has first been created in the root project.
	 *
	 * @param string $projectFilename Full path of the newly created file in the root project.
	 * @return void
	 */
	public function postFileCreationCallback(string $projectFilename);

	/**
	 * Defines what happens when a file to replicate already exists in the root project.
	 *
	 * @param string $packageFilename Full path of the base file in the package.
	 * @param string $projectFilename Full path of the existing file in the root project.
	 * @param string|null $currentlyInstalledFilename Full path of the source file in the currently installed package.
	 * This can be used to compare the previous version of a source file vs the updated version.
	 * When null, there is no currently installed version of this file (ie: new file or fresh package install).
	 * @return void
	 */
	public function handleExistingFile(string $packageFilename, string $projectFilename, ?string $currentlyInstalledFilename = null);
}
