<?php
/**
 * Tests for the ImapAbuseCheck class.
 *
 * Because this class is not namespaced and depends heavily on IMAP extensions,
 * database connections, MongoDB, and global functions, we test it through
 * ReflectionClass for structure and by invoking only the pure/static methods
 * that have no side effects.
 */

namespace Detain\MyAdminAbuse\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ImapAbuseCheckTest extends TestCase
{
    /** @var ReflectionClass */
    private $ref;

    protected function setUp(): void
    {
        // ImapAbuseCheck is a global (non-namespaced) class defined in src/ImapAbuseCheck.php
        if (!class_exists('ImapAbuseCheck', false)) {
            // We cannot safely require the file because the constructor has
            // heavy side-effects.  Instead, just skip structural tests.
        }
        $this->ref = new ReflectionClass('ImapAbuseCheck');
    }

    // ------------------------------------------------------------------
    //  Class structure
    // ------------------------------------------------------------------

    /**
     * Tests that the ImapAbuseCheck class exists when its source file is loaded.
     * This validates the autoloading / file inclusion path.
     */
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists('ImapAbuseCheck'));
    }

    /**
     * Tests that the class is not abstract — it is instantiated in bin scripts.
     */
    public function testClassIsNotAbstract(): void
    {
        $this->assertFalse($this->ref->isAbstract());
    }

    // ------------------------------------------------------------------
    //  Public properties
    // ------------------------------------------------------------------

    /**
     * Tests that all expected public properties exist on the class.
     * These properties are accessed directly throughout the codebase.
     */
    public function testExpectedPublicPropertiesExist(): void
    {
        $expected = [
            'imap_server', 'imap_username', 'imap_password', 'imap_folder',
            'ip_regex', 'delete_attachments', 'mbox', 'MC',
            'limit_ips', 'ips', 'mongo_client', 'mb_db', 'mb_users',
            'mb_ips', 'emails', 'abused', 'db', 'all_ips', 'client_ips',
            'preg_match', 'preg_match_all', 'email_headers',
            'charset', 'htmlmsg', 'plainmsg', 'attachments',
        ];
        foreach ($expected as $prop) {
            $this->assertTrue(
                $this->ref->hasProperty($prop),
                "Property \${$prop} should exist on ImapAbuseCheck"
            );
            $this->assertTrue(
                $this->ref->getProperty($prop)->isPublic(),
                "Property \${$prop} should be public"
            );
        }
    }

    // ------------------------------------------------------------------
    //  ip_regex default value
    // ------------------------------------------------------------------

    /**
     * Tests that the ip_regex default value is a valid regex that matches IPv4.
     * The regex is used by register_preg_match to locate IPs in email bodies.
     */
    public function testIpRegexDefaultMatchesIpv4(): void
    {
        $defaults = $this->ref->getDefaultProperties();
        $regex = $defaults['ip_regex'];
        $this->assertIsString($regex);
        // Wrap it as a full regex and test against a known IP
        $this->assertSame(1, preg_match('/' . $regex . '/', '192.168.1.1', $m));
        $this->assertSame('192.168.1.1', $m['ip']);
    }

    /**
     * Tests that the ip_regex does NOT match invalid IP octets > 255.
     */
    public function testIpRegexRejectsInvalidOctets(): void
    {
        $defaults = $this->ref->getDefaultProperties();
        $regex = '/^' . $defaults['ip_regex'] . '$/';
        // 999.999.999.999 should not fully match (first octet limited to 255)
        $this->assertSame(0, preg_match($regex, '999.999.999.999'));
    }

    /**
     * Tests that ip_regex matches edge-case IPs like 0.0.0.0 and 255.255.255.255.
     */
    public function testIpRegexMatchesEdgeCases(): void
    {
        $defaults = $this->ref->getDefaultProperties();
        $regex = '/^' . $defaults['ip_regex'] . '$/';
        $this->assertSame(1, preg_match($regex, '0.0.0.0'));
        $this->assertSame(1, preg_match($regex, '255.255.255.255'));
        $this->assertSame(1, preg_match($regex, '10.0.0.1'));
    }

    // ------------------------------------------------------------------
    //  Default values for array/scalar properties
    // ------------------------------------------------------------------

    /**
     * Tests that array properties default to empty arrays.
     */
    public function testArrayPropertiesDefaultToEmpty(): void
    {
        $defaults = $this->ref->getDefaultProperties();
        $this->assertSame([], $defaults['ips']);
        $this->assertSame([], $defaults['mb_users']);
        $this->assertSame([], $defaults['mb_ips']);
        $this->assertSame([], $defaults['emails']);
        $this->assertSame([], $defaults['preg_match']);
        $this->assertSame([], $defaults['preg_match_all']);
    }

    /**
     * Tests that scalar properties have the expected defaults.
     */
    public function testScalarPropertyDefaults(): void
    {
        $defaults = $this->ref->getDefaultProperties();
        $this->assertSame(0, $defaults['abused']);
        $this->assertFalse($defaults['limit_ips']);
    }

    // ------------------------------------------------------------------
    //  Method existence and signatures
    // ------------------------------------------------------------------

    /**
     * Tests that all expected public methods exist on the class.
     * This catches accidental renames or removals.
     */
    public function testExpectedMethodsExist(): void
    {
        $methods = [
            'get_ip_regex', 'set_default_email_headers', 'set_all_ips',
            'load_all_ips', 'load_client_ips', 'connect',
            'register_preg_match', 'register_preg_match_all',
            'process', 'delete_messages', 'disconnect',
            'getmsg', 'getpart', 'get_folders', 'list_folders',
            'fix_headers',
        ];
        foreach ($methods as $method) {
            $this->assertTrue(
                $this->ref->hasMethod($method),
                "Method {$method}() should exist on ImapAbuseCheck"
            );
        }
    }

    /**
     * Tests that fix_headers() is public and static.
     * It is called statically from abuse_admin.php.
     */
    public function testFixHeadersIsPublicStatic(): void
    {
        $method = $this->ref->getMethod('fix_headers');
        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
    }

    /**
     * Tests that the constructor requires at least 4 parameters.
     * (imap_server, username, password, db) with 2 optional.
     */
    public function testConstructorParameterCount(): void
    {
        $ctor = $this->ref->getConstructor();
        $this->assertNotNull($ctor);
        $this->assertSame(4, $ctor->getNumberOfRequiredParameters());
        $this->assertSame(6, $ctor->getNumberOfParameters());
    }

    /**
     * Tests register_preg_match signature: 1 required param, 3 total.
     */
    public function testRegisterPregMatchSignature(): void
    {
        $m = $this->ref->getMethod('register_preg_match');
        $this->assertSame(1, $m->getNumberOfRequiredParameters());
        $this->assertSame(3, $m->getNumberOfParameters());
    }

    /**
     * Tests register_preg_match_all signature: 1 required param, 3 total.
     */
    public function testRegisterPregMatchAllSignature(): void
    {
        $m = $this->ref->getMethod('register_preg_match_all');
        $this->assertSame(1, $m->getNumberOfRequiredParameters());
        $this->assertSame(3, $m->getNumberOfParameters());
    }

    /**
     * Tests process() signature: 0 required, 2 total (type, limit).
     */
    public function testProcessSignature(): void
    {
        $m = $this->ref->getMethod('process');
        $this->assertSame(0, $m->getNumberOfRequiredParameters());
        $this->assertSame(2, $m->getNumberOfParameters());
    }

    // ------------------------------------------------------------------
    //  fix_headers() — pure static method
    // ------------------------------------------------------------------

    /**
     * Tests that fix_headers() returns a string for simple header input.
     * This method has no side effects and can be called directly.
     */
    public function testFixHeadersReturnsString(): void
    {
        $result = \ImapAbuseCheck::fix_headers("From: test@example.com\nSubject: Test\n\nBody text here");
        $this->assertIsString($result);
    }

    /**
     * Tests that fix_headers() strips HTML tags from the output.
     */
    public function testFixHeadersStripsHtml(): void
    {
        $input = "From: test@example.com\n\n<b>Bold</b> and <i>italic</i>";
        $result = \ImapAbuseCheck::fix_headers($input);
        $this->assertStringNotContainsString('<b>', $result);
        $this->assertStringNotContainsString('<i>', $result);
    }

    /**
     * Tests that fix_headers() handles empty string input gracefully.
     */
    public function testFixHeadersHandlesEmptyString(): void
    {
        $result = \ImapAbuseCheck::fix_headers('');
        $this->assertIsString($result);
    }

    /**
     * Tests that fix_headers() collapses multiple blank lines.
     */
    public function testFixHeadersCollapsesBlankLines(): void
    {
        $input = "Line1\n\n\n\nLine2";
        $result = \ImapAbuseCheck::fix_headers($input);
        // Should not contain three consecutive newlines
        $this->assertStringNotContainsString("\n\n\n", $result);
    }
}
