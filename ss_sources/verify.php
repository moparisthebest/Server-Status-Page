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

function verify() {
    header("Content-type: text/plain");
    //echo 'this is verify';
    //echo time(); return;
    //global $g_allowed_alpha; echo randString($g_allowed_alpha); return;
    if (!isset($_GET['server']) || !isset($_GET['key'])) {
        echo "Error: Both server and key must be set.\n";
        return;
    }

    // if we are here, server and key are set
    $server = $_GET['server'];
    $key = $_GET['key'];

    writeToFile("server: $server key: $key");

    if (verifyIP($server, $ip, $remote_ip)) {
        echo "Success: $server resolves to $ip, which matches your ip, $remote_ip.\n";
        writeToFile("Success: $server resolves to $ip, which matches your ip, $remote_ip.");
    } else {
        echo "Error: $server resolves to $ip, which does not match your ip, $remote_ip.\n";
        writeToFile("Error: $server resolves to $ip, which does not match your ip, $remote_ip.\n");
        return;
    }

    // if we are here, remote ip matches the hostname, so verify the key
    mysql_con();
    global $g_mysqli;
    $stmt = $g_mysqli->prepare('SELECT `id`, `key`, `rs_name`, `rs_pass`, `verified` FROM `toadd` WHERE `ip` = ? LIMIT 1') or debug($g_mysqli->error);
    $stmt->bind_param("s", $server);
    $stmt->execute();
    // bind result variables
    $stmt->bind_result($id, $db_key, $rs_name, $rs_pass, $verified);
    if (!$stmt->fetch()) {
        echo "Error: This server does not exist, you may repost it, and then verify it.\n";
        writeToFile("server doesn't exist");
        return;
    }
    $stmt->close();

    if ($key != $db_key) {
        echo "Error: The key is not correct, you may only verify the ip with the correct key.\n";
        writeToFile("key incorrect");
        return;
    }

    if ($verified == 1) {
        writeToFile("already verified");
        echo "You have already verified that you own this server.
Your server will be checked by logging into it with the following credentials:
Username: $rs_name
Password: $rs_pass
to make sure it is online, and if successful, it will be posted.
";
        return;
    }

    // if we are here, the ip and key is valid so set the server as verified
    $sql = "UPDATE `toadd` SET `verified` = '1' WHERE `id` = ? LIMIT 1";
    $stmt = $g_mysqli->prepare($sql) or debug($g_mysqli->error);
    $stmt->bind_param("i", $id);

    // execute the query
    $stmt->execute();
    if ($stmt->affected_rows == 1) {
        writeToFile("success verified");
        echo "Congratulations, you have verified you own this IP.
Your server will now be checked by logging into it with the following credentials:
Username: $rs_name
Password: $rs_pass
to make sure it is online, and if successful, it will be posted.
";
    } else {
        writeToFile("strange failure");
        global $g_admin_contact;
        echo "Strange failure, PM " . strip_tags($g_admin_contact) . " on the forums to with details so he can fix it.\n";
    }
    $stmt->close();

    close_mysql();
}

function writeToFile($message, $fname = 'verify_log', $mode = 'a') {
    @$fp = fopen($fname, $mode);
    @fwrite($fp, time() . ': ' . $message . "\n");
    @fclose($fp);
}

?>