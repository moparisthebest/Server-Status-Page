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

function display() {
    //echo 'this is display';
    $online = isset($_GET['offline']) ? 0 : 1;

    display_table($online, "`online` = '$online' AND `sponsored` = '0'");
}

function display_table($online, $where, $num_servers = null) {

    $num_per_page = 30;

    global $g_headers;
    $g_headers = array(
        'name' => 'Server Name',
        'version' => 'Client Version',
        'uname' => 'Owner',
        'uptime' => 'Uptime',
        'time' => 'Since',
        'vote' => 'Votes',
    );

    $start = isset($_GET['start']) ? $_GET['start'] : 0;

    mysql_con();
    global $g_mysqli;
    $start = $g_mysqli->real_escape_string($start);
    //$start = mysqli_real_escape_string($g_mysqli, $start);
    //if($_SERVER['REMOTE_ADDR'] == "24.172.204.242") echo "start is: $start";
    if (isset($_GET['sort']) && isset($g_headers[$_GET['sort']])) {
        $order_by = 'ORDER BY `' . $g_mysqli->real_escape_string($_GET['sort']) . '` ' . (isset($_GET['desc']) ? 'DESC' : 'ASC');
    } else {
        //default sort
//        $_GET['sort'] = 'uptime';
//        $_GET['desc'] = '';
        $order_by = 'ORDER BY `uptime` DESC, `time` ASC';
    }

    $order_by .= " LIMIT $start, $num_per_page";

    if ($start == 0 && $online == 1 && !isset($_GET['sort']))
        echoTable('Spons', "`sponsored` != '0'", "ORDER BY `sponsored` DESC, RAND() LIMIT 10");

    echoTable('Other', $where, $order_by, $online, $start, $num_per_page, $num_servers);
    close_mysql();
}

function getPageIndex($where, $start, $num_per_page, $num_servers, $online) {
    if ($num_servers == null) {
        global $g_mysqli;
        $stmt = $g_mysqli->prepare("SELECT COUNT(*) FROM `servers` WHERE " . $where) or debug($g_mysqli->error);
        $stmt->execute();
        // bind result variables
        $stmt->bind_result($num_servers);
        $stmt->fetch();
        $stmt->close();
    }
    //echo (sprintf('$num_servers: %s', $num_servers));
    // if we don't have enough for pages, just forget it
    if ($num_servers <= $num_per_page)
        return null;
    else
        return ss_constructPageIndex($_SERVER['PHP_SELF'], &$start, $num_servers, $num_per_page, $online);
}

function echoTable($class, $where, $order_by, $online = 1, $start = 0, $num_per_page = 30, $num_servers = null) {
    global $g_mysqli;
    //echo "SELECT `name`, `pic_url`, `uid`, `uname`, `online`, `ip`, `port`, `version`, `uptime`, `time`, `vote` FROM `servers` WHERE ".$where.' '.$order_by;
    $stmt = $g_mysqli->prepare("SELECT `name`, `pic_url`, `uid`, `uname`, `online`, `ip`, `port`, `version`, `uptime`, `time`, `vote` FROM `servers` WHERE " . $where . ' ' . $order_by) or debug($g_mysqli->error);
    $stmt->execute();
    // bind result variables
    $stmt->bind_result($name, $pic_url, $uid, $uname, $online, $ip, $port, $version, $uptime, $time, $votes);

    // only echo table if there are results
    if ($stmt->fetch()) {
        $pageindex = getPageIndex($where, $start, $num_per_page, &$num_servers, $online);
        echoTableHeader($class, $num_servers, $pageindex, $online);

        $odd = false;
        do {
            echoTableRow($class, $name, $pic_url, $uid, $uname, $ip, $port, $version, $uptime, $time, $votes, $online, $odd);
            $odd = !$odd;
        } while ($stmt->fetch());

        echoTableFooter();
    } elseif ($class != "Spons")
        echo "No servers yet, be the first!";

    $stmt->close();
}

function echoTableRow($class, $name, $pic_url, $uid, $uname, $ip, $port, $version, $uptime, $time, $votes, $online = 1, $odd = False) {
    global $thispage, $g_img_dir;

    if ($pic_url != '' && $class == "Spons")
        $name = '<img src="' . $pic_url . '" alt="' . $name . '" width="185" height="25" />';

    $play = echoPlay($online, $ip, $port, $version);
    // date("m-d-y", $time)
    // strftime($time_format, $time+$time_offset)
    ?>
<tr<?php if ($odd) echo ' class="odd"'; ?>>
    <td><a href="?server=<?php echo $ip; ?>"><?php echo $name; ?></a></td>
    <td><?php echo $version; ?></td>
    <td><a href="<?php echo urlForUid($uid); ?>"><?php echo $uname; ?></a></td>
    <td><?php echo $uptime; ?>%</td>
    <td><?php echo date("m-d-y", $time); ?></td>
    <td><?php echo ($votes > 0) ? '+' . $votes : $votes; ?></td>
    <td><a href="<?php echo $thispage ?>?action=up&amp;server=<?php echo $ip ?>"><img
            src="<?php echo $g_img_dir; ?>/up.png" alt="Up"/></a><a
            href="<?php echo $thispage ?>?action=down&amp;server=<?php echo $ip ?>"><img
            src="<?php echo $g_img_dir; ?>/down.png" alt="Down"/></a></td>
    <?php
    if (!can_mod() || $class == "Spons") {
        ?>
        <td><?php echo $play; ?></td>
        <?php
    } else {
        ?>
        <td><a href="<?php echo $thispage ?>?action=delete&amp;server=<?php echo $ip ?>">X</a> / <a
                href="<?php echo $thispage ?>?action=ban&amp;server=<?php echo $ip ?>">X</a></td>
        <?php
    }
    ?>
</tr>
<?php
}

function echoTableHeader($class, $num, $pageindex = null, $online = 1) {
    global $g_headers, $thispage, $g_img_dir;

    if ($class == "Spons")
        $caption = 'Sponsored Servers';
    elseif ($online == 2)
        $caption = 'Search Results'; // other
    else
        $caption = 'Other Servers';

    ?>
    <table class="<?php echo strtolower($class); ?>" summary="<?php echo $caption; ?>">
      <caption>
          <?php echo $caption; ?>
      </caption>
    <thead>
    <tr>
        <?php
        if ($class == "Spons")
            $link = '          <th scope="col">%s</th>' . "\n";
        else
            $link = '          <th scope="col"><a class="tdheader" href="' . $thispage . '?' . ((isset($_GET['action']) && strpos($_GET['action'], 'search') !== false) ? 'action=search3&amp;' : '') . (isset($_GET['offline']) ? 'offline&amp;' : '') . 'sort=%s">%s</a></th>' . "\n";

        foreach ($g_headers as $sort => $name) {
            if ($class == "Spons") {
                printf($link, $name);
            } else {
                $pic = '';
                if ((!isset($_GET['sort']) && $sort == 'uptime') || $sort == $_GET['sort']) {
                    if (!isset($_GET['sort']) || isset($_GET['desc'])) {
                        $name .= ' <img src="' . $g_img_dir . '/sort_down.gif" alt="" />';
                    } else {
                        $sort .= '&amp;desc';
                        $name .= ' <img src="' . $g_img_dir . '/sort_up.gif" alt="" />';
                    }
                }
                printf($link, $sort, $name);
            }
        }

        ?>

        <th scope="col">Vote here!</th>
        <?php
        if (!can_mod() || $class == "Spons") {
            ?>
            <th scope="col">Play (select detail)</th>
            <?php
        } else {
            ?>
            <th scope="col">Delete / Ban</th>
            <?php
        }
        ?>
    </tr>
    </thead>

    <tfoot>
    <tr>
        <th scope="row">Total</th>
        <th scope="row" colspan="7"><?php echo $num; ?> Servers</th>
    </tr>
        <?php
        if ($class == "Spons") {
            ?>
        <tr>
            <th scope="row">Info</th>
            <th scope="row" colspan="7"><a href="/sponsbid.php">How to get Sponsored</a>.</th>
        </tr>
            <?php
        }
        ?>
        <?php
        if ($pageindex != null) {
            ?>
        <tr>
            <th scope="row">Pages</th>
            <th scope="row" colspan="7"><?php echo $pageindex; ?></th>
        </tr>
            <?php
        }
        ?>
    </tfoot>

      <tbody>
<?php
}

function echoTableFooter() {
    ?>
      </tbody>

    </table>
    <br/>
<?php
}

function ss_constructPageIndex($base_url, &$start, $max_value, $num_per_page, $online) {

    switch ($online) {
        case 0:
            $prefix = '?offline&amp;';
            break;
        case 1:
            $prefix = '?';
            break;
        // this means search
        case 2:
            $prefix = '?action=search3&amp;';
            break;
    }
    $prefix .= (isset($_GET['sort']) ? 'sort=' . $_GET['sort'] . '&amp;' : '') . (isset($_GET['desc']) ? 'desc&amp;' : '');


    // Save whether $start was less than 0 or not.
    $start_invalid = $start < 0;

    // Make sure $start is a proper variable - not less than 0.
    if ($start_invalid)
        $start = 0;
    // Not greater than the upper bound.
    elseif ($start >= $max_value)
        $start = max(0, (int)$max_value - (((int)$max_value % (int)$num_per_page) == 0 ? $num_per_page : ((int)$max_value % (int)$num_per_page))); // And it has to be a multiple of $num_per_page!
    else
        $start = max(0, (int)$start - ((int)$start % (int)$num_per_page));

    $base_link = '<a href="' . strtr($base_url, array('%' => '%%')) . $prefix . 'start=%d' . '">%s</a> ';

    // If they didn't enter an odd value, pretend they did.
    $PageContiguous = (int)3;

    // Show the first page. (>1< ... 6 7 [8] 9 10 ... 15)
    if ($start > $num_per_page * $PageContiguous)
        $pageindex = sprintf($base_link, 0, '1');
    else
        $pageindex = '';

    // Show the ... after the first page.  (1 >...< 6 7 [8] 9 10 ... 15)
    if ($start > $num_per_page * ($PageContiguous + 1))
        $pageindex .= '<b> ... </b>';

    // Show the pages before the current one. (1 ... >6 7< [8] 9 10 ... 15)
    for ($nCont = $PageContiguous; $nCont >= 1; $nCont--)
        if ($start >= $num_per_page * $nCont) {
            $tmpStart = $start - $num_per_page * $nCont;
            $pageindex .= sprintf($base_link, $tmpStart, $tmpStart / $num_per_page + 1);
        }

    // Show the current page. (1 ... 6 7 >[8]< 9 10 ... 15)
    if (!$start_invalid)
        $pageindex .= '[<b>' . ($start / $num_per_page + 1) . '</b>] ';
    else
        $pageindex .= sprintf($base_link, $start, $start / $num_per_page + 1);

    // Show the pages after the current one... (1 ... 6 7 [8] >9 10< ... 15)
    $tmpMaxPages = (int)(($max_value - 1) / $num_per_page) * $num_per_page;
    for ($nCont = 1; $nCont <= $PageContiguous; $nCont++)
        if ($start + $num_per_page * $nCont <= $tmpMaxPages) {
            $tmpStart = $start + $num_per_page * $nCont;
            $pageindex .= sprintf($base_link, $tmpStart, $tmpStart / $num_per_page + 1);
        }

    // Show the '...' part near the end. (1 ... 6 7 [8] 9 10 >...< 15)
    if ($start + $num_per_page * ($PageContiguous + 1) < $tmpMaxPages)
        $pageindex .= '<b> ... </b>';

    // Show the last number in the list. (1 ... 6 7 [8] 9 10 ... >15<)
    if ($start + $num_per_page * $PageContiguous < $tmpMaxPages)
        $pageindex .= sprintf($base_link, $tmpMaxPages, $tmpMaxPages / $num_per_page + 1);

    return $pageindex;
}

?>