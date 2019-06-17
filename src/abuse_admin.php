<?php
/**
 * Administrative Functionality
 * @author Joe Huss <detain@interserver.net>
 * @copyright 2019
 * @package MyAdmin
 * @category Admin
 */

/**
 * @return bool
 * @throws \Exception
 * @throws \SmartyException
 */
function abuse_admin()
{
	function_requirements('get_server_from_ip');
	function_requirements('class.ImapAbuseCheck');
	add_js('bootstrap');
	$module = get_module_name('default');
	$db = get_module_db($module);
	$mailed = 0;
	$maxmailed = 5;
	$limit = 'limit 20';
	function_requirements('has_acl');
	if ($GLOBALS['tf']->ima != 'admin' || !has_acl('client_billing')) {
		dialog('Not admin', 'Not Admin or you lack the permissions to view this page.');
		return false;
	}
	page_title('Report Abuse');
	$headers = get_default_mail_headers(['TITLE' => 'Abuse', 'EMAIL_FROM' => 'abuse@interserver.net']);
	$email_template = file_get_contents(__DIR__.'/templates/admin/abuse.tpl');
	/* stats to get
	biggest abusers (today, 3 day, 7 day, etc..)
	Optionally limit abuse results to a single username
	% responded / unresponded
	*/
	$times = ['24 HOUR', '3 DAY', '7 DAY'];
	$table2 = new \TFTable;
	$table2->hide_title();
	$table2->hide_table();
	$table2->hide_form();
	if (isset($GLOBALS['tf']->variables->request['lid'])) {
		$lid = $db->real_escape($GLOBALS['tf']->variables->request['lid']);
		$lid_data = $GLOBALS['tf']->accounts->read($GLOBALS['tf']->accounts->cross_reference($lid));
		$table = new \TFTable;
		$table->set_col_options('style="vertical-align: middle; padding-top: 1px; padding-right: 3px;"');
		if (isset($lid_data['picture']) && null !== $lid_data['picture'] && $lid_data['picture'] != '') {
			$table->set_title('<span style="float: left;"><img src="'.htmlentities($lid_data['picture'], ENT_QUOTES, 'UTF-8').'" width="20" height="20" style="padding-left: 5px;"> Customer:</span>');
		} else {
			$table->set_title('<span style="float: left;"><span class="glyphicon glyphicon-user " style="padding-left: 5px;"></span> Customer:</span>');
		}
		$table->set_col_options('style="vertical-align: middle; padding-top: 1px; padding-right: 3px;"');
		$table->add_field($table->make_link('choice=none.edit_customer&custid='.$lid_data['account_id'], $lid), 'l');
		$table->add_row();
		$table2->set_col_options('style="vertical-align: top; padding-left: 5px; padding-right: 5px;"');
		$table2->add_field($table->get_table());
	}
	$table_orig = new \TFTable;
	foreach ($times as $time) {
		$min_date = mysql_date_sub(null, 'INTERVAL 24 HOUR');
		if (isset($lid)) {
			$query = "select accounts.account_id, abuse_lid, account_value, abuse_ip, sum(abuse_amount) as total_amount from abuse left join accounts on account_lid=abuse_lid left join accounts_ext on accounts_ext.account_id=accounts.account_id and account_key='picture' where abuse_lid='{$lid}' and abuse_time between date_sub(now(), INTERVAL 24 HOUR) and now() group by abuse_lid, abuse_ip order by sum(abuse_amount) desc {$limit};";
		} else {
			$query = "select accounts.account_id, abuse_lid, account_value, abuse_ip, sum(abuse_amount) as total_amount from abuse left join accounts on account_lid=abuse_lid left join accounts_ext on accounts_ext.account_id=accounts.account_id and account_key='picture' where abuse_time between date_sub(now(), INTERVAL 24 HOUR) and now() group by abuse_lid, abuse_ip order by sum(abuse_amount) desc {$limit};";
		}
		$db->query($query, __LINE__, __FILE__);
		$table = clone $table_orig;
		$table->set_title($time.' Spam Stats');
		while ($db->next_record(MYSQL_ASSOC)) {
			$table->set_col_options('style="vertical-align: middle; padding-top: 1px; padding-left: 3px; padding-right: 10px;"');
			$table->add_field('<a href="https://my.interserver.net/misha/search.php?comments=no&expand=1&search='.$db->Record['abuse_ip'].'">'.$db->Record['abuse_ip'].'</a>', 'l');
			$table->set_col_options('style="vertical-align: middle; padding-top: 1px; padding-right: 10px;"');
			$table->add_field($db->Record['total_amount'], 'r');
			if (!isset($lid)) {
				$table->set_col_options('style="vertical-align: middle; padding-top: 1px; padding-right: 3px;"');
				$table->add_field($table->make_link('choice=none.abuse&lid='.$db->Record['abuse_lid'], $db->Record['abuse_lid']), 'r');
				$table->set_col_options('style="vertical-align: middle; padding-top: 1px; padding-right: 3px;"');
				if (isset($db->Record['account_value']) && null !== $db->Record['account_value'] && $db->Record['account_value'] != '') {
					$table->add_field($table->make_link('choice=none.edit_customer&custid='.$db->Record['account_id'], '<img src="'.htmlentities($db->Record['account_value'], ENT_QUOTES, 'UTF-8').'" width="20" height="20">'), 'l');
				} else {
					$table->add_field($table->make_link('choice=none.edit_customer&custid='.$db->Record['account_id'], '<img src="'.get_gravatar($db->Record['abuse_lid'], 20, 'retro', 'x').'" width="20" height="20">'), 'l');
				}
			}
			$table->add_row();
		}
		$table2->set_col_options('style="vertical-align: top; padding-left: 5px; padding-right: 5px;"');
		$table2->add_field($table->get_table());
	}
	$table2->add_row();
	add_output($table2->get_table());

	if (isset($lid)) {
		$db->query("select * from abuse left join abuse_data using (abuse_id) where abuse_lid='{$lid}'");
		$rows = [];
		while ($db->next_record(MYSQL_ASSOC)) {
			unset($db->Record['abuse_lid']);
			if (!isset($header)) {
				$header = array_keys($db->Record);
				foreach ($header as $idx => $field) {
					$header[$idx] = ucwords(str_replace('abuse_', '', $field));
				}
			}
			$headerlimit = 50;
			$db->Record['abuse_headers'] = (mb_strlen($db->Record['abuse_headers']) <= $headerlimit ? $db->Record['abuse_headers'] : '<a href="'.$GLOBALS['tf']->link('index.php', 'choice=none.abuse&lid='.$lid.'&id='.$db->Record['abuse_id']).'" class="btn" data-toggle="popover" data-trigger="hover" data-placement="bottom" '.(stripos($db->Record['abuse_headers'], '<html>') === false ? 'data-html="true" data-content="'.htmlentities(nl2br($db->Record['abuse_headers']), ENT_QUOTES, 'UTF-8').'"' : 'data-html="true" data-content="'.htmlentities($db->Record['abuse_headers'], ENT_QUOTES, 'UTF-8').'"').'>'.mb_substr($db->Record['abuse_headers'], 0, $headerlimit).'...</a>');
			$rows[] = $db->Record;
			if (isset($GLOBALS['tf']->variables->request['id']) && $GLOBALS['tf']->variables->request['id'] == $db->Record['abuse_id']) {
				$table = new \TFTable;
				$table->set_title('Abuse '.$db->Record['abuse_id'].' Entry');
				foreach ($db->Record as $key => $value) {
					$table->add_field($key);
					$table->add_field(htmlspecial($value));
					$table->add_row();
				}
				add_output($table->get_table());
			}
		}
		$GLOBALS['tf']->add_html_head_js_string('
jQuery(document).ready(function () {
	jQuery("[data-toggle=popover]").popover();
	//jQuery("[data-toggle=tooltip]").tooltip();
});
');
		$GLOBALS['tf']->add_html_head_css_file('
.tablesorter>body>tr>td {
	opacity: 1;
}
.tablesorter-jui tbody>tr.hover>td,
.tablesorter-jui tbody>tr:hover>td {
	opacity: 1;
	filter: alpha(opacity=100);
}
div.popover {
	max-width: 850px;
	overflow: scroll;
}
div.tooltip {
	width: 500px;
	height: 300px;
}
');
		add_js('tablesorter');
		$smarty = new \TFSmarty;
		$smarty->debugging = true;
		$smarty->assign('sortcol', 0);
		$smarty->assign('sortdir', 1);
		$smarty->assign('size', 10);
		$smarty->assign('textextraction', "'complex'");
		$smarty->assign('table_header', $header);
		$smarty->assign('table_rows', $rows);
		add_output(str_replace(['mainelement', 'itemtable', 'itempager'], [$module.'abusemainelement', $module.'abusetable', $module.'abusepager'], $smarty->fetch('tablesorter/tablesorter.tpl')));
	}
	if (isset($GLOBALS['tf']->variables->request['headers']) && verify_csrf('abuse_admin')) {
		$ip = $GLOBALS['tf']->variables->request['ip'];
		if (validIp($ip, false)) {
			$server_data = get_server_from_ip($ip);
			if (isset($server_data['email']) && $server_data['email'] != '') {
				$email = $server_data['email'];
				$db->query(make_insert_query('abuse', [
					'abuse_id' => null,
					'abuse_time' => mysql_now(),
					'abuse_ip' => $ip,
					'abuse_type' => $GLOBALS['tf']->variables->request['type'],
					'abuse_amount' => $GLOBALS['tf']->variables->request['amount'],
					'abuse_lid' => $email,
					'abuse_status' => 'pending'
				]), __LINE__, __FILE__);
				$id = $db->getLastInsertId('abuse', 'abuse_id');
				$db->query(make_insert_query('abuse_data', [
					'abuse_id' => $id,
					'abuse_headers' => ImapAbuseCheck::fix_headers($GLOBALS['tf']->variables->request['headers']),
				]), __LINE__, __FILE__);
				$subject = 'InterServer Abuse Report for '.$ip;
				$message = str_replace(
					['{$email}', '{$ip}', '{$type}', '{$count}', '{$id}', '{$key}'],
					[$server_data['email_abuse'], $ip, $GLOBALS['tf']->variables->request['type'], $GLOBALS['tf']->variables->request['amount'], $id, md5($id . $ip . $GLOBALS['tf']->variables->request['type'])],
					$email_template
				);
				mail($server_data['email_abuse'], $subject, $message, $headers);
				//mail('john@interserver.net', $subject, $message, $headers);
				//$mailed++;
				//if ($mailed > $maxmailed)
				//{
				//	add_output($maxmailed.' Reached, Bailing');
				//	return false;
				//}
				add_output('Abuse Entry for '.$ip.' Added - Emailing '.($server_data['email_abuse'] != $email ? $server_data['email_abuse'].' (for client '.$email.')' : $server_data['email_abuse']).'<br>');
			} else {
				add_output('Error Finding Owner For '.$ip.'<br>');
			}
		}
	}
	if (isset($GLOBALS['tf']->variables->request['evidence']) && verify_csrf('abuse_admin_multiple')) {
		$ips = explode("\n", trim($GLOBALS['tf']->variables->request['ips']));
		foreach ($ips as $ip) {
			$ip = trim($ip);
			if (validIp($ip, false)) {
				$server_data = get_server_from_ip($ip);
				if (isset($server_data['email']) && $server_data['email'] != '') {
					$email = $server_data['email'];
					$db->query(make_insert_query('abuse', [
						'abuse_id' => null,
						'abuse_time' => mysql_now(),
						'abuse_ip' => $ip,
						'abuse_type' => $GLOBALS['tf']->variables->request['type'],
						'abuse_amount' => 1,
						'abuse_lid' => $email,
						'abuse_status' => 'pending'
					]), __LINE__, __FILE__);
					$id = $db->getLastInsertId('abuse', 'abuse_id');
					$db->query(make_insert_query('abuse_data', [
						'abuse_id' => $id,
						'abuse_headers' => ImapAbuseCheck::fix_headers($GLOBALS['tf']->variables->request['headers']),
					]), __LINE__, __FILE__);
					$subject = 'InterServer Abuse Report for '.$ip;
					$message = str_replace(
						['{$email}', '{$ip}', '{$type}', '{$count}', '{$id}', '{$key}'],
						[$server_data['email_abuse'], $ip, $GLOBALS['tf']->variables->request['type'], 1, $id, md5($id . $ip . $GLOBALS['tf']->variables->request['type'])],
						$email_template
					);
					mail($server_data['email_abuse'], $subject, $message, $headers);
					//mail('john@interserver.net', $subject, $message, $headers);
					//$mailed++;
					//if ($mailed > $maxmailed)
					//{
					//	add_output($maxmailed.' Reached, Bailing');
					//	return false;
					//}
					add_output('Abuse Entry for '.$ip.' Added - Emailing '.($server_data['email_abuse'] != $email ? $server_data['email_abuse'].' (for client '.$email.')' : $server_data['email_abuse']).'<br>');
				} else {
					add_output('Error Finding Owner For '.$ip.'<br>');
				}
			}
		}
	}
	//add_output('Files:<br>');
	//add_output(print_r($_FILES, TRUE));
	//Array ( [import] => Array ( [name] => monitoring-19318.txt [type] => text/plain [tmp_name] => /tmp/phptWYULC [error] => 0 [size] => 3622 ) )
	if (isset($_FILES['import']) && isset($_FILES['import']['tmp_name']) && verify_csrf('abuse_admin_uce')) {
		if (strlen($_FILES['import']['tmp_name']) > 1 && file_exists($_FILES['import']['tmp_name'])) {
			add_output('Importing File<br>');
			$lines = explode("\n", file_get_contents($_FILES['import']['tmp_name']));
			for ($x = 0, $x_max = count($lines); $x < $x_max; $x++) {
				if (mb_strpos($lines[$x], ',') !== false && is_numeric(mb_substr($lines[$x], 0, 1))) {
					$parts = explode(',', $lines[$x]);
					$ip = $parts[0];
					$date = new \DateTime(is_numeric($parts[1]) && mb_strlen($parts[1]) == 10 ? date(MYSQL_DATE_FORMAT, $parts[1]) : $parts[1]);
					if (isset($GLOBALS['tf']->variables->request['dates']) && $GLOBALS['tf']->variables->request['dates'] != 'all' && is_numeric($GLOBALS['tf']->variables->request['dates'])) {
						$limit_date = new \DateTime(date(MYSQL_DATE_FORMAT, time() - $GLOBALS['tf']->variables->request['dates']));
						if ($date < $limit_date) {
							continue;
						}
					}
					$date = $date->format(MYSQL_DATE_FORMAT);
					$server_data = get_server_from_ip($ip);
					if (isset($server_data['email']) && $server_data['email'] != '') {
						$type = 'uceprotect';
						$email = $server_data['email'];
						$db->query(make_insert_query('abuse', [
							'abuse_id' => null,
							'abuse_ip' => $ip,
							'abuse_type' => $type,
							'abuse_time' => $date,
							'abuse_amount' => 1,
							'abuse_lid' => $email,
							'abuse_status' => 'pending'
						]), __LINE__, __FILE__);
						$id = $db->getLastInsertId('abuse', 'abuse_id');
						$subject = 'InterServer Abuse Report for '.$ip;
						$message = str_replace(
							['{$email}', '{$ip}', '{$type}', '{$count}', '{$id}', '{$key}'],
							[$server_data['email_abuse'], $ip, $type, 1, $id, md5($id . $ip . $type)],
							$email_template
						);
						mail($server_data['email_abuse'], $subject, $message, $headers);
						//mail('john@interserver.net', $subject, $message, $headers);
						//$mailed++;
						//if ($mailed > $maxmailed)
						//{
						//  add_output($maxmailed.' Reached, Bailing');
						//  return false;
						//}
						add_output('Abuse Entry for '.$ip.' Added - Emailing '.($server_data['email_abuse'] != $email ? $server_data['email_abuse'].' (for client '.$email.')' : $server_data['email_abuse']).'<br>');
					} else {
						add_output('Error Finding Owner For '.$ip.'<br>');
					}
				}
			}
		} else {
			add_output('There was an error with the uploaded file, specificly the tmp filename was empty or non existant.<br>');
			add_output('<pre style="text-align: left;">$_FILES = '.var_export($_FILES, true).';</pre>');
		}
	}
	if (isset($GLOBALS['tf']->variables->request['csvtext']) && $GLOBALS['tf']->variables->request['csvtext'] != '' && verify_csrf('abuse_admin_uce')) {
		add_output('Importing CSV Text<br>');
		$lines = explode("\n", $GLOBALS['tf']->variables->request['csvtext']);
		for ($x = 0, $x_max = count($lines); $x < $x_max; $x++) {
			if (mb_strpos($lines[$x], ',') !== false && is_numeric(mb_substr($lines[$x], 0, 1))) {
				$parts = explode(',', $lines[$x]);
				$ip = $parts[0];
				$date = new \DateTime((is_numeric($parts[1]) && mb_strlen($parts[1]) == 10) ? date(MYSQL_DATE_FORMAT, $parts[1]) : $parts[1]);
				$date = $date->format(MYSQL_DATE_FORMAT);
				$server_data = get_server_from_ip($ip);
				if (isset($server_data['email']) && $server_data['email'] != '') {
					$email = $server_data['email'];
					$type = 'uceprotect';
					$db->query(make_insert_query('abuse', [
						'abuse_id' => null,
						'abuse_ip' => $ip,
						'abuse_type' => 'uceprotect',
						'abuse_time' => $date,
						'abuse_amount' => 1,
						'abuse_lid' => $email,
						'abuse_status' => 'pending'
					]), __LINE__, __FILE__);
					$id = $db->getLastInsertId('abuse', 'abuse_id');
					$subject = 'InterServer Abuse Report for '.$ip;
					$message = str_replace(
						['{$email}', '{$ip}', '{$type}', '{$count}', '{$id}', '{$key}'],
						[$server_data['email_abuse'], $ip, $type, 1, $id, md5($id . $ip . $type)],
						$email_template
					);
					mail($server_data['email_abuse'], $subject, $message, $headers);
					//mail('john@interserver.net', $subject, $message, $headers);
					//$mailed++;
					//if ($mailed > $maxmailed)
					//{
					//  add_output($maxmailed.' Reached, Bailing');
					//  return false;
					//}
					add_output('Abuse Entry for '.$ip.' Added - Emailing '.($server_data['email_abuse'] != $email ? $server_data['email_abuse'].' (for client '.$email.')' : $server_data['email_abuse']).'<br>');
				} else {
					add_output('Error Finding Owner For '.$ip.'<br>');
				}
			}
		}
	}
	// display the current abuse entries
	//uceprotect links to - http://www.uceprotect.net/en/rblcheck.php?ipr=$ip
	if (isset($GLOBALS['tf']->variables->request['tmcsvtext']) && $GLOBALS['tf']->variables->request['tmcsvtext'] != '' && verify_csrf('abuse_admin_trend')) {
		add_output('Importing TrendMicro CSV Text<br>');
		$lines = explode("\n", $GLOBALS['tf']->variables->request['tmcsvtext']);
		for ($x = 0, $x_max = count($lines); $x < $x_max; $x++) {
			if (is_numeric(mb_substr($lines[$x], 0, 1))) {
				$ip = $lines[$x];
				$server_data = get_server_from_ip($ip);
				if (isset($server_data['email']) && $server_data['email'] != '') {
					$email = $server_data['email'];
					$type = 'trendmicro';
					$db->query(make_insert_query('abuse', [
						'abuse_id' => null,
						'abuse_ip' => $ip,
						'abuse_type' => $type,
						'abuse_time' => ['now()'],
						'abuse_amount' => 1,
						'abuse_lid' => $email,
						'abuse_status' => 'pending'
					]), __LINE__, __FILE__);
					$id = $db->getLastInsertId('abuse', 'abuse_id');
					$db->query(make_insert_query('abuse_data', [
						'abuse_id' => $id,
						'abuse_headers' => ImapAbuseCheck::fix_headers($GLOBALS['tf']->variables->request['headers']),
					]), __LINE__, __FILE__);
					$subject = 'InterServer Abuse Report for '.$ip;
					$message = str_replace(
						['{$email}', '{$ip}', '{$type}', '{$count}', '{$id}', '{$key}'],
						[$server_data['email_abuse'], $ip, $type, 1, $id, md5($id . $ip . $type)],
						$email_template
					);
					mail($server_data['email_abuse'], $subject, $message, $headers);
					//mail('john@interserver.net', $subject, $message, $headers);
					//$mailed++;
					//if ($mailed > $maxmailed)
					//{
					//  add_output($maxmailed.' Reached, Bailing');
					//  return false;
					//}
					add_output('Abuse Entry for '.$ip.' Added - Emailing '.($server_data['email_abuse'] != $email ? $server_data['email_abuse'].' (for client '.$email.')' : $server_data['email_abuse']).'<br>');
				} else {
					add_output('Error Finding Owner For '.$ip.'<br>');
				}
			}
		}
	}

	$table = new \TFTable;
	$table->csrf('abuse_admin');
	$table->set_title('Report Abuse');
	$table->add_field('Headers');
	$table->add_field('<textarea rows=8 cols=50 name="headers"></textarea>');
	$table->add_row();
	$table->add_field('IP of the complaint');
	$table->add_field($table->make_input('ip', '', 30));
	$table->add_row();
	$table->add_field('Type of Abuse');
	$table->add_field(make_select(
		'type',
		[
		'scanning',
		'hacking',
		'spam',
		'child porn',
		'phishing site',
		'other'
	],
		[
		'scanning',
		'hacking',
		'spam',
		'child porn',
		'phishing site',
		'other'
								  ]
					  ));
	$table->add_row();
	$table->add_field('Amount');
	$table->add_field($table->make_input('amount', 1, 15));
	$table->add_row();
	$table->set_colspan(2);
	$table->add_field($table->make_submit('Submit'));
	$table->add_row();
	add_output($table->get_table());
	$table = new \TFTable;
	$table->csrf('abuse_admin_multiple');
	$table->set_title('Multiple IP Abuse Reporting');
	$table->add_field('Evidence');
	$table->add_field('<textarea rows=8 cols=50 name="evidence"></textarea>');
	$table->add_row();
	$table->add_field('IPs of the complaint<br>One per line');
	$table->add_field('<textarea rows=8 cols=20 name="ips"></textarea>');
	$table->add_row();
	$table->add_field('Type of Abuse');
	$table->add_field(make_select(
		'type',
		[
		'scanning',
		'hacking',
		'spam',
		'child porn',
		'phishing site',
		'other'
	],
		[
		'scanning',
		'hacking',
		'spam',
		'child porn',
		'phishing site',
		'other'
								  ]
					  ));
	$table->add_row();
	$table->set_colspan(2);
	$table->add_field($table->make_submit('Submit'));
	$table->add_row();
	add_output($table->get_table());
	$table = new \TFTable;
	$table->csrf('abuse_admin_uce');
	$table->set_form_options('enctype="multipart/form-data"');
	$table->set_title('Import UCEProtect Abuse CSV');
	$table->add_field('File');
	$table->add_field('<input type="file" name="import">');
	$table->add_row();
	$table->add_field('Dates');
	$table->add_field($table->make_radio('dates', 'all', true).'All  '.$table->make_radio('dates', 60 * 60 * 24 * 2, false).'Last 2 Days  ');
	$table->add_row();
	$table->set_colspan(2);
	$table->add_field('or');
	$table->add_row();
	$table->add_field('CSV');
	$table->add_field('<textarea rows=15 cols=100 name="csvtext"></textarea>');
	$table->add_row();
	$table->set_colspan(2);
	$table->add_field($table->make_submit('Submit'));
	$table->add_row();
	add_output($table->get_table());
	$table = new \TFTable;
	$table->csrf('abuse_admin_trend');
	$table->set_form_options('enctype="multipart/form-data"');
	$table->set_title('Import Trend Micro Abuse');
	$table->add_field('CSV');
	$table->add_field('<textarea rows=15 cols=100 name="tmcsvtext"></textarea>');
	$table->add_row();
	$table->set_colspan(2);
	$table->add_field($table->make_submit('Submit'));
	$table->add_row();
	add_output($table->get_table());
	return true;
}
