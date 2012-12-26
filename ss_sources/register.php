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

function register2() {
    forceLogin('register');
//	error('this is register2');
    // preview is set, refer to echoPostForm()
    if (isset($_POST['preview'])) {
        echoPostForm();
        return;
    }
    // neither preview or post is set, probably hacking attempt
    // but let's say something nicer instead
    if (!isset($_POST['post'])) {
        error('Session expired, go back and try again');
        return;
    }
    // if we get here, post is set, verify the rest of the info

    // verify user input
    $requiredPosts = array('name', 'ip', 'port', 'version', 'message');
    foreach ($requiredPosts as $r) {
        if (!isset($_POST[$r])) {
            error("You must provide a $r.");
            echoPostForm();
            return;
        }
    }

    $name = trim($_POST['name']);
    $ip = trim($_POST['ip']);
    $port = trim($_POST['port']);
    $version = trim($_POST['version']);
    $message = trim($_POST['message']);
    $pic_url = trim($_POST['pic_url']);

    // if the info isn't valid, set up a preview instead
    if (!verifyInput($name, $ip, $port, $version, $message, $pic_url, isset($_POST['edit']))) {
        echoPostForm();
        return;
    }

    // if we make it here, then all the input is valid

    // connect to the db and set the edit and spons variables
    $edit = false;
    $spons = false;
    mysql_con();
    global $g_mysqli, $uid;
    $stmt = $g_mysqli->prepare('SELECT `sponsored` FROM `servers` WHERE `uid` = ? LIMIT 1') or debug($g_mysqli->error);
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    // bind result variables
    $stmt->bind_result($sponsored);
    if ($stmt->fetch()) {
        $edit = true;
        $spons = ($sponsored > 0);
    }
    $stmt->close();

    // enter into database

    // if it isn't a sponsored server, they can't have a picture
    if (!$spons)
        $pic_url = '';

    if ($edit) {
        $sql = 'UPDATE `servers` SET `name` = ?, `pic_url` = ?, `version` = ?, `info` = ? WHERE `uid` = ? LIMIT 1';
        $stmt = $g_mysqli->prepare($sql) or debug($g_mysqli->error);
        $stmt->bind_param("ssisi", $name, $pic_url, $version, $message, $uid);
        $success_msg = "Server $name succesfully updated.";
        $fail_msg = "Editing $name failed, did you actually change anything?";
    } else {
        // since we are adding a new server, need to make sure it isn't already added, user isn't banned etc

        // make sure it's not already scheduled to be added
        if (checkRows("SELECT `id` FROM `toadd` WHERE `ip` = ? OR `uid` = ?", 'si', $ip, $uid)) {
            error("This server has already been posted, but not yet approved, have some patience!.");
            return;
        }
        // make sure user and IP is not banned
        if (checkRows("SELECT `id` FROM `banned` WHERE `ip` = ? OR `uid` = ?", 'si', $ip, $uid)) {
            global $g_admin_contact;
            error("This server has been banned, contact $g_admin_contact on the forums for assistance.");
            return;
        }
        // we know another server hasn't been posted by this user, because we would be in edit
        // but this ip may have been posted by another user, check to make sure
        if (checkRows("SELECT `id` FROM `servers` WHERE `ip` = ?", 's', $ip)) {
            error("This server has already been posted, you may not post it again.");
            return;
        }

        global $g_allowed_key;
//die('$g_allowed_key: '.$g_allowed_key);
        $key = randString($g_allowed_key, 5, 10);
        $rs_name = randString($g_allowed_key);
        $rs_pass = randString($g_allowed_key);

        $verified = 1;
        if (!verifyIP($ip, &$resolved_ip, &$remote_ip)) {
            $verified = 0;
            global $thispage, $g_admin_contact;
            $verify_url = $thispage . "?action=verify&amp;server=$ip&amp;key=$key";
            $verify_msg = "<br />The server you posted, $ip, resolves to $resolved_ip, which does not match your ip, $remote_ip.\n<br />
				This means that you must verify that you own this IP by visiting this URL from the IP that you posted.<br />
				If you have a browser on the machine, simply visit the following URL:<br />
				<a href=\"$verify_url\">$verify_url</a><br />
				If you only have a command line, visit the URL with wget, curl, or an equivalent command to this:<br />
				<div class=\"codeheader\">Code:</div><div class=\"code\"><pre style=\"margin-top: 0; display: inline;\">wget -O- -q \"$verify_url\"</pre></div><br />
				The message you recieve will tell you if verification was successful.<br />
				If you have problems with this, PM $g_admin_contact on the forums.";
        }

        global $uname, $g_admin_contact, $g_checker_ip;
        // don't bother with pic_url, they can edit it if they are sponsored
        $sql = 'INSERT INTO `toadd` (`uid`, `uname`, `name`, `ip`, `port`, `version`, `time`, `info`, `ipaddress`, `rs_name`, `rs_pass`, `key`, `verified`) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $stmt = $g_mysqli->prepare($sql) or debug($g_mysqli->error);
        $stmt->bind_param("isssiiisssssi", $uid, $uname, $name, $ip, $port, $version, time(), $message, $_SERVER['REMOTE_ADDR'], $rs_name, $rs_pass, $key, $verified);
        $success_msg = "<strong class=\"largetext error\">Further Action Required, Read:</strong><br />New server $name succesfully entered, however it will not show up on the list until it has been verified and approved.<br /> Your server will now be checked by logging into it with the following credentials:\n<br />
Username: <strong class=\"highlight\">$rs_name</strong>\n<br />
Password: <strong class=\"highlight\">$rs_pass</strong>\n<br />
to make sure it is online, and if successful, it will be posted.  <br />
You must register this username and password for me on your server and allow it to be logged into from the IP $g_checker_ip<br />
The server will be deleted from the queue if not verified and logged into within 24 hours of posting.<br />" . $verify_msg;
        $fail_msg = 'Registration failed, PM ' . $g_admin_contact . ' on the forums to with details so he can fix it.';
    }

    // execute the query
    $stmt->execute();
    if ($stmt->affected_rows == 1) {
        echo $success_msg;
    } else {
        error($fail_msg);
    }

    $stmt->close();

    close_mysql();
}

function register() {
    forceLogin();
//	echo "this is register<br />\n";
    $edit = false;
    // then we are trying to edit a server
    // load the proper values from the database
    if (isset($_GET['edit'])) {
        mysql_con();
        global $g_mysqli, $uid;
        $stmt = $g_mysqli->prepare('SELECT `name`, `ip`, `port`, `version`, `info`, `pic_url`, `sponsored` FROM `servers` WHERE `uid` = ? LIMIT 1') or debug($g_mysqli->error);
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        // bind result variables
        $stmt->bind_result($name, $ip, $port, $version, $info, $pic_url, $sponsored);
        if (!$stmt->fetch()) {
            error('You have not posted a server, but you may register a new one instead.<br />');
            return;
        }
        $edit = true;
        $stmt->close();
        close_mysql();
        if ($sponsored == 0)
            unset($pic_url);
    }
    /*	if($edit)
            echo 'edit true';
        else
            echo 'edit false';
    */
    echoForm($name, $ip, $port, $version, $info, $pic_url, $edit);
}

// returns true if input is valid
// prints out a message and returns false otherwise
function verifyInput($name, $ip, $port, $version, $info, $pic_url, $edit) {

    global $g_allowed_url, $g_allowed_alpha, $g_allowed_dns, $g_versions;

    // validate name
    $namelen = strlen($name);
    if ($namelen > 25 || $namelen < 1) {
        error("The name cannot exceed 25 characters, and must be at least one.<br />");
        return false;
    }
    if (!isAllowed($name, $g_allowed_alpha)) {
        error("The name can only contain the following characters:<br />$g_allowed_alpha<br /><br />");
        return false;
    }

    // only bother with ip and port if we are not editing, don't care about permissions because it will
    // only be entered into the database if they are actually posting a new server and not editing
    if (!$edit) {
        // validate ip
        if (strlen($ip) < 6) {
            error("The ip must be at least 6 characters.<br />");
            return false;
        }
        if (!isAllowed($ip, $g_allowed_dns)) {
            error("The ip can only contain the following characters:<br />$g_allowed_dns<br /><br />");
            return false;
        }
        if ($ip[0] == '.' || $ip[strlen($ip) - 1] == '.') {
            error("The ip cannot start or begin with a period.<br />");
            return false;
        }

        //validate port
        if ($port > 65535 || $port < 1) {
            error("Please enter a valid port number between 1 and 65534.<br />");
            return false;
        }

        // now that the ip and port are validated, check to make sure the server is online
        $fp = @fsockopen($ip, $port, $errno, $errstr, 4);
        if (!$fp) {
            error("The server " . $name . " is offline, it must be online before you can register it here.");
            return false;
        }
        fclose($fp);
    }

    // validate info
    $info_len = strlen($info);
    if ($info_len > 10000) {
        error("The info cannot exceed 10,000 characters. You currently have $info_len characters.");
        return false;
    }

    // validate version
    if (!in_array($version, $g_versions)) {
        // they must be hackers, since the select box only contains values from $g_versions
        error("The version must be one of the supported versions.<br />");
        return false;
    }

    // validate picture, again don't care about permissions because it will
    // only be entered into the database if they are sponsored
    $piclen = strlen($pic_url);
    if ($piclen > 0) {
        $ext = strtolower(substr($pic_url, $piclen - 3, $piclen));
        if ($ext == 'gif') {
            error("The picture cannot be of type gif.<br />");
            return false;
        }
        if (!isAllowed($pic_url, $g_allowed_url)) {
            error("The picture can only contain the following characters:<br />$g_allowed_url<br /><br />");
            return false;
        }
    }

    // we have passed through all the trials, return true
    return true;
}

function echoPostForm($edit = null) {
    if ($edit == null)
        $edit = isset($_POST['edit']);
    else
        $edit = false;
    echoForm($_POST['name'], $_POST['ip'], $_POST['port'], $_POST['version'], $_POST['message'], $_POST['pic_url'], $edit);
}

function echoForm($name, $ip, $port, $version, $message, $pic_url, $edit = false) {
    global $g_versions, $g_img_dir, $g_default_port;
    if (isset($name))
        censor($name);
    $preview_message = $message;
    if (isset($preview_message)) {
        // Do all bulletin board code tags, with smileys.
        $preview_message = bb2html($preview_message, true);
    }
    ?>
<script type="text/javascript" src="script.js"></script>
<div class="post"<?php echo (isset($preview_message) ? '' : ' style="display: none;"'); ?>>
    <?php echo $preview_message; ?>
</div>

<form action="<?php echo actionURL('register2'); ?>" method="post" id="postmodify"
      onsubmit="submitonce(this);saveEntities();" enctype="multipart/form-data">
<fieldset>
<table class="other" summary="Register your Server">
    <caption>
        Register Server
    </caption>

    <thead>
    <tr>
        <td colspan="2">
            <div class="title">Rules</div>

            <ul style="margin: 0px 5px 5px 25px;">
                <li>The server must be <b>online</b> to add it to the status list.</li>

                <li>Selling admin or mod spots on your server is <b>against the rules</b> and will result in a ban,
                    here, and on the
                    forums.
                </li>
            </ul>
        </td>
    </tr>
    </thead>

    <tbody>
    <tr>
        <td>Name</td>

        <td><input type="text" name="name" value="<?php echo $name; ?>"/></td>
    </tr>
        <?php
        if (isset($pic_url)) {
            ?>
        <tr>
            <td>Picture</td>

            <td><input type="text" name="pic_url" value="<?php echo $pic_url; ?>"/></td>
        </tr>
            <?php
        }
        if (!$edit) {
            ?>
        <tr class="odd">
            <td>IP</td>

            <td><input type="text" name="ip" value="<?php echo $ip; ?>"/></td>
        </tr>

        <tr>
            <td>Port</td>

            <td><input type="text" name="port" value="<?php echo (isset($port) ? $port : $g_default_port); ?>"/></td>
        </tr>
            <?php
        }
        ?>

    <tr class="odd">
        <td>Version</td>

        <td><select name="version">
            <?php
            $v_template = "<option>%s</option>\n";
            if (isset($version))
                printf($v_template, $version);
            foreach ($g_versions as $v)
                printf($v_template, $v);
            ?>
        </select></td>
    </tr>

    <tr>
        <td colspan="2">Info</td>
    </tr>
    </tbody>
</table>

<table border="0" width="100%" cellspacing="1" cellpadding="3" class="smf_edit">
<tr>
<td align="right"></td>

<td valign="middle">
<a href="javascript:void(0);" onclick=
        "surroundText('[b]', '[/b]', document.forms.postmodify.message); return false;"><img
        onmouseover="bbc_highlight(this, true);"
        onmouseout="if (window.bbc_highlight) bbc_highlight(this, false);" src=
        "<?php echo $g_img_dir; ?>/bbc/bold.gif" width="23" height="22" alt="Bold" title=
                "Bold" style=
                "background-image: url(<?php echo $g_img_dir; ?>/bbc/bbc_bg.gif); margin: 1px 2px 1px 1px;"/></a><a
        href="javascript:void(0);"
        onclick="surroundText('[i]', '[/i]', document.forms.postmodify.message); return false;"><img onmouseover=
                                                                                                             "bbc_highlight(this, true);"
                                                                                                     onmouseout="if (window.bbc_highlight) bbc_highlight(this, false);"
                                                                                                     src=
                                                                                                             "<?php echo $g_img_dir; ?>/bbc/italicize.gif"
                                                                                                     width="23"
                                                                                                     height="22"
                                                                                                     alt=
                                                                                                             "Italicized"
                                                                                                     title="Italicized"
                                                                                                     style=
                                                                                                             "background-image: url(<?php echo $g_img_dir; ?>/bbc/bbc_bg.gif); margin: 1px 2px 1px 1px;"/></a><a
        href="javascript:void(0);"
        onclick="surroundText('[u]', '[/u]', document.forms.postmodify.message); return false;"><img onmouseover=
                                                                                                             "bbc_highlight(this, true);"
                                                                                                     onmouseout="if (window.bbc_highlight) bbc_highlight(this, false);"
                                                                                                     src=
                                                                                                             "<?php echo $g_img_dir; ?>/bbc/underline.gif"
                                                                                                     width="23"
                                                                                                     height="22"
                                                                                                     alt=
                                                                                                             "Underline"
                                                                                                     title="Underline"
                                                                                                     style=
                                                                                                             "background-image: url(<?php echo $g_img_dir; ?>/bbc/bbc_bg.gif); margin: 1px 2px 1px 1px;"/></a><a
        href="javascript:void(0);"
        onclick="surroundText('[s]', '[/s]', document.forms.postmodify.message); return false;"><img onmouseover=
                                                                                                             "bbc_highlight(this, true);"
                                                                                                     onmouseout="if (window.bbc_highlight) bbc_highlight(this, false);"
                                                                                                     src=
                                                                                                             "<?php echo $g_img_dir; ?>/bbc/strike.gif"
                                                                                                     width="23"
                                                                                                     height="22"
                                                                                                     alt=
                                                                                                             "Strikethrough"
                                                                                                     title="Strikethrough"
                                                                                                     style=
                                                                                                             "background-image: url(<?php echo $g_img_dir; ?>/bbc/bbc_bg.gif); margin: 1px 2px 1px 1px;"/></a><img
        src="<?php echo $g_img_dir; ?>/bbc/divider.gif"
        alt="|" style="margin: 0 3px 0 3px;"/><a href="javascript:void(0);" onclick=
        "surroundText('[shadow=red,left]', '[/shadow]', document.forms.postmodify.message); return false;"><img
        onmouseover=
                "bbc_highlight(this, true);" onmouseout="if (window.bbc_highlight) bbc_highlight(this, false);" src=
                "<?php echo $g_img_dir; ?>/bbc/shadow.gif" width="23" height="22" alt="Shadow"
        title="Shadow" style=
        "background-image: url(<?php echo $g_img_dir; ?>/bbc/bbc_bg.gif); margin: 1px 2px 1px 1px;"/></a><img
        src="<?php echo $g_img_dir; ?>/bbc/divider.gif"
        alt="|" style="margin: 0 3px 0 3px;"/><a href="javascript:void(0);" onclick=
        "surroundText('[pre]', '[/pre]', document.forms.postmodify.message); return false;"><img onmouseover=
                                                                                                         "bbc_highlight(this, true);"
                                                                                                 onmouseout="if (window.bbc_highlight) bbc_highlight(this, false);"
                                                                                                 src=
                                                                                                         "<?php echo $g_img_dir; ?>/bbc/pre.gif"
                                                                                                 width="23"
                                                                                                 height="22" alt=
        "Preformatted Text" title="Preformatted Text" style=
                                                                                                         "background-image: url(<?php echo $g_img_dir; ?>/bbc/bbc_bg.gif); margin: 1px 2px 1px 1px;"/></a><a
        href="javascript:void(0);"
        onclick="surroundText('[left]', '[/left]', document.forms.postmodify.message); return false;"><img
        onmouseover=
                "bbc_highlight(this, true);" onmouseout="if (window.bbc_highlight) bbc_highlight(this, false);" src=
                "<?php echo $g_img_dir; ?>/bbc/left.gif" width="23" height="22" alt="Left Align"
        title="Left Align" style=
        "background-image: url(<?php echo $g_img_dir; ?>/bbc/bbc_bg.gif); margin: 1px 2px 1px 1px;"/></a><a
        href="javascript:void(0);"
        onclick="surroundText('[center]', '[/center]', document.forms.postmodify.message); return false;"><img
        onmouseover=
                "bbc_highlight(this, true);" onmouseout="if (window.bbc_highlight) bbc_highlight(this, false);" src=
                "<?php echo $g_img_dir; ?>/bbc/center.gif" width="23" height="22" alt="Centered"
        title="Centered" style=
        "background-image: url(<?php echo $g_img_dir; ?>/bbc/bbc_bg.gif); margin: 1px 2px 1px 1px;"/></a><a
        href="javascript:void(0);"
        onclick="surroundText('[right]', '[/right]', document.forms.postmodify.message); return false;"><img
        onmouseover=
                "bbc_highlight(this, true);" onmouseout="if (window.bbc_highlight) bbc_highlight(this, false);" src=
                "<?php echo $g_img_dir; ?>/bbc/right.gif" width="23" height="22" alt=
                "Right Align" title="Right Align" style=
                "background-image: url(<?php echo $g_img_dir; ?>/bbc/bbc_bg.gif); margin: 1px 2px 1px 1px;"/></a><img
        src="<?php echo $g_img_dir; ?>/bbc/divider.gif"
        alt="|" style="margin: 0 3px 0 3px;"/><a href="javascript:void(0);" onclick=
        "replaceText('[hr]', document.forms.postmodify.message); return false;"><img
        onmouseover="bbc_highlight(this, true);"
        onmouseout="if (window.bbc_highlight) bbc_highlight(this, false);" src=
        "<?php echo $g_img_dir; ?>/bbc/hr.gif" width="23" height="22" alt=
                "Horizontal Rule" title="Horizontal Rule" style=
                "background-image: url(<?php echo $g_img_dir; ?>/bbc/bbc_bg.gif); margin: 1px 2px 1px 1px;"/></a><img
        src="<?php echo $g_img_dir; ?>/bbc/divider.gif"
        alt="|" style="margin: 0 3px 0 3px;"/><a href="javascript:void(0);" onclick=
        "surroundText('[size=10pt]', '[/size]', document.forms.postmodify.message); return false;"><img onmouseover=
                                                                                                                "bbc_highlight(this, true);"
                                                                                                        onmouseout="if (window.bbc_highlight) bbc_highlight(this, false);"
                                                                                                        src=
                                                                                                                "<?php echo $g_img_dir; ?>/bbc/size.gif"
                                                                                                        width="23"
                                                                                                        height="22"
                                                                                                        alt="Font Size"
                                                                                                        title="Font Size"
                                                                                                        style=
                                                                                                                "background-image: url(<?php echo $g_img_dir; ?>/bbc/bbc_bg.gif); margin: 1px 2px 1px 1px;"/></a><a
        href="javascript:void(0);"
        onclick="surroundText('[font=Verdana]', '[/font]', document.forms.postmodify.message); return false;"><img
        onmouseover=
                "bbc_highlight(this, true);" onmouseout="if (window.bbc_highlight) bbc_highlight(this, false);" src=
                "<?php echo $g_img_dir; ?>/bbc/face.gif" width="23" height="22" alt="Font Face"
        title="Font Face" style=
        "background-image: url(<?php echo $g_img_dir; ?>/bbc/bbc_bg.gif); margin: 1px 2px 1px 1px;"/></a>
<select onchange=
                "surroundText('[color=' + this.options[this.selectedIndex].value.toLowerCase() + ']', '[/color]', document.forms.postmodify.message); this.selectedIndex = 0; document.forms.postmodify.message.focus(document.forms.postmodify.message.caretPos);"
        style="margin-bottom: 1ex;">
    <option value="" selected="selected">
        Change Color
    </option>
    <option value="Black">
        Black
    </option>
    <option value="Red">
        Red
    </option>
    <option value="Yellow">
        Yellow
    </option>
    <option value="Pink">
        Pink
    </option>
    <option value="Green">
        Green
    </option>
    <option value="Orange">
        Orange
    </option>
    <option value="Purple">
        Purple
    </option>
    <option value="Blue">
        Blue
    </option>
    <option value="Beige">
        Beige
    </option>
    <option value="Brown">
        Brown
    </option>
    <option value="Teal">
        Teal
    </option>
    <option value="Navy">
        Navy
    </option>
    <option value="Maroon">
        Maroon
    </option>
    <option value="LimeGreen">
        Lime Green
    </option>
</select><br/>
<a href="javascript:void(0);" onclick=
        "surroundText('[img]', '[/img]', document.forms.postmodify.message); return false;"><img onmouseover=
                                                                                                         "bbc_highlight(this, true);"
                                                                                                 onmouseout="if (window.bbc_highlight) bbc_highlight(this, false);"
                                                                                                 src=
                                                                                                         "<?php echo $g_img_dir; ?>/bbc/img.gif"
                                                                                                 width="23"
                                                                                                 height="22"
                                                                                                 alt="Insert Image"
                                                                                                 title="Insert Image"
                                                                                                 style=
                                                                                                         "background-image: url(<?php echo $g_img_dir; ?>/bbc/bbc_bg.gif); margin: 1px 2px 1px 1px;"/></a><a
        href="javascript:void(0);"
        onclick="surroundText('[url]', '[/url]', document.forms.postmodify.message); return false;"><img
        onmouseover=
                "bbc_highlight(this, true);" onmouseout="if (window.bbc_highlight) bbc_highlight(this, false);" src=
                "<?php echo $g_img_dir; ?>/bbc/url.gif" width="23" height="22" alt=
                "Insert Hyperlink" title="Insert Hyperlink" style=
                "background-image: url(<?php echo $g_img_dir; ?>/bbc/bbc_bg.gif); margin: 1px 2px 1px 1px;"/></a><a
        href="javascript:void(0);"
        onclick="surroundText('[email]', '[/email]', document.forms.postmodify.message); return false;"><img
        onmouseover=
                "bbc_highlight(this, true);" onmouseout="if (window.bbc_highlight) bbc_highlight(this, false);" src=
                "<?php echo $g_img_dir; ?>/bbc/email.gif" width="23" height="22" alt=
                "Insert Email" title="Insert Email" style=
                "background-image: url(<?php echo $g_img_dir; ?>/bbc/bbc_bg.gif); margin: 1px 2px 1px 1px;"/></a><a
        href="javascript:void(0);"
        onclick="surroundText('[ftp]', '[/ftp]', document.forms.postmodify.message); return false;"><img
        onmouseover=
                "bbc_highlight(this, true);" onmouseout="if (window.bbc_highlight) bbc_highlight(this, false);" src=
                "<?php echo $g_img_dir; ?>/bbc/ftp.gif" width="23" height="22" alt=
                "Insert FTP Link" title="Insert FTP Link" style=
                "background-image: url(<?php echo $g_img_dir; ?>/bbc/bbc_bg.gif); margin: 1px 2px 1px 1px;"/></a><img
        src="<?php echo $g_img_dir; ?>/bbc/divider.gif"
        alt="|" style="margin: 0 3px 0 3px;"/><a href="javascript:void(0);" onclick=
        "surroundText('[table]', '[/table]', document.forms.postmodify.message); return false;"><img onmouseover=
                                                                                                             "bbc_highlight(this, true);"
                                                                                                     onmouseout="if (window.bbc_highlight) bbc_highlight(this, false);"
                                                                                                     src=
                                                                                                             "<?php echo $g_img_dir; ?>/bbc/table.gif"
                                                                                                     width="23"
                                                                                                     height="22"
                                                                                                     alt=
                                                                                                             "Insert Table"
                                                                                                     title="Insert Table"
                                                                                                     style=
                                                                                                             "background-image: url(<?php echo $g_img_dir; ?>/bbc/bbc_bg.gif); margin: 1px 2px 1px 1px;"/></a><a
        href="javascript:void(0);"
        onclick="surroundText('[tr]', '[/tr]', document.forms.postmodify.message); return false;"><img onmouseover=
                                                                                                               "bbc_highlight(this, true);"
                                                                                                       onmouseout="if (window.bbc_highlight) bbc_highlight(this, false);"
                                                                                                       src=
                                                                                                               "<?php echo $g_img_dir; ?>/bbc/tr.gif"
                                                                                                       width="23"
                                                                                                       height="22"
                                                                                                       alt=
                                                                                                               "Insert Table Row"
                                                                                                       title="Insert Table Row"
                                                                                                       style=
                                                                                                               "background-image: url(<?php echo $g_img_dir; ?>/bbc/bbc_bg.gif); margin: 1px 2px 1px 1px;"/></a><a
        href="javascript:void(0);"
        onclick="surroundText('[td]', '[/td]', document.forms.postmodify.message); return false;"><img onmouseover=
                                                                                                               "bbc_highlight(this, true);"
                                                                                                       onmouseout="if (window.bbc_highlight) bbc_highlight(this, false);"
                                                                                                       src=
                                                                                                               "<?php echo $g_img_dir; ?>/bbc/td.gif"
                                                                                                       width="23"
                                                                                                       height="22"
                                                                                                       alt=
                                                                                                               "Insert Table Column"
                                                                                                       title="Insert Table Column"
                                                                                                       style=
                                                                                                               "background-image: url(<?php echo $g_img_dir; ?>/bbc/bbc_bg.gif); margin: 1px 2px 1px 1px;"/></a><img
        src="<?php echo $g_img_dir; ?>/bbc/divider.gif"
        alt="|" style="margin: 0 3px 0 3px;"/><a href="javascript:void(0);" onclick=
        "surroundText('[sup]', '[/sup]', document.forms.postmodify.message); return false;"><img onmouseover=
                                                                                                         "bbc_highlight(this, true);"
                                                                                                 onmouseout="if (window.bbc_highlight) bbc_highlight(this, false);"
                                                                                                 src=
                                                                                                         "<?php echo $g_img_dir; ?>/bbc/sup.gif"
                                                                                                 width="23"
                                                                                                 height="22"
                                                                                                 alt="Superscript"
                                                                                                 title="Superscript"
                                                                                                 style=
                                                                                                         "background-image: url(<?php echo $g_img_dir; ?>/bbc/bbc_bg.gif); margin: 1px 2px 1px 1px;"/></a><a
        href="javascript:void(0);"
        onclick="surroundText('[sub]', '[/sub]', document.forms.postmodify.message); return false;"><img
        onmouseover=
                "bbc_highlight(this, true);" onmouseout="if (window.bbc_highlight) bbc_highlight(this, false);" src=
                "<?php echo $g_img_dir; ?>/bbc/sub.gif" width="23" height="22" alt="Subscript"
        title="Subscript" style=
        "background-image: url(<?php echo $g_img_dir; ?>/bbc/bbc_bg.gif); margin: 1px 2px 1px 1px;"/></a><a
        href="javascript:void(0);"
        onclick="surroundText('[tt]', '[/tt]', document.forms.postmodify.message); return false;"><img onmouseover=
                                                                                                               "bbc_highlight(this, true);"
                                                                                                       onmouseout="if (window.bbc_highlight) bbc_highlight(this, false);"
                                                                                                       src=
                                                                                                               "<?php echo $g_img_dir; ?>/bbc/tele.gif"
                                                                                                       width="23"
                                                                                                       height="22"
                                                                                                       alt="Teletype"
                                                                                                       title="Teletype"
                                                                                                       style=
                                                                                                               "background-image: url(<?php echo $g_img_dir; ?>/bbc/bbc_bg.gif); margin: 1px 2px 1px 1px;"/></a><img
        src="<?php echo $g_img_dir; ?>/bbc/divider.gif"
        alt="|" style="margin: 0 3px 0 3px;"/><a href="javascript:void(0);" onclick=
        "surroundText('[code]', '[/code]', document.forms.postmodify.message); return false;"><img onmouseover=
                                                                                                           "bbc_highlight(this, true);"
                                                                                                   onmouseout="if (window.bbc_highlight) bbc_highlight(this, false);"
                                                                                                   src=
                                                                                                           "<?php echo $g_img_dir; ?>/bbc/code.gif"
                                                                                                   width="23"
                                                                                                   height="22"
                                                                                                   alt="Insert Code"
                                                                                                   title="Insert Code"
                                                                                                   style=
                                                                                                           "background-image: url(<?php echo $g_img_dir; ?>/bbc/bbc_bg.gif); margin: 1px 2px 1px 1px;"/></a><a
        href="javascript:void(0);"
        onclick="surroundText('[quote]', '[/quote]', document.forms.postmodify.message); return false;"><img
        onmouseover=
                "bbc_highlight(this, true);" onmouseout="if (window.bbc_highlight) bbc_highlight(this, false);" src=
                "<?php echo $g_img_dir; ?>/bbc/quote.gif" width="23" height="22" alt=
                "Insert Quote" title="Insert Quote" style=
                "background-image: url(<?php echo $g_img_dir; ?>/bbc/bbc_bg.gif); margin: 1px 2px 1px 1px;"/></a><img
        src="<?php echo $g_img_dir; ?>/bbc/divider.gif"
        alt="|" style="margin: 0 3px 0 3px;"/><a href="javascript:void(0);" onclick=
        "surroundText('[list]\n[li]', '[/li]\n[li][/li]\n[/list]', document.forms.postmodify.message); return false;"><img
        onmouseover="bbc_highlight(this, true);"
        onmouseout="if (window.bbc_highlight) bbc_highlight(this, false);" src=
        "<?php echo $g_img_dir; ?>/bbc/list.gif" width="23" height="22" alt="Insert List"
        title="Insert List" style=
        "background-image: url(<?php echo $g_img_dir; ?>/bbc/bbc_bg.gif); margin: 1px 2px 1px 1px;"/></a>
</td>
</tr>

<tr>
    <td align="right"></td>

    <td valign="middle"><a href="javascript:void(0);" onclick=
            "replaceText(' :)', document.forms.postmodify.message); return false;"><img src=
                                                                                                "<?php echo $g_img_dir; ?>/bbc/smileys/smile.gif"
                                                                                        alt="Smiley"
                                                                                        title="Smiley"/></a> <a href=
                                                                                                                        "javascript:void(0);"
                                                                                                                onclick="replaceText(' ;)', document.forms.postmodify.message); return false;"><img
            src=
                    "<?php echo $g_img_dir; ?>/bbc/smileys/wink.gif" alt="Wink" title="Wink"/></a> <a href=
                                                                                                              "javascript:void(0);"
                                                                                                      onclick="replaceText(' :D', document.forms.postmodify.message); return false;"><img
            src=
                    "<?php echo $g_img_dir; ?>/bbc/smileys/biggrin.gif" alt="Big Grin" title="Big Grin"/></a> <a href=
                                                                                                                         "javascript:void(0);"
                                                                                                                 onclick="replaceText(' :mad:', document.forms.postmodify.message); return false;"><img
            src=
                    "<?php echo $g_img_dir; ?>/bbc/smileys/mad.gif" alt="Mad" title="Mad"/></a> <a
            href="javascript:void(0);"
            onclick="replaceText(' :(', document.forms.postmodify.message); return false;"><img src=
                                                                                                        "<?php echo $g_img_dir; ?>/bbc/smileys/frown.gif"
                                                                                                alt="Sad" title="Sad"/></a>
        <a href=
                   "javascript:void(0);"
           onclick="replaceText(' :eek:', document.forms.postmodify.message); return false;"><img src=
                                                                                                          "<?php echo $g_img_dir; ?>/bbc/smileys/eek.gif"
                                                                                                  alt="Shocked"
                                                                                                  title="Shocked"/></a>
        <a href=
                   "javascript:void(0);"
           onclick="replaceText(' :cool:', document.forms.postmodify.message); return false;"><img src=
                                                                                                           "<?php echo $g_img_dir; ?>/bbc/smileys/cool.gif"
                                                                                                   alt="Cool"
                                                                                                   title="Cool"/></a> <a
                href=
                        "javascript:void(0);"
                onclick="replaceText(' :rolleyes:', document.forms.postmodify.message); return false;"><img src=
                                                                                                                    "<?php echo $g_img_dir; ?>/bbc/smileys/rolleyes.gif"
                                                                                                            alt="Roll Eyes"
                                                                                                            title="Roll Eyes"/></a>
        <a href=
                   "javascript:void(0);" onclick="replaceText(' :P', document.forms.postmodify.message); return false;"><img
                src=
                        "<?php echo $g_img_dir; ?>/bbc/smileys/tongue.gif" alt="Tongue" title="Tongue"/></a> <a href=
                                                                                                                        "javascript:void(0);"
                                                                                                                onclick="replaceText(' :o', document.forms.postmodify.message); return false;"><img
                src=
                        "<?php echo $g_img_dir; ?>/bbc/smileys/redface.gif" alt="Embarrassed" title="Embarrassed"/></a>
        <a href=
                   "javascript:void(0);"
           onclick="replaceText(' :confused:', document.forms.postmodify.message); return false;"><img src=
                                                                                                               "<?php echo $g_img_dir; ?>/bbc/smileys/confused.gif"
                                                                                                       alt="Confused"
                                                                                                       title="Confused"/></a>
        <a href=
                   "javascript:void(0);" onclick="replaceText(' :|', document.forms.postmodify.message); return false;"><img
                src=
                        "<?php echo $g_img_dir; ?>/bbc/smileys/shifty.gif" alt="shifty" title="shifty"/></a> <a href=
                                                                                                                        "javascript:void(0);"
                                                                                                                onclick="replaceText(' ;D', document.forms.postmodify.message); return false;"><img
                src=
                        "<?php echo $g_img_dir; ?>/bbc/smileys/winkgrin.gif" alt="winksmile" title="winksmile"/></a>
    </td>
</tr>

<tr>
    <td valign="top" align="right"></td>

    <td>
        <textarea class="editor" name="message" rows="12" cols="60" onselect="storeCaret(this);"
                  onclick="storeCaret(this);" onkeyup=
                "storeCaret(this);" onchange="storeCaret(this);" tabindex="2">
            <?php echo $message; ?>
        </textarea></td>
</tr>

<tr>
    <td align="center" colspan="2">
        <?php
        if ($edit) {
            ?>
            <input type="hidden" name="edit" value="1"/>
            <input type="hidden" name="ip" value="<?php echo $ip; ?>"/>
            <input type="hidden" name="port" value="<?php echo (isset($port) ? $port : '43594'); ?>"/>
            <?php
        }
        ?>
        <input type="submit" name="post" value="<?php echo $edit ? 'Edit' : 'Register'; ?> Server" tabindex="3"
               onclick="return submitThisOnce(this);" accesskey="s"/> <input type=
                                                                                     "submit" name="preview"
                                                                             value="Preview Info" tabindex="4"
                                                                             onclick="return event.ctrlKey || previewPost();"
                                                                             accesskey="p"/></td>
</tr>

<tr>
    <td colspan="2"></td>
</tr>
</table>
</fieldset>
</form>
<?php
}

?>