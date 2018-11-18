<?php

namespace Detain\MyAdminAbuse;

use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Class Plugin
 *
 * @package Detain\MyAdminAbuse
 */
class Plugin
{
	public static $name = 'Abuse Plugin';
	public static $description = 'Allows handling of Abuse emails and honeypots';
	public static $help = '';
	public static $type = 'plugin';

	/**
	 * Plugin constructor.
	 */
	public function __construct()
	{
	}

	/**
	 * @return array
	 */
	public static function getHooks()
	{
		return [
			'system.settings' => [__CLASS__, 'getSettings'],
			'ui.menu' => [__CLASS__, 'getMenu'],
			'function.requirements' => [__CLASS__, 'getRequirements']
		];
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getMenu(GenericEvent $event)
	{
		$menu = $event->getSubject();
		if ($GLOBALS['tf']->ima == 'admin') {
			function_requirements('has_acl');
			if (has_acl('client_billing')) {
				$menu->add_link('admin', 'choice=none.abuse_admin', '/images/myadmin/spam-can.png', _('Abuse'));
			}
		}
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getRequirements(GenericEvent $event)
	{
        /**
         * @var \MyAdmin\Plugins\Loader $this->loader
         */
        $loader = $event->getSubject();
		$loader->add_page_requirement('abuse', '/../vendor/detain/myadmin-abuse-plugin/src/abuse.php');
		$loader->add_page_requirement('abuse_admin', '/../vendor/detain/myadmin-abuse-plugin/src/abuse_admin.php');
		$loader->add_requirement('class.ImapAbuseCheck', '/../vendor/detain/myadmin-abuse-plugin/src/ImapAbuseCheck.php');
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
    public static function getSettings(GenericEvent $event)
    {
        /**
         * @var \MyAdmin\Settings $settings
         **/
        $settings = $event->getSubject();
		$settings->add_text_setting(_('General'), _('Abuse'), 'abuse_imap_user', _('Abuse IMAP User'), _('Abuse IMAP Username'), ABUSE_IMAP_USER);
		$settings->add_text_setting(_('General'), _('Abuse'), 'abuse_imap_pass', _('Abuse IMAP Pass'), _('Abuse IMAP Password'), ABUSE_IMAP_PASS);
	}
}
