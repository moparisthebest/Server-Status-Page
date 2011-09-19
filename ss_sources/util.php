<?php
/*
MoparScape.org server status page
Copyright (C) 2011  Travis Burtrum (moparisthebest)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Affero General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

//die('disabled for a moment, upgrading the page...');
if (!defined('SS_PAGE'))
	die('Hacking attempt...');

$thispage = 'http'.(isset($_SERVER['HTTPS']) ? 's' : '').'://'.$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF'];

function doSetup(){
	global $g_mysqli, $g_allowed_url, $g_allowed_alpha, $g_allowed_key, $g_allowed_dns, $thispage, $g_versions, $g_login_check;
	$g_allowed_url = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ /-:.%0123456789";
	$g_allowed_alpha = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789- ";
	$g_allowed_key = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
	$g_allowed_dns = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-.";
	$g_versions = array(317, 508);

	define('MSCP', 1);
	global $forumid;
	$forumid = 1;
	require_once('/path/to/smf/SSI.php');

	global $user_info;
	$user = ssi_welcome('array');

	// vars from SMF that we use, for easy compatibility for future versions
	global $groups, $time_format, $time_offset, $is_admin, $is_guest, $uname, $uid;
	$groups = $user_info['groups'];
	$time_format = $user_info['time_format'];
	# this is the number of seconds to add to time()
	$time_offset = $user_info['time_offset']*3600;
	$is_admin = $user['is_admin'] || $groups[0] == 2 || $groups[0] == 63 || in_array(70, $groups);
	$is_guest = $user['is_guest'];
	$uname = $user['name'];
	$uid = $user['id'];
}

function error($s){
	info($s, 'Error');
}

function info($s, $header='Very Important'){
	echo '
		<div style="margin: 2ex; padding: 2ex; border: 2px dashed #cc3344; color: black; background-color: #ffe4e9;">
			<div style="float: left; width: 2ex; font-size: 2em; color: red;">!!</div>
			<b style="text-decoration: underline;">'.$header.':</b><br />
			<div style="padding-left: 6ex;">
			'.$s.'
			</div>
		</div>';
}

function debug($s){
	die('error: '.$s);
}

function forward($url = null){
	if($url == null){
		global $thispage;
		$url = $thispage;
	}
	close_mysql();
	header("Location: $url");
	exit;
}

function randString($allowed, $min_length = 4, $max_length = 8){
	$allowed_len = strlen($allowed);
	$length = mt_rand($min_length,$max_length);
	$ret = '';
	for($x = 0; $x < $length; ++$x)
		$ret .= $allowed[mt_rand(0, $allowed_len)];
	return $ret;
}

function actionURL($action){
	global $thispage;
	return $thispage.'?action='.$action;
}

function can_mod(){
	global $is_admin;
	return $is_admin;
}

function forceAdmin(){
	global $is_admin;
	if(!$is_admin)
		forward();
}

function forceLogin($action = null){
     global $g_login_check;

     // then we already checked
     if(isset($g_login_check))
          return;

     $g_login_check = 1;

	global $is_guest;
	if ($is_guest){
		global $thispage;
		if($action != null)
			$thisurl = $thispage.'?action='.$action;
		else
			$thisurl = $thispage.'?'.$_SERVER['QUERY_STRING'];

		echo 'Enter your forum username and password to login:<br />';
		ssi_login($thisurl);
		echoFooterExit();
	}else{
		global $uname, $time_format, $time_offset;
		echo "Welcome $uname!";
//		echo ' | Time: '.strftime($time_format, time()+$time_offset);
		echo '<br />';
	}
}

//make sure the string is normalized first.
function isAllowed($s, $allowed){
	for($x = 0; $x < strlen($s); ++$x)
		if(strpos($allowed, $s[$x]) === false)
			return false;
	return true;
}

//make sure the string is normalized first.
function stripUnAllowed($s, $allowed){
	for($x = 0; $x < strlen($s); ++$x)
		if(strpos($allowed, $s[$x]) === false)
			$s[$x] = ' ';
	return str_replace(' ', '', $s);
}

function verifyIP($hostname, $ip, $remote_ip){
	$ip = gethostbyname($hostname);
	$remote_ip = $_SERVER['REMOTE_ADDR'];
	return $ip == $remote_ip;
}
/*
function checkRows111(){
        $args = func_get_args();
        $sql = array_shift($args);
        $link = self::establish_db_conn();
        if (!$stmt = mysqli_prepare($link, $sql)) {
            self::close_db_conn();
            die('Please check your sql statement : unable to prepare');
        }
        $types = str_repeat('s', count($args));
        array_unshift($args, $types);
        array_unshift($args, $stmt);
        call_user_func_array('mysqli_stmt_bind_param', $args);

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_result_metadata($stmt);
        $fields = array();
        while ($field = mysqli_fetch_field($result)) {
            $name = $field->name;
            $fields[$name] = &$$name;
        }
        array_unshift($fields, $stmt);
        call_user_func_array('mysqli_stmt_bind_result', $fields);

        array_shift($fields);
        $results = array();
        while (mysqli_stmt_fetch($stmt)) {
            $temp = array();
            foreach($fields as $key => $val) { $temp[$key] = $val; }
            array_push($results, $temp);
        }

        mysqli_free_result($result);
        mysqli_stmt_close($stmt);
        self::close_db_conn();

        return $results;
}
*/
function checkRows($sql, $types){
	$numargs = func_num_args();
	//echo "Number of arguments: $numargs<br />\n";
	if (strlen($types) != ($numargs - 2)) {
		debug("checkRows: Length of types must be equal to the number of extra args passed in.");
		return false;
	}
	global $g_mysqli;
	$stmt = $g_mysqli->prepare($sql) or debug($g_mysqli->error);

	$arg_list = func_get_args();
	// start at 2, because of $sql and $types
	$params = array();
	for ($i = 0; $i < $numargs-1; $i++){
		$params[$i] = &$arg_list[$i+1];
	}
	//print_r($params);
	call_user_func_array(array($stmt, 'bind_param'), $params);

	$stmt->execute();
	$rows = $stmt->fetch();
	$stmt->close();
	return $rows;
}

function getTimeStamp(){
	$contents = file_get_contents("timestamp") or die("Can't read timestamp");
	return $contents;
}

function mysql_con(){
	global $g_mysqli;

	// then we are already connected
	if(isset($g_mysqli))
		return;

	$host = 'localhost';
	$user = 'user';
	$pass = 'pass';
	$db = 'serverstat';

	$g_mysqli = new mysqli($host, $user, $pass, $db);

	/* check connection */
	if (mysqli_connect_errno()) {
		printf("Connect failed: %s\n", mysqli_connect_error());
		exit();
	}

	/* change character set to utf8 */
	if (!$g_mysqli->set_charset("utf8")) {
	    printf("Error loading character set utf8: %s\n", $g_mysqli->error);
	}
}

function close_mysql(){
	global $g_mysqli;

	// then we are already connected
	if(isset($g_mysqli)){
		$g_mysqli->close();
		unset($GLOBALS['g_mysqli']);
	}
}

function html_special(&$bb_code){
	return htmlspecialchars($bb_code, ENT_QUOTES, 'UTF-8');
}

function html_special_decode(&$bb_code){
	return htmlspecialchars_decode($bb_code, ENT_QUOTES);
}

function bb2html($bb_code, $previewing = false){
	$bb_code = html_special($bb_code);
	//old preparsecode($bb_code);
	require_once('/path/to/smf/Sources/Subs-Post.php');
	preparsecode($bb_code, $previewing);

	// Do all bulletin board code tags, with or without smileys.
	//old $bb_code = parse_bbc($bb_code, 1);

//	require_once('/home/mopar/htdocs/moparisthebest.com/smf/Sources/Subs-Post.php');
	censorText($bb_code);
	$bb_code = parse_bbc($bb_code);

	return $bb_code;
}

// echos the header
function echoHeader($action) {
	global $thispage;
	//$action = (empty($_REQUEST['action'])) ? 'display' : $_REQUEST['action'];
//<meta http-equiv="content-type" content="text/html; charset=utf-8" />
/*echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";*/
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="content-type" content="text/html; charset=utf-8" />
<title>Mopar's Server Status Checker - Beta 2</title>
<link rel="stylesheet" type="text/css" href="newstyle.css" />
</head>
<body>
<div id="wrapper">
  <div id="header">
    <div id="lefthead"> <a href="http://www.moparscape.org/smf/index.php"><img src="images/visit.png" alt="Visit Forums" /></a> </div>
    <div id="righthead"> <a href="http://www.moparscape.org/moparscape.html"><img src="images/dlmoparscape.png" alt="Download MoparScape Here!" /></a> </div>
    <div id="banner"> <a href="http://www.moparscape.org/serverstatus.php"><img src="images/mscp_banner.png" alt="MoparScape Server Status" /></a> </div>

  <ul id="nav">
    <li><a href="?"<?php if($action == 'display' && !isset($_GET['offline'])) echo ' class="on"'; ?>>Online Servers</a></li>
    <li><a href="?offline"<?php if($action == 'display' && isset($_GET['offline'])) echo ' class="on"'; ?>>Offline Servers</a></li>
    <li><a href="?sort=vote&amp;desc<?php if(isset($_GET['offline'])) echo '&amp;offline'; ?>"<?php if($action == 'display' && isset($_GET['sort']) && $_GET['sort'] == 'vote' && isset($_GET['desc'])) echo ' class="on"'; ?>>Most Popular</a></li>
    <li><a href="?action=random<?php if(isset($_GET['offline'])) echo '&amp;offline'; ?>">Random Server</a></li>
    <li><a href="?action=register"<?php if($action == 'register' && !isset($_GET['edit'])) echo ' class="on"'; ?>>Register Server</a></li>
    <li><a href="?action=register&amp;edit"<?php if($action == 'register' && isset($_GET['edit'])) echo ' class="on"'; ?>>Edit my Server</a></li>
    <li><a href="?action=search"<?php if(strpos($action, 'search') !== false) echo ' class="on"'; ?>>Search</a></li>
    <li><?php ssi_logout($thispage.'?'.$_SERVER['QUERY_STRING']); ?></li>
  </ul>
  </div>

  <div id="leftcolumn">
<script type="text/javascript"><!--
google_ad_client = "ca-pub-3055920918910714";
/* serverstatus */
google_ad_slot = "0121476779";
google_ad_width = 160;
google_ad_height = 600;
//-->
</script>
<script type="text/javascript"
src="http://pagead2.googlesyndication.com/pagead/show_ads.js">
</script>
  </div>
  <div id="centercolumn">
This new page is beta, servers may be added and deleted while I finish it. Thanks for being patient. Post
comments about the new page <a href="http://www.moparscape.org/smf/index.php/topic,363862.0.html">here</a>.<br /><br />
<?php
	}

// echos the footer and then exits
function echoFooterExit($echo = ''){
	global $thispage;
	$uri = urlencode($thispage.'?'.$_SERVER['QUERY_STRING']);
	// can call this because it only closes it if it is set
	close_mysql();
	echo $echo;
?>
  </div>
  <div id="rightcolumn">
<script type="text/javascript"><!--
google_ad_client = "ca-pub-3055920918910714";
/* serverstatus */
google_ad_slot = "0121476779";
google_ad_width = 160;
google_ad_height = 600;
//-->
</script>
<script type="text/javascript"
src="http://pagead2.googlesyndication.com/pagead/show_ads.js">
</script>
  </div>
  <div id="footer">
    <p>
    <a href="http://validator.w3.org/check?uri=<?php echo $uri; ?>"><img
        src="images/valid-xhtml10-blue.png"
        alt="Valid XHTML 1.0 Strict" height="31" width="88" /></a>
 Copyright &copy; 2009 MoparScape.org
    <a href="http://jigsaw.w3.org/css-validator/validator?uri=<?php echo $uri; ?>"><img
        src="images/vcss-blue.gif"
        alt="Valid CSS 2.1!" height="31" width="88" /></a>
  </p>
  </div>
</div>
<script type="text/javascript">
var gaJsHost = (("https:" == document.location.protocol) ? "https://ssl." : "http://www.");
document.write(unescape("%3Cscript src=\'" + gaJsHost + "google-analytics.com/ga.js\' type=\'text/javascript\'%3E%3C/script%3E"));
</script>
<script type="text/javascript">
try {
var pageTracker = _gat._getTracker("UA-6877554-1");
pageTracker._trackPageview();
} catch(err) {}</script>

</body>
</html>

<?php
	exit;
}
/*
DROP TABLE IF EXISTS `toadd`;
CREATE TABLE IF NOT EXISTS `toadd` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `uid` mediumint(8) unsigned NOT NULL,
  `uname` varchar(80) NOT NULL,
  `online` tinyint(1) unsigned NOT NULL default '1',
  `name` tinytext NOT NULL,
  `pic_url` tinytext NOT NULL default '',
  `ip` varchar(30) NOT NULL,
  `port` smallint(5) unsigned NOT NULL,
  `version` smallint(3) unsigned NOT NULL,
  `time` int(10) unsigned NOT NULL,
  `info` text NOT NULL,
  `oncount` int(11) unsigned NOT NULL default '1',
  `totalcount` int(11) unsigned NOT NULL default '1',
  `uptime` tinyint(3) unsigned NOT NULL default '100',
  `ipaddress` varchar(15) NOT NULL,
  `sponsored` smallint(5) unsigned NOT NULL default '0',
  `rs_name` tinytext NOT NULL,
  `rs_pass` tinytext NOT NULL,
  `key` varchar(15) NOT NULL,
  `verified` tinyint(1) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`),
  KEY `uid` (`uid`),
  KEY `online` (`online`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `banned`;
CREATE TABLE IF NOT EXISTS `banned` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `uid` mediumint(8) unsigned NOT NULL,
  `uname` varchar(80) NOT NULL,
  `online` tinyint(1) unsigned NOT NULL default '1',
  `name` tinytext NOT NULL,
  `pic_url` tinytext NOT NULL default '',
  `ip` varchar(30) NOT NULL,
  `port` smallint(5) unsigned NOT NULL,
  `version` smallint(3) unsigned NOT NULL,
  `time` int(10) unsigned NOT NULL,
  `info` text NOT NULL,
  `oncount` int(11) unsigned NOT NULL default '1',
  `totalcount` int(11) unsigned NOT NULL default '1',
  `uptime` tinyint(3) unsigned NOT NULL default '100',
  `ipaddress` varchar(15) NOT NULL,
  `sponsored` smallint(5) unsigned NOT NULL default '0',
  `rs_name` tinytext NOT NULL,
  `rs_pass` tinytext NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `uid` (`uid`),
  KEY `online` (`online`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ;

DROP TABLE IF EXISTS `servers`;
CREATE TABLE IF NOT EXISTS `servers` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `uid` mediumint(8) unsigned NOT NULL,
  `uname` varchar(80) NOT NULL,
  `online` tinyint(1) unsigned NOT NULL default '1',
  `name` tinytext NOT NULL,
  `pic_url` tinytext NOT NULL,
  `ip` varchar(30) NOT NULL,
  `port` smallint(5) unsigned NOT NULL,
  `version` smallint(3) unsigned NOT NULL,
  `time` int(10) unsigned NOT NULL,
  `info` text NOT NULL,
  `oncount` int(11) unsigned NOT NULL default '1',
  `totalcount` int(11) unsigned NOT NULL default '1',
  `uptime` tinyint(3) unsigned NOT NULL default '100',
  `ipaddress` varchar(15) NOT NULL,
  `sponsored` smallint(5) unsigned NOT NULL default '0',
  `rs_name` tinytext NOT NULL,
  `rs_pass` tinytext NOT NULL,
  `vote` int(11) NOT NULL default '0',
  PRIMARY KEY  (`id`),
  KEY `uid` (`uid`),
  KEY `online` (`online`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ;

DROP TABLE IF EXISTS `log_voted`;
CREATE TABLE IF NOT EXISTS `log_voted` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `uid` mediumint(8) unsigned NOT NULL,
  `uname` varchar(80) NOT NULL,
  `server_id` int(11) unsigned NOT NULL,
  `time` int(10) unsigned NOT NULL,
  `ip` varchar(15) NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `uid` (`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ;
*/
?>