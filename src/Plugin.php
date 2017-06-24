<?php

namespace Detain\MyAdminAbuse;

use Symfony\Component\EventDispatcher\GenericEvent;

class Plugin {

	public static $name = 'Abuse Plugin';
	public static $description = 'Allows handling of Abuse emails and honeypots';
	public static $help = '';
	public static $type = 'plugin';


	public function __construct() {
	}

	public static function getHooks() {
		return [
			'system.settings' => [__CLASS__, 'getSettings'],
		];
	}

	public static function Menu(GenericEvent $event) {
		$menu = $event->getSubject();
		if ($GLOBALS['tf']->ima == 'admin') {
			//$menu->add_link('admin', 'choice=none.abuse_licenses_list', 'whm/createacct.gif', 'List all Abuse');
		}
	}

	public static function getRequirements(GenericEvent $event) {
		// will be executed when the licenses.loader event is dispatched
		$loader = $event->getSubject();
		$loader->add_requirement('class.Abuse', '/../vendor/detain/myadmin-abuse-plugin/src/Abuse.php');
		$loader->add_requirement('deactivate_kcare', '/../vendor/detain/myadmin-abuse-plugin/src/abuse.inc.php');
		$loader->add_requirement('deactivate_abuse', '/../vendor/detain/myadmin-abuse-plugin/src/abuse.inc.php');
		$loader->add_requirement('get_abuse_licenses', '/../vendor/detain/myadmin-abuse-plugin/src/abuse.inc.php');
	}

	public static function getSettings(GenericEvent $event) {
		// will be executed when the licenses.settings event is dispatched
		$settings = $event->getSubject();
		$settings->add_text_setting('General', 'Abuse', 'abuse_imap_user', 'Abuse IMAP User:', 'Abuse IMAP Username', ABUSE_IMAP_USER);
		$settings->add_text_setting('General', 'Abuse', 'abuse_imap_pass', 'Abuse IMAP Pass:', 'Abuse IMAP Password', ABUSE_IMAP_PASS);
	}

}
