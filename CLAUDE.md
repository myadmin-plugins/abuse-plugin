# MyAdmin Abuse Plugin

PHP plugin for the MyAdmin control panel. Monitors IMAP mailboxes for abuse complaints, resolves offending IPs to customers, sends notifications.

## Commands

```bash
composer install                     # install deps (PHPUnit ^9.6)
vendor/bin/phpunit                   # run all tests (config: phpunit.xml.dist)
vendor/bin/phpunit --coverage-text   # text coverage report
```

## Architecture

**Namespace:** `Detain\MyAdminAbuse\` → `src/` (PSR-4 via `composer.json`)
**Test namespace:** `Detain\MyAdminAbuse\Tests\` → `tests/` (bootstrap: `tests/bootstrap.php`)

**Source:** `src/Plugin.php` · `src/ImapAbuseCheck.php` · `src/abuse.php` · `src/abuse_admin.php`

- `src/Plugin.php` — registers `system.settings`, `ui.menu`, `function.requirements` hooks
- `src/ImapAbuseCheck.php` — IMAP processor; parses emails, extracts IPs, writes `abuse`/`abuse_data` tables; no namespace (loaded via `add_requirement`)
- `src/abuse.php` — client-facing page: view/respond to complaint (token or session auth)
- `src/abuse_admin.php` — admin dashboard: stats, manual report, UCEProtect/Trend Micro CSV import

**Bin scripts (cron):** `bin/abuse_imap_downloader.php` · `bin/match_abuse.php` · `bin/clean_up_abuse_headers.php`

- `bin/abuse_imap_downloader.php` — reads `INCLUDE_ROOT/config/abuse.json`, runs `ImapAbuseCheck->process()`
- `bin/match_abuse.php` — correlates `abuse_ip` with MailBaby/ZoneMTA users via MongoDB + `ZONEMTA_*` constants
- `bin/clean_up_abuse_headers.php` — batch-cleans `abuse_headers` via `ImapAbuseCheck::fix_headers()`

## Plugin Hook Pattern

`src/Plugin.php` — all handler methods are `public static function name(GenericEvent $event)`:

```php
public static function getHooks(): array {
    return [
        'system.settings'      => [__CLASS__, 'getSettings'],
        'ui.menu'              => [__CLASS__, 'getMenu'],
        'function.requirements'=> [__CLASS__, 'getRequirements'],
    ];
}
// Register pages/classes in getRequirements():
$loader->add_page_requirement('abuse', '/src/abuse.php');
$loader->add_requirement('class.ImapAbuseCheck', '/src/ImapAbuseCheck.php');
```

## Database Pattern

Never use PDO. Use the MyAdmin DB wrapper from `get_module_db()`:

```php
$db = get_module_db('default');  // or 'mail' for mail module
$db->query("SELECT * FROM abuse WHERE abuse_id={$id}", __LINE__, __FILE__);
$db->next_record(MYSQL_ASSOC);
$row = $db->Record;

// Inserts: always make_insert_query()
$db->query(make_insert_query('abuse', [
    'abuse_id'     => null,
    'abuse_time'   => mysql_now(),
    'abuse_ip'     => $ip,
    'abuse_type'   => 'spam',   // scanning|hacking|spam|child porn|phishing site|other
    'abuse_amount' => 1,
    'abuse_lid'    => $email,
    'abuse_status' => 'pending',
]), __LINE__, __FILE__);
$id = $db->getLastInsertId('abuse', 'abuse_id');
$db->query(make_insert_query('abuse_data', ['abuse_id' => $id, 'abuse_headers' => ImapAbuseCheck::fix_headers($headers)]), __LINE__, __FILE__);

$db->real_escape($userInput);   // escape all user input
```

## IMAP Processing Pattern

Config from `INCLUDE_ROOT/config/abuse.json`. Pattern used in `bin/abuse_imap_downloader.php`:

```php
$abuse = new ImapAbuseCheck(
    '{'.$check['host'].':'.$check['port'].'/imap/ssl}'.$check['mailbox'],
    ABUSE_IMAP_USER, ABUSE_IMAP_PASS, $db,
    $check['delete_attachments'], $check['mail_limit']
);
$abuse->register_preg_match('/pattern with %IP%/', 'headers', 'ip');     // single match
$abuse->register_preg_match_all('/pattern/', 'body', 'ip');              // all matches
$abuse->process('spam', 100);
// Static helper — strips HTML, collapses blank lines:
$clean = ImapAbuseCheck::fix_headers($rawHeaders);
```

## Security

- Admin pages: always check `$GLOBALS['tf']->ima == 'admin'` AND `has_acl('client_billing')`
- CSRF: `verify_csrf('abuse_admin')` per form — tokens: `abuse_admin`, `abuse_admin_multiple`, `abuse_admin_uce`, `abuse_admin_trend`
- Validate IPs: `validIp($ip, false)` before `get_server_from_ip($ip)`
- Escape: `$db->real_escape()` on all `$_GET`/`$_POST` values
- Ownership check in `src/abuse.php`: verify `$server_data['email'] == $accounts->data['account_lid']` before showing complaint
- Email notifications: template at `include/templates/email/client/abuse.tpl` via `(new \MyAdmin\Mail())->clientMail()`

## Testing

Tests in `tests/` — PHPUnit 9 (`phpunit.xml.dist`), bootstrap `tests/bootstrap.php`:

- `tests/FileExistenceTest.php` — asserts `src/` and `bin/` files exist
- `tests/PluginTest.php` — reflection tests for `Plugin` hooks, static props, handler signatures
- `tests/ImapAbuseCheckTest.php` — reflection tests for properties, methods, `ip_regex` defaults
- `tests/SourceCodeAnalysisTest.php` — `file_get_contents` checks for required source patterns

Reflection test pattern (no DB, no IMAP needed):

```php
$ref = new ReflectionClass(\Detain\MyAdminAbuse\Plugin::class);
$method = $ref->getMethod('getHooks');
$this->assertTrue($method->isPublic() && $method->isStatic());
```

## Conventions

- Commit messages: lowercase, descriptive (`fix imap header parsing`, `add uceprotect import`)
- Tabs for indentation (per `.scrutinizer.yml` coding style config)
- `ImapAbuseCheck` has no namespace — it is a global class loaded via `function_requirements('class.ImapAbuseCheck')`
- Log with `myadmin_log('myadmin', 'debug', $msg, __LINE__, __FILE__)`
- Run `caliber refresh` before committing; stage `CLAUDE.md .claude/ CALIBER_LEARNINGS.md`

<!-- caliber:managed:pre-commit -->
## Before Committing

**IMPORTANT:** Before every git commit, you MUST ensure Caliber syncs agent configs with the latest code changes.

First, check if the pre-commit hook is already installed:
```bash
grep -q "caliber" .git/hooks/pre-commit 2>/dev/null && echo "hook-active" || echo "no-hook"
```

- If **hook-active**: the hook handles sync automatically — just commit normally. Tell the user: "Caliber will sync your agent configs automatically via the pre-commit hook."
- If **no-hook**: run Caliber manually before committing:
  1. Tell the user: "Caliber: Syncing agent configs with your latest changes..."
  2. Run: `caliber refresh && git add CLAUDE.md .claude/ .cursor/ .cursorrules .github/copilot-instructions.md .github/instructions/ AGENTS.md CALIBER_LEARNINGS.md .agents/ .opencode/ 2>/dev/null`
  3. After it completes, briefly tell the user what Caliber updated. Then proceed with the commit.

If `caliber` is not found, tell the user: "This project uses Caliber for agent config sync. Run /setup-caliber to get set up."
<!-- /caliber:managed:pre-commit -->

<!-- caliber:managed:learnings -->
## Session Learnings

Read `CALIBER_LEARNINGS.md` for patterns and anti-patterns learned from previous sessions.
These are auto-extracted from real tool usage — treat them as project-specific rules.
<!-- /caliber:managed:learnings -->

<!-- caliber:managed:sync -->
## Context Sync

This project uses [Caliber](https://github.com/caliber-ai-org/ai-setup) to keep AI agent configs in sync across Claude Code, Cursor, Copilot, and Codex.
Configs update automatically before each commit via `caliber refresh`.
If the pre-commit hook is not set up, run `/setup-caliber` to configure everything automatically.
<!-- /caliber:managed:sync -->
