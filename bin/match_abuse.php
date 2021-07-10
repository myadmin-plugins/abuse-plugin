<?php

use \MyDb\Mysqli\Db;

include __DIR__.'/../../../../include/functions.inc.php';
$mb_db = new Db(ZONEMTA_MYSQL_DB, ZONEMTA_MYSQL_USERNAME, ZONEMTA_MYSQL_PASSWORD, ZONEMTA_MYSQL_HOST);
$mongo_client= new \MongoDB\Client('mongodb://'.ZONEMTA_USERNAME.':'.rawurlencode(ZONEMTA_PASSWORD).'@'.ZONEMTA_HOST.':27017/');
$mongo_users = $mongo_client->selectDatabase('zone-mta')->selectCollection('users');
$mb_users = [];
$result = $mongo_users->find();
foreach ($result as $user)
	$mb_users[] = $user->username;
$ips = explode("\n", trim(`grep address /home/sites/zone-mta/config/pools.js |cut -d\" -f4`));
$db = get_module_db('mail');
$db2 = get_module_db('mail');
$db->query("select abuse.*, abuse_plainmsg from abuse left join abuse_data using (abuse_id) where abuse_ip in ('".implode("','",$ips)."') and (abuse_plainmsg like '%Authenticated sender: %' or abuse_plainmsg like '%smtp.auth=%');");
while ($db->next_record(MYSQL_ASSOC)) {
	$mbUser = null;
	$mbId = null;
	if (preg_match_all('/Authenticated sender: (?P<user>[^\)]*)\)/ms', $db->Record['abuse_plainmsg'], $matches) ||
		preg_match_all('/smtp.auth=(?P<user>\S*)\s/ms', $db->Record['abuse_plainmsg'], $matches)) {
		foreach ($matches['user'] as $user) {
			if (in_array($user, $mb_users)) {
				$mbUser = $user;
				echo 'Abuse ID '.$db->Record['abuse_id'].' found MailBaby user '.$mbUser.PHP_EOL;
			}
		}
	}
	if (preg_match_all('/^ by (\S+|\S+ \(\S+\)) with (LMP|SMTP|ESMTP|ESMTPA|ESMTPS|ESMTPSA|HTTP) id (\S+)\.(\d{3})\s*$/mU', $db->Record['abuse_plainmsg'], $matches)) {
		$ids = $matches[3];
		foreach ($ids as $id) {
			$mb_db->query("select * from mail_messagestore where id='{$id}'");
			if ($mb_db->num_rows() > 0) {
				$mb_db->next_record(MYSQL_ASSOC);
				$mbId = $id;
				$mbUser = $mb_db->Record['user'];
				echo 'Abuse ID '.$db->Record['abuse_id'].' found MailBaby mail id '.$mbId.' user '.$mbUser.PHP_EOL;
			}
		}
	}
	$updates = [];
	if (!is_null($mbUser))
		$updates[] = "abuse_mb_user='{$mbUser}'";
	if (!is_null($mbId))
		$updates[] = "abuse_mb_id='{$mbId}'";
	if (count($updates) > 0) {
		$db2->query("update abuse set ".implode(', ', $updates)." where abuse_id='{$db->Record['abuse_id']}'");
	}
}

