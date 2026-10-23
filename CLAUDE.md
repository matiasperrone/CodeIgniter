# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

CodeIgniter 2.x, now at 2.3.0 (`CI_VERSION` in `system/core/CodeIgniter.php`), a legacy PHP MVC framework from EllisLab. This fork keeps it running on **PHP 5.6 through 8.5**: any change must stay valid on both ends of that range (no short arrays, scalar types, `::class` or other 7+/8+ syntax in `system/`, `application/` or `tests/`; the attribute `#[\AllowDynamicProperties]` above each class is a plain `#` comment on PHP < 8). Work happens on `2.3.0-php-8.x`; `develop` is the main branch.

There is no build step. Run it with `php -S localhost:8000 index.php`. It is a Composer *project* (`composer.json`, no PSR-4 autoload of `system/`): `$config['composer_autoload']` in `application/config/config.php` makes the bootstrap require `vendor/autoload.php`.

## Tests

The suite is dependency free (no PHPUnit) and is the backwards-compatibility contract for the PHP 8 migration:

```
php8.5 tests/run.php                      # one interpreter (php5.6, php8.3, php8.4, php8.5 are installed side by side)
php8.5 tests/run.php --file=EmailTest     # one case file (cases/*Test.php)
php8.5 tests/run.php --filter=xss         # tests whose name contains "xss"
tests/run-matrix.sh                       # every installed version, with a summary
```

Things that are easy to trip over:

- Each case file runs in its own process (so helpers defined in one file are not visible in another) and the framework is booted through the real `system/core/CodeIgniter.php` (`tests/bootstrap.php`). Anything raised while *booting* fails the `boot` test; any notice/warning/deprecation raised *inside* a test fails that test; `lint` compiles every PHP file and fails on any diagnostic.
- Failing on a deprecation is the point: PHP 8.x deprecations (dynamic properties, null to non-nullable internals, optional-before-required parameters, ...) are test failures, not noise.
- `tests/fixtures/encrypt.php` holds ciphertexts produced by the original mcrypt `CI_Encrypt`; regenerate with `tests/tools/make_encrypt_fixtures.php` only against the *unmodified* library (use a `git worktree` of the baseline commit).
- To expect a result a PHP 5-era quirk produces, pin the quirk (the suite documents several, e.g. `Xmlrpcs::multicall_error('key')` notices, PDO SQLite `num_rows`), rather than "fixing" it inside a compatibility change.

## Architecture

- `index.php` is the front controller. It sets `ENVIRONMENT` (default `development`, which controls `error_reporting`), defines `$system_path`/`$application_folder`, and then requires `system/core/CodeIgniter.php`.
- `system/core/compat/` holds polyfills (currently `random_bytes()`), required by `CodeIgniter.php` right after `Common.php`; each defines a function only if the running PHP lacks it.
- `system/` is the framework and should only change for framework fixes. `application/` is user code, and its directory layout mirrors the loadable types (controllers, models, views, libraries, helpers, language, config, core, hooks, third_party).
- `system/core/CodeIgniter.php` is the bootstrap. It runs in this order: load constants and `Common.php`, then Benchmark, Hooks, Config, Utf8, URI, Router, Output (with cache check), Security, Input, Lang, then the requested controller and method, then Output display. Hook points (`pre_system`, `pre_controller`, `post_controller_constructor`, `post_controller`, `display_override`, `cache_override`, `post_system`) are fired from there and configured in `application/config/hooks.php`.
- Core classes in `system/core/` are instantiated through `load_class()` in `Common.php`. Classes with the `CI_` prefix can be overridden or extended from `application/core/` using the `MY_` prefix (configured by `subclass_prefix` in `application/config/config.php`). Resolution order is `application/` first, then `system/`.
- `CI_Controller` is a singleton (`get_instance()`). Libraries, models and helpers are loaded through `CI_Loader` (`system/core/Loader.php`) and attached to the controller as properties. Config files for libraries and drivers are looked up in `application/config/` and then `config/<ENVIRONMENT>/`.
- `system/libraries/` holds the standard libraries (Session, Form_validation, Email, Upload, Image_lib, etc.). `system/helpers/` holds the procedural helpers. `system/language/` holds the bundled language files.
- `system/database/` is the DB layer. `DB.php` is the factory, which picks a driver from `system/database/drivers/<driver>/` (mysql, mysqli, pdo, postgre, sqlite, oci8, odbc, mssql, sqlsrv, cubrid). Each driver folder provides `*_driver`, `*_result`, `*_forge` and `*_utility` classes that extend the shared base classes `DB_driver`, `DB_result`, `DB_forge`, `DB_utility`. Query building is in `DB_active_rec.php`. A change to shared behaviour usually has to be mirrored in every driver. `DB.php` maps `dbdriver 'mysql'` to `mysqli` when the removed mysql extension is missing.
- `user_guide/` is the HTML documentation, including `changelog.html` and the `installation/upgrade_*.html` pages. User-visible changes, version bumps and security fixes are recorded there.
- `CI_Encrypt` has two backends (`$config['encryption_driver']`: `auto|mcrypt|openssl`); mcrypt stays the default when loaded so existing ciphertexts decode. OpenSSL only covers `rijndael-128` (AES), not the default `rijndael-256`.
- The Xmlrpc/Xmlrpcs state for a parse is keyed by `xmlrpc_parser_key($parser)` because the parser is an `XMLParser` object on PHP 8 and a resource before.

## Conventions

- Files use tabs for indentation and Allman-style braces, with the legacy PHP 5.x style and the license header block at the top. Match the surrounding code.
- Every directory contains an `index.html` placeholder to block directory listing. Add one to any new directory.
- Files begin with `if ( ! defined('BASEPATH')) exit('No direct script access allowed');`. Keep it on new framework and application files.
