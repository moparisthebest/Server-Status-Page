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
	die('Hacking attempt...');

function view(){
	//echo 'this is view';
	mysql_con();
	global $g_mysqli;
	$stmt = $g_mysqli->prepare('SELECT `name`, `pic_url`, `uid`, `uname`, `ip`, `port`, `version`, `uptime`, `time`, `info`, `online`, `sponsored`, `vote` FROM `servers` WHERE `ip` = ? LIMIT 1') or debug($g_mysqli->error);
	$stmt->bind_param("s", $_GET['server']);
	$stmt->execute();
	// bind result variables
	$stmt->bind_result($name, $pic_url, $uid, $uname, $ip, $port, $version, $uptime, $time, $info, $online, $spons, $votes);
	if(!$stmt->fetch()){
		echo 'This server does not exist.<br />';
		return;
	}
	$stmt->close();
	close_mysql();

	if($online == 1){
		$link = "http://www.moparscape.org/index.php?server=%s&amp;port=%s&amp;version=%s&amp;detail=";
		$link = sprintf($link, $ip, $port, $version);
		$play = '<a href="%s0">High</a> / <a href="%s1">Low</a>';
		$play = sprintf($play, $link, $link);
	}else{
		$play = '<div class="offline">Server Offline!</div>';
	}

	$info = bb2html($info);

	$status_img_url = "http://".$_SERVER['SERVER_NAME']."/serverstatus/$ip.png";
	$this_url = "http://".$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];

?>
    <script type="text/javascript">
//<![CDATA[
      <!--
function selectText()
{
	var oCodeArea = document.getElementById('selectme');


	if (typeof(oCodeArea) != 'object' || oCodeArea == null)
		return false;

	// Start off with my favourite, internet explorer.
	if ('createTextRange' in document.body)
	{
		var oCurRange = document.body.createTextRange();
		oCurRange.moveToElementText(oCodeArea);
		oCurRange.select();
	}
	// Firefox at el.
	else if (window.getSelection)
	{
		var oCurSelection = window.getSelection();
		// Safari is special!
		if (oCurSelection.setBaseAndExtent)
		{
			var oLastChild = oCodeArea.lastChild;
			oCurSelection.setBaseAndExtent(oCodeArea, 0, oLastChild, 'innerText' in oLastChild ? oLastChild.innerText.length : oLastChild.textContent.length);
		}
		else
		{
			var curRange = document.createRange();
			curRange.selectNodeContents(oCodeArea);

			oCurSelection.removeAllRanges();
			oCurSelection.addRange(curRange);
		}
	}

	return false;
}

      //-->
      //]]>
      </script>

    <table class="<?php echo ($spons == 0) ? 'other' : 'spons'; ?>" summary="<?php echo $name; ?>">
      <caption>
      <?php echo ($pic_url != '') ? '<img src="'.$pic_url.'" alt="'.$name.'" width="185" height="25" />' : $name; ?>
      </caption>
      <thead>
        <tr>
          <th scope="col">IP</th>
          <th scope="col">Port</th>
          <th scope="col">Client Version</th>
          <th scope="col">Owner</th>
          <th scope="col">Uptime</th>
          <th scope="col">Since</th>
          <th scope="col">Votes</th>
          <th scope="col">Vote here!</th>
          <th scope="col">Play (select detail)</th>
<?php
	if(can_mod() && $spons == 0){
?>
          <th scope="col">Delete / Ban</th>
<?php
	}
?>
        </tr>
      </thead>

      <tfoot>
        <tr>
          <th scope="row">Image: </th>
          <th scope="row" colspan="3"><a href="<?php echo $this_url; ?>"><img src="<?php echo $status_img_url; ?>" alt="Status Image" /></a></th>
          <th scope="row">BBcode:<br /><a href="javascript:void(0);" onclick="return selectText();">[Select]</a></th>
          <th scope="row" colspan="<?php echo ((can_mod() && $spons == 0) ? '5' : '4'); ?>"><div id="selectme">[url=<?php echo $this_url; ?>][img]<?php echo $status_img_url; ?>[/img][/url]</div></th>
        </tr>
      </tfoot>

      <tbody>

        <tr>
          <td><?php echo $ip; ?></td>
          <td><?php echo $port; ?></td>
          <td><?php echo $version; ?></td>
          <td><a href="http://www.moparscape.org/smf/index.php?action=profile;u=<?php echo $uid; ?>"><?php echo $uname; ?></a></td>
          <td><?php echo $uptime; ?>%</td>
          <td><?php echo date("m-d-y", $time); ?></td>
          <td><?php echo ($votes > 0) ?  '+'.$votes: $votes; ?></td>
          <td><a href="<?php echo $thispage ?>?action=up&amp;server=<?php echo $ip ?>"><img src="http://<?php echo $_SERVER['SERVER_NAME']; ?>/images/up.png" alt="Up" /></a><a href="<?php echo $thispage ?>?action=down&amp;server=<?php echo $ip ?>"><img src="http://<?php echo $_SERVER['SERVER_NAME']; ?>/images/down.png" alt="Down" /></a></td>
          <td><?php echo $play; ?></td>
<?php
	if(can_mod() && $spons == 0){
?>
          <td><a href="<?php echo $thispage ?>?action=delete&amp;server=<?php echo $ip ?>">X</a> / <a href="<?php echo $thispage ?>?action=ban&amp;server=<?php echo $ip ?>">X</a></td>
<?php
	}
?>
        </tr>
      </tbody>      

    </table>
        <div class="post">
<?php echo $info; ?>
        </div>
<?php
}

?>