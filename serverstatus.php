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
if($_REQUEST['action'] != 'verify')
	require_once('/path/to/agreed.php');

//ini_set('display_errors', 0);
//error_reporting(E_ALL);

define('SS_PAGE', 1);

global $ss_sourcedir;
$ss_sourcedir = './ss_sources';

require_once($ss_sourcedir.'/util.php');

// What function shall we execute? (done like this for memory's sake.)
// defaults
$header = true;
$do_setup = true;
$action = ss_main($header, $do_setup);

if($do_setup)
	doSetup();

if($header)
	echoHeader($action);

call_user_func($action);

if($header)
	echoFooterExit();

// The main controlling function.
function ss_main(&$header, &$do_setup)
{
	global $ss_sourcedir;

	if (empty($_REQUEST['action'])){
		if(!empty($_REQUEST['server'])){
			require_once($ss_sourcedir . '/view.php');
			return 'view';
		}
		require_once($ss_sourcedir . '/display.php');
		return 'display';
	}

	// Here's the monstrous $_REQUEST['action'] array - $_REQUEST['action'] => array($file, $function, $header, $do_setup).
	$actionArray = array(
		'display' => array('display.php', 'display'),
		'view' => array('view.php', 'view'),
		'register' => array('register.php', 'register'),
		'register2' => array('register.php', 'register2'),
		'verify' => array('verify.php', 'verify', false, false),
		'random' => array('random.php', 'random_page', false, false),
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
	if (!isset($_REQUEST['action']) || !isset($actionArray[$_REQUEST['action']]))
	{
		// xxx maybe they are trying to ddos us (action=hackedbybattlescapecrew)
		//global $thispage;
		//die('No action found, try '.$thispage);

		// Fall through to the display then...
		require_once($ss_sourcedir . '/display.php');
		return 'display';
	}

	// Otherwise, it was set - so let's go to that action.
	require_once($ss_sourcedir . '/' . $actionArray[$_REQUEST['action']][0]);
	// here is the only place we NEED to set $header and $do_setup
	$header = (isset($actionArray[$_REQUEST['action']][2]) ? $actionArray[$_REQUEST['action']][2] : true);
	$do_setup = (isset($actionArray[$_REQUEST['action']][3]) ? $actionArray[$_REQUEST['action']][3] : true);
	return $actionArray[$_REQUEST['action']][1];
}
?>