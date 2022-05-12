<?php

namespace Eckinox\Composer\Tests\MockPackage;

use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Util\Filesystem;
use Eckinox\Composer\HandlerInterface;

class ReplicationHandler implements HandlerInterface
{

	protected $package;
	protected $filesystem;
	protected $io;

	public function __construct(PackageInterface $package, Filesystem $filesystem, IOInterface $io)
	{
		$this->package = $package;
		$this->filesystem = $filesystem;
		$this->io = $io;
	}

	public function handleExistingFile(string $packageFilename, string $projectFilename, ?string $currentlyInstalledFilename = null)
	{

	}

	public function postFileCreationCallback(string $projectFilename)
	{
		if (basename($projectFilename) == "rename-me.txt") {
			$newFilename = substr($projectFilename, 0, strlen($projectFilename) - 13) . "renamed.txt";
			rename($projectFilename, $newFilename);
		}
	}
}
