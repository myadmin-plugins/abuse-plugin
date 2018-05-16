<?php
/**
 * TF Related Functionality
 * @author Joe Huss <detain@interserver.net>
 * @copyright 2018
 * @package MyAdmin
 * @category Abuse
 */

use MyAdmin\Orm\Abuse;
use MyAdmin\Orm\Abuse_Data;

/**
 * Handles checking IMAP email addresses looking through the emails for specific patterns indicating
 * that an IP was blacklisted or being reported for abuse.   it checks to see if the IP is one of ours,
 * and finds the matching client and notifies them where appropriate
 */
class ImapAbuseCheck
{
	public $imap_server;
	public $imap_username;
	public $imap_password;
	public $imap_folder;
	public $ip_regex = '(?P<ip>(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?))';
	public $delete_attachments;
	public $mbox;
	public $MC;
	public $limit_ips = false;
	public $ips = [];
	public $emails = [];
	public $abused = 0;
	public $db;
	public $all_ips;
	public $client_ips;
	public $preg_match = [];
	public $preg_match_all = [];
	public $email_headers;
	// per message variables
	public $charset;
	public $htmlmsg;
	public $plainmsg;
	public $attachments;

	/**
	 * @param string $imap_server the imap server {address:port/type}INBOX.mailbox , ie: {mx.interserver.net:143/imap}INBOX.Hotmail
	 * @param string $username the username for connecting to the imap server
	 * @param string $password the password for connecting to the imap server
	 * @param \Db $db the database handler
	 * @param int $delete_attachments whether or not to delete the attachments and emails
	 * @param bool|false|int $limit_ips how many types of spam should a person receive a day for this type before hitting a limit
	 */
	public function __construct($imap_server, $username, $password, $db, $delete_attachments = 1, $limit_ips = false) {
		$this->imap_server = $imap_server;
		$this->imap_folder = preg_replace('/^{.*}/m', '', $this->imap_server);
		$this->imap_username = $username;
		$this->imap_password = $password;
		$this->db = $db;
		$this->set_default_email_headers();
		$this->delete_attachments = $delete_attachments;
		$this->limit_ips = $limit_ips;
		if (isset($GLOBALS['abuse_ips']))
			$this->ips = $GLOBALS['abuse_ips'];
		//echo sizeof($this->ips) . " ips loaded, limiting to " . $this->limit_ips . " ips/address\n";
		//print_r($this->ips);
		if (isset($GLOBALS['all_ips']))
			$this->all_ips = $GLOBALS['all_ips'];
		else
			$this->load_all_ips();
		if (isset($GLOBALS['all_client_ips']))
			$this->client_ips = $GLOBALS['all_client_ips'];
		else
			$this->load_client_ips();
		$this->connect();
		function_requirements('get_server_from_ip');
	}

	/**
	 * returns the ip regex string
	 *
	 * @return string the ip regex string
	 */
	public function get_ip_regex() {
		return $this->ip_regex;
	}

	/**
	 * sets the email headers to the default
	 *
	 * @return void
	 */
	public function set_default_email_headers() {
		$this->email_headers = "MIME-Version: 1.0\nContent-type: text/html; charset=UTF-8\nFrom: Abuse <abuse@interserver.net>\n";
	}

	/**
	 * @param $all_ips
	 */
	public function set_all_ips($all_ips) {
		$this->all_ips = $all_ips;
	}

	/**
	 * loads all the IP blocks into the class and global all_ips
	 */
	public function load_all_ips() {
		//echo "Loading IP Blocks\n";
		function_requirements('get_all_ips_from_ipblocks');
		$this->all_ips = get_all_ips_from_ipblocks(true);
		$GLOBALS['all_ips'] = $this->all_ips;
	}

	/**
	 * loads client ips into class and global all_client_ips
	 */
	public function load_client_ips() {
		//echo "Loading IP Blocks\n";
		function_requirements('get_client_ips');
		$this->client_ips = get_client_ips(true);
		$GLOBALS['all_client_ips'] = $this->client_ips;
	}

	/**
	 * connects to the imap server
	 */
	public function connect() {
		$this->mbox = imap_open($this->imap_server, $this->imap_username, $this->imap_password) or die('Cannot connect to '.$this->imap_server);
		/*  This Gave me this:
		stdClass Object
		(
		[Date] => Thu, 27 Mar 2014 12:20:15 -0400 (EDT)
		[Driver] => imap
		[Mailbox] => {mx.interserver.net:143/imap/readonly/user="abuse1@interserver.net"}INBOX.cop
		[Nmsgs] => 54
		[Recent] => 21
		)*/
		$this->MC = imap_check($this->mbox);
		echo "{$this->imap_folder} Got {$this->MC->Nmsgs} Messages".PHP_EOL;
	}

	/**
	 * registers a regular expression with the imap class
	 *
	 * @param string $regex
	 * @param string $against
	 * @param string $field
	 */
	public function register_preg_match($regex, $against = 'headers', $field = 'ip') {
		$regex = str_replace('%IP%', $this->get_ip_regex(), $regex);
		$this->preg_match[] = [
			'regex' => $regex,
			'against' => $against,
			'field' => $field
		];
	}

	/**
	 * registers a preg_match_all type match with the imap class
	 *
	 * @param string $regex
	 * @param string $against
	 * @param string $field
	 */
	public function register_preg_match_all($regex, $against = 'headers', $field = 'ip') {
		$regex = str_replace('%IP%', $this->get_ip_regex(), $regex);
		$this->preg_match_all[] = [
			'regex' => $regex,
			'against' => $against,
			'field' => $field
		];
	}

	/**
	 * @param string $type
	 * @param bool   $limit
	 */
	public function process($type = 'spam', $limit = false) {
		//print_r($this->MC);
		if ($this->MC->Nmsgs > 0) {
			$abused = 0;
			$db = $this->db;
			if ($limit === false)
				$result = imap_fetch_overview($this->mbox, "1:{$this->MC->Nmsgs}", 0);
			else {
				if ($limit > $this->MC->Nmsgs)
					$limit = $this->MC->Nmsgs;
				$result = imap_fetch_overview($this->mbox, "1:{$limit}", 0);
			}
			foreach ($result as $overview) {
				$this->getmsg($overview->msgno);
				$subject = $overview->subject;
				//echo "#{$overview->msgno} ({$overview->date}) - From: {$overview->from}    {$overview->subject}\n";
				$headers = imap_fetchbody($this->mbox, $overview->msgno, '0');
				$body = imap_fetchbody($this->mbox, $overview->msgno, '1');
				//echo $body.PHP_EOL;
				//echo "Headers:\n$headers\n";
				//echo "Body:\n$body\n";
				$ip = false;
				foreach ($this->preg_match as $match_data) {
					if ($match_data['against'] == 'body') {
						$match_against = $body;
					} elseif ($match_data['against'] == 'bodyfull') {
						$match_against = $this->plainmsg;
					} else {
						$match_against = $headers;
					}
					$match_res = preg_match($match_data['regex'], $match_against, $matches);
					if ($match_res) {
						if (trim($matches[$match_data['field']]) != '')
							$ip = trim($matches[$match_data['field']]);
					} else {
						//print_r($match_res);
						//echo "{$this->imap_folder} Couldn't Find IP in " . $match_data['against'] . ":\n			" . str_replace("\n", "\n			", $match_against) . "\nUsing " . $match_data['regex'].PHP_EOL;
					}
				}
				foreach ($this->preg_match_all as $match_data) {
					if ($match_data['against'] == 'body')
						$match_against = $body;
					if ($match_data['against'] == 'bodyfull') {
						$match_against = $this->plainmsg;
					} else {
						$match_against = $headers;
					}
					$match_res = preg_match_all($match_data['regex'], $match_against, $matches);
					if ($match_res) {
						if (is_array($matches[$match_data['field']]) && trim($matches[$match_data['field']][0]) != '') {
							$ip = trim($matches[$match_data['field']][0]);
						} elseif (trim($matches[$match_data['field']]) != '') {
							$ip = trim($matches[$match_data['field']]);
						}
					} else {
						//print_r($match_res);
						//echo "{$this->imap_folder} Couldn't Find IP in {$match_data['against']}:\n	" . str_replace("\n", "\n	", $match_against) . "\nUsing " . $match_data['regex'].PHP_EOL;
					}
				}
				if ($ip !== false && validIp($ip, FALSE) && (in_array($ip, $this->all_ips) || in_array($ip, $this->client_ips))) {
					if (in_array($ip, $this->client_ips)) {
						$server_data = ['email' => 'sreekanth@nettlinxinc.com'];
					} else {
						$server_data = get_server_from_ip($ip);
					}
					if (mb_substr($ip, 0, 10) == '66.45.228.' || (isset($server_data['email']) && $server_data['email'] != '')) {
						//					if ($this->abused >= 5) exit;
						$email = (null === $server_data['email_abuse'] ? $server_data['email'] : $server_data['email_abuse']);
						$subject = 'InterServer Abuse Report for '.$ip;
						if (mb_substr($ip, 0, 10) == '66.45.228.') {
							echo "{$this->imap_folder} Overwriting IP $ip Contact $email => abuse@interserver.net".PHP_EOL;
							$email = 'abuse@interserver.net';
						}
						if ($email == 'sales@3shost.com') {
							echo "{$this->imap_folder} Overwriting IP $ip Contact $email => abuse@interserver.net".PHP_EOL;
							$email = 'abuse@interserver.net';
						}
						if ($email == 'john@interserver.net')
							$email = 'abuse@interserver.net';
						//print_r(array('ip' => $ip, 'email' => $email, 'subject' => $subject, 'plainmsg' => $this->plainmsg, 'htmlmsg' => $this->htmlmsg));
						//print_r(xml2array(trim($this->htmlmsg), 1, 'attribute'));
						//exit;
						$abuse = new Abuse($db);
						$abuse->setTime(mysql_now())
							->setIp($ip)
							->setType($type)
							->setAmount(1)
							->setLid($email)
							->setStatus('pending')
							->save();
						$id = $abuse->getId();
						$abuseData = new Abuse_Data($db);
						$abuseData->setId($id)
							->setHeaders(self::fix_headers($this->plainmsg.$this->htmlmsg))
							->setPlainmsg($this->plainmsg)
							->setHtmlmsg($this->htmlmsg)
							->save();
						$email_template = file_get_contents(__DIR__.'/templates/abuse.tpl');
						$message = str_replace(
							['{$email}', '{$ip}', '{$type}', '{$count}', '{$id}', '{$key}'],
							[$email, $ip, 'spam', 1, $id, md5("${id}${ip}${type}")],
							$email_template);
						//$email = 'john@interserver.net';
						if (!isset($this->ips[$ip]))
							$this->ips[$ip] = 0;
						if (($this->limit_ips === false || $this->ips[$ip] < $this->limit_ips) && !in_array($server_data['status'], ['canceled', 'expired'])) {
							echo "{$this->imap_folder} Abuse Entry for {$ip} Added - Emailing {$email}".PHP_EOL;
							mail($email, $subject, $message, $this->email_headers);
						} else {
							echo "{$this->imap_folder} Abuse Entry for {$ip} Added - Not Emailing {$email} ({$this->ips[$ip]} >= {$this->limit_ips} Limit)".PHP_EOL;
						}
						$this->ips[$ip]++;
						$this->abused++;
					} else {
						//print_r($server_data);
						echo "{$this->imap_folder} Error Finding Owner For {$ip}".PHP_EOL;
					}
				} else {
					echo "{$this->imap_folder} Invalid IP {$ip} or not ours in Message Headers:" . json_encode(explode("\n", $headers)). "".PHP_EOL;
				}
				//echo "OVERVIEW:" . $overview->msgno . " " . $overview->subject . " " . $overview->date . "\nBODY:$body\n";
				if ($this->delete_attachments == 1)
					imap_delete($this->mbox, $overview->msgno);
			}
			$GLOBALS['abuse_ips'] = $this->ips;
		}
		if ($this->delete_attachments == 1)
			$this->delete_messages();
		$this->disconnect();
	}

	/**
	 * delete all the email messages in the given mailbox
	 *
	 * @return void
	 */
	public function delete_messages() {
		imap_expunge($this->mbox);
	}

	/**
	 * close connection to the imap server
	 *
	 * @return void
	 */
	public function disconnect() {
		imap_close($this->mbox);
	}

	/**
	 * loads the imap message and all its parts
	 *
	 * @param $mid
	 */
	public function getmsg($mid) {
		// input $mbox = IMAP stream, $mid = message id
		// output all the following:
		//  htmlmsg, plainmsg, charset, attachments
		/*
		Got 2 Messages
		mbox = 'Resource id #85';
		msgno = '1';
		charset = NULL;
		htmlmsg = '';
		plainmsg = '[ SpamCop V4.8.1.007 ]
		This message is brief for your comfort.  Please use links below for details.


		';
		attachments = array (
		);
		*/
		$this->htmlmsg = $this->plainmsg = $this->charset = '';
		$this->attachments = [];
		// HEADER
		$h = imap_header($this->mbox, $mid);
		// add code here to get date, from, to, cc, subject...
		// BODY
		$s = imap_fetchstructure($this->mbox, $mid);
		if (!isset($s->parts)) // simple

			$this->getpart($mid, $s, 0); // pass 0 as part-number
		else { // multipart: cycle through each part
			foreach ($s->parts as $partno0 => $p)
				$this->getpart($mid, $p, $partno0 + 1);
		}
	}

	/**
	 * @param $mid
	 * @param $p
	 * @param $partno
	 */
	public function getpart($mid, $p, $partno) {
		// $partno = '1', '2', '2.1', '2.1.3', etc for multipart, 0 if simple
		// DECODE DATA
		$data = $partno ? imap_fetchbody($this->mbox, $mid, $partno) : // multipart
			imap_body($this->mbox, $mid); // simple
		// Any part may be encoded, even plain text messages, so check everything.
		if ($p->encoding == 4)
			$data = quoted_printable_decode($data);
		elseif ($p->encoding == 3)
			$data = base64_decode($data);
		// PARAMETERS
		// get all parameters, like charset, filenames of attachments, etc.
		$params = [];
		if (isset($p->parameters))
			foreach ($p->parameters as $x)
				$params[strtolower($x->attribute)] = $x->value;
		if (isset($p->dparameters))
			foreach ($p->dparameters as $x)
				$params[strtolower($x->attribute)] = $x->value;
		// ATTACHMENT
		// Any part with a filename is an attachment,
		// so an attached text file (type 0) is not mistaken as the message.
		if (isset($params['filename']) || isset($params['name'])) {
			// filename may be given as 'Filename' or 'Name' or both
			$filename = isset($params['filename']) ? $params['filename'] : $params['name'];
			// filename may be encoded, so see imap_mime_header_decode()
			$this->attachments[$filename] = $data; // this is a problem if two files have same name
		}
		// TEXT
		if ($p->type == 0 && $data) {
			// Messages may be split in different parts because of inline attachments,
			// so append parts together with blank row.
			if (strtolower($p->subtype) == 'plain')
				$this->plainmsg .= trim($data) . "\n\n";
			else
				$this->htmlmsg .= $data.'<br><br>';
			if (isset($params['charset']))
				$this->charset = $params['charset']; // assume all parts are same charset
		}
		// EMBEDDED MESSAGE
		// Many bounce notifications embed the original message as type 2,
		// but AOL uses type 1 (multipart), which is not handled here.
		// There are no PHP functions to parse embedded messages,
		// so this just appends the raw source to the main message.
		elseif ($p->type == 2 && $data) {

			$this->plainmsg .= $data . "\n\n";
		}
		// SUBPART RECURSION
		if (isset($p->parts)) {
			foreach ($p->parts as $partno0 => $p2)
				$this->getpart($mid, $p2, $partno.'.'.($partno0 + 1)); // 1.2, 1.2.1, etc.
		}
	}

	/**
	 * displays the folders for an imap account
	 */
	public function get_folders() {
		/* This Gave me this:
		(0) {mx.interserver.net:143/imap/readonly}INBOX.Archives.2009,'.',64<br />
		...
		(8) {mx.interserver.net:143/imap/readonly}INBOX.Archives.2012,'.',64<br />
		(9) {mx.interserver.net:143/imap/readonly}INBOX.Comcast,'.',64<br />
		(10) {mx.interserver.net:143/imap/readonly}INBOX.USA,'.',64<br />
		(11) {mx.interserver.net:143/imap/readonly}INBOX.Archives.2008,'.',64<br />

		(12) {mx.interserver.net:143/imap/readonly}INBOX.cop,'.',64<br />
		*/

		$list = imap_getmailboxes($this->mbox, $this->imap_server, '*');
		if (is_array($list)) {
			foreach ($list as $key => $val) {
				echo "($key) " . __LINE__.PHP_EOL;
				echo imap_utf7_decode($val->name).',';
				echo "'" . $val->delimiter . "',";
				echo $val->attributes . "<br />\n";
			}
		} else {
			echo 'imap_getmailboxes failed: '.imap_last_error().PHP_EOL;
		}
	}

	/**
	 * displays all he folders in the imap account
	 */
	public function list_folders() {
		/* This Gave me this:
		<h1>Mailboxes</h1>
		{mx.interserver.net:143/imap/readonly}INBOX.Archives.2009<br />
		..
		{mx.interserver.net:143/imap/readonly}INBOX.Comcast<br />
		{mx.interserver.net:143/imap/readonly}INBOX.USA<br />
		{mx.interserver.net:143/imap/readonly}INBOX.Archives.2008<br />
		{mx.interserver.net:143/imap/readonly}INBOX.cop<br />
		{mx.interserver.net:143/imap/readonly}INBOX.Archives.2010<br />
		*/
		echo "<h1>Mailboxes</h1>\n";
		$folders = imap_list($this->mbox, $this->imap_server, '*');
		if ($folders == false) {
			echo "Call failed<br />\n";
		} else {
			foreach ($folders as $val)
				echo $val . "<br />".PHP_EOL;
		}
	}

	public static function fix_headers($headers) {
		$out = '';
		$state = 0;
		$headers = false;
		$lines = explode("\n", trim(str_replace("\r",'',$headers)));
		foreach ($lines as $line)
			if ($state == 0) {
				$out .= $line.PHP_EOL;
				if (trim($line) == '')
					$state++;
			} elseif ($state == 1)
				if (preg_match('/^[A-Z][a-zA-Z0-9\-]*: /', trim($line))) {
					$headers = true;
					$out .= $line.PHP_EOL;
					$state--;
				} elseif ($headers == true && trim($line) != '') {
					$state++;
				} elseif ($headers == false)
					$out .= $line.PHP_EOL;
		$out = preg_replace("/\n\s*\n/m", "\n", strip_tags($out));
		return $out;
	}

}
