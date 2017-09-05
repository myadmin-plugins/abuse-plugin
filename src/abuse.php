<?php
/**
 * Administrative Functionality
 * @author Joe Huss <detain@interserver.net>
 * @copyright 2017
 * @package MyAdmin
 * @category Admin
 */
/**
 * abuse()
 *
 * @return bool|void
 * @throws \Exception
 * @throws \SmartyException
 */
function abuse() {
	/*
	CREATE TABLE my.abuse (
	abuse_id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
	abuse_ip varchar(255) NOT NULL,
	abuse_type varchar(255) NOT NULL,
	abuse_amount int(11) UNSIGNED NOT NULL,
	abuse_headers text DEFAULT NULL,
	PRIMARY KEY (abuse_id),
	UNIQUE INDEX abuse_ip (abuse_ip)
	)
	ENGINE = INNODB
	*/
	//$customer = $GLOBALS['tf']->variables->request['customer'];
	function_requirements('get_server_from_ip');
	$module = get_module_name('default');
	$db = get_module_db($module);
	$GLOBALS['tf']->accounts->set_db_module($module);
	$GLOBALS['tf']->history->set_db_module($module);
	$logged_in = FALSE;
	$continue = FALSE;
	if (isset($GLOBALS['tf']->variables->request['key']) && isset($GLOBALS['tf']->variables->request['id'])) {
		$key = $GLOBALS['tf']->variables->request['key'];
		$id = (int)$GLOBALS['tf']->variables->request['id'];
		$db->query("select md5(concat(abuse_id,abuse_ip,abuse_type)) as abuse_key from abuse where abuse_id=$id");
		if ($db->num_rows() == 1) {
			$db->next_record(MYSQL_ASSOC);
			if ($db->Record['abuse_key'] == $key)
				$continue = TRUE;
		}
	}
	if (!$continue && $GLOBALS['tf']->session->verify()) {
		$logged_in = TRUE;
		$continue = TRUE;
		$GLOBALS['tf']->accounts->data = $GLOBALS['tf']->accounts->read($GLOBALS['tf']->session->account_id);
	}
	if ($continue !== TRUE) {
		add_output('Invalid Authentication, Please Login first or use the URL given in the email.');
		return FALSE;
	}
	unset($continue);
	if ($GLOBALS['tf']->ima == 'admin' && !isset($GLOBALS['tf']->variables->request['id'])) {
		function_requirements('abuse_admin');
		abuse_admin();
	} else {
		add_output('Login Not Required<br>');
		add_output('<script type="text/javascript">
jQuery(document).ready(function() {
	$("html, body").animate({ scrollTop: $("#abusetable").offset().top }, 1000);
});
</script>
');
		page_title('Manage Abuse Complaints');
		if (isset($GLOBALS['tf']->variables->request['id'])) {
			$id = (int)$GLOBALS['tf']->variables->request['id'];
			$db->query("select * from abuse where abuse_id=$id");
			if ($db->num_rows() > 0) {
				$db->next_record(MYSQL_ASSOC);
				$ip = $db->Record['abuse_ip'];
				$server_data = get_server_from_ip($ip);
				if (($logged_in && $GLOBALS['tf']->accounts->data['account_lid'] == $server_data['email']) || ($logged_in && $GLOBALS['tf']->accounts->data['account_lid'] == $db->Record['abuse_lid']) || ($logged_in == FALSE) || ($GLOBALS['tf']->ima == 'admin')) {
					if (isset($GLOBALS['tf']->variables->request['response'])) {
						$db->query("update abuse set abuse_status='" . $db->real_escape($GLOBALS['tf']->variables->request['response_status']) . "', abuse_response='" . $db->real_escape($GLOBALS['tf']->variables->request['response']) .	"' where abuse_id=$id", __LINE__, __FILE__);
						$db->query("select * from abuse where abuse_id=$id");
						$db->next_record(MYSQL_ASSOC);
						add_output('Abuse Entry Updated <a href="'.$GLOBALS['tf']->link('index.php', 'choice=none.abuse').'">View Pending Abuse Complaints</a>');
					}
					$table = new \TFTable;
					//$table->add_hidden('id', $id);
					$table->set_post_location('abuse.php?id='.$id . ($logged_in === TRUE || !isset($key) ? '' : '&key='.$key));
					$table->set_options('cellpadding=3 id="abusetable"');
					$table->set_title('Manage Abuse Complaint');
					$table->set_row_options('style="vertical-align: top;"');
					$table->add_field('IP', 'l');
					$table->add_field($ip, 'l');
					$table->add_row();
					$table->set_row_options('style="vertical-align: top;"');
					$table->add_field('Date', 'l');
					$table->add_field($db->Record['abuse_time'], 'l');
					$table->add_row();
					$table->set_row_options('style="vertical-align: top;"');
					$table->add_field('Amount', 'l');
					$table->add_field($db->Record['abuse_amount'], 'l');
					$table->add_row();
					$table->set_row_options('style="vertical-align: top;"');
					$table->add_field('Type', 'l');
					$table->add_field($db->Record['abuse_type'], 'l');
					$table->add_row();
					$table->set_row_options('style="vertical-align: top;"');
					if ($db->Record['abuse_type'] == 'uceprotect') {
						$table->add_field('uceprotect', 'l');
						$table->add_field('<a href="http://www.uceprotect.net/en/rblcheck.php?ipr='.$ip.'" target="_blank">http://www.uceprotect.net/en/rblcheck.php?ipr='.$ip.'</a>', 'l');
						$table->add_row();
					} else {
						$table->set_col_options('style="vertical-align: top;"');
						$table->add_field('Headers', 'l');
						$table->add_field('<div style="max-width: 1000px; min-width: 500px; font-size: 0.9em; white-space: pre; font-family: monospace; display: block; overflow: scroll;">'.htmlspecial($db->Record['abuse_headers']).'</div>', 'l');
						$table->add_row();
					}
					$table->set_row_options('style="vertical-align: top;"');
					$table->add_field('Status', 'l');
					$table->add_field(make_select('response_status', [
						'resolved',
						'notspam',
						'notabuse',
						'pending'
					], [
						'Resolved',
						'Not Spam',
						'Not Abuse',
						'Pending'
												  ], $db->Record['abuse_status']), 'l');
					$table->add_row();
					$table->set_row_options('style="vertical-align: top;"');
					$table->add_field('Response', 'l');
					$table->add_field('<textarea name="response" rows=10 cols=50>'.$db->Record['abuse_response'].'</textarea>', 'l');
					$table->add_row();
					$table->add_field('', 'l');
					$table->add_field($table->make_submit('Submit Response'), 'l');
					$table->add_row();
					add_output($table->get_table());
				} else {
					$eparts = explode('@', $server_data['email']);
					$anonemail = mb_substr($eparts[0], 0, 1);
					for ($x = 0; $x < mb_strlen($server_data['email']) -1; $x++)
						$anonemail .= '*';
					$anonemail .= $eparts[1];
					add_output('Your account '.$GLOBALS['tf']->accounts->data['account_lid']. ' does not match the owner of this complaint '.$anonemail);
				}
			} else {
				add_output('Invalid complaint');
			}
		} else {
			$db->query("select * from abuse where abuse_status='pending' and abuse_lid='" . $db->real_escape($GLOBALS['tf']->accounts->data['account_lid']) . "'");
			if ($db->num_rows() > 0) {
				$table = new \TFTable;
				$table->set_title('Abuse Complaints');
				$table->add_field('IP');
				$table->add_field('Time');
				$table->add_field('Type');
				$table->add_field('');
				$table->add_row();
				while ($db->next_record(MYSQL_ASSOC)) {
					$table->add_field($db->Record['abuse_ip']);
					$table->add_field($db->Record['abuse_time']);
					$table->add_field($db->Record['abuse_type']);
					$table->add_field('<a href="'.$GLOBALS['tf']->link('abuse.php', 'id='.$db->Record['abuse_id'] . ($logged_in === TRUE ? '' : '&key='.$key)).'">Update</a>');
					$table->add_row();
				}
				add_output($table->get_table());
			} else {
				add_output('No Abuse complaints');
			}
		}
	}
}
