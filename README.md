# Composer installer plugin for packages with file replication
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

## Testing
To test this plugin, make sure the dependencies are installed, and run 

```bash
composer test
```

Test cases are located in the `tests/` directory and are executed by PHPUnit.