---
name: plugin-hook-handler
description: Adds a new event hook to src/Plugin.php following the MyAdmin plugin hook pattern. Use when registering a new system event ('add hook', 'new plugin event'), adding a settings field ('add settings field'), or registering a new page or class requirement ('register page', 'add requirement'). Covers getHooks() return array, public static handler method with GenericEvent $event, add_page_requirement(), add_requirement(), add_text_setting(), add_password_setting(). Do NOT use for modifying page logic inside src/abuse.php or src/abuse_admin.php.
---
# Plugin Hook Handler

## Critical

- All handler methods MUST be `public static function name(GenericEvent $event)` — never instance methods.
- `getHooks()` is the single source of truth. Every handler listed there MUST exist as a method in the same class. Missing methods cause a fatal dispatch error.
- Never register hooks anywhere except `getHooks()` and never handle events except through the matching static method.
- The `use Symfony\Component\EventDispatcher\GenericEvent;` import is required at the top of `src/Plugin.php`.
- All string literals shown to users must be wrapped in `_()`  for gettext i18n.

## Instructions

### Step 1 — Choose the hook name and handler method name

Pick a dot-namespaced hook name (e.g. `system.settings`, `function.requirements`). Choose a camelCase method name for the handler (e.g. `getSettings`, `getRequirements`).

Verify the hook name does not already exist in `getHooks()` before proceeding.

### Step 2 — Register the hook in `getHooks()`

Open `src/Plugin.php`. Add your entry to the array returned by `getHooks()`:

```php
public static function getHooks()
{
    return [
        'system.settings'      => [__CLASS__, 'getSettings'],
        'function.requirements'=> [__CLASS__, 'getRequirements'],
        'your.hook.name'       => [__CLASS__, 'YourHandlerMethod'], // <-- add here
    ];
}
```

Verify the array key is a string and the value is `[__CLASS__, 'MethodName']`.

### Step 3 — Implement the static handler method

Add the method to the `Plugin` class body. Always accept `GenericEvent $event` and always retrieve the subject via `$event->getSubject()`:

```php
/**
 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
 */
public static function YourHandlerMethod(GenericEvent $event)
{
    $subject = $event->getSubject();
    // your logic here
}
```

Verify the method name exactly matches the string in `getHooks()`.

### Step 4a — Registering a page requirement (use inside `getRequirements`)

Use `$loader->add_page_requirement()` to map a `choice=` URL parameter to a PHP file.
Use `$loader->add_requirement()` to lazy-load a class file by its `class.*` key.

```php
public static function getRequirements(GenericEvent $event)
{
    /** @var \MyAdmin\Plugins\Loader $loader */
    $loader = $event->getSubject();

    // Map ?choice=my_page to a PHP file:
    $loader->add_page_requirement('my_page', '/../vendor/detain/myadmin-abuse-plugin/src/my_page.php');

    // Lazy-load a class when function_requirements('class.MyClass') is called:
    $loader->add_requirement('class.MyClass', '/../vendor/detain/myadmin-abuse-plugin/src/MyClass.php');
}
```

Paths are relative to `INCLUDE_ROOT` — always start with `/../vendor/`.

Verify the target `.php` file exists at that path before committing.

### Step 4b — Adding a settings field (use inside `getSettings`)

Retrieve the `$settings` subject, then call the appropriate `add_*_setting()` method.
Signature: `add_text_setting($tab, $section, $key, $label, $description, $default)`

```php
public static function getSettings(GenericEvent $event)
{
    /** @var \MyAdmin\Settings $settings */
    $settings = $event->getSubject();

    // Plain text field:
    $settings->add_text_setting(
        _('General'),           // tab
        _('Abuse'),             // section
        'abuse_imap_user',      // DB/config key (snake_case)
        _('Abuse IMAP User'),   // label
        _('Abuse IMAP Username'), // description
        ABUSE_IMAP_USER         // default (PHP constant)
    );

    // Password field (masked in UI):
    $settings->add_password_setting(
        _('General'),
        _('Abuse'),
        'abuse_imap_pass',
        _('Abuse IMAP Pass'),
        _('Abuse IMAP Password'),
        ABUSE_IMAP_PASS
    );
}
```

Verify the config key is snake_case and matches the constant name used as its default.

### Step 5 — Adding a menu link (use inside `getMenu`)

```php
public static function getMenu(GenericEvent $event)
{
    $menu = $event->getSubject();
    if ($GLOBALS['tf']->ima == 'admin') {
        function_requirements('has_acl');
        if (has_acl('client_billing')) {
            $menu->add_link('admin', 'choice=none.my_page', '/images/myadmin/icon.png', _('My Label'));
        }
    }
}
```

ACL guard (`has_acl()`) is required for all admin-only links. Load it with `function_requirements('has_acl')` first.

### Step 6 — Verify

Run the test suite to confirm no dispatch errors:

```
composer test
```

All tests must pass before committing.

## Examples

**User says:** "Add a new `abuse.report` hook that logs incoming events."

**Actions taken:**

1. Add to `getHooks()` in `src/Plugin.php`:
   ```php
   'abuse.report' => [__CLASS__, 'handleAbuseReport'],
   ```
2. Add handler:
   ```php
   public static function handleAbuseReport(GenericEvent $event)
   {
       $report = $event->getSubject();
       myadmin_log('abuse', 'info', 'Abuse report received: '.$report['ip'], __LINE__, __FILE__);
   }
   ```
3. Run `composer test` — all tests pass.

**Result:** The `abuse.report` event is now handled by `Plugin::handleAbuseReport()` and will fire whenever `run_event('abuse.report', $data, 'abuse')` is called.

## Common Issues

**"Call to undefined method" or "Method not found" during dispatch**
- The string in `getHooks()` does not match the actual method name. Grep for the method: `grep -n 'function YourHandlerMethod' src/Plugin.php`. Fix the spelling in `getHooks()` to match exactly.

**Settings field not appearing in UI**
- The `$tab` or `$section` arguments don't match an existing tab/section. Check existing calls in `getSettings()` and reuse the exact same translated string (e.g. `_('General')`).

**Page returns 404 / choice not found**
- `add_page_requirement()` key does not match the `?choice=` URL parameter value. Verify: `grep 'add_page_requirement' src/Plugin.php` and check the URL you're hitting.

**File path in `add_requirement` throws include error**
- The path is wrong or the file doesn't exist. Confirm: `ls $(pwd)/src/MyClass.php`. Paths must start with `/../vendor/detain/myadmin-abuse-plugin/src/`.

**ACL check always fails for admin menu link**
- Missing `function_requirements('has_acl')` before calling `has_acl()`. Always call `function_requirements` first inside `getMenu()`.
