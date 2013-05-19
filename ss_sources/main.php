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

global $g_source_dir, $thispage, $g_allowed_url, $g_allowed_alpha, $g_allowed_key, $g_allowed_dns, $g_ss_version;
$g_ss_version = '0.5';
$thispage = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['SERVER_NAME'] . $_SERVER['PHP_SELF'];
$g_allowed_key = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
$g_allowed_alpha = $g_allowed_key . "- ";
$g_allowed_dns = $g_allowed_key . "-.";
$g_allowed_url = $g_allowed_key . " /-:.%";

require_once($g_source_dir . '/util.php');

// What function shall we execute? (done like this for memory's sake.)
// defaults
$header = true;
$do_setup = true;
$action = ss_main($header, $do_setup);

if ($do_setup)
    doUserSetup();

if ($header)
    echoHeader($action);

call_user_func($action);

if ($header)
    echoFooterExit();

// The main controlling function.
function ss_main(&$header, &$do_setup) {
    global $g_source_dir;

    if (empty($_REQUEST['action'])) {
        if (!empty($_REQUEST['server'])) {
            require_once($g_source_dir . '/view.php');
            return 'view';
        }
        require_once($g_source_dir . '/display.php');
        return 'display';
    }

    // Here's the monstrous $_REQUEST['action'] array - $_REQUEST['action'] => array($file, $function, $header, $do_setup).
    $actionArray = array(
        'display' => array('display.php', 'display'),
        'view' => array('view.php', 'view'),
        'register' => array('register.php', 'register'),
        'register2' => array('register.php', 'register2'),
        'verify' => array('verify.php', 'verify', false, false),
        'check_ip' => array('check_ip.php', 'check_ip', false, false),
        'random' => array('random.php', 'random_page', false, false),
        'image' => array('image_server.php', 'gen_image', false, false),
        'zip' => array('tbszip.php', 'zip_sources', false, false),
        'up' => array('vote.php', 'vote'),
        'down' => array('vote.php', 'vote'),
        'vote' => array('vote.php', 'vote2'),
        'ban' => array('moderate.php', 'banServ'),
        'delete' => array('moderate.php', 'deleteServ'),
        'search' => array('search.php', 'search'),
        'search2' => array('search.php', 'search2'),
        'search3' => array('search.php', 'search3'),
    );

    // Get the function and file to include - if it's not there, do the board index.
    if (!isset($_REQUEST['action']) || !isset($actionArray[$_REQUEST['action']])) {
        // xxx maybe they are trying to ddos us (action=hackedbybattlescapecrew)
        //global $thispage;
        //die('No action found, try '.$thispage);

        // Fall through to the display then...
        require_once($g_source_dir . '/display.php');
        return 'display';
    }

    // Otherwise, it was set - so let's go to that action.
    require_once($g_source_dir . '/' . $actionArray[$_REQUEST['action']][0]);
    // here is the only place we NEED to set $header and $do_setup
    $header = (isset($actionArray[$_REQUEST['action']][2]) ? $actionArray[$_REQUEST['action']][2] : true);
    $do_setup = (isset($actionArray[$_REQUEST['action']][3]) ? $actionArray[$_REQUEST['action']][3] : true);
    return $actionArray[$_REQUEST['action']][1];
}

?>