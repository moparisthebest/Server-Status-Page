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

function check_ip() {
    header("Content-type: text/plain");

    if (!isset($_REQUEST['server']) || !isset($_REQUEST['ip'])) {
        echo "Error: Both server and ip must be set.";
        return;
    }

    // if we are here, server and key are set
    $server = $_REQUEST['server'];
    $ip = $_REQUEST['ip'];
    @$since_time = is_numeric($_REQUEST['since']) ? $_REQUEST['since'] : 86400; // default to 1 day ago

    $before_time = time() - $since_time;

    mysql_con();
    global $g_mysqli;
    $stmt = $g_mysqli->prepare('SELECT count(*) FROM log_voted WHERE ip = ? AND time > ? AND server_id IN (SELECT id FROM servers WHERE ip = ?)') or debug($g_mysqli->error);
    $stmt->bind_param("sis", $ip, $before_time, $server);
    $stmt->execute();
    // bind result variables
    $stmt->bind_result($count);
    if (!$stmt->fetch())
        $count = 0;
    $stmt->close();
    close_mysql();

    echo $count;
}
?>