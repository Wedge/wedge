<?php
/**
 * Wedge
 *
 * Support for handling so-called pretty URLs.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('SMF'))
	die('Hacking attempt...');

// Generate a pretty URL from a given text
function pretty_generate_url($text, $is_board = false, $slash = false)
{
	global $modSettings, $txt;

	if (strpos(strtolower($text), '[en]') !== false)
	{
		global $user_info;
		$lang = $user_info['language'];
		$user_info['language'] = 'english';
		parse_lang($text);
		$user_info['language'] = $lang;
	}

	// Do you know your ABCs?
	$characterHash = array(
		'-' =>	array ('`', '«', '»', '"', ';-)', ';)', ';o)', ':-)', ':)', ':o)', '^^', '^_^', ';-p', ':-p', ';-P', ':-P', ':D', ';D', '>_<', '°_°', '@_@', '^o^', ':-/', ' ', ' '),
		chr(18)	=>	array ("'", 'ﺀ', 'ع', '‘', '’'),
		'('	=>	array ('{', '['),
		')'	=>	array ('}', ']'),
		'a'	=>	array ('ª', 'ą', 'Ą', 'а', 'А', 'ạ', 'Ạ', 'ả', 'Ả', 'Ầ', 'ầ', 'Ấ', 'ấ', 'Ậ', 'ậ', 'Ẩ', 'ẩ', 'Ẫ', 'ẫ', 'Ă', 'ă', 'Ắ', 'ắ', 'Ẵ', 'ẵ', 'Ặ', 'ặ', 'Ằ', 'ằ', 'Ẳ', 'ẳ', 'α', 'Α'),
		'b'	=>	array ('б', 'Б', 'ب'),
		'c'	=>	array ('ć', 'Ć', 'č', 'Č', '¢'),
		'ch' =>	array ('ч', 'Ч', 'χ', 'Χ'),
		'd'	=>	array ('Ð', 'д', 'Д', 'د', 'ض', 'đ', 'Đ', 'δ', 'Δ'),
		'e'	=>	array ('ę', 'Ę', 'е', 'Е', 'ё', 'Ё', 'э', 'Э', 'Ẹ', 'ẹ', 'Ẻ', 'ẻ', 'Ẽ', 'ẽ', 'Ề', 'ề', 'Ế', 'ế', 'Ệ', 'ệ', 'Ể', 'ể', 'Ễ', 'ễ', 'ε', 'Ε', '€'),
		'f'	=>	array ('ф', 'Ф', 'ﻑ', 'φ', 'Φ'),
		'g'	=>	array ('ğ', 'Ğ', 'г', 'Г', 'γ', 'Γ'),
		'h'	=>	array ('ح', 'ه'),
		'i'	=>	array ('ı', 'İ', 'и', 'И', 'Ị', 'ị', 'Ỉ', 'ỉ', 'Ĩ', 'ĩ', 'η', 'Η', 'Ι', 'ι'),
		'k'	=>	array ('к', 'К', 'ك', 'κ', 'Κ'),
		'kh' =>	array ('х', 'Х', 'خ'),
		'l'	=>	array ('ł', 'Ł', 'л', 'Л', 'ل', 'λ', 'Λ'),
		'm'	=>	array ('м', 'М', 'م', 'μ', 'Μ'),
		'n'	=>	array ('ń', 'Ń', 'н', 'Н', 'ن', 'ν', 'Ν'),
		'o'	=>	array ('°', 'º', 'о', 'О', 'Ọ', 'ọ', 'Ỏ', 'ỏ', 'Ộ', 'ộ', 'Ố', 'ố', 'Ỗ', 'ỗ', 'Ồ', 'ồ', 'Ổ', 'ổ', 'Ơ', 'ơ', 'Ờ', 'ờ', 'Ớ', 'ớ', 'Ợ', 'ợ', 'Ở', 'ở', 'Ỡ', 'ỡ', 'ο', 'Ο', 'ω', 'Ω'),
		'p'	=>	array ('%', 'п', 'П', 'π', 'Π'),
		'ps' =>	array ('ψ', 'Ψ'),
		'r'	=>	array ('р', 'Р', 'ر'),
		's'	=>	array ('ş', 'Ş', 'ś', 'Ś', 'с', 'С', 'س', 'ص', 'š', 'Š', 'σ', 'ς', 'Σ'),
		'sh' =>	array ('ш', 'Ш', 'ش'),
		'shch' => array ('щ', 'Щ'),
		't'	=>	array ('т', 'Т', 'ت', 'ط', 'τ', 'Τ', 'ţ', 'Ţ'),
		'th' =>	array ('ث', 'θ', 'Θ'),
		'ts' =>	array ('ц', 'Ц'),
		'u'	=>	array ('у', 'У', 'Ụ', 'ụ', 'Ủ', 'ủ', 'Ũ', 'ũ', 'Ư', 'ư', 'Ừ', 'ừ', 'Ứ', 'ứ', 'Ự', 'ự', 'Ử', 'ử', 'Ữ', 'ữ', 'υ', 'Υ'),
		'v'	=>	array ('в', 'В', 'β', 'Β'),
		'x'	=>	array ('×', 'ξ', 'Ξ'),
		'y'	=>	array ('й', 'Й', 'ы', 'Ы', 'ي', 'Ỳ', 'ỳ', 'Ỵ', 'ỵ', 'Ỷ', 'ỷ', 'Ỹ', 'ỹ'),
		'ya' =>	array ('я', 'Я'),
		'yu' =>	array ('ю', 'Ю'),
		'z'	=>	array ('ż', 'Ż', 'ź', 'Ź', 'з', 'З', 'ز', 'ظ', 'ž', 'Ž', 'ζ', 'Ζ'),
		'zh' =>	array ('ж', 'Ж'),
	);

	$text = preg_replace('/(&#(\d{1,7});)/e', 'fix_accents(\'$2\')', $text); // Turns &#12345; to UTF-8

	$text = str_replace(array('&amp;', '&quot;', '£', '¥', 'ß', '¹', '²', '³', '©', '®', '™', '½', '¼', '¾', '§'),
						array('&', '"', 'p', 'yen', 'ss', '1', '2', '3', 'c', 'r', 'tm', '1-2', '1-4', '3-4', 's'), $text);
	$text = str_replace(array ('ج', 'ذ', 'غ', 'ﻻ', 'ق', 'و', 'ا', 'ﻯ'), array('j', 'dh', 'gh', 'la', 'q', 'w', 'aa', 'ae'), $text);

	foreach ($characterHash as $replace => $search)
		$text = str_replace($search, $replace, $text);

	if (function_exists('mb_convert_encoding'))
		$text = strtolower(mb_convert_encoding($text, 'HTML-ENTITIES', 'UTF-8'));
	else
		$text = strtolower(htmlentities($text, ENT_NOQUOTES, 'UTF-8'));

	$text = preg_replace('/[\x80-\xff]/', '-', $text);
	$text = preg_replace('/&(..?)(acute|grave|cedil|uml|circ|ring|tilde|lig|slash);/', '$1', $text);
	$text = str_replace(array('&#169;', '&#0169;', '&copy;', '&#153;', '&#0153;', '&trade;', '&#174;', '&#0174;', '&reg;', '&#160;', '&nbsp;'),
						array('c', 'c', 'c', 'tm', 'tm', 'tm', 'r', 'r', 'r', '-', '-'), $text); // © ™ ® nbsp
	$text = preg_replace('/(&#(\d{1,7}|x[0-9a-f]{1,6});)/e', 'entity_replace(\'$2\')', $text); // Turns &#12345; to %AB%CD

// gen-delims = :/?#[]@
// sub-delims = !$&'()*+,;=

// punct:	!"#$%&'()*+,-./:;<=>?@[\]^_`{|}~
// -delim: "%-.<>\^_`{|}~
// keep:	$%_
// $text = preg_replace(array('/([\x00-\x11\x13-\x1f\x80-\x9f\xa0-\xff])/', '/&[^;]*?;/', '~([^a-z0-9\x12\$%_-])~'), '-', $text);
	$text = preg_replace(array('/[\x00-\x1f\x80-\xff]/', '/&[^;]*?;/', '~[^a-z0-9\$%_' . ($slash ? '/' : '') . '-]~'), '-', $text);
	$text = str_replace(array('"',"'"), chr(18), $text);

	// If this is a board name, then only [a-z0-9] and hyphens are allowed -- standard host name policy.
	if ($is_board)
		$text = preg_replace('/[^a-z0-9-]/', '-', $text);

	return preg_replace(array('/^-+|-+$/', '/-+/'), array('', '-'), $text);
}

function entity_replace($string)
{
	$num = substr($string, 0, 1) === 'x' ? hexdec(substr($string, 1)) : (int) $string;
	$rep = $num > 0x10FFFF || ($num >= 0xD800 && $num <= 0xDFFF) ? '' : ($num < 0x80 ?
	chr($num) : ($num == 0 || ($num >= 0x80 && $num < 0x100) ? '-' : ($num < 0x800 ?
	chr(192 | $num >> 6) . chr(128 | $num & 63) : ($num < 0x10000 ?
	chr(224 | $num >> 12) . chr(128 | $num >> 6 & 63) . chr(128 | $num & 63) :
	chr(240 | $num >> 18) . chr(128 | $num >> 12 & 63) . chr(128 | $num >> 6 & 63) . chr(128 | $num & 63)))));
	return preg_replace('/([\x80-\xff])/e', 'sprintf(\'%%%x\', ord(\'$1\'))', $rep);
}

function fix_accents($num)
{
	$num = (int) $num;
	if ($num < 0x100)
		return chr($num);
	return '&#' . $num . ';';
}

// Remove percent-encoded multi-byte characters that were not completely trimmed at the end of a pretty URL
function trimpercent($str)
{
	if (strpos($str, '%') === false)
		return trim($str, '-' . chr(18));
	return trim(preg_replace('/(?:%f[0-4](?:%(?:[8-9a-b](?:[0-9a-f](?:%(?:[8-9a-b](?:[0-9a-f](?:%[8-9a-b]?)?)?)?)?)?)?)?|%e[0-9a-f](?:%(?:[8-9a-b](?:[0-9a-f](?:%[8-9a-b]?)?)?)?)?|%d[0-9a-f](?:%[8-9a-b]?)?|%c[2-9a-f](?:%[8-9a-b]?)?|%[0-f]?)$/', '', $str), '-' . chr(18));
// !!!	return trim(preg_replace('/(%f[0-4](%([8-9a-b]([0-9a-f](%([8-9a-b]([0-9a-f](%[8-9a-b]?)?)?)?)?)?)?)?|%e[0-9a-f](%([8-9a-b]([0-9a-f](%[8-9a-b]?)?)?)?)?|%d[0-9a-f](%[8-9a-b]?)?|%c[2-9a-f](%[8-9a-b]?)?|%[0-f]?)$/', '', $str), '-' . chr(18));
}

function is_already_taken($url, $id, $id_owner)
{
	global $context;

	$query = wesql::query('
		SELECT id_board, url, id_owner
		FROM {db_prefix}boards AS b
		WHERE
			(b.url = SUBSTRING({string:url}, 1, urllen) AND b.id_owner != {int:owner})
			OR (b.url = {string:url} AND b.id_board != {int:id})',
		array(
			'url' => $url,
			'id' => $id,
			'owner' => $id_owner,
		)
	);

	// Count that query!
	$context['pretty']['db_count']++;

	if (wesql::num_rows($query) > 0)
	{
		list ($board) = wesql::fetch_row($query);
		wesql::free_result($query);
		return $board;
	}

	wesql::free_result($query);
	return false;
}

// Update the database based on the installed filters
function pretty_update_filters()
{
	global $modSettings, $boarddir, $boardurl;

	// Get the settings
	$prettyFilters = unserialize($modSettings['pretty_filters']);
	$filterSettings = array();

	foreach ($prettyFilters as $filter)
		// Get the important data from enabled filters
		if ($filter['enabled'])
			if (isset($filter['filter']))
				$filterSettings[$filter['filter']['priority']] = $filter['filter']['callback'];

	// Update the settings table
	ksort($filterSettings);
	updateSettings(array('pretty_filter_callbacks' => serialize($filterSettings)));

	// Clear the URLs cache
	wesql::query('
		TRUNCATE TABLE {db_prefix}pretty_urls_cache');

	// Don't rewrite anything for this page
	$modSettings['pretty_enable_filters'] = false;
}

function pretty_update_topic($subject, $topic_id)
{
	global $context;

	$pretty_text = trimpercent(substr(pretty_generate_url($subject), 0, 80));

	// Can't be empty
	if ($pretty_text == '')
		$pretty_text = '-';

	// Update the database
	wesql::query('
		REPLACE INTO {db_prefix}pretty_topic_urls (id_topic, pretty_url)
		VALUES ({int:topic_id}, {string:pretty_text})', array(
			'topic_id' => $topic_id,
			'pretty_text' => $pretty_text
		));

	// Count this query!
	if (isset($context))
		$context['pretty']['db_count']++;
}

// - Add URL value to boards table if not already there
// - Check for existing prettified boards
// - Prettify any remaining board
// - Reset cache
// - Reset filters
// - Save new pretty board URLs

// !!! @todo: Move code to SMF converter!
function install_pretty_urls()
{
	global $modSettings, $boardurl;

	// Start the list
	$output = '<ul>';

	// Get the current pretty board urls, or make new arrays if there are none
	$pretty_board_urls = array();
	$pretty_board_lookup = array();

	$req = wesql::query('
		SHOW COLUMNS FROM {db_prefix}boards
		LIKE {string:url}',
		array(
			'url' => 'url',
		)
	);
	$is_url_there = wesql::num_rows($req);
	wesql::free_result($req);

	if ($is_url_there == 0)
		wesql::query('
			ALTER TABLE {db_prefix}boards
			ADD `url` VARCHAR(64) NOT NULL AFTER `name`,
			ADD INDEX url(url);',
			array()
		);

	// Get the board names
	$query = wesql::query('
		SELECT id_board, name, url
		FROM {db_prefix}boards',
		array()
	);

	while ($row = wesql::fetch_assoc($query))
	{
		// Don't replace the board urls if they already exist
		if ($row['url'] == '')
		{
			$pretty_text = pretty_generate_url($row['name'], true);
			// Can't be empty, can't be a number and can't be the same as another
			if ($pretty_text == '' || is_numeric($pretty_text) || isset($pretty_board_lookup[$pretty_text]))
				// Add suffix '-bID_BOARD' to the pretty url
				$pretty_text .= ($pretty_text != '' ? '-b' : 'b') . $row['id_board'];
			// Update the arrays
			$pretty_board_urls[$row['id_board']] = $pretty_text;
			$pretty_board_lookup[$pretty_text] = $row['id_board'];
		}
		else
		{
			// If the pretty URL is already set, set the board lookup but make sure the URL won't be saved again
			$pretty_board_urls[$row['id_board']] = '';
			$pretty_board_lookup[$row['url']] = $row['id_board'];
		}
	}
	wesql::free_result($query);
	$output .= '<li>Generating board URLs</li>';

	// Create the pretty_topic_urls table
	wesql::query('
		CREATE TABLE IF NOT EXISTS {db_prefix}pretty_topic_urls (
		`id_topic` mediumint(8) NOT NULL default "0",
		`pretty_url` varchar(80) NOT NULL,
		PRIMARY KEY (`id_topic`),
		UNIQUE (`pretty_url`))');
	$output .= '<li>Creating the pretty_topic_urls table</li>';

	// Fix old topics by replacing ' with chr(18)
	wesql::query('
		UPDATE {db_prefix}pretty_topic_urls
		SET pretty_url = REPLACE(pretty_url, {string:quote}, {string:chr18})',
		array(
			'quote' => "'",
			'chr18' => chr(18),
		)
	);

	// Delete the pretty_urls_cache table
	wesql::query('
		DROP TABLE IF EXISTS {db_prefix}pretty_urls_cache');
	$output .= '<li>Clearing the URL cache</li>';

	// Create or recreate it
	wesql::query('
		CREATE TABLE {db_prefix}pretty_urls_cache (
		`url_id` VARCHAR(255) NOT NULL,
		`replacement` VARCHAR(255) NOT NULL,
		`log_time` TIMESTAMP NOT NULL,
		PRIMARY KEY (`url_id`))');
	$output .= '<li>Creating the pretty_urls_cache table</li>';

	if (empty($modSettings['pretty_root_url']))
		$modSettings['pretty_root_url'] = $boardurl;

	// Default filter settings
	$prettyFilters = array(
		'boards' => array(
			'id' => 'boards',
			'enabled' => 1,
			'filter' => array(
				'priority' => 45,
				'callback' => 'pretty_urls_board_filter',
			),
			'settings' => array(
				'pretty_root_url' => 'text',
			),
		),
		'topics' => array(
			'id' => 'topics',
			'enabled' => 1,
			'filter' => array(
				'priority' => 40,
				'callback' => 'pretty_urls_topic_filter',
			),
			'requires' => 'boards',
		),
		'actions' => array(
			'id' => 'actions',
			'enabled' => 0,
			'filter' => array(
				'priority' => 90,
				'callback' => 'pretty_urls_actions_filter',
			),
		),
		'profiles' => array(
			'id' => 'profiles',
			'enabled' => 0,
			'filter' => array(
				'priority' => 80,
				'callback' => 'pretty_profiles_filter',
			),
		),
	);
	$output .= '<li>Saving board names</li>';

	foreach ($pretty_board_urls as $url_id => $url_name)
	{
		if ($url_name != '')
			wesql::query('
				UPDATE {db_prefix}boards
				SET url = {string:url}
				WHERE id_board = {int:url_id} AND url = {string:empty}', array(
					'url' => $url_name,
					'url_id' => $url_id,
					'empty' => ''
				)
			);
	}

	// Add the pretty_root_url and pretty_enable_filters settings:
	$pretty_root_url = isset($modSettings['pretty_root_url']) ? $modSettings['pretty_root_url'] : $boardurl;
	$pretty_enable_filters = isset($modSettings['pretty_enable_filters']) ? $modSettings['pretty_enable_filters'] : '0';

	// Update the settings table
	updateSettings(array(
		'pretty_filters' => serialize($prettyFilters),
	));
	$output .= '<li>Adding some settings</li>';

	// Update the filter callbacks
	pretty_update_filters();
	$output .= '<li>Processing the installed filters</li>';

	return $output . '</ul>';
}

?>