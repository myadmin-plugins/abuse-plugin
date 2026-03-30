---
name: abuse-record-insert
description: Inserts a complete abuse incident across the `abuse` and `abuse_data` tables using make_insert_query(). Use when adding new code that reports an abuse incident — manual, CSV import, or IMAP-triggered. Covers the exact field list for both tables, getLastInsertId(), ImapAbuseCheck::fix_headers() for abuse_headers, and clientMail() notification via client/abuse.tpl. Trigger phrases: 'insert abuse record', 'report abuse', 'add abuse entry', 'log abuse incident'. Do NOT use for querying, updating, or deleting existing abuse records.
---
# Abuse Record Insert

## Critical

- **Never use PDO.** Always use `$db = get_module_db('default')` and `make_insert_query()`.
- **Never interpolate raw `$_GET`/`$_POST` into SQL.** Use `$db->real_escape()` or `make_insert_query()`.
- **Always validate the IP first** with `validIp($ip, false)` before any DB write.
- **Always resolve the owner** via `get_server_from_ip($ip)` and verify `$server_data['email'] != ''` before inserting.
- **`abuse_id` must be `null`** in the `abuse` insert — the DB auto-increments it.
- **`abuse_status` must be `'pending'`** for all new records.
- Valid `abuse_type` values: `'scanning'`, `'hacking'`, `'spam'`, `'child porn'`, `'phishing site'`, `'other'`, `'uceprotect'`, `'trendmicro'`.

## Instructions

1. **Get the DB handle**

   ```php
   $db = get_module_db('default');
   ```

   Verify `$db` is not null before proceeding.

2. **Validate the IP and resolve the server owner**

   ```php
   if (!validIp($ip, false)) {
       // skip or surface an error — do not insert
   }
   $server_data = get_server_from_ip($ip);
   if (!isset($server_data['email']) || $server_data['email'] == '') {
       add_output('Error Finding Owner For ' . $ip . '<br>');
       return;
   }
   $email = $server_data['email'];
   ```

   `$server_data['email']` is the billing contact; `$server_data['email_abuse']` is the address to notify (may differ).

3. **Insert the `abuse` row**

   Use `mysql_now()` for current-time inserts, or a pre-formatted `MYSQL_DATE_FORMAT` string for historical dates (e.g. from CSV).

   ```php
   $db->query(make_insert_query('abuse', [
       'abuse_id'     => null,
       'abuse_time'   => mysql_now(),      // or $date->format(MYSQL_DATE_FORMAT)
       'abuse_ip'     => $ip,
       'abuse_type'   => $type,            // one of the valid values listed in Critical
       'abuse_amount' => 1,
       'abuse_lid'    => $email,
       'abuse_status' => 'pending',
   ]), __LINE__, __FILE__);
   $id = $db->getLastInsertId('abuse', 'abuse_id');
   ```

   Verify `$id > 0` before proceeding to Step 4.

4. **Insert the `abuse_data` row** (when raw headers/body are available)

   Always pass headers through `ImapAbuseCheck::fix_headers()` to strip HTML and normalise newlines.

   ```php
   $db->query(make_insert_query('abuse_data', [
       'abuse_id'      => $id,
       'abuse_headers' => ImapAbuseCheck::fix_headers($rawHeaders),
   ]), __LINE__, __FILE__);
   ```

   For IMAP-sourced records the class concatenates plain + HTML message bodies:

   ```php
   'abuse_headers' => trim(ImapAbuseCheck::fix_headers($this->plainmsg . $this->htmlmsg)),
   ```

   Skip this step only when no header/body content exists (e.g. bare IP-list CSV imports without evidence).

5. **Build and send the client notification email**

   The template key is always `'client/abuse.tpl'`. The link key used in the template is `md5($id . $ip . $type)`.

   ```php
   $subject = 'InterServer Abuse Report for ' . $ip;
   $message = str_replace(
       ['{$email}', '{$ip}', '{$type}', '{$count}', '{$id}', '{$key}'],
       [
           $server_data['email_abuse'],
           $ip,
           $type,
           $amount,          // 1 for most sources; $amount for manual single-IP form
           $id,
           md5($id . $ip . $type),
       ],
       $email_template       // loaded earlier from the abuse email template file
   );
   (new \MyAdmin\Mail())->clientMail($subject, $message, $server_data['email_abuse'], 'client/abuse.tpl');
   ```

   Send to `$server_data['email_abuse']`, not `$email` (they can differ).

6. **Confirm to the admin UI**

   ```php
   add_output('Abuse Entry for ' . $ip . ' Added - Emailing '
       . ($server_data['email_abuse'] !== $email
           ? $server_data['email_abuse'] . ' (for client ' . $email . ')'
           : $server_data['email_abuse'])
       . '<br>');
   ```

## Examples

**User says:** "Add code that manually reports a single IP for spam abuse and emails the owner."

**Actions taken:**

```php
$db = get_module_db('default');
$ip   = $db->real_escape($GLOBALS['tf']->variables->request['ip']);
$type = 'spam';

if (!validIp($ip, false)) {
    add_output('Invalid IP<br>');
    return;
}

$server_data = get_server_from_ip($ip);
if (!isset($server_data['email']) || $server_data['email'] == '') {
    add_output('Error Finding Owner For ' . $ip . '<br>');
    return;
}
$email = $server_data['email'];

// Step 3 — abuse row
$db->query(make_insert_query('abuse', [
    'abuse_id'     => null,
    'abuse_time'   => mysql_now(),
    'abuse_ip'     => $ip,
    'abuse_type'   => $type,
    'abuse_amount' => 1,
    'abuse_lid'    => $email,
    'abuse_status' => 'pending',
]), __LINE__, __FILE__);
$id = $db->getLastInsertId('abuse', 'abuse_id');

// Step 4 — abuse_data row
$rawHeaders = $GLOBALS['tf']->variables->request['headers'] ?? '';
$db->query(make_insert_query('abuse_data', [
    'abuse_id'      => $id,
    'abuse_headers' => ImapAbuseCheck::fix_headers($rawHeaders),
]), __LINE__, __FILE__);

// Step 5 — notify
$subject = 'InterServer Abuse Report for ' . $ip;
$message = str_replace(
    ['{$email}', '{$ip}', '{$type}', '{$count}', '{$id}', '{$key}'],
    [$server_data['email_abuse'], $ip, $type, 1, $id, md5($id . $ip . $type)],
    $email_template
);
(new \MyAdmin\Mail())->clientMail($subject, $message, $server_data['email_abuse'], 'client/abuse.tpl');

// Step 6 — UI feedback
add_output('Abuse Entry for ' . $ip . ' Added - Emailing ' . $server_data['email_abuse'] . '<br>');
```

**Result:** One row in `abuse` with `abuse_status='pending'`, one row in `abuse_data` with sanitised headers, and a notification email sent to the abuse contact.

## Common Issues

**`make_insert_query` produces a query with wrong column count or missing fields:**
- Ensure `abuse_id => null` is present — omitting it causes an auto-increment mismatch.
- `abuse_lid` must be the billing email string, not an integer ID.

**`getLastInsertId` returns 0 or null:**
- The `make_insert_query()` call failed silently. Always pass `__LINE__, __FILE__` as the 2nd/3rd args to `$db->query()` so errors surface in logs.
- Confirm the `abuse` table exists and the DB handle is for `'default'`, not `'mail'`.

**`ImapAbuseCheck::fix_headers()` not found:**
- The class file is not autoloaded. Ensure `function_requirements('class.ImapAbuseCheck')` has been called, or that the file is included via `add_requirement` in `Plugin::getRequirements()`.

**`clientMail()` sends to the wrong address:**
- Use `$server_data['email_abuse']`, not `$server_data['email']`. These can be different accounts (e.g., resellers).

**Email template placeholders not replaced:**
- The `str_replace` keys must exactly match `{$email}`, `{$ip}`, `{$type}`, `{$count}`, `{$id}`, `{$key}` — curly braces and dollar signs included.
- `$email_template` must be loaded from the abuse email template before the `str_replace` call. Check that `$email_template` is not empty.

**Historical date inserts (CSV import) showing wrong timestamps:**
- Do not use `mysql_now()` for CSV rows. Parse the source date with `new \DateTime(...)` and format with `$date->format(MYSQL_DATE_FORMAT)` before passing to `make_insert_query`.