<?php
/* TODO:
 - service type, category, and services  adding
 - dealing with the SERVICE_TYPES_abuse define
 - add way to call/hook into install/uninstall
*/
return [
	'name' => 'Abuse Plugin',
	'description' => 'Allows handling of Abuse emails and honeypots',
	'help' => '',
	'author' => 'detain@interserver.net',
	'home' => 'https://github.com/detain/myadmin-abuse-plugin',
	'repo' => 'https://github.com/detain/myadmin-abuse-plugin',
	'version' => '1.0.1',
	'type' => 'licenses',
	'hooks' => [
		/*'plugin.install' => ['Detain\MyAdminAbuse\Plugin', 'Install'],
		'plugin.uninstall' => ['Detain\MyAdminAbuse\Plugin', 'Uninstall'],
		'system.settings' => ['Detain\MyAdminAbuse\Plugin', 'Settings'],
		'function.requirements' => ['Detain\MyAdminAbuse\Plugin', 'Requirements'],
		'ui.menu' => ['Detain\MyAdminAbuse\Plugin', 'Menu']*/
	],
];
