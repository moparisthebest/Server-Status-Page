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

// echos the header
function echoHeader($action) {
    global $thispage, $g_img_dir, $g_theme, $g_extra_links;
    /*echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";*/
    ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
        "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8"/>
    <title><?php echo $g_theme['title']; ?></title>
    <link rel="stylesheet" type="text/css" href="<?php echo $g_img_dir; ?>/default.css"/>
</head>
<body>
<div id="wrapper">
    <div id="header">
        <div id="lefthead"><a href="<?php echo $g_theme['left_link']; ?>"><img src="<?php echo $g_img_dir; ?>/left.png"
                                                                               alt="<?php echo $g_theme['left_link_alt']; ?>"/></a>
        </div>
        <div id="righthead"><a href="<?php echo $g_theme['right_link']; ?>"><img
                src="<?php echo $g_img_dir; ?>/right.png" alt="<?php echo $g_theme['right_link_alt']; ?>"/></a></div>
        <div id="banner"><a href="<?php echo $g_theme['center_link']; ?>"><img
                src="<?php echo $g_img_dir; ?>/center.png" alt="<?php echo $g_theme['center_link_alt']; ?>"/></a></div>

        <ul id="nav">
            <li><a href="?"<?php if ($action == 'display' && !isset($_GET['offline'])) echo ' class="on"'; ?>>Online
                Servers</a></li>
            <li><a href="?offline"<?php if ($action == 'display' && isset($_GET['offline'])) echo ' class="on"'; ?>>Offline
                Servers</a></li>
            <li>
                <a href="?sort=vote&amp;desc<?php if (isset($_GET['offline'])) echo '&amp;offline'; ?>"<?php if ($action == 'display' && isset($_GET['sort']) && $_GET['sort'] == 'vote' && isset($_GET['desc'])) echo ' class="on"'; ?>>Most
                    Popular</a></li>
            <li><a href="?action=random<?php if (isset($_GET['offline'])) echo '&amp;offline'; ?>">Random Server</a>
            </li>
            <li>
                <a href="?action=register"<?php if ($action == 'register' && !isset($_GET['edit'])) echo ' class="on"'; ?>>Register
                    Server</a></li>
            <li>
                <a href="?action=register&amp;edit"<?php if ($action == 'register' && isset($_GET['edit'])) echo ' class="on"'; ?>>Edit
                    my Server</a></li>
            <li><a href="?action=search"<?php if (strpos($action, 'search') !== false) echo ' class="on"'; ?>>Search</a></li>
            <?php
            if(is_array($g_extra_links))
                foreach($g_extra_links as $text => $link)
                    echo "<li><a href=\"$link\">$text</a></li>";
            ?>
            <li><?php echoLogoutLink($thispage . '?' . $_SERVER['QUERY_STRING']); ?></li>
        </ul>
    </div>

    <div id="leftcolumn">
        <?php echoAdIfExists(); ?>
    </div>
    <div id="centercolumn">
        <?php echo $g_theme['header']; ?>
    <?php
}

// echos the footer
function echoFooter($uri) {
    global $g_img_dir, $g_ss_version;
    ?>
  </div>
  <div id="rightcolumn">
      <?php echoAdIfExists(); ?>
  </div>
    <div id="footer">
        <p>
            <a href="http://validator.w3.org/check?uri=<?php echo $uri; ?>"><img
                    src="<?php echo $g_img_dir; ?>/valid-xhtml10-blue.png"
                    alt="Valid XHTML 1.0 Strict" height="31" width="88"/></a>
            <a style="color: black;" class="black" href="https://github.com/moparisthebest/Server-Status-Page">ServerStatus <?php echo $g_ss_version; ?></a>
            |
            <a style="color: black;" class="black" href="http://www.gnu.org/licenses/agpl-3.0.html">Copyright &copy;
                2009-2012</a>,
            <a style="color: black;" class="black" href="http://www.moparscape.org/smf/">MoparScape.org</a> |
            <a style="color: black;" class="black" href="?action=zip">Download</a>
            <a href="http://jigsaw.w3.org/css-validator/validator?uri=<?php echo $uri; ?>"><img
                    src="<?php echo $g_img_dir; ?>/vcss-blue.gif"
                    alt="Valid CSS 2.1!" height="31" width="88"/></a>
        </p>
    </div>
</div>
<?php echoAnalyticsIfExists(); ?>
</body>
</html>
<?php
}


?>