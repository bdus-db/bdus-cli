# bdus-cli
A php command line utility to create new Bradypus web databases

## Usage

The utily can be used to validate [Bradypus](https://github.com/jbogdani/BraDypUS) 
configuration files and to a create a new application from these files.

### Example 1. Validation
```bash
php bdus.php validate /path/to/existing/configuration/directory
```
A report will be printed and the validation will halt in case of any error.
`/path/to/existing/configuration/directory` can be a relative path, 
an absolute path or even a remote path, accessible via an URL, for example a GitHub repository.

### Example 2. New app creation
```bash
php bdus.php create /path/to/existing/configuration/directory /path/local/destination/directory
```

`/path/local/destination/directory` must be a **writeable** and **non existing** directory.
The script will fail if the path is already available and **will not overrite** existing files.
