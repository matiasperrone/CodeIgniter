# CodeIgniter 2 (Fork with PHP 8.x support)

Open Source PHP Framework (originally from EllisLab)

For more info, please refer to the user-guide at http://www.codeigniter.com/userguide2/  
(also available within the download package for offline use)

**Note:** CodeIgniter 2.x is no longer maintained upstream (the last upstream release is 2.2.6; new
projects should use the latest CodeIgniter 4). This branch (**2.3.0**) keeps 2.x alive by making it run
on **PHP 5.6 through PHP 8.5** and by making it installable with Composer. See the
[upgrade notes](user_guide/installation/upgrade_230.html) before moving an existing 2.2.x site.

## Install with Composer

```
composer create-project codeigniter/framework my-app
```

(or `composer install` in a checkout). Then enable the Composer auto-loader so the packages you add with
`composer require` are available in your controllers, models and libraries:

```php
// application/config/config.php
$config['composer_autoload'] = TRUE;   // loads vendor/autoload.php
```

## Tests

A dependency-free test suite lives in `tests/` and runs unchanged on every supported PHP version:

```
composer test            # php tests/run.php, with the PHP on your PATH
composer test:all        # tests/run-matrix.sh: php5.6, php8.3, php8.4, php8.5
```

See [tests/README.md](tests/README.md).
