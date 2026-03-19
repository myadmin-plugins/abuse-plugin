# MyAdmin Abuse Plugin

[![Tests](https://github.com/detain/myadmin-abuse-plugin/actions/workflows/tests.yml/badge.svg)](https://github.com/detain/myadmin-abuse-plugin/actions/workflows/tests.yml)
[![Latest Stable Version](https://poser.pugx.org/detain/myadmin-abuse-plugin/version)](https://packagist.org/packages/detain/myadmin-abuse-plugin)
[![Total Downloads](https://poser.pugx.org/detain/myadmin-abuse-plugin/downloads)](https://packagist.org/packages/detain/myadmin-abuse-plugin)
[![License](https://poser.pugx.org/detain/myadmin-abuse-plugin/license)](https://packagist.org/packages/detain/myadmin-abuse-plugin)

Abuse handling plugin for the [MyAdmin](https://github.com/detain/myadmin) control panel. This plugin monitors IMAP mailboxes for incoming abuse complaints (spam reports, blacklist notifications, phishing alerts), matches the offending IP addresses to customer services, and automatically notifies the responsible account holders.

## Features

- **IMAP Abuse Monitoring** -- Connects to configurable IMAP mailboxes and parses abuse complaint emails using regex pattern matching to extract offending IP addresses.
- **IP-to-Customer Resolution** -- Looks up IP addresses against the server inventory and client IP pools to identify the responsible customer.
- **MailBaby Integration** -- Detects outbound mail abuse through ZoneMTA / MailBaby user matching and message ID correlation.
- **Admin Dashboard** -- Provides an admin interface for manually reporting abuse, importing UCEProtect CSV data, and importing Trend Micro blocklist entries.
- **Client Self-Service** -- Allows customers to view and respond to abuse complaints via authenticated or token-based URLs.
- **Automated Notifications** -- Sends templated email notifications to affected customers with complaint details and response links.

## Installation

Install with Composer:

```sh
composer require detain/myadmin-abuse-plugin
```

The plugin registers itself with the MyAdmin event dispatcher and adds:

- `system.settings` -- IMAP credential configuration fields
- `ui.menu` -- Admin menu entry for the abuse dashboard
- `function.requirements` -- Page and class autoload registrations

## Usage

### Plugin Registration

The plugin hooks are registered automatically when loaded by the MyAdmin plugin system:

```php
$hooks = \Detain\MyAdminAbuse\Plugin::getHooks();
// Returns: ['system.settings' => ..., 'ui.menu' => ..., 'function.requirements' => ...]
```

### IMAP Abuse Checker

The `ImapAbuseCheck` class processes abuse mailboxes from cron:

```php
$abuse = new ImapAbuseCheck($imapServer, $username, $password, $db);
$abuse->register_preg_match('/pattern with %IP%/', 'headers', 'ip');
$abuse->process('spam', 100);
```

### Admin Interface

Navigate to the abuse admin page in MyAdmin to:

- View abuse statistics (24-hour, 3-day, 7-day breakdowns)
- Submit individual abuse reports by IP
- Bulk-report multiple IPs
- Import UCEProtect CSV files
- Import Trend Micro blocklist data

## Running Tests

```sh
composer install
vendor/bin/phpunit
```

To generate a coverage report:

```sh
vendor/bin/phpunit --coverage-text
```

## License

This package is licensed under the [LGPL-2.1](https://www.gnu.org/licenses/old-licenses/lgpl-2.1.html) license.
