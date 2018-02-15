<?php
include 'include/functions.inc.php';
$db = get_module_db('default');
$db2 = get_module_db('default');
$limit = 10000;
$offset = 0;
$continue = true;
while ($continue == true) {
	$start = time();
	echo "Grabbing Rows $offset - ".($offset+$limit);
	$db->query("select * from abuse where abuse_id >= 440000 and abuse_headers != '' limit $offset, $limit;");
	echo " got ".$db->num_rows()." rows";
	if ($db->num_rows() < $limit)
		$continue = false;
	$offset += $limit;
	$first = true;
	while ($db->next_record(MYSQL_ASSOC)) {
		if ($first == true)
			echo " got id " . $db->Record['abuse_id'];
		$first = false;
		$out = '';
		$state = 0;
		$headers = false;
		$lines = explode("\n", trim(str_replace("\r",'',$db->Record['abuse_headers'])));
		foreach ($lines as $line) {
			if ($state == 0) {
				$out .= $line.PHP_EOL;
				if (trim($line) == '')
					$state++;
			} elseif ($state == 1) {
				if (preg_match('/^[A-Z][a-zA-Z0-9\-]*: /', trim($line))) {
					$headers = true;
					$out .= $line.PHP_EOL;
					$state--;
				} elseif ($headers == true && trim($line) != '') {
					$state++;
				} elseif ($headers == false) {
					$out .= $line.PHP_EOL;
				}
			}
			
		}
		$out = preg_replace("/\n\s*\n/m", "\n", strip_tags($out));
		//echo "OLD: {$db->Record['abuse_headers']}\n";
		//echo "NEW: {$out}\n";
		if ($out != $db->Record['abuse_headers']) {
			$db2->query("update abuse set abuse_headers='".$db->real_escape($out)."' where abuse_id={$db->Record['abuse_id']}");
			//echo ".";
		}
	}
	$end = time();
	echo " processed in ".($end-$start)." seconds\n";
}
