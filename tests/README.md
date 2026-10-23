# Test suite

Dependency-free tests that run unchanged on every supported PHP version
(5.6 through 8.5). They characterise the framework's current behaviour, so
they are the backwards-compatibility contract for the PHP 8 migration.

```
tests/run-matrix.sh                     # php5.6, php8.3, php8.4, php8.5
PHP_VERSIONS="5.6 8.5" tests/run-matrix.sh
php8.5 tests/run.php                    # a single interpreter
php8.5 tests/run.php --file=EmailTest   # one case file
php8.5 tests/run.php --filter=xss       # tests whose name contains "xss"
```

* Each file in `cases/` runs in its own process; a test that calls `exit`
  (e.g. `show_error()`) is reported as `ABORT` instead of ending the run.
* The framework is booted through the real `system/core/CodeIgniter.php`.
  Anything it raises while loading (notices, warnings, deprecations) fails
  the `boot` test; anything raised inside a test fails that test.
* Test code itself must stay PHP 5.3-syntax compatible (no `[]` arrays,
  no `::class`, no scalar type declarations).
* `fixtures/encrypt.php` holds ciphertexts produced by the original
  mcrypt-based `CI_Encrypt`; `tools/make_encrypt_fixtures.php` regenerates
  them (only ever run it against the unmodified library).
