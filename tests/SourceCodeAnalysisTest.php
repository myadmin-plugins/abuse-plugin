<?php
/**
 * Static analysis tests that inspect source files via file_get_contents.
 *
 * These tests verify coding conventions, expected patterns, and structural
 * properties of the source code without executing any database or IMAP
 * dependent code paths.
 */

namespace Detain\MyAdminAbuse\Tests;

use PHPUnit\Framework\TestCase;

class SourceCodeAnalysisTest extends TestCase
{
    /** @var string Absolute path to the package root */
    private $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__);
    }

    // ------------------------------------------------------------------
    //  Plugin.php analysis
    // ------------------------------------------------------------------

    /**
     * Tests that Plugin.php opens with a proper PHP tag and namespace declaration.
     */
    public function testPluginPhpHasNamespace(): void
    {
        $source = file_get_contents($this->root . '/src/Plugin.php');
        $this->assertStringContainsString('namespace Detain\\MyAdminAbuse;', $source);
    }

    /**
     * Tests that Plugin.php imports GenericEvent from Symfony.
     */
    public function testPluginImportsGenericEvent(): void
    {
        $source = file_get_contents($this->root . '/src/Plugin.php');
        $this->assertStringContainsString(
            'use Symfony\\Component\\EventDispatcher\\GenericEvent;',
            $source
        );
    }

    /**
     * Tests that Plugin.php defines the three expected hook handler methods.
     */
    public function testPluginDefinesHookHandlers(): void
    {
        $source = file_get_contents($this->root . '/src/Plugin.php');
        $this->assertStringContainsString('public static function getMenu', $source);
        $this->assertStringContainsString('public static function getRequirements', $source);
        $this->assertStringContainsString('public static function getSettings', $source);
    }

    /**
     * Tests that Plugin.php registers page requirements with expected paths.
     */
    public function testPluginRegistersPageRequirements(): void
    {
        $source = file_get_contents($this->root . '/src/Plugin.php');
        $this->assertStringContainsString("add_page_requirement('abuse'", $source);
        $this->assertStringContainsString("add_page_requirement('abuse_admin'", $source);
        $this->assertStringContainsString("add_requirement('class.ImapAbuseCheck'", $source);
    }

    // ------------------------------------------------------------------
    //  ImapAbuseCheck.php analysis
    // ------------------------------------------------------------------

    /**
     * Tests that ImapAbuseCheck.php defines the class without a namespace.
     * The class is global (no namespace) and referenced as such in the codebase.
     */
    public function testImapAbuseCheckHasNoNamespace(): void
    {
        $source = file_get_contents($this->root . '/src/ImapAbuseCheck.php');
        // Should not contain a namespace declaration
        $this->assertDoesNotMatchRegularExpression(
            '/^namespace\s+\S+;/m',
            $source
        );
    }

    /**
     * Tests that ImapAbuseCheck.php declares the class.
     */
    public function testImapAbuseCheckDeclaresClass(): void
    {
        $source = file_get_contents($this->root . '/src/ImapAbuseCheck.php');
        $this->assertStringContainsString('class ImapAbuseCheck', $source);
    }

    /**
     * Tests that ImapAbuseCheck imports the expected ORM classes.
     */
    public function testImapAbuseCheckImportsOrm(): void
    {
        $source = file_get_contents($this->root . '/src/ImapAbuseCheck.php');
        $this->assertStringContainsString('use MyAdmin\\Orm\\Abuse;', $source);
        $this->assertStringContainsString('use MyAdmin\\Orm\\Abuse_Data;', $source);
    }

    /**
     * Tests that ImapAbuseCheck uses the Db class from MyDb.
     */
    public function testImapAbuseCheckImportsDb(): void
    {
        $source = file_get_contents($this->root . '/src/ImapAbuseCheck.php');
        $this->assertStringContainsString('use MyDb\\Mysqli\\Db;', $source);
    }

    /**
     * Tests that ImapAbuseCheck contains the fix_headers static method.
     */
    public function testImapAbuseCheckHasFixHeaders(): void
    {
        $source = file_get_contents($this->root . '/src/ImapAbuseCheck.php');
        $this->assertStringContainsString('public static function fix_headers', $source);
    }

    // ------------------------------------------------------------------
    //  abuse.php analysis
    // ------------------------------------------------------------------

    /**
     * Tests that abuse.php declares the abuse() function.
     */
    public function testAbusePhpDeclaresFunction(): void
    {
        $source = file_get_contents($this->root . '/src/abuse.php');
        $this->assertStringContainsString('function abuse()', $source);
    }

    /**
     * Tests that abuse.php uses TFSmarty for template rendering.
     */
    public function testAbusePhpUsesSmarty(): void
    {
        $source = file_get_contents($this->root . '/src/abuse.php');
        $this->assertStringContainsString('new TFSmarty()', $source);
    }

    // ------------------------------------------------------------------
    //  abuse_admin.php analysis
    // ------------------------------------------------------------------

    /**
     * Tests that abuse_admin.php declares the abuse_admin() function.
     */
    public function testAbuseAdminPhpDeclaresFunction(): void
    {
        $source = file_get_contents($this->root . '/src/abuse_admin.php');
        $this->assertStringContainsString('function abuse_admin()', $source);
    }

    /**
     * Tests that abuse_admin.php references the abuse types expected by the UI.
     * The admin form offers these predefined abuse type categories.
     */
    public function testAbuseAdminDefinesAbuseTypes(): void
    {
        $source = file_get_contents($this->root . '/src/abuse_admin.php');
        $types = ['scanning', 'hacking', 'spam', 'phishing site', 'other'];
        foreach ($types as $type) {
            $this->assertStringContainsString(
                "'{$type}'",
                $source,
                "Abuse type '{$type}' should be listed in the admin form"
            );
        }
    }

    /**
     * Tests that abuse_admin.php uses CSRF protection for form submissions.
     */
    public function testAbuseAdminUsesCsrf(): void
    {
        $source = file_get_contents($this->root . '/src/abuse_admin.php');
        $this->assertStringContainsString("verify_csrf('abuse_admin')", $source);
        $this->assertStringContainsString("verify_csrf('abuse_admin_multiple')", $source);
        $this->assertStringContainsString("verify_csrf('abuse_admin_uce')", $source);
        $this->assertStringContainsString("verify_csrf('abuse_admin_trend')", $source);
    }

    /**
     * Tests that abuse_admin uses ACL checks before allowing access.
     */
    public function testAbuseAdminChecksAcl(): void
    {
        $source = file_get_contents($this->root . '/src/abuse_admin.php');
        $this->assertStringContainsString("has_acl('client_billing')", $source);
    }

    /**
     * Tests that abuse_admin calls ImapAbuseCheck::fix_headers() statically.
     */
    public function testAbuseAdminCallsFixHeaders(): void
    {
        $source = file_get_contents($this->root . '/src/abuse_admin.php');
        $this->assertStringContainsString('ImapAbuseCheck::fix_headers(', $source);
    }

    // ------------------------------------------------------------------
    //  bin/ scripts analysis
    // ------------------------------------------------------------------

    /**
     * Tests that bin scripts include the bootstrap and ImapAbuseCheck source.
     */
    public function testBinScriptsIncludeRequiredFiles(): void
    {
        $downloader = file_get_contents($this->root . '/bin/abuse_imap_downloader.php');
        $this->assertStringContainsString('functions.inc.php', $downloader);
        $this->assertStringContainsString('ImapAbuseCheck.php', $downloader);

        $cleanup = file_get_contents($this->root . '/bin/clean_up_abuse_headers.php');
        $this->assertStringContainsString('functions.inc.php', $cleanup);
        $this->assertStringContainsString('ImapAbuseCheck.php', $cleanup);
    }

    /**
     * Tests that the IMAP downloader script uses register_preg_match.
     * This is the main entry point for processing abuse mailboxes.
     */
    public function testDownloaderUsesRegisterPregMatch(): void
    {
        $source = file_get_contents($this->root . '/bin/abuse_imap_downloader.php');
        $this->assertStringContainsString('register_preg_match(', $source);
        $this->assertStringContainsString('register_preg_match_all(', $source);
    }

    // ------------------------------------------------------------------
    //  composer.json analysis
    // ------------------------------------------------------------------

    /**
     * Tests that composer.json is valid JSON with expected fields.
     */
    public function testComposerJsonIsValid(): void
    {
        $raw = file_get_contents($this->root . '/composer.json');
        $data = json_decode($raw, true);
        $this->assertNotNull($data, 'composer.json should be valid JSON');
        $this->assertSame('detain/myadmin-abuse-plugin', $data['name']);
        $this->assertArrayHasKey('autoload', $data);
        $this->assertArrayHasKey('psr-4', $data['autoload']);
        $this->assertArrayHasKey('Detain\\MyAdminAbuse\\', $data['autoload']['psr-4']);
    }

    /**
     * Tests that the PSR-4 autoload points to src/.
     */
    public function testComposerAutoloadPointsToSrc(): void
    {
        $data = json_decode(file_get_contents($this->root . '/composer.json'), true);
        $this->assertSame('src/', $data['autoload']['psr-4']['Detain\\MyAdminAbuse\\']);
    }
}
