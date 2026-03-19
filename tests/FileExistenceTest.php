<?php
/**
 * Tests that all expected source and configuration files exist in the package.
 *
 * This is a structural test that validates the package layout has not been
 * accidentally broken by a refactor or incomplete commit.
 */

namespace Detain\MyAdminAbuse\Tests;

use PHPUnit\Framework\TestCase;

class FileExistenceTest extends TestCase
{
    /** @var string Absolute path to the package root */
    private $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__);
    }

    /**
     * Tests that the main src/ PHP files exist.
     * These are the core source files loaded by the plugin system.
     */
    public function testSrcFilesExist(): void
    {
        $files = [
            'src/Plugin.php',
            'src/ImapAbuseCheck.php',
            'src/abuse.php',
            'src/abuse_admin.php',
        ];
        foreach ($files as $file) {
            $this->assertFileExists(
                $this->root . '/' . $file,
                "Source file {$file} should exist"
            );
        }
    }

    /**
     * Tests that the bin/ script files exist.
     * These are cron/CLI scripts used operationally.
     */
    public function testBinFilesExist(): void
    {
        $files = [
            'bin/abuse_imap_downloader.php',
            'bin/clean_up_abuse_headers.php',
            'bin/match_abuse.php',
        ];
        foreach ($files as $file) {
            $this->assertFileExists(
                $this->root . '/' . $file,
                "Bin file {$file} should exist"
            );
        }
    }

    /**
     * Tests that composer.json exists at the package root.
     */
    public function testComposerJsonExists(): void
    {
        $this->assertFileExists($this->root . '/composer.json');
    }

    /**
     * Tests that README.md exists at the package root.
     */
    public function testReadmeExists(): void
    {
        $this->assertFileExists($this->root . '/README.md');
    }
}
