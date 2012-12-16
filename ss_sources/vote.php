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

function vote() {
//	forceLogin();

    global $uid, $recaptcha_pubkey;

    $action = $_GET['action'];

    if (!isset($_GET['server']))
        forward();

    $server = $_GET['server'];

    if (!getMysqlId($server, $id, $suid, $name, $ip))
        return;

    if ($uid == $suid) {
        echo "It isn't right for you to vote on your own server, now is it?";
        return;
    }

    echo "Are you sure you wish to vote <b>$action</b> server $name? You only get to vote once per hour.<br />";
    ?>
<form action="<?php echo actionURL('vote'); ?>" method="post" enctype="multipart/form-data" style="margin: 0;">
    <fieldset style="margin: 0;">
        <input type="hidden" name="id" value="<?php echo $id; ?>"/>
        <input type="hidden" name="vote" value="<?php echo $action; ?>"/>
        <input type="hidden" name="ip" value="<?php echo $ip; ?>"/>

        <?php
        if (!empty($recaptcha_pubkey)) {
            require_once('recaptchalib.php');
            echo recaptcha_get_html($recaptcha_pubkey, null, ($_SERVER['HTTPS'] == "on"));
        }
        ?>

        <input type="submit" name="submit" value="Vote" accesskey="s"/>
    </fieldset>
</form>
<?php
}

function vote2() {
//	forceLogin();

    global $uid, $uname, $thispage, $time_format, $time_offset, $g_mysqli, $recaptcha_privkey;

    //wait time in seconds to vote again
    $wait_time = 3600;

    $action = $_POST['vote'];

    if ($action != 'up' && $action != 'down')
        forward();

    if (!empty($recaptcha_privkey)) {
        require_once('recaptchalib.php');
        $resp = recaptcha_check_answer($recaptcha_privkey,
            $_SERVER["REMOTE_ADDR"],
            $_POST["recaptcha_challenge_field"],
            $_POST["recaptcha_response_field"]);
    }

    if (!$resp->is_valid) {
        // What happens when the CAPTCHA was entered incorrectly
        //die ("The reCAPTCHA wasn't entered correctly. Go back and try it again."."(reCAPTCHA said: " . $resp->error . ")");
        //forward();
        error("The reCAPTCHA wasn't entered correctly. Go back and try it again.");
        return;
    }

    $server = $_POST['ip'];

    if (!getMysqlId($server, $id, $suid, $name, $ip))
        return;

    if ($uid == $suid) {
        echo "It isn't right for you to vote on your own server, now is it?";
        return;
    }

    $expected_referer = "$thispage?action=$action&server=$ip";

//die($expected_referer.':'.$_SERVER['HTTP_REFERER']);
//die($ip.':'.$server);
//die($id.':'.$_POST['id']);
//die('$uid:'.$uid.' $uname:'.$uname.' $time_format:'.$time_format.' $time_offset:'.$time_offset);
    if ($ip != $server || $id != $_POST['id'] || $_SERVER['HTTP_REFERER'] != $expected_referer)
        forward($expected_referer);

    // we checked out so far, make sure they haven't voted in the last hour
    $threshold = time() - $wait_time;

    // first check the session variable, since it is cheaper than a query
    if (isset($_SESSION['last_voted']) && $_SESSION['last_voted'] > $threshold) {
        echo 'You have voted within the last hour, you may do this again at ' . strftime($time_format, $_SESSION['last_voted'] + $wait_time + $time_offset) . '<br />';
        return;
    }

    $stmt = $g_mysqli->prepare("SELECT `time` FROM `log_voted` WHERE `time` > ? AND ( (`uid` != '0' AND`uid` = ?) OR `ip` = ?) LIMIT 1") or debug($g_mysqli->error);
    $stmt->bind_param("iis", $threshold, $uid, $_SERVER['REMOTE_ADDR']);
    $stmt->execute();
    // bind result variables
    $stmt->bind_result($time);
    if ($stmt->fetch()) {
        echo 'You have voted within the last hour, you may do this again at ' . strftime($time_format, $time + $wait_time + $time_offset) . '<br />';
        return;
    }
    $stmt->close();

    // we haven't voted in the last hour, now enter the vote AND update the log
    if ($action == 'up')
        $op = '+';
    else
        $op = '-';

    $sql = "UPDATE `servers` SET `vote` = `vote` $op '1' WHERE `id` = ? LIMIT 1";
    $stmt = $g_mysqli->prepare($sql) or debug($g_mysqli->error);
    $stmt->bind_param("i", $id);

    // execute the query
    $stmt->execute();
    if ($stmt->affected_rows != 1) {
        global $g_admin_contact;
        echo 'Vote failed, PM ' . $g_admin_contact . ' on the forums to with details so he can fix it.';
        return;
    }
    $stmt->close();

    // we have voted now, so we need to insert it into the log to enforce the 1 per hour limit, and set session variable last_voted
    $_SESSION['last_voted'] = time();
    $sql = 'INSERT INTO `log_voted` (`uid`, `uname`, `server_id`, `time`, `ip`, `op`) VALUES(?, ?, ?, ?, ?, ?)';
    $stmt = $g_mysqli->prepare($sql) or debug($g_mysqli->error);
    $stmt->bind_param("isiiss", $uid, $uname, $id, $_SESSION['last_voted'], $_SERVER['REMOTE_ADDR'], $op);
    $stmt->execute() or debug($g_mysqli->error);
    if ($stmt->affected_rows != 1) {
        global $g_admin_contact;
        echo 'Vote log failed, PM ' . $g_admin_contact . ' on the forums to with details so he can fix it.';
        return;
    }
    $stmt->close();

    close_mysql();

    forward("$thispage?server=$ip");

}

function getMysqlId($server, &$id, &$suid, &$name, &$ip) {
    global $g_mysqli;

    mysql_con();
    $stmt = $g_mysqli->prepare('SELECT `id`, `uid`, `name`, `ip` FROM `servers` WHERE `ip` = ? LIMIT 1') or debug($g_mysqli->error);
    $stmt->bind_param("s", $server);
    $stmt->execute();
    // bind result variables
    $stmt->bind_result($id, $suid, $name, $ip);
    if (!$stmt->fetch()) {
        echo 'This server does not exist.<br />';
        return false;
    }
    $stmt->close();
    return true;
}

?>