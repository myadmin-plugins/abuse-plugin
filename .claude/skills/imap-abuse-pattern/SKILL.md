---
name: imap-abuse-pattern
description: Instantiates ImapAbuseCheck and registers regex patterns for IMAP mailbox processing. Use when adding a new abuse mail source, registering a new pattern type, or modifying bin/abuse_imap_downloader.php. Covers constructor args (host, ABUSE_IMAP_USER, ABUSE_IMAP_PASS, $db, delete_attachments, mail_limit), register_preg_match(), register_preg_match_all(), process(), and fix_headers(). Trigger phrases: 'add imap check', 'new abuse pattern', 'register pattern', 'process mailbox'. Do NOT use for abuse_data DB inserts — use abuse-record-insert instead.
---
# IMAP Abuse Pattern

## Critical

- **Never instantiate `ImapAbuseCheck` with raw credentials** — always use the `ABUSE_IMAP_USER` and `ABUSE_IMAP_PASS` constants; never hardcode passwords.
- The constructor calls `imap_open()` immediately — if the server is unreachable the script dies with `Cannot connect to {server}`. Verify IMAP host/port/mailbox before constructing.
- `%IP%` in any pattern string is automatically replaced with the built-in IPv4 regex (`$this->ip_regex`). Always use `%IP%` as the IP placeholder — never paste the raw regex manually.
- The `$field` argument to `register_preg_match()` / `register_preg_match_all()` must match a named capture group in the pattern (e.g. `'ip'` matches `(?P<ip>...)`). A mismatch silently skips the IP.
- `process()` validates every extracted IP with `validIp($ip, false)` **and** checks it against `$this->all_ips` / `$this->client_ips`. IPs not in either list are logged and skipped — registering a pattern is not enough; the IP must belong to InterServer.
- Do **not** call `make_insert_query()` inside `ImapAbuseCheck` logic — abuse record creation uses ORM objects (`Abuse`, `Abuse_Data`). Use the `abuse-record-insert` skill for direct DB inserts.

## Instructions

### Step 1 — Include dependencies

At the top of any bin script that uses `ImapAbuseCheck`:

```php
include_once __DIR__.'/../../../../include/functions.inc.php';
include_once __DIR__.'/../src/ImapAbuseCheck.php';
```

Verify both files exist at those relative paths before proceeding. `functions.inc.php` must load first — it defines `get_module_db()`, `function_requirements()`, and the `ABUSE_IMAP_*` / `ZONEMTA_*` constants.

### Step 2 — Bootstrap session and DB (bin scripts only)

For cron/bin scripts, create a session and grab the DB handle exactly as `abuse_imap_downloader.php` does:

```php
$GLOBALS['tf']->session->create(160307, 'services', false, 0, false, substr(basename($_SERVER['argv'][0], '.php'), 0, 32));
$db = $GLOBALS['tf']->db;
```

For utility scripts that don't need a session (like `clean_up_abuse_headers.php`):

```php
$db = get_module_db('default');
```

Verify `$db` is a valid DB object before passing it to the constructor.

### Step 3 — Load abuse.json config

The mailbox list lives in `INCLUDE_ROOT/config/abuse.json`. Load it:

```php
$checks = json_decode(file_get_contents(INCLUDE_ROOT.'/config/abuse.json'), true);
```

Each entry has these keys:

```json
{
  "host": "mx.interserver.net",
  "port": 993,
  "mailbox": "INBOX.Hotmail",
  "delete_attachments": 1,
  "mail_limit": 5,
  "type": "spam",
  "limit": false,
  "patterns": [
    { "type": "match",     "pattern": "/X-IP: %IP%/", "what": "headers" },
    { "type": "match_all", "pattern": "/%IP%/",        "what": "body" }
  ]
}
```

Verify `json_decode()` does not return `null` before iterating.

### Step 4 — Construct ImapAbuseCheck

Build the IMAP server string as `{host:port/imap/ssl}MAILBOX`:

```php
$abuse = new ImapAbuseCheck(
    '{'.$check['host'].':'.$check['port'].'/imap/ssl}'.$check['mailbox'],
    ABUSE_IMAP_USER,
    ABUSE_IMAP_PASS,
    $db,
    $check['delete_attachments'],   // 1 = delete after processing, 0 = keep
    $check['mail_limit']            // false = unlimited, int = max/day per IP
);
```

The constructor auto-calls `connect()`, loads `all_ips`/`client_ips` into globals for reuse across multiple instances, and sets up MongoDB for MailBaby matching.

### Step 5 — Register patterns

Iterate `$check['patterns']` and dispatch on `type`:

```php
foreach ($check['patterns'] as $pattern) {
    if ($pattern['type'] == 'match') {
        // Use when one IP per email is expected
        $abuse->register_preg_match($pattern['pattern'], $pattern['what']);
    } elseif ($pattern['type'] == 'match_all') {
        // Use when multiple IPs may appear; takes first match
        $abuse->register_preg_match_all($pattern['pattern'], $pattern['what']);
    }
}
```

Signature: `register_preg_match(string $regex, string $against = 'headers', string $field = 'ip')`

`$against` values:
- `'headers'` — match against raw `imap_fetchbody(..., '0')` headers (default)
- `'body'` — match against raw `imap_fetchbody(..., '1')` body part
- `'bodyfull'` — match against decoded `$this->plainmsg` (all text parts combined)

`$field` must be a named capture group in the regex. Always use `%IP%` as the placeholder:

```php
// Correct — %IP% expands to the full IPv4 named-group regex
$abuse->register_preg_match('/Reported-Source: %IP%/', 'headers', 'ip');

// Correct — custom named group
$abuse->register_preg_match('/Source: (?P<addr>\S+)/', 'headers', 'addr');
```

### Step 6 — Process the mailbox

```php
$abuse->process($check['type'], $check['limit']);
// $check['type'] — abuse type string: 'spam'|'scanning'|'hacking'|'phishing site'|'child porn'|'other'
// $check['limit'] — false = all messages, int = cap at N messages
```

`process()` iterates all messages, extracts IPs via registered patterns, validates ownership, creates `Abuse`/`Abuse_Data` ORM records, emails the customer, then calls `imap_expunge()` if `delete_attachments == 1`.

### Step 7 — fix_headers() for batch cleanup (optional)

Use the static method to sanitize raw email headers stored in `abuse_data.abuse_headers`:

```php
$out = ImapAbuseCheck::fix_headers($db->Record['abuse_headers']);
if ($out != $db->Record['abuse_headers']) {
    $db2->query("update abuse_data set abuse_headers='".$db->real_escape($out)."' where abuse_id={$db->Record['abuse_id']}");
}
```

`fix_headers()` strips HTML tags and collapses blank lines. Always use a second DB handle (`$db2`) so the outer `$db` cursor is not clobbered mid-loop.

## Examples

**Adding a new mailbox source for UCEProtect reports:**

User says: "Add a new IMAP check for INBOX.UCEProtect that scans body for IPs and limits to 3 per day."

Add to `INCLUDE_ROOT/config/abuse.json`:
```json
{
  "host": "mx.interserver.net",
  "port": 993,
  "mailbox": "INBOX.UCEProtect",
  "delete_attachments": 1,
  "mail_limit": 3,
  "type": "spam",
  "limit": false,
  "patterns": [
    { "type": "match_all", "pattern": "/%IP%/", "what": "bodyfull" }
  ]
}
```

No code changes to `bin/abuse_imap_downloader.php` are needed — the loop reads all entries from `abuse.json` automatically. After adding the entry, test with:

```bash
php bin/abuse_imap_downloader.php
# Output: INBOX.UCEProtect Got N Messages
# Output: INBOX.UCEProtect Abuse Entry for X.X.X.X Added - Emailing user@example.com
```

**Registering a pattern in code directly:**

```php
$abuse = new ImapAbuseCheck(
    '{mx.interserver.net:993/imap/ssl}INBOX.Spamcop',
    ABUSE_IMAP_USER, ABUSE_IMAP_PASS, $db, 1, 5
);
// Match IP in a specific header line
$abuse->register_preg_match('/X-Spamcop-Reportee-Ip: %IP%/', 'headers', 'ip');
// Fallback: scan full decoded body
$abuse->register_preg_match_all('/%IP%/', 'bodyfull', 'ip');
$abuse->process('spam', false);
```

## Common Issues

**`Cannot connect to {mx.example.com:993/imap/ssl}INBOX.Foo` — script dies**
1. Check IMAP host/port: `openssl s_client -connect mx.example.com:993`
2. Verify `ABUSE_IMAP_USER` / `ABUSE_IMAP_PASS` constants are defined in the loaded config.
3. Confirm the mailbox name exists: temporarily call `$abuse->list_folders()` before `process()`.

**`INBOX.Foo Invalid IP false or not ours` on every message**
- The pattern didn't match. Add a debug line before `process()`: `print_r($abuse->preg_match);`
- Check `$against` value — `'body'` uses raw MIME part 1, `'bodyfull'` uses the decoded plaintext. Switch to `'bodyfull'` if the IP is in a forwarded/quoted section.
- Confirm `%IP%` is present in the pattern and the named group is `'ip'` (or matches the `$field` argument).

**IP found but `Error Finding Owner For X.X.X.X`**
- The IP is not in `all_ips` (IP blocks) or `client_ips`. It's a valid IP but not InterServer's.
- This is expected for external IPs — no action needed.

**`MongoDB {host} down: ...` warning in output**
- MailBaby/ZoneMTA matching is disabled but processing continues. This is non-fatal.
- Check `ZONEMTA_HOST` / `ZONEMTA_USERNAME` / `ZONEMTA_PASSWORD` constants if MailBaby matching is required.

**`json_decode` returns null for abuse.json**
- Run `php -r "json_decode(file_get_contents('path/to/abuse.json'), true); echo json_last_error_msg();"` to find the syntax error.
- Ensure all pattern regex strings have JSON-escaped backslashes (`\\` in JSON = `\` in PHP).