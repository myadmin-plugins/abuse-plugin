<?php
/**
 * Abuse IMAP Downloader
 * Checks the various configured IMAP accounts and mailboxes for abuse complaints or spam
 * and registers them with our system and after matching them up to a specific service
 * through its ip, we contact the clients letting them know of the abuse complaint.
 *
 * @author Joe Huss <detain@interserver.net>
 * @copyright 2019
 * @package Abuse
 * @category Crontab
 */

include_once __DIR__.'/../../../../include/functions.inc.php';
include_once __DIR__.'/../src/ImapAbuseCheck.php';
$GLOBALS['tf']->session->create(160307, 'services', false, 0, false, substr(basename($_SERVER['argv'][0], '.php'), 0, 32));
$db = $GLOBALS['tf']->db;
$db->query('select abuse_ip,count(abuse_lid) as count from abuse where Year(abuse_time)=Year(now()) and Month(abuse_time)=Month(now()) and Day(abuse_time)=Day(now()) group by abuse_ip');
$abuse_ips = [];
$total_sent = 0;
while ($db->next_record(MYSQL_ASSOC)) {
	$total_sent += $db->Record['count'];
	$abuse_ips[$db->Record['abuse_ip']] = $db->Record['count'];
}
echo 'Loaded '.$total_sent.' Abuse Records From Today for '.count($abuse_ips).' Unique Email Addresses'.PHP_EOL;
$checks = json_decode(file_get_contents(INCLUDE_ROOT.'/config/abuse.json'), true);
foreach ($checks as $check) {
	$abuse = new ImapAbuseCheck('{'.$check['host'].':'.$check['port'].'/imap/ssl}'.$check['mailbox'], ABUSE_IMAP_USER, ABUSE_IMAP_PASS, $db, $check['delete_attachments'], $check['mail_limit']);
	foreach ($check['patterns'] as $pattern) {
		if ($pattern['type'] == 'match') {
			$abuse->register_preg_match($pattern['pattern'], $pattern['what']);
		} elseif ($pattern['type'] == 'match_all') {
			$abuse->register_preg_match_all($pattern['pattern'], $pattern['what']);
		}
	}
	$abuse->process($check['type'], $check['limit']);
}
