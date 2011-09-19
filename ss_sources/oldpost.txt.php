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
die('hacking attempt...');

// Parses some bbc before sending into the database...
function preparsecode(&$message, $previewing = false)
{
	global $user_info, $modSettings, $context;

	// This line makes all languages *theoretically* work even with the wrong charset ;).
	//$message = preg_replace('~&amp;#(\d{4,5}|[2-9]\d{2,4}|1[2-9]\d);~', '&#$1;', $message);

	// Clean up after nobbc ;).
	$message = preg_replace('~\[nobbc\](.+?)\[/nobbc\]~ie', '\'[nobbc]\' . strtr(\'$1\', array(\'[\' => \'&#91;\', \']\' => \'&#93;\', \':\' => \'&#58;\', \'@\' => \'&#64;\')) . \'[/nobbc]\'', $message);

	// Remove \r's... they're evil!
	$message = strtr($message, array("\r" => ''));

	// You won't believe this - but too many periods upsets apache it seems!
	$message = preg_replace('~\.{100,}~', '...', $message);

	// Trim off trailing quotes - these often happen by accident.
	while (substr($message, -7) == '[quote]')
		$message = substr($message, 0, -7);
	while (substr($message, 0, 8) == '[/quote]')
		$message = substr($message, 8);

	// Check if all code tags are closed.
	$codeopen = preg_match_all('~(\[code(?:=[^\]]+)?\])~is', $message, $dummy);
	$codeclose = preg_match_all('~(\[/code\])~is', $message, $dummy);

	// Close/open all code tags...
	if ($codeopen > $codeclose)
		$message .= str_repeat('[/code]', $codeopen - $codeclose);
	elseif ($codeclose > $codeopen)
		$message = str_repeat('[code]', $codeclose - $codeopen) . $message;

	// Now that we've fixed all the code tags, let's fix the img and url tags...
	$parts = preg_split('~(\[/code\]|\[code(?:=[^\]]+)?\])~i', $message, -1, PREG_SPLIT_DELIM_CAPTURE);

	// The regular expression non breaking space has many versions.
	$non_breaking_space = $context['utf8'] ? ($context['server']['complex_preg_chars'] ? '\x{A0}' : pack('C*', 0xC2, 0xA0)) : '\xA0';

	// Only mess with stuff outside [code] tags.
	for ($i = 0, $n = count($parts); $i < $n; $i++)
	{
		// It goes 0 = outside, 1 = begin tag, 2 = inside, 3 = close tag, repeat.
		if ($i % 4 == 0)
		{
			fixTags($parts[$i]);

			// Replace /me.+?\n with [me=name]dsf[/me]\n.
			if (strpos($user_info['name'], '[') !== false || strpos($user_info['name'], ']') !== false || strpos($user_info['name'], '\'') !== false || strpos($user_info['name'], '"') !== false)
				$parts[$i] = preg_replace('~(?:\A|\n)/me(?: |&nbsp;)([^\n]*)(?:\z)?~i', '[me=&quot;' . $user_info['name'] . '&quot;]$1[/me]', $parts[$i]);
			else
				$parts[$i] = preg_replace('~(?:\A|\n)/me(?: |&nbsp;)([^\n]*)(?:\z)?~i', '[me=' . $user_info['name'] . ']$1[/me]', $parts[$i]);

			if (!$previewing && strpos($parts[$i], '[html]') !== false)
			{
				if (allowedTo('admin_forum'))
					$parts[$i] = preg_replace('~\[html\](.+?)\[/html\]~ise', '\'[html]\' . strtr(un_htmlspecialchars(\'$1\'), array("\n" => \'&#13;\', \'  \' => \' &#32;\')) . \'[/html]\'', $parts[$i]);
				// We should edit them out, or else if an admin edits the message they will get shown...
				else
				{
					while (strpos($parts[$i], '[html]') !== false)
						$parts[$i] = preg_replace('~\[[/]?html\]~i', '', $parts[$i]);
				}
			}

			// Let's look at the time tags...
			$parts[$i] = preg_replace('~\[time(=(absolute))*\](.+?)\[/time\]~ie', '\'[time]\' . (is_numeric(\'$3\') || @strtotime(\'$3\') == 0 ? \'$3\' : strtotime(\'$3\') - (\'$2\' == \'absolute\' ? 0 : (($modSettings[\'time_offset\'] + $user_info[\'time_offset\']) * 3600))) . \'[/time]\'', $parts[$i]);

			$list_open = substr_count($parts[$i], '[list]') + substr_count($parts[$i], '[list ');
			$list_close = substr_count($parts[$i], '[/list]');
			if ($list_close - $list_open > 0)
				$parts[$i] = str_repeat('[list]', $list_close - $list_open) . $parts[$i];
			if ($list_open - $list_close > 0)
				$parts[$i] = $parts[$i] . str_repeat('[/list]', $list_open - $list_close);

			// Make sure all tags are lowercase.
			$parts[$i] = preg_replace('~\[([/]?)(list|li|table|tr|td)([^\]]*)\]~ie', '\'[$1\' . strtolower(\'$2\') . \'$3]\'', $parts[$i]);

			$mistake_fixes = array(
				// Find [table]s not followed by [tr].
				'~\[table\](?![\s' . $non_breaking_space . ']*\[tr\])~s' . ($context['utf8'] ? 'u' : '') => '[table][tr]',
				// Find [tr]s not followed by [td].
				'~\[tr\](?![\s' . $non_breaking_space . ']*\[td\])~s' . ($context['utf8'] ? 'u' : '') => '[tr][td]',
				// Find [/td]s not followed by something valid.
				'~\[/td\](?![\s' . $non_breaking_space . ']*(?:\[td\]|\[/tr\]|\[/table\]))~s' . ($context['utf8'] ? 'u' : '') => '[/td][/tr]',
				// Find [/tr]s not followed by something valid.
				'~\[/tr\](?![\s' . $non_breaking_space . ']*(?:\[tr\]|\[/table\]))~s' . ($context['utf8'] ? 'u' : '') => '[/tr][/table]',
				// Find [/td]s incorrectly followed by [/table].
				'~\[/td\][\s' . $non_breaking_space . ']*\[/table\]~s' . ($context['utf8'] ? 'u' : '') => '[/td][/tr][/table]',
				// Find [table]s, [tr]s, and [/td]s (possibly correctly) followed by [td].
				'~\[(table|tr|/td)\]([\s' . $non_breaking_space . ']*)\[td\]~s' . ($context['utf8'] ? 'u' : '') => '[$1]$2[_td_]',
				// Now, any [td]s left should have a [tr] before them.
				'~\[td\]~s' => '[tr][td]',
				// Look for [tr]s which are correctly placed.
				'~\[(table|/tr)\]([\s' . $non_breaking_space . ']*)\[tr\]~s' . ($context['utf8'] ? 'u' : '') => '[$1]$2[_tr_]',
				// Any remaining [tr]s should have a [table] before them.
				'~\[tr\]~s' => '[table][tr]',
				// Look for [/td]s followed by [/tr].
				'~\[/td\]([\s' . $non_breaking_space . ']*)\[/tr\]~s' . ($context['utf8'] ? 'u' : '') => '[/td]$1[_/tr_]',
				// Any remaining [/tr]s should have a [/td].
				'~\[/tr\]~s' => '[/td][/tr]',
				// Look for properly opened [li]s which aren't closed.
				'~\[li\]([^\[\]]+?)\[li\]~s' => '[li]$1[_/li_][_li_]',
				'~\[li\]([^\[\]]+?)$~s' => '[li]$1[/li]',
				// Lists - find correctly closed items/lists.
				'~\[/li\]([\s' . $non_breaking_space . ']*)\[/list\]~s' . ($context['utf8'] ? 'u' : '') => '[_/li_]$1[/list]',
				// Find list items closed and then opened.
				'~\[/li\]([\s' . $non_breaking_space . ']*)\[li\]~s' . ($context['utf8'] ? 'u' : '') => '[_/li_]$1[_li_]',
				// Now, find any [list]s or [/li]s followed by [li].
				'~\[(list(?: [^\]]*?)?|/li)\]([\s' . $non_breaking_space . ']*)\[li\]~s' . ($context['utf8'] ? 'u' : '') => '[$1]$2[_li_]',
				// Any remaining [li]s weren't inside a [list].
				'~\[li\]~' => '[list][li]',
				// Any remaining [/li]s weren't before a [/list].
				'~\[/li\]~' => '[/li][/list]',
				// Put the correct ones back how we found them.
				'~\[_(li|/li|td|tr|/tr)_\]~' => '[$1]',
			);

			// Fix up some use of tables without [tr]s, etc. (it has to be done more than once to catch it all.)
			for ($j = 0; $j < 3; $j++)
				$parts[$i] = preg_replace(array_keys($mistake_fixes), $mistake_fixes, $parts[$i]);
		}
	}

	// Put it back together!
	if (!$previewing)
		$message = strtr(implode('', $parts), array('  ' => '&nbsp; ', "\n" => '<br />', $context['utf8'] ? "\xC2\xA0" : "\xA0" => '&nbsp;'));
	else
		$message = strtr(implode('', $parts), array('  ' => '&nbsp; ', $context['utf8'] ? "\xC2\xA0" : "\xA0" => '&nbsp;'));

	// Now let's quickly clean up things that will slow our parser (which are common in posted code.)
	$message = strtr($message, array('[]' => '&#91;]', '[&#039;' => '&#91;&#039;'));
}

// Fix any URLs posted - ie. remove 'javascript:'.
function fixTags(&$message)
{
	global $modSettings;

	// WARNING: Editing the below can cause large security holes in your forum.
	// Edit only if you are sure you know what you are doing.

	$fixArray = array(
		// [img]http://...[/img] or [img width=1]http://...[/img]
		array(
			'tag' => 'img',
			'protocols' => array('http', 'https'),
			'embeddedUrl' => false,
			'hasEqualSign' => false,
			'hasExtra' => true,
		),
		// [url]http://...[/url]
		array(
			'tag' => 'url',
			'protocols' => array('http', 'https'),
			'embeddedUrl' => true,
			'hasEqualSign' => false,
		),
		// [url=http://...]name[/url]
		array(
			'tag' => 'url',
			'protocols' => array('http', 'https'),
			'embeddedUrl' => true,
			'hasEqualSign' => true,
		),
		// [iurl]http://...[/iurl]
		array(
			'tag' => 'iurl',
			'protocols' => array('http', 'https'),
			'embeddedUrl' => true,
			'hasEqualSign' => false,
		),
		// [iurl=http://...]name[/iurl]
		array(
			'tag' => 'iurl',
			'protocols' => array('http', 'https'),
			'embeddedUrl' => true,
			'hasEqualSign' => true,
		),
		// [ftp]ftp://...[/ftp]
		array(
			'tag' => 'ftp',
			'protocols' => array('ftp', 'ftps'),
			'embeddedUrl' => true,
			'hasEqualSign' => false,
		),
		// [ftp=ftp://...]name[/ftp]
		array(
			'tag' => 'ftp',
			'protocols' => array('ftp', 'ftps'),
			'embeddedUrl' => true,
			'hasEqualSign' => true,
		),
		// [flash]http://...[/flash]
		array(
			'tag' => 'flash',
			'protocols' => array('http', 'https'),
			'embeddedUrl' => false,
			'hasEqualSign' => false,
			'hasExtra' => true,
		),
	);

	// Fix each type of tag.
	foreach ($fixArray as $param)
		fixTag($message, $param['tag'], $param['protocols'], $param['embeddedUrl'], $param['hasEqualSign'], !empty($param['hasExtra']));

	// Now fix possible security problems with images loading links automatically...
	$message = preg_replace('~(\[img.*?\])(.+?)\[/img\]~eis', '\'$1\' . preg_replace(\'~action(=|%3d)(?!dlattach)~i\', \'action-\', \'$2\') . \'[/img]\'', $message);

	// Limit the size of images posted?
	if (!empty($modSettings['max_image_width']) || !empty($modSettings['max_image_height']))
	{
		// Find all the img tags - with or without width and height.
		preg_match_all('~\[img(\s+width=\d+)?(\s+height=\d+)?(\s+width=\d+)?\](.+?)\[/img\]~is', $message, $matches, PREG_PATTERN_ORDER);

		$replaces = array();
		foreach ($matches[0] as $match => $dummy)
		{
			// If the width was after the height, handle it.
			$matches[1][$match] = !empty($matches[3][$match]) ? $matches[3][$match] : $matches[1][$match];

			// Now figure out if they had a desired height or width...
			$desired_width = !empty($matches[1][$match]) ? (int) substr(trim($matches[1][$match]), 6) : 0;
			$desired_height = !empty($matches[2][$match]) ? (int) substr(trim($matches[2][$match]), 7) : 0;

			// One was omitted, or both.  We'll have to find its real size...
			if (empty($desired_width) || empty($desired_height))
			{
				list ($width, $height) = url_image_size(un_htmlspecialchars($matches[4][$match]));

				// They don't have any desired width or height!
				if (empty($desired_width) && empty($desired_height))
				{
					$desired_width = $width;
					$desired_height = $height;
				}
				// Scale it to the width...
				elseif (empty($desired_width) && !empty($height))
					$desired_width = (int) (($desired_height * $width) / $height);
				// Scale if to the height.
				elseif (!empty($width))
					$desired_height = (int) (($desired_width * $height) / $width);
			}

			// If the width and height are fine, just continue along...
			if ($desired_width <= $modSettings['max_image_width'] && $desired_height <= $modSettings['max_image_height'])
				continue;

			// Too bad, it's too wide.  Make it as wide as the maximum.
			if ($desired_width > $modSettings['max_image_width'] && !empty($modSettings['max_image_width']))
			{
				$desired_height = (int) (($modSettings['max_image_width'] * $desired_height) / $desired_width);
				$desired_width = $modSettings['max_image_width'];
			}

			// Now check the height, as well.  Might have to scale twice, even...
			if ($desired_height > $modSettings['max_image_height'] && !empty($modSettings['max_image_height']))
			{
				$desired_width = (int) (($modSettings['max_image_height'] * $desired_width) / $desired_height);
				$desired_height = $modSettings['max_image_height'];
			}

			$replaces[$matches[0][$match]] = '[img' . (!empty($desired_width) ? ' width=' . $desired_width : '') . (!empty($desired_height) ? ' height=' . $desired_height : '') . ']' . $matches[4][$match] . '[/img]';
		}

		// If any img tags were actually changed...
		if (!empty($replaces))
			$message = strtr($message, $replaces);
	}
}

// Fix a specific class of tag - ie. url with =.
function fixTag(&$message, $myTag, $protocols, $embeddedUrl = false, $hasEqualSign = false, $hasExtra = false)
{
	global $boardurl, $scripturl;

	if (preg_match('~^([^:]+://[^/]+)~', $boardurl, $match) != 0)
		$domain_url = $match[1];
	else
		$domain_url = $boardurl . '/';

	$replaces = array();

	if ($hasEqualSign)
		preg_match_all('~\[(' . $myTag . ')=([^\]]*?)\](?:(.+?)\[/(' . $myTag . ')\])?~is', $message, $matches);
	else
		preg_match_all('~\[(' . $myTag . ($hasExtra ? '(?:[^\]]*?)' : '') . ')\](.+?)\[/(' . $myTag . ')\]~is', $message, $matches);

	foreach ($matches[0] as $k => $dummy)
	{
		// Remove all leading and trailing whitespace.
		$replace = trim($matches[2][$k]);
		$this_tag = $matches[1][$k];
		$this_close = $hasEqualSign ? (empty($matches[4][$k]) ? '' : $matches[4][$k]) : $matches[3][$k];

		$found = false;
		foreach ($protocols as $protocol)
		{
			$found = strncasecmp($replace, $protocol . '://', strlen($protocol) + 3) === 0;
			if ($found)
				break;
		}

		if (!$found && $protocols[0] == 'http')
		{
			if (substr($replace, 0, 1) == '/')
				$replace = $domain_url . $replace;
			elseif (substr($replace, 0, 1) == '?')
				$replace = $scripturl . $replace;
			elseif (substr($replace, 0, 1) == '#' && $embeddedUrl)
			{
				$replace = '#' . preg_replace('~[^A-Za-z0-9_\-#]~', '', substr($replace, 1));
				$this_tag = 'iurl';
				$this_close = 'iurl';
			}
			else
				$replace = $protocols[0] . '://' . $replace;
		}
		elseif (!$found)
			$replace = $protocols[0] . '://' . $replace;

		if ($hasEqualSign && $embeddedUrl)
			$replaces[$matches[0][$k]] = '[' . $this_tag . '=' . $replace . ']' . (empty($matches[4][$k]) ? '' : $matches[3][$k] . '[/' . $this_close . ']');
		elseif ($hasEqualSign)
			$replaces['[' . $matches[1][$k] . '=' . $matches[2][$k] . ']'] = '[' . $this_tag . '=' . $replace . ']';
		elseif ($embeddedUrl)
			$replaces['[' . $matches[1][$k] . ']' . $matches[2][$k] . '[/' . $matches[3][$k] . ']'] = '[' . $this_tag . '=' . $replace . ']' . $matches[2][$k] . '[/' . $this_close . ']';
		else
			$replaces['[' . $matches[1][$k] . ']' . $matches[2][$k] . '[/' . $matches[3][$k] . ']'] = '[' . $this_tag . ']' . $replace . '[/' . $this_close . ']';

	}

	foreach ($replaces as $k => $v)
	{
		if ($k == $v)
			unset($replaces[$k]);
	}

	if (!empty($replaces))
		$message = strtr($message, $replaces);
}

?>