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

# remove these next two lines
if (!defined('SS_PAGE'))
    die(highlight_file(__FILE__, true));

# uncomment these to show all errors
//ini_set('display_errors', 0);
//error_reporting(E_ALL);

# you need to set these regardless
global $g_source_dir, $g_sql_host, $g_sql_user, $g_sql_pass, $g_sql_db, $g_versions, $g_theme, $g_img_dir, $g_admin_contact;
# path to ss_sources
$g_source_dir = './ss_sources';
$g_img_dir = './images';
# MySQL details
$g_sql_host = 'localhost';
$g_sql_user = 'user';
$g_sql_pass = 'pass';
$g_sql_db = 'serverstat';
# versions you support
$g_versions = array(317, 508);
$g_admin_contact = '<a href="/smf/index.php?action=profile;u=youruid">yourname</a>';

define('SS_PAGE', 1);

# include forum connector
require_once($g_source_dir . '/forums/smf.php');
# do custom setup for above forum connector
global $path_to_smf, $smf_admin_groups, $g_forum_url;
$path_to_smf = '/path/to/smf';
$smf_admin_groups = array(2); # 2 is global moderators, usually
$g_forum_url = '/smf/index.php';

# include theme
require_once($g_source_dir . '/themes/default.php');
# do custom setup for above theme
$g_theme = array();
$g_theme['title'] = "Server Status Checker";
$g_theme['left_link'] = '/smf/index.php';
$g_theme['left_link_alt'] = 'Visit Forums';
$g_theme['right_link'] = '/download.html';
$g_theme['right_link_alt'] = 'Download Here!';
$g_theme['center_link'] = '/serverstatus.php';
$g_theme['center_link_alt'] = 'Server Status';
$g_theme['header'] = 'Welcome visitor!';

# recaptcha setup for votes, if empty, recaptcha won't be used
# get keys here: https://www.google.com/recaptcha/admin/create?app=php
//global $recaptcha_pubkey, $recaptcha_privkey;
//$recaptcha_pubkey = "";
//$recaptcha_privkey = "";

function echoPlay($online, $ip, $port, $version) {
    if ($online == 1) {
        $link = "http://www.moparscape.org/index.php?server=%s&amp;port=%s&amp;version=%s&amp;detail=";
        $link = sprintf($link, $ip, $port, $version);
        $play = '<a href="%s0">High</a> / <a href="%s1">Low</a>';
        $play = sprintf($play, $link, $link);
        return $play;
    } else {
        $play = '<div class="offline">Server Offline!</div>';
        return $play;
    }
}

// ads and such
function echoAd() {

}

function echoAnalytics() {

}

# end configuration, kick off the application
require_once($g_source_dir . '/main.php');
?>