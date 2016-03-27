## Codeigniter - Application Installer

**Using Composer:**

```
composer global require "dsv/codeigniter-installer"
```

Make sure to place the ~/.composer/vendor/bin directory in your bash `PATH` so the ```codeigniter``` executable can be located by your system. 

Once installed, you can run the command:

```
codeigniter new <app_name>
``` 

It will create a fresh CodeIgniter installation in the directory you specify. Also you can specify the CI version with the second param, so the installer can search for an alternate application environment:

```
codeigniter new blog 3.0.3
```
