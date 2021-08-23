# Composer installer plugin handling file replication

![CI](https://github.com/eckinox/eckinox-installer-plugin/workflows/CI/badge.svg)

A simple Composer plugin to install (meta)packages with extra files and configurations.

## Usage
To get started, simply require this plugin in your project using:

```bash
composer require --dev eckinox/installer-plugin
```

Once that's done, you can require any Composer package with the `eckinox-metapackage` type.

### Creating `eckinox-metapackage` packages
Eckinox metapackages are the same as all regular packages, with one exception: they can include a `replicate` directory at their root.

Whenever a package of that type is installed, every file and directory in the package's `replicate` directory will be replicated as-is in the project's directory.

If a directory in the `replicate` directory already exists in the project, its files will simply be appended to the existing directory. The original won't be deleted.  
However, existing files in the project _will_ be overwritten if they have the same name as another file in the package's `replicate` directory.

#### Adding custom logic via Handlers
If you need to add extra logic and processing during the replication process, you can create an handler class in your package.

Handler classes extend the `Eckinox\Composer\HandlerInterface`, and must be declared in the package's `composer.json` file as such:
```json
	...
    "extra": {
        "class": "Eckinox\\PackageName\\YourHandler"
    },
	...
```

Your handler class must define the following methods:
- `postFileCreationCallback`: Defines what happens when after a file has first been created in the root project.
- `handleExistingFile`: Defines what happens when a file to replicate already exists in the root project.

Here is a example of a very basic handler class that simply renames a `rename-me.txt` file to `renamed.txt` when it is first replicated in the main project.
```php
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

	public function handleExistingFile(string $packageFilename, string $projectFilename)
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
```

## Testing
To test this plugin, make sure the dependencies are installed, and run 

```bash
composer test
```

Test cases are located in the `tests/` directory and are executed by PHPUnit.