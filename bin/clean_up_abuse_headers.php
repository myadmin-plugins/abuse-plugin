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
	$db->query("select * from abuse left join abuse_data using (abuse_id) where abuse_id >= 440000 and abuse_headers != '' limit $offset, $limit;");
	echo " got ".$db->num_rows()." rows";
	if ($db->num_rows() < $limit)
		$continue = false;
	$offset += $limit;
	while ($db->next_record(MYSQL_ASSOC)) {
		$out = fix_headers($db->Record['abuse_headers']);
		if ($out != $db->Record['abuse_headers']) {
			$db2->query("update abuse_data set abuse_headers='".$db->real_escape($out)."' where abuse_id={$db->Record['abuse_id']}");
			//echo ".";
		}
	}
	$end = time();
	echo " processed in ".($end-$start)." seconds\n";
}
