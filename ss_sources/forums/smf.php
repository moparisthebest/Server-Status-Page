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

# globals that must be defined for this to function
# global $path_to_smf, $smf_admin_groups, $g_forum_url;

function doUserSetup(){
    global $user_info, $path_to_smf, $smf_admin_groups;
    require_once($path_to_smf.'/SSI.php');
    $user = ssi_welcome('array');

    // vars from SMF that we use, for easy compatibility for future versions
    global $groups, $time_format, $time_offset, $is_admin, $is_guest, $uname, $uid;
    $groups = $user_info['groups'];
    $time_format = $user_info['time_format'];
    # this is the number of seconds to add to time()
    $time_offset = $user_info['time_offset']*3600;
    $is_admin = $user['is_admin'];
    foreach ($smf_admin_groups as $admin_group)
        if(in_array($admin_group, $groups))
            $is_admin = true;
    $is_guest = $user['is_guest'];
    $uname = $user['name'];
    $uid = $user['id'];
}

function &censor(&$text){
    global $path_to_smf;
    require_once($path_to_smf.'/Sources/Subs-Post.php');
    censorText($text);
}

function bb2html($bb_code, $previewing = false){
    $bb_code = html_special($bb_code);

    global $path_to_smf;
    require_once($path_to_smf.'/Sources/Subs-Post.php');

    preparsecode($bb_code, $previewing);
    censorText($bb_code);
    $bb_code = parse_bbc($bb_code);

    return $bb_code;
}

function echoLogin($returnto){
    ssi_login($returnto);
}

function echoLogoutLink($returnto){
    ssi_logout($returnto);
}

function urlForUid($uid){
    global $g_forum_url;
    return $g_forum_url.'?action=profile;u='.$uid;
}
?>