---
name: phpunit-reflection-test
description: Writes a PHPUnit 9 test class using ReflectionClass to validate class structure without instantiation or DB. Use when adding tests for new methods, properties, or static behaviors in src/Plugin.php or src/ImapAbuseCheck.php. Matches the pattern in tests/PluginTest.php and tests/ImapAbuseCheckTest.php. Trigger phrases: 'add test', 'write test for', 'test method signature', 'test class structure'. Do NOT use for integration tests that require DB, IMAP connection, or MongoDB.
---
# PHPUnit Reflection Test

## Critical

- **Never instantiate classes under test.** All structure checks must go through `ReflectionClass`. Classes in this plugin have heavy constructor side-effects (IMAP, DB, MongoDB).
- **No DB, no IMAP, no MongoDB in these tests.** If a test would require a live connection, it belongs in an integration test, not here.
- **`ImapAbuseCheck` is not namespaced.** Reference it as the bare string `'ImapAbuseCheck'`, not with a namespace. Namespaced classes use `ClassName::class`.
- Run tests with `composer test` (config: `phpunit.xml.dist`). All tests must pass before committing.
- Static pure methods (like `ImapAbuseCheck::fix_headers()`) may be called directly — they have no side effects.

---

## Instructions

### Step 1 — Identify the class under test

Determine:
- **Namespace:** namespaced classes live under `Detain\MyAdminAbuse\` (e.g., `src/Plugin.php`). Non-namespaced classes (e.g., `src/ImapAbuseCheck.php`) are referenced as bare strings.
- **File path:** confirm the source file exists in `src/`.

Verify before proceeding: `class_exists('TargetClass')` or `class_exists(TargetClass::class)` returns `true` in the test bootstrap.

### Step 2 — Create the test file

File location: `tests/ClassNameTest.php` (filename must exactly match the test class name, e.g. `tests/PluginTest.php` for `class PluginTest`).

Boilerplate (namespaced class):

```php
<?php
/**
 * Tests for Detain\MyAdminAbuse\ClassName
 *
 * Validates class structure, static properties, and method signatures
 * using ReflectionClass — no DB, IMAP, or framework required.
 */

namespace Detain\MyAdminAbuse\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class ClassNameTest extends TestCase
{
    /** @var ReflectionClass */
    private $ref;

    protected function setUp(): void
    {
        $this->ref = new ReflectionClass(\Detain\MyAdminAbuse\ClassName::class);
    }
}
```

Boilerplate (non-namespaced class like `ImapAbuseCheck`):

```php
<?php
namespace Detain\MyAdminAbuse\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ImapAbuseCheckTest extends TestCase
{
    /** @var ReflectionClass */
    private $ref;

    protected function setUp(): void
    {
        // Non-namespaced; loaded via bootstrap — do not require the file here
        $this->ref = new ReflectionClass('ImapAbuseCheck');
    }
}
```

Verify: the test file is under `tests/` and the class name matches `<ClassName>Test`.

### Step 3 — Add class-structure tests

Always include these baseline checks:

```php
public function testClassExists(): void
{
    $this->assertTrue(class_exists(\Detain\MyAdminAbuse\ClassName::class));
}

public function testNamespace(): void
{
    $this->assertSame('Detain\\MyAdminAbuse', $this->ref->getNamespaceName());
}

public function testIsInstantiableClass(): void
{
    $this->assertFalse($this->ref->isAbstract());
    $this->assertFalse($this->ref->isInterface());
    $this->assertTrue($this->ref->isInstantiable());
}
```

Verify: these three tests pass with `composer test -- --filter testClassExists`.

### Step 4 — Test method existence and visibility

For each method you need to cover:

```php
// Existence
public function testExpectedMethodsExist(): void
{
    $methods = ['methodOne', 'methodTwo', 'methodThree'];
    foreach ($methods as $method) {
        $this->assertTrue(
            $this->ref->hasMethod($method),
            "Method {$method}() should exist"
        );
    }
}

// Public + static
public function testMethodNameIsPublicStatic(): void
{
    $method = $this->ref->getMethod('methodName');
    $this->assertTrue($method->isPublic());
    $this->assertTrue($method->isStatic());
}

// Parameter count
public function testMethodNameSignature(): void
{
    $m = $this->ref->getMethod('methodName');
    $this->assertSame(1, $m->getNumberOfRequiredParameters());
    $this->assertSame(3, $m->getNumberOfParameters());
    // Optional: check parameter name
    $params = $m->getParameters();
    $this->assertSame('event', $params[0]->getName());
}
```

Verify: run `composer test -- --filter testMethodName` after each addition.

### Step 5 — Test properties

```php
// Existence and visibility
public function testExpectedPublicPropertiesExist(): void
{
    $expected = ['propA', 'propB', 'propC'];
    foreach ($expected as $prop) {
        $this->assertTrue($this->ref->hasProperty($prop), "Property \${$prop} should exist");
        $this->assertTrue($this->ref->getProperty($prop)->isPublic(), "Property \${$prop} should be public");
    }
}

// Default values
public function testPropertyDefaults(): void
{
    $defaults = $this->ref->getDefaultProperties();
    $this->assertSame([], $defaults['arrayProp']);
    $this->assertSame(0,  $defaults['intProp']);
    $this->assertFalse($defaults['boolProp']);
}

// Static property value (only if no side-effects)
public function testStaticPropertyName(): void
{
    $this->assertSame('Expected Value', \Detain\MyAdminAbuse\ClassName::$staticProp);
}
```

### Step 6 — Test pure static methods directly

Only call methods that have **no** DB/IMAP/MongoDB side-effects:

```php
public function testPureStaticMethodReturnsExpectedType(): void
{
    $result = \ImapAbuseCheck::fix_headers("From: a@b.com\n\nBody");
    $this->assertIsString($result);
}

public function testPureStaticMethodHandlesEdgeCase(): void
{
    $result = \ImapAbuseCheck::fix_headers('');
    $this->assertIsString($result);
}
```

### Step 7 — Run the full suite

```
composer test
```

All tests must be green. Fix any failures before committing.

---

## Examples

**User says:** "Add a test for the `getHooks` method on Plugin to confirm it returns an array with `system.settings`, `ui.menu`, and `function.requirements` keys."

**Actions taken:**
1. Open `tests/PluginTest.php`.
2. Add inside `PluginTest`:

```php
public function testGetHooksReturnsExpectedKeys(): void
{
    $hooks = \Detain\MyAdminAbuse\Plugin::getHooks();
    $this->assertIsArray($hooks);
    $this->assertArrayHasKey('system.settings', $hooks);
    $this->assertArrayHasKey('ui.menu', $hooks);
    $this->assertArrayHasKey('function.requirements', $hooks);
}

public function testGetHooksIsPublicStatic(): void
{
    $method = $this->ref->getMethod('getHooks');
    $this->assertTrue($method->isPublic());
    $this->assertTrue($method->isStatic());
}
```

3. Run `composer test -- --filter testGetHooks`.

**Result:** Two passing tests, no DB or IMAP touched.

---

**User says:** "Write a test for ImapAbuseCheck to verify `fix_headers` strips HTML."

**Actions taken:**
1. Open `tests/ImapAbuseCheckTest.php`.
2. Add:

```php
public function testFixHeadersStripsHtml(): void
{
    $input  = "From: a@b.com\n\n<b>Bold</b> text";
    $result = \ImapAbuseCheck::fix_headers($input);
    $this->assertStringNotContainsString('<b>', $result);
}
```

3. Run `composer test -- --filter testFixHeadersStripsHtml`.

**Result:** One passing test.

---

## Common Issues

**Error:** `Class 'ImapAbuseCheck' not found`
- The class is loaded via `tests/bootstrap.php`, not PSR-4 autoload. Check that `tests/bootstrap.php` includes the file. Do not add a `require` inside the test itself — it may double-include and cause a fatal.

**Error:** `ReflectionException: Class Detain\MyAdminAbuse\Plugin does not exist`
- Composer autoload is not wired. Run `composer install` and confirm `vendor/autoload.php` is required in `tests/bootstrap.php`.

**Error:** `Call to undefined method ReflectionClass::getMethod()` returning wrong results
- You passed a string with wrong casing. PHP class names in `ReflectionClass` are case-insensitive, but property/method names are not. Double-check exact spelling against the source file.

**Error:** `assertSame(1, $m->getNumberOfRequiredParameters()) failed, got 0`
- The method signature has a default for all params. Use `getNumberOfParameters()` for total count and `getNumberOfRequiredParameters()` for mandatory-only. Recheck the source method signature in `src/`.

**Error:** `Cannot access protected property via getDefaultProperties()`
- `getDefaultProperties()` returns all properties regardless of visibility. If the assertion fails, the property name is misspelled or the default differs from what you expect — read the source file to confirm.

**Error:** Tests pass locally but fail in CI
- CI runs `composer test` with `phpunit.xml.dist`. Confirm your new test class is in `tests/` and the filename exactly matches the class name (`ClassNameTest.php` → `class ClassNameTest`).
