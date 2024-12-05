<?php
/**
 * Administrative Functionality
 * @author Joe Huss <detain@interserver.net>
 * @copyright 2025
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
function abuse()
{
    /*
    CREATE TABLE my.abuse (
    abuse_id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    abuse_ip varchar(255) NOT NULL,
    abuse_type varchar(255) NOT NULL,
    abuse_amount int(11) UNSIGNED NOT NULL,
    PRIMARY KEY (abuse_id),
    UNIQUE INDEX abuse_ip (abuse_ip)
    )
    ENGINE = INNODB
    */
    //$customer = $GLOBALS['tf']->variables->request['customer'];
    function_requirements('get_server_from_ip');
    $module = get_module_name('default');
    $db = get_module_db($module);
    $logged_in = false;
    $continue = false;
    if (isset($GLOBALS['tf']->variables->request['key']) && isset($GLOBALS['tf']->variables->request['id'])) {
        $key = $GLOBALS['tf']->variables->request['key'];
        $id = (int)$GLOBALS['tf']->variables->request['id'];
        $db->query("select md5(concat(abuse_id,abuse_ip,abuse_type)) as abuse_key from abuse where abuse_id=$id");
        if ($db->num_rows() == 1) {
            $db->next_record(MYSQL_ASSOC);
            if ($db->Record['abuse_key'] == $key) {
                $continue = true;
            }
        }
    }
    if (!$continue && $GLOBALS['tf']->session->verify()) {
        $logged_in = true;
        $continue = true;
        $GLOBALS['tf']->accounts->data = $GLOBALS['tf']->accounts->read($GLOBALS['tf']->session->account_id);
        $GLOBALS['tf']->ima = $GLOBALS['tf']->accounts->data['ima'];
    }
    if ($continue !== true) {
        add_output('Invalid Authentication, Please Login first or use the URL given in the email.');
        return false;
    }
    unset($continue);
    if ($GLOBALS['tf']->ima == 'admin' && !isset($GLOBALS['tf']->variables->request['id'])) {
        function_requirements('abuse_admin');
        abuse_admin();
        add_output('<script type="text/javascript">
jQuery(document).ready(function() {
	$("html, body").animate({ scrollTop: $("#abusetable").offset().top }, 1000);
});
</script>
');
    } else {
        $smarty = new TFSmarty();
        page_title('Manage Abuse Complaints');
        if (isset($GLOBALS['tf']->variables->request['id'])) {
            $id = (int)$GLOBALS['tf']->variables->request['id'];
            $db->query("select * from abuse left join abuse_data using (abuse_id) where abuse_id={$id}");
            if ($db->num_rows() > 0) {
                $db->next_record(MYSQL_ASSOC);
                $ip = $db->Record['abuse_ip'];
                $server_data = get_server_from_ip($ip);
                if (($logged_in && $GLOBALS['tf']->accounts->data['account_lid'] == $server_data['email']) || ($logged_in && $GLOBALS['tf']->accounts->data['account_lid'] == $db->Record['abuse_lid']) || ($logged_in == false) || ($GLOBALS['tf']->ima == 'admin')) {
                    if (isset($GLOBALS['tf']->variables->request['response'])) {
                        $db->query("update abuse set abuse_status='" . $db->real_escape($GLOBALS['tf']->variables->request['response_status']) . "' where abuse_id={$id}", __LINE__, __FILE__);
                        $db->query("update abuse_data set abuse_response='" . $db->real_escape($GLOBALS['tf']->variables->request['response']) . "' where abuse_id={$id}", __LINE__, __FILE__);
                        $db->query("select * from abuse left join abuse_data using (abuse_id) where abuse_id={$id}");
                        $db->next_record(MYSQL_ASSOC);
                        add_output('Abuse Entry Updated <a href="'.$GLOBALS['tf']->link('index.php', 'choice=none.abuse').'">View Pending Abuse Complaints</a>');
                    }
                    $smarty->assign($db->Record);
                    $smarty->assign('post_location', 'abuse.php?id='.$id . ($logged_in === true || !isset($key) ? '' : '&key='.$key));
                    $smarty->assign('response_status', make_select('response_status', ['resolved','notspam','notabuse','pending'], ['Resolved','Not Spam','Not Abuse','Pending'], $db->Record['abuse_status']));
                    add_output($smarty->fetch('admin/abuse.tpl'));
                } else {
                    $eparts = explode('@', $server_data['email']);
                    $anonemail = mb_substr($eparts[0], 0, 1);
                    for ($x = 0; $x < mb_strlen($server_data['email']) -1; $x++) {
                        $anonemail .= '*';
                    }
                    $anonemail .= $eparts[1];
                    add_output('Your account '.$GLOBALS['tf']->accounts->data['account_lid']. ' does not match the owner of this complaint '.$anonemail);
                }
            } else {
                add_output('Invalid complaint');
            }
        } else {
            function_requirements('crud_abuse');
            crud_abuse();
        }
    }
}
