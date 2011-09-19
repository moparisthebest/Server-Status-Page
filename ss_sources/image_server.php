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

$script_name = '/serverstatus/';
$script_name_len = strlen($script_name);
/*
function echoImage($online = -1){
	if($online == 1)
		$url = 'http://mopar.moparscape.org/images/online.gif';
	elseif($online == 0)
		$url = 'http://mopar.moparscape.org/images/offline.gif';
	else
		$url = 'http://mopar.moparscape.org/images/error.gif';
	close_mysql();
	header("Location: $url");
	//echo("Location: $url");	debug_print_backtrace();
	exit;
}
*/

function scale(&$img, $scale) {
	$width = imagesx($img) * $scale/100;
	$height = imagesy($img) * $scale/100; 
	resize($img, $width, $height);
}

function resize(&$img, $width, $height) {
	// enforce max res. 1920x1200
	if($width > 1920 || $height > 1200)
		return;
	$new_image = imagecreatetruecolor($width, $height);
	imagecopyresampled($new_image, $img, 0, 0, 0, 0, $width, $height, imagesx($img), imagesy($img));
	$img = $new_image;
}
/*
function centerImageString($image, $string, $font_size, $y){
	$text_width = imagefontwidth($font_size)*strlen($string);
	$center = ceil(imagesx($image) / 2);
	$x = $center - (ceil($text_width/2));
//	$color = imagecolorallocate($image, 230, 230, 255);
	$color = imagecolorallocate($image, 199, 208, 227);
	imagestring($image, $font_size, $x, $y, $string, $color);
}
*/
function centerTtfString($image, $string, $font_size, $y, $font='./fonts/arial_bold.ttf'){
	$color = imagecolorallocate($image, 199, 208, 227);

	$tb = imagettfbbox($font_size, 0, $font, $string);

	$x = ceil((imagesx($image) - $tb[2]) / 2); // lower left X coordinate for text
	imagettftext($image, $font_size, 0, $x, $y, $color, $font, $string);
}

function echoImage($online = -1, $text = 'Error!', $size_req = ''){
	close_mysql();

	if($online == 1)
		$file = '../images/online.png';
	elseif($online == 0)
		$file = '../images/offline.png';
	else
		$file = '../images/error.png';

	$im = imagecreatefrompng($file);
//	the following not needed, since there is no more transparency in image
//	imagealphablending($im, true); // setting alpha blending on
//	imagesavealpha($im, true); // save alphablending setting (important)

//	centerImageString($im, $text, 5, 20);
	// size 12, y 35 can be used with <= 27 chars
	centerTtfString($im, $text, 11, 34);

	if($size_req != ''){

		$x_pos = strpos($size_req, 'x');
		if($x_pos !== false){
			$width = substr($size_req, 0, $x_pos);
			$height = substr($size_req, ++$x_pos, strlen($size_req));
			// width and height must be digit integers
			if(ctype_digit($width) && ctype_digit($height))
				resize($im, $width, $height);
		}elseif(ctype_digit($size_req)){
			scale($im, $size_req);
		}

	}

	// Turn on output buffering
	ob_start();

	// Output will now go to a buffer rather than the browser.
	imagepng($im);
	imagedestroy($im);
	
//	header("X-Powered-By: lighttpd (Ubuntu)");
	header('Content-Type: image/png');
	//header('Last-Modified: Mon, 05 Jan 2009 21:37:52 GMT');
	header("Pragma: no-cache");
	header('Cache-Control: private');
	header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
	header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
//	header('Expires: 0');
//	header('Content-Length: ' . filesize($file));

	// Tell the browser the number of bytes that have been
	// written to the buffer.
	header("Content-Length: " . ob_get_length());

	// Now send the buffer's contents to the browser and turn off
	// output buffering.
	ob_end_flush();
//	ob_clean();
//	flush();
//	readfile($file);

	exit;
}

define('SS_PAGE', 1);
require_once('./util.php');

// if there is no URI, some error, so forward to error
if (empty($_SERVER['REQUEST_URI']))
	echoImage();

// get the server, if there isn't one, forward to error
if ( (substr($_SERVER['REQUEST_URI'], strrpos($_SERVER['REQUEST_URI'], '.'), 4) == '.png') && (substr($_SERVER['REQUEST_URI'], 0, $script_name_len) == $script_name) )
	$server = substr($_SERVER['REQUEST_URI'], $script_name_len, strrpos($_SERVER['REQUEST_URI'], '.')-$script_name_len);
else
	echoImage();

// check if $server contains a size request, it would be before the /, the real ip after.
$slash_pos = strpos($server, '/');
if($slash_pos !== false){
	$size_req = substr($server, 0, $slash_pos);
	$server = substr($server, ++$slash_pos, strlen($server));
}else
	$size_req = '';

mysql_con();
global $g_mysqli;
$stmt = $g_mysqli->prepare('SELECT `online` FROM `servers` WHERE `ip` = ? LIMIT 1') or debug($g_mysqli->error);
$stmt->bind_param("s", $server);
$stmt->execute() or debug($g_mysqli->error);
// bind result variables
$stmt->bind_result($online);

// if there is no server in the database, forward to error
if(!$stmt->fetch())
	echoImage(-1, $server, $size_req);

$stmt->close();

// forward to the right page
echoImage($online, $server, $size_req);
?>