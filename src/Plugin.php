<?php

namespace Detain\MyAdminAbuse;

use Symfony\Component\EventDispatcher\GenericEvent;
use MyAdmin\Settings;

class Plugin {

	public function __construct() {
	}

	public static function Install(GenericEvent $event) {
		$plugin = $event->getSubject();
		$service_category = $plugin->add_service_category('licenses', 'abuse', 'Abuse');
		$plugin->define('SERVICE_TYPES_ABUSE', $service_category);
		$service_type = $plugin->add_service_type($service_category, 'licenses', 'Abuse');
		$plugin->add_service($service_category, $service_type, 'licenses', 'Abuse License', 10.00, 0, 1, 1, '');
		$plugin->add_service($service_category, $service_type, 'licenses', 'Abuse Type2 License', 11.95, 0, 1, 2, '');
		$plugin->add_service($service_category, $service_type, 'licenses', 'KernelCare License', 2.95, 0, 1, 16, '');
	}

	public static function Uninstall(GenericEvent $event) {
	}

	public static function Menu(GenericEvent $event) {
		// will be executed when the licenses.settings event is dispatched
		$menu = $event->getSubject();
		if ($GLOBALS['tf']->ima == 'admin') {
			$menu->add_link($module.'api', 'choice=none.abuse_licenses_list', 'whm/createacct.gif', 'List all Abuse');
		}
	}

	public static function Requirements(GenericEvent $event) {
		// will be executed when the licenses.loader event is dispatched
		$loader = $event->getSubject();
		$loader->add_requirement('class.Abuse', '/../vendor/detain/myadmin-abuse-plugin/src/Abuse.php');
		$loader->add_requirement('deactivate_kcare', '/../vendor/detain/myadmin-abuse-plugin/src/abuse.inc.php');
		$loader->add_requirement('deactivate_abuse', '/../vendor/detain/myadmin-abuse-plugin/src/abuse.inc.php');
		$loader->add_requirement('get_abuse_licenses', '/../vendor/detain/myadmin-abuse-plugin/src/abuse.inc.php');
	}

	public static function Settings(GenericEvent $event) {
		// will be executed when the licenses.settings event is dispatched
		$settings = $event->getSubject();
		$settings->add_text_setting('General', 'Abuse', 'abuse_imap_user', 'Abuse IMAP User:', 'Abuse IMAP Username', ABUSE_IMAP_USER);
		$settings->add_text_setting('General', 'Abuse', 'abuse_imap_pass', 'Abuse IMAP Pass:', 'Abuse IMAP Password', ABUSE_IMAP_PASS);
	}

}
