## Codeigniter - Application Installer

A Composer global command that installs the latest official Codeigniter framework.

[![asciicast](https://asciinema.org/a/45174.png)](https://asciinema.org/a/45174)

#### Using Composer:

```
composer global require "dsv/codeigniter-installer"
```

Once installed, you can run the command:

```
codeigniter new <app_name>
``` 

Also you can specify the CI version with the second param, so the installer can search for an alternate application version:

```
codeigniter new <app_name> 3.0.6
```

#### Note: 

Make sure to place the `~/.composer/vendor/bin` directory in your bash `PATH` so the `codeigniter` executable can be located by your system. 
