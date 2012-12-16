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

function banServ() {
    forceAdmin();

    global $g_mysqli, $g_admin_contact;

    mysql_con();

    $sql = "INSERT INTO `banned` SELECT * FROM `servers` WHERE `ip` = ? AND `sponsored` = '0' LIMIT 1";
    $stmt = $g_mysqli->prepare($sql) or debug($g_mysqli->error);
    $stmt->bind_param("s", $_GET['server']);

    // execute the query
    $stmt->execute() or debug($g_mysqli->error);
    if ($stmt->affected_rows != 1) {
        echo 'Ban failed, is it a sponsored server?, PM ' . $g_admin_contact . ' on the forums to with details so he can fix it.';
        return;
    }
    $stmt->close();

    deleteServ();
}

function deleteServ() {
    forceAdmin();

    global $g_mysqli, $g_admin_contact;

    mysql_con();

    $sql = "DELETE FROM `servers` WHERE `ip` = ? AND `sponsored` = '0' LIMIT 1";
    $stmt = $g_mysqli->prepare($sql) or debug($g_mysqli->error);
    $stmt->bind_param("s", $_GET['server']);

    // execute the query
    $stmt->execute() or debug($g_mysqli->error);
    if ($stmt->affected_rows != 1) {
        echo 'Delete failed, is it a sponsored server?, PM ' . $g_admin_contact . ' on the forums to with details so he can fix it.';
        return;
    }
    $stmt->close();

    forward();


}

?>