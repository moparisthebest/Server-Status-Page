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

if (!defined('SS_PAGE'))
    die(highlight_file(__FILE__, true));

function error($s) {
    info($s, 'Error');
}

function info($s, $header = 'Very Important') {
    echo '
		<div style="margin: 2ex; padding: 2ex; border: 2px dashed #cc3344; color: black; background-color: #ffe4e9;">
			<div style="float: left; width: 2ex; font-size: 2em; color: red;">!!</div>
			<b style="text-decoration: underline;">' . $header . ':</b><br />
			<div style="padding-left: 6ex;">
			' . $s . '
			</div>
		</div>';
}

function debug($s) {
    die('error: ' . $s);
}

function forward($url = null) {
    if ($url == null) {
        global $thispage;
        $url = $thispage;
    }
    close_mysql();
    header("Location: $url");
    exit;
}

function randString($allowed, $min_length = 4, $max_length = 8) {
    $allowed_len = strlen($allowed);
    $length = mt_rand($min_length, $max_length);
    $ret = '';
    for ($x = 0; $x < $length; ++$x)
        $ret .= $allowed[mt_rand(0, $allowed_len)];
    return $ret;
}

function actionURL($action) {
    global $thispage;
    return $thispage . '?action=' . $action;
}

function can_mod() {
    global $is_admin;
    return $is_admin;
}

function forceAdmin() {
    global $is_admin;
    if (!$is_admin)
        forward();
}

function forceLogin($action = null) {
    global $g_login_check;

    // then we already checked
    if (isset($g_login_check))
        return;

    $g_login_check = 1;
    //return;
    global $is_guest;
    if ($is_guest) {
        global $thispage;
        if ($action != null)
            $thisurl = $thispage . '?action=' . $action;
        else
            $thisurl = $thispage . '?' . $_SERVER['QUERY_STRING'];

        echo 'Enter your forum username and password to login:<br />';
        echoLogin($thisurl);
        echoFooterExit();
    } else {
        global $uname, $time_format, $time_offset;
        echo "Welcome $uname!";
//		echo ' | Time: '.strftime($time_format, time()+$time_offset);
        echo '<br />';
    }
}

//make sure the string is normalized first.
function isAllowed($s, $allowed) {
    for ($x = 0; $x < strlen($s); ++$x)
        if (strpos($allowed, $s[$x]) === false)
            return false;
    return true;
}

//make sure the string is normalized first.
function stripUnAllowed($s, $allowed) {
    for ($x = 0; $x < strlen($s); ++$x)
        if (strpos($allowed, $s[$x]) === false)
            $s[$x] = ' ';
    return str_replace(' ', '', $s);
}

function verifyIP($hostname, &$ip, &$remote_ip) {
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
function checkRows($sql, $types) {
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
    for ($i = 0; $i < $numargs - 1; $i++) {
        $params[$i] = & $arg_list[$i + 1];
    }
    //print_r($params);
    call_user_func_array(array($stmt, 'bind_param'), $params);

    $stmt->execute();
    $rows = $stmt->fetch();
    $stmt->close();
    return $rows;
}

function getTimeStamp() {
    $contents = file_get_contents("timestamp") or die("Can't read timestamp");
    return $contents;
}

function mysql_con() {
    global $g_mysqli, $g_sql_host, $g_sql_user, $g_sql_pass, $g_sql_db;

    // then we are already connected
    if (isset($g_mysqli))
        return;

    $g_mysqli = new mysqli($g_sql_host, $g_sql_user, $g_sql_pass, $g_sql_db);

    /* check connection */
    if (mysqli_connect_errno()) {
        printf("Connect failed: %s\n", mysqli_connect_error());
        exit();
    }

    /* change character set to utf8 */
    if (!$g_mysqli->set_charset("utf8")) {
        printf("Error loading character set utf8: %s\n", $g_mysqli->error);
    }

    $g_sql_host = '';
    $g_sql_user = '';
    $g_sql_pass = '';
    $g_sql_db = '';
}

function close_mysql() {
    global $g_mysqli;

    // then we are already connected
    if (isset($g_mysqli)) {
        $g_mysqli->close();
        unset($GLOBALS['g_mysqli']);
    }
}

function html_special(&$bb_code) {
    return htmlspecialchars($bb_code, ENT_QUOTES, 'UTF-8');
}

function html_special_decode(&$bb_code) {
    return htmlspecialchars_decode($bb_code, ENT_QUOTES);
}

// echos ad code if it exists
function echoAdIfExists() {
    if (function_exists('echoAd')) {
        echoAd();
    }
}

// echos ad code if it exists
function echoAnalyticsIfExists() {
    if (function_exists('echoAnalytics')) {
        echoAnalytics();
    }
}

// echos the footer and then exits
function echoFooterExit($echo = '') {
    global $thispage;
    $uri = urlencode($thispage . '?' . $_SERVER['QUERY_STRING']);
    // can call this because it only closes it if it is set
    close_mysql();
    echo $echo;
    echoFooter($uri);
    exit;
}

?>