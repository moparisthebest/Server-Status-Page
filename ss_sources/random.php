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

function random_page() {
    mysql_con();
    global $g_mysqli, $thispage;
    $stmt = $g_mysqli->prepare("SELECT `ip` FROM `servers` WHERE `online` = ? ORDER BY RAND() LIMIT 1") or debug($g_mysqli->error);
    $online = isset($_GET['offline']) ? 0 : 1;
    $stmt->bind_param("i", $online);
    $stmt->execute();
    // bind result variables
    $stmt->bind_result($rand_server);
    $stmt->fetch();
    $stmt->close();
    close_mysql();
    if (isset($_GET['offline']))
        $rand_server .= "&offline";
    header("Location: $thispage?server=$rand_server");
}

?>