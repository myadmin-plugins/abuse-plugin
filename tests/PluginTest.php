<?php
/**
 * Tests for Detain\MyAdminAbuse\Plugin
 *
 * Validates the Plugin class structure, static properties, hook definitions,
 * and event handler method signatures using ReflectionClass so that no
 * external dependencies (database, IMAP, framework) are required.
 */

namespace Detain\MyAdminAbuse\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class PluginTest extends TestCase
{
    /** @var ReflectionClass */
    private $ref;

    protected function setUp(): void
    {
        $this->ref = new ReflectionClass(\Detain\MyAdminAbuse\Plugin::class);
    }

    // ------------------------------------------------------------------
    //  Class structure
    // ------------------------------------------------------------------

    /**
     * Tests that the Plugin class exists and can be reflected.
     * This is a baseline sanity check for the entire test suite.
     */
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(\Detain\MyAdminAbuse\Plugin::class));
    }

    /**
     * Tests that the Plugin class lives in the expected namespace.
     * Ensures PSR-4 autoloading configuration is correct.
     */
    public function testNamespace(): void
    {
        $this->assertSame('Detain\\MyAdminAbuse', $this->ref->getNamespaceName());
    }

    /**
     * Tests that Plugin is not abstract or an interface, since it is
     * intended to be instantiated directly by the plugin loader.
     */
    public function testIsInstantiableClass(): void
    {
        $this->assertFalse($this->ref->isAbstract());
        $this->assertFalse($this->ref->isInterface());
        $this->assertTrue($this->ref->isInstantiable());
    }

    // ------------------------------------------------------------------
    //  Static properties
    // ------------------------------------------------------------------

    /**
     * Tests that the $name static property is set to 'Abuse Plugin'.
     * The plugin loader uses this property for display purposes.
     */
    public function testStaticPropertyName(): void
    {
        $this->assertSame('Abuse Plugin', \Detain\MyAdminAbuse\Plugin::$name);
    }

    /**
     * Tests that the $description static property is a non-empty string.
     * The plugin loader uses this property for display purposes.
     */
    public function testStaticPropertyDescription(): void
    {
        $this->assertIsString(\Detain\MyAdminAbuse\Plugin::$description);
        $this->assertNotEmpty(\Detain\MyAdminAbuse\Plugin::$description);
    }

    /**
     * Tests that the $type static property is 'plugin'.
     * This identifies the package type to the MyAdmin plugin system.
     */
    public function testStaticPropertyType(): void
    {
        $this->assertSame('plugin', \Detain\MyAdminAbuse\Plugin::$type);
    }

    /**
     * Tests that the $help static property is defined (may be empty string).
     */
    public function testStaticPropertyHelp(): void
    {
        $this->assertTrue($this->ref->hasProperty('help'));
        $this->assertIsString(\Detain\MyAdminAbuse\Plugin::$help);
    }

    // ------------------------------------------------------------------
    //  Constructor
    // ------------------------------------------------------------------

    /**
     * Tests that the constructor takes no required parameters.
     * The plugin loader instantiates plugins with no arguments.
     */
    public function testConstructorHasNoRequiredParameters(): void
    {
        $ctor = $this->ref->getConstructor();
        $this->assertNotNull($ctor);
        $this->assertSame(0, $ctor->getNumberOfRequiredParameters());
    }

    // ------------------------------------------------------------------
    //  getHooks()
    // ------------------------------------------------------------------

    /**
     * Tests that getHooks() is a public static method.
     * The plugin loader calls this statically to register event listeners.
     */
    public function testGetHooksIsPublicStatic(): void
    {
        $method = $this->ref->getMethod('getHooks');
        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
    }

    /**
     * Tests that getHooks() returns an array with the expected event keys.
     * Each key is an event name that the Symfony EventDispatcher will bind.
     */
    public function testGetHooksReturnsExpectedKeys(): void
    {
        $hooks = \Detain\MyAdminAbuse\Plugin::getHooks();
        $this->assertIsArray($hooks);
        $this->assertArrayHasKey('system.settings', $hooks);
        $this->assertArrayHasKey('ui.menu', $hooks);
        $this->assertArrayHasKey('function.requirements', $hooks);
    }

    /**
     * Tests that each hook value is a callable-style array [class, method].
     * This ensures the Symfony EventDispatcher can invoke them.
     */
    public function testGetHooksValuesAreCallableArrays(): void
    {
        $hooks = \Detain\MyAdminAbuse\Plugin::getHooks();
        foreach ($hooks as $event => $handler) {
            $this->assertIsArray($handler, "Handler for {$event} should be an array");
            $this->assertCount(2, $handler, "Handler for {$event} should have 2 elements");
            $this->assertSame(\Detain\MyAdminAbuse\Plugin::class, $handler[0]);
            $this->assertTrue(
                $this->ref->hasMethod($handler[1]),
                "Method {$handler[1]} should exist on Plugin class"
            );
        }
    }

    /**
     * Tests that getHooks() maps exactly three events.
     */
    public function testGetHooksCountIsThree(): void
    {
        $hooks = \Detain\MyAdminAbuse\Plugin::getHooks();
        $this->assertCount(3, $hooks);
    }

    // ------------------------------------------------------------------
    //  Event handler signatures
    // ------------------------------------------------------------------

    /**
     * Tests that getMenu() accepts exactly one parameter (GenericEvent).
     * The Symfony EventDispatcher passes the event object as the sole argument.
     */
    public function testGetMenuSignature(): void
    {
        $method = $this->ref->getMethod('getMenu');
        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
        $this->assertSame(1, $method->getNumberOfParameters());
        $params = $method->getParameters();
        $this->assertSame('event', $params[0]->getName());
    }

    /**
     * Tests that getRequirements() accepts exactly one parameter (GenericEvent).
     */
    public function testGetRequirementsSignature(): void
    {
        $method = $this->ref->getMethod('getRequirements');
        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
        $this->assertSame(1, $method->getNumberOfParameters());
    }

    /**
     * Tests that getSettings() accepts exactly one parameter (GenericEvent).
     */
    public function testGetSettingsSignature(): void
    {
        $method = $this->ref->getMethod('getSettings');
        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
        $this->assertSame(1, $method->getNumberOfParameters());
    }

    /**
     * Tests that all three event handler methods type-hint GenericEvent.
     * This ensures compatibility with the Symfony EventDispatcher contract.
     */
    public function testEventHandlersTypeHintGenericEvent(): void
    {
        $handlerMethods = ['getMenu', 'getRequirements', 'getSettings'];
        foreach ($handlerMethods as $name) {
            $param = $this->ref->getMethod($name)->getParameters()[0];
            $type = $param->getType();
            $this->assertNotNull($type, "{$name}() first param should be type-hinted");
            $this->assertSame(
                'Symfony\\Component\\EventDispatcher\\GenericEvent',
                $type->getName(),
                "{$name}() first param should type-hint GenericEvent"
            );
        }
    }
}
