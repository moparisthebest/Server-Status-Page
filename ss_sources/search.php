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

function search() {
    forceLogin();

    $chk_template = "\t\t" . '<input type="checkbox" checked="checked" name="versions[]" value="%s" /> %s <br />' . "\n";
    global $g_versions;

    echo "Enter your search terms.<br />";
    ?>
<script type="text/javascript">
    //<![CDATA[
    <!--
    function toggle(source) {
        checkboxes = document.getElementsByName('versions[]');
        for (var i in checkboxes)
            checkboxes[i].checked = source.checked;
    }

    //-->
    //]]>
</script>
<form action="<?php echo actionURL('search2'); ?>" method="post" enctype="multipart/form-data" style="margin: 0;">
    <fieldset style="margin: 0;">
        <input type="text" name="query" value="<?php echo $_POST['query']; ?>"/><br/><br/>
        Version:<br/>
        <?php
        foreach ($g_versions as $v)
            printf($chk_template, $v, $v);
        ?>
        <br/>
        <input type="checkbox" checked="checked" onClick="toggle(this)"/> Check All<br/>
        <br/>
        <input type="submit" name="submit" value="Search" accesskey="s"/>
    </fieldset>
</form>
<?php
}

function search2() {
    forceLogin();

    global $uid, $uname, $thispage, $time_format, $time_offset, $g_mysqli, $g_versions;

    //wait time in seconds to search again
    $wait_time = 20;

    $query = $_POST['query'];

    if ($query == "") {
        error("You must type something to search for!<br />");
        search();
        return;
    }

    $versions = $_POST['versions'];
//      print_r($versions);
//      echo '<br />';
//      print_r($g_versions);
//      echo '<br />';
//      print_r(array_diff($versions, $g_versions));
    if (count($versions) == 0 || count(array_diff($versions, $g_versions)) != 0) {
        error("You must specify a valid version!<br />");
        search();
        return;
    }

    // we checked out so far, make sure they haven't searched in the set amount of time
    $threshold = time() - $wait_time;

    // first check the session variable, since it is cheaper than a query
    if (isset($_SESSION['last_search']) && $_SESSION['last_search'] > $threshold) {
        echo "You have searched within the last $wait_time seconds, you may do this again in " . ($_SESSION['last_search'] - $threshold) . ' seconds.<br />';
        return;
    }

    // do processing on $query


    // get your sphinx on
    require_once('sphinxapi.php');

    $cl = new SphinxClient();
    $cl->SetServer("localhost", 9312);
    $cl->SetLimits(0, 6000);
    $cl->SetMatchMode(SPH_MATCH_ANY);
    $cl->SetFilter('version', $versions);

    // clean a dirty query
    $query = clean_word_sphinx($query, $cl);

    //echo "<br />query: ".$query."<br />";

    $result = $cl->Query($query, 'sstat_index');

    if ($result === false) {
        error("Query failed: " . $cl->GetLastError() . ".\n");
        return;
    }

    if ($cl->GetLastWarning()) {
        echo "WARNING (not an error!): " . $cl->GetLastWarning();
    }

    // if there weren't any matches, say so
    if (empty($result["matches"])) {
        error("No results for that query.");
        return;
    }

    // then it's successfull, set it up
    $_SESSION['last_search_results'] = implode(",", array_keys($result["matches"]));
    $_SESSION['last_search_total'] = $result['total'];
    //echo "<br />implode: ".$_SESSION['last_search_results']."<br />";
    //echo "num results: ".$_SESSION['last_search_total']."<br />";
    //echo "count: ".count($result["matches"])."<br />";
    //foreach ( $result["matches"] as $doc => $docinfo )
    //     echo "$doc\n";
    //print_r( $result );
    search3();


    $_SESSION['last_search'] = time();
}

function search3() {
    forceLogin();

    if (!isset($_SESSION['last_search_results']) || !isset($_SESSION['last_search_total'])) {
        search();
        return;
    }

    // then we are in business
    require_once('display.php');

    // online is 2 for searches
    display_table(2, "`id` IN (" . $_SESSION['last_search_results'] . ")", $_SESSION['last_search_total']);
}

// Clean up a search word/phrase/term for Sphinx (from SMF)
function clean_word_sphinx($sphinx_term, $sphinx_client) {
    // Multiple quotation marks in a row can cause fatal errors, so handle them
    $sphinx_term = preg_replace('/""+/', '"', $sphinx_term);
    // Unmatched (i.e. odd number of) quotation marks also cause fatal errors, so handle them
    if (substr_count($sphinx_term, '"') % 2)
        // Using preg_replace since it supports limiting the number of replacements
        $sphinx_term = preg_replace('/"/', '', $sphinx_term, 1);
    // Use the Sphinx API's built-in EscapeString function to escape special characters
    $sphinx_term = $sphinx_client->EscapeString($sphinx_term);
    // Since it escapes quotation marks and we don't want that, unescape them
    $sphinx_term = str_replace('\"', '"', $sphinx_term);
    return $sphinx_term;
}

?>
