# [Bug]: Inconsistent environment variable names

### What happened?
The `README.md` documents `EFATURA_TRANSMITTER_LED` and `EFATURA_MIDDLEWARE_ENV`, while `config/efatura.php` uses `EFATURA_ENVIRONMENT`. Furthermore, the `InstallCommand` introduces `EFATURA_LED_CODE`.

### How to reproduce the bug
1. Read `README.md` for configuration instructions.
2. Inspect `config/efatura.php`.
3. Run `php artisan efatura:install` and inspect the `.env` file updates.
4. Observe the mismatch in variable names.

### Package Version
dev-main

### PHP Version
8.2.x

### Laravel Version
10.x

### Which operating systems does this happen with?
Linux

### Notes
The configuration file, installation command, and documentation should use consistent environment variable names to avoid confusion and configuration errors.
