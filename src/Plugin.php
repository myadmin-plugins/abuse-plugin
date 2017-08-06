<?php

namespace Detain\MyAdminAbuse;

use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Class Plugin
 *
 * @package Detain\MyAdminAbuse
 */
class Plugin {

	public static $name = 'Abuse Plugin';
	public static $description = 'Allows handling of Abuse emails and honeypots';
	public static $help = '';
	public static $type = 'plugin';

	/**
	 * Plugin constructor.
	 */
	public function __construct() {
	}

	/**
	 * @return array
	 */
	public static function getHooks() {
		return [
			'system.settings' => [__CLASS__, 'getSettings'],
			'ui.menu' => [__CLASS__, 'getMenu'],
			'function.requirements' => [__CLASS__, 'getRequirements']
		];
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getMenu(GenericEvent $event) {
		$menu = $event->getSubject();
		if ($GLOBALS['tf']->ima == 'admin') {
			function_requirements('has_acl');
					if (has_acl('client_billing'))
							$menu->add_link('admin', 'choice=none.abuse_admin', '//my.interserver.net/bower_components/webhostinghub-glyphs-icons/icons/development-16/Black/icon-spam.png', 'Abuse');
		}
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getRequirements(GenericEvent $event) {
		$loader = $event->getSubject();
		$loader->add_requirement('abuse', __DIR__.'/abuse.php');
		$loader->add_requirement('abuse_admin', __DIR__.'/abuse_admin.php');
		$loader->add_requirement('class.ImapAbuseCheck', __DIR__.'/ImapAbuseCheck.php');
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getSettings(GenericEvent $event) {
		$settings = $event->getSubject();
		$settings->add_text_setting('General', 'Abuse', 'abuse_imap_user', 'Abuse IMAP User:', 'Abuse IMAP Username', ABUSE_IMAP_USER);
		$settings->add_text_setting('General', 'Abuse', 'abuse_imap_pass', 'Abuse IMAP Pass:', 'Abuse IMAP Password', ABUSE_IMAP_PASS);
	}

}
