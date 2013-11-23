<?php
/**
 * Various maintenance-related tasks, including member re-attribution, cleaning the forum cache and so on.
 *
 * @package Wedge
 * @copyright 2010 René-Gilles Deberdt, wedge.org
 * @license http://wedge.org/license/
 * @author see contributors.txt
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/* /!!!

	void ManageMaintenance()
		// !!!

	void MaintainDatabase()
		// !!!

	void MaintainMembers()
		// !!!

	void MaintainTopics()
		// !!!

	void MaintainCleanCache()
		// !!!

	void MaintainFindFixErrors()
		// !!!

	void MaintainEmptyUnimportantLogs()
		// !!!

	void ConvertUtf8()
		- converts the data and database tables to UTF-8 character set.
		- requires the admin_forum permission.
		- uses the convert_utf8 block of the Admin template.
		- only works if UTF-8 is not the global character set.
		- supports all character sets used by Wedge's language files.
		- redirects to ?action=admin;area=maintain after finishing.
		- is linked from the maintenance screen (if applicable).
		- accessed by ?action=admin;area=maintain;sa=database;activity=convertutf8.

	void ConvertEntities()
		- converts HTML-entities to UTF-8 characters.
		- requires the admin_forum permission.
		- uses the convert_entities block of the Admin template.
		- only works if UTF-8 has been set as database and global character set.
		- is divided in steps of 10 seconds.
		- is linked from the maintenance screen (if applicable).
		- accessed by ?action=admin;area=maintain;sa=database;activity=convertentities.

	void OptimizeTables()
		- optimizes all tables in the database and lists how much was saved.
		- requires the admin_forum permission.
		- shows as the maintain_forum admin area.
		- updates the optimize scheduled task such that the tables are not
		  automatically optimized again too soon.
		- accessed from ?action=admin;area=maintain;sa=database;activity=optimize.

	void AdminBoardRecount()
		- recounts many forum totals that can be recounted automatically
		  without harm.
		- requires the admin_forum permission.
		- shows the maintain_forum admin area.
		- fixes topics with wrong num_replies.
		- updates the num_posts and num_topics of all boards.
		- recounts instant_messages but not unread_messages.
		- repairs messages pointing to boards with topics pointing to
		  other boards.
		- updates the last message posted in boards and children.
		- updates member count, latest member, topic count, and message count.
		- redirects back to ?action=admin;area=maintain when complete.
		- accessed via ?action=admin;area=maintain;sa=database;activity=recount.

	void VersionDetail()
		- parses the comment headers in all files for their version information
		  and outputs that for some javascript to check with wedge.org.
		- does not connect directly with wedge.org, instead expects the client to.
		- requires the admin_forum permission.
		- uses the view_versions admin area.
		- loads the view_versions block (in the Admin template.)
		- accessed through ?action=admin;area=maintain;sa=routine;activity=version.

	void MaintainReattributePosts()
		// !!!

	void MaintainPurgeInactiveMembers()
		// !!!

	void MaintainRemoveOldPosts(bool do_action = true)
		// !!!

	mixed MaintainMassMoveTopics()
		- Moves topics from one board to another.
		- User the not_done template to pause the process.
*/

// The maintenance access point.
function ManageMaintenance()
{
	global $txt, $context;

	// You absolutely must be an admin by here!
	isAllowedTo('admin_forum');

	// Need something to talk about?
	loadLanguage('ManageMaintenance');
	loadTemplate('ManageMaintenance');

	// This uses admin tabs - as it should!
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['maintain_title'],
		'description' => $txt['maintain_info'],
		'tabs' => array(
			'routine' => array(),
			'database' => array(),
			'members' => array(),
			'topics' => array(),
		),
	);

	// So many things you can do - but frankly I won't let you - just these!
	$subActions = array(
		'routine' => array(
			'function' => 'MaintainRoutine',
			'template' => 'maintain_routine',
			'activities' => array(
				'version' => 'VersionDetail',
				'repair' => 'MaintainFindFixErrors',
				'recount' => 'AdminBoardRecount',
				'logs' => 'MaintainEmptyUnimportantLogs',
				'cleancache' => 'MaintainCleanCache',
			),
		),
		'database' => array(
			'function' => 'MaintainDatabase',
			'template' => 'maintain_database',
			'activities' => array(
				'optimize' => 'OptimizeTables',
				'convertentities' => 'ConvertEntities',
				'convertutf8' => 'ConvertUtf8',
			),
		),
		'members' => array(
			'function' => 'MaintainMembers',
			'template' => 'maintain_members',
			'activities' => array(
				'reattribute' => 'MaintainReattributePosts',
				'purgeinactive' => 'MaintainPurgeInactiveMembers',
				'recountposts' => 'MaintainRecountPosts',
			),
		),
		'topics' => array(
			'function' => 'MaintainTopics',
			'template' => 'maintain_topics',
			'activities' => array(
				'massmove' => 'MaintainMassMoveTopics',
				'pruneold' => 'MaintainRemoveOldPosts',
			),
		),
	);

	// Yep, sub-action time!
	if (isset($_REQUEST['sa'], $subActions[$_REQUEST['sa']]))
		$subAction = $_REQUEST['sa'];
	else
		$subAction = 'routine';

	// Doing something special?
	if (isset($_REQUEST['activity'], $subActions[$subAction]['activities'][$_REQUEST['activity']]))
		$activity = $_REQUEST['activity'];

	// Set a few things.
	$context['page_title'] = $txt['maintain_title'];
	$context['sub_action'] = $subAction;
	if (!empty($subActions[$subAction]['template']))
		wetem::load($subActions[$subAction]['template']);

	// Finally fall through to what we are doing.
	$subActions[$subAction]['function']();

	// Any special activity?
	if (isset($activity))
		$subActions[$subAction]['activities'][$activity]();

	// Converted to UTF-8? Show a maintenance notice.
	if (isset($_GET['done']) && $_GET['done'] == 'convertutf8')
		$context['maintenance_finished'] = $txt['utf8_title'];
}

// Supporting function for the database maintenance area.
// !!! We don't do anything here any more; there's no logic for this function any longer because the only branches it used to make are to do with
//	setting up for the conversions (non-UTF-8 to UTF-8 and entities to UTF-8 chars) which are no longer relevant. Leaving this here for now, though!
function MaintainDatabase()
{
}

// Supporting function for the routine maintenance area.
function MaintainRoutine($return_value = false)
{
	global $context, $txt;

	$context['maintenance_tasks'] = array(
		'maintain_version' => array($txt['maintain_version'], $txt['maintain_version_info'], 'action=admin;area=maintain;sa=routine;activity=version'),
		'maintain_errors' => array($txt['maintain_errors'], $txt['maintain_errors_info'], 'action=admin;area=repairboards'),
		'maintain_recount' => array($txt['maintain_recount'], $txt['maintain_recount_info'], 'action=admin;area=maintain;sa=routine;activity=recount'),
		'maintain_logs' => array($txt['maintain_logs'], $txt['maintain_logs_info'], 'action=admin;area=maintain;sa=routine;activity=logs'),
		'maintain_cache' => array($txt['maintain_cache'], $txt['maintain_cache_info'], 'action=admin;area=maintain;sa=routine;activity=cleancache'),
	);

	call_hook('maintenance_routine', array(&$return_value));

	if ($return_value)
		return $context['maintenance_tasks'];

	if (isset($_GET['done']) && $_GET['done'] == 'recount')
		$context['maintenance_finished'] = $txt['maintain_recount'];
}

// Supporting function for the members maintenance area.
function MaintainMembers()
{
	global $context, $txt;

	// Get membergroups - for deleting members and the like.
	$result = wesql::query('
		SELECT id_group, group_name
		FROM {db_prefix}membergroups',
		array(
		)
	);
	$context['membergroups'] = array(
		array(
			'id' => 0,
			'name' => $txt['maintain_members_ungrouped']
		),
	);
	while ($row = wesql::fetch_assoc($result))
	{
		$context['membergroups'][] = array(
			'id' => $row['id_group'],
			'name' => $row['group_name']
		);
	}
	wesql::free_result($result);

	if (isset($_GET['done']) && $_GET['done'] == 'recountposts')
		$context['maintenance_finished'] = $txt['maintain_recountposts'];
}

// Supporting function for the topics maintenance area.
function MaintainTopics()
{
	global $context, $txt;

	// Let's load up the boards in case they are useful.
	$result = wesql::query('
		SELECT b.id_board, b.name, b.child_level, c.name AS cat_name, c.id_cat
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
		WHERE {query_see_board}
			AND redirect = {string:empty}',
		array(
			'empty' => '',
		)
	);
	$context['categories'] = array();
	while ($row = wesql::fetch_assoc($result))
	{
		if (!isset($context['categories'][$row['id_cat']]))
			$context['categories'][$row['id_cat']] = array(
				'name' => $row['cat_name'],
				'boards' => array()
			);

		$context['categories'][$row['id_cat']]['boards'][] = array(
			'id' => $row['id_board'],
			'name' => $row['name'],
			'child_level' => $row['child_level']
		);
	}
	wesql::free_result($result);

	if (isset($_GET['done']) && $_GET['done'] == 'purgeold')
		$context['maintenance_finished'] = $txt['maintain_old'];
	elseif (isset($_GET['done']) && $_GET['done'] == 'massmove')
		$context['maintenance_finished'] = $txt['move_topics_maintenance'];
}

// Find and fix all errors.
function MaintainFindFixErrors()
{
	loadSource('RepairBoards');
	RepairBoards();
}

// Wipes the whole cache directory.
function MaintainCleanCache()
{
	global $context, $txt;

	// Just wipe the whole cache directory!
	clean_cache();
	clean_cache('js');
	clean_cache('css');

	$context['maintenance_finished'] = $txt['maintain_cache'];
}

// Empties all uninmportant logs
function MaintainEmptyUnimportantLogs()
{
	global $context, $txt;

	checkSession();

	// No one's online now.... MUHAHAHAHA :P
	// Dump the banning and spam logs.
	wesql::query('TRUNCATE {db_prefix}log_online');
	wesql::query('TRUNCATE {db_prefix}log_floodcontrol');

	// Start id_error back at 0 and dump the error log.
	wesql::query('TRUNCATE {db_prefix}log_errors');

	// Last but not least, the search logs!
	wesql::query('TRUNCATE {db_prefix}log_search_topics');
	wesql::query('TRUNCATE {db_prefix}log_search_messages');
	wesql::query('TRUNCATE {db_prefix}log_search_results');

	updateSettings(array('search_pointer' => 0));

	$context['maintenance_finished'] = $txt['maintain_logs'];
}

// !!! This entire function is going to be deprecated. Leaving it here for now as it will become the foundation of the convertor later.
// Convert both data and database tables to UTF-8 character set.
function ConvertUtf8()
{
	global $context, $txt, $settings, $db_prefix, $db_character_set;

	// Show me your badge!
	isAllowedTo('admin_forum');

	// The character sets used in Wedge's language files with their DB equivalent.
	$charsets = array(
		// Chinese-traditional.
		'big5' => 'big5',
		// Chinese-simplified.
		'gbk' => 'gbk',
		// West European.
		'ISO-8859-1' => 'latin1',
		// Romanian.
		'ISO-8859-2' => 'latin2',
		// Turkish.
		'ISO-8859-9' => 'latin5',
		// West European with Euro sign.
		'ISO-8859-15' => 'latin9',
		// Thai.
		'tis-620' => 'tis620',
		// Persian, Chinese, etc.
		'UTF-8' => 'utf8',
		// Russian.
		'windows-1251' => 'cp1251',
		// Greek.
		'windows-1253' => 'utf8',
		// Hebrew.
		'windows-1255' => 'utf8',
		// Arabic.
		'windows-1256' => 'cp1256',
	);

	// Get a list of character sets supported by your MySQL server.
	$request = wesql::query('
		SHOW CHARACTER SET',
		array(
		)
	);
	$db_charsets = array();
	while ($row = wesql::fetch_assoc($request))
		$db_charsets[] = $row['Charset'];

	wesql::free_result($request);

	// Character sets supported by both MySQL and Wedge's language files.
	$charsets = array_intersect($charsets, $db_charsets);

	// This is for the first screen telling backups are good.
	if (!isset($_POST['proceed']))
	{
		// Use the messages.body column as indicator for the database charset.
		$request = wesql::query('
			SHOW FULL COLUMNS
			FROM {db_prefix}messages
			LIKE {literal:body}'
		);
		$column_info = wesql::fetch_assoc($request);
		wesql::free_result($request);

		// A collation looks like latin1_swedish. We only need the character set.
		list ($context['database_charset']) = explode('_', $column_info['Collation']);
		$context['database_charset'] = in_array($context['database_charset'], $charsets) ? array_search($context['database_charset'], $charsets) : $context['database_charset'];

		// No need to convert to UTF-8 if it already is.
		if ($db_character_set === 'utf8' && !empty($settings['global_character_set']) && $settings['global_character_set'] === 'UTF-8')
			fatal_lang_error('utf8_already_utf8');

		// Grab the character set from the default language file.
		loadLanguage('index', $settings['language'], true);
		$context['charset_detected'] = $txt['lang_character_set'];
		$context['charset_about_detected'] = sprintf($txt['utf8_detected_charset'], $settings['language'], $context['charset_detected']);

		// Go back to your own language.
		loadLanguage('index', we::$user['language'], true);

		// Show a warning if the character set seems not to be supported.
		if (!isset($charsets[strtr(strtolower($context['charset_detected']), array('utf' => 'UTF', 'iso' => 'ISO'))]))
		{
			$context['charset_warning'] = sprintf($txt['utf8_charset_not_supported'], $txt['lang_character_set']);

			// Default to ISO-8859-1.
			$context['charset_detected'] = 'ISO-8859-1';
		}

		$context['charset_list'] = array_keys($charsets);

		$context['page_title'] = $txt['utf8_title'];
		wetem::load('convert_utf8');
		return;
	}

	// After this point we're starting the conversion. But first: session check.
	checkSession();

	// Translation table for the character sets not native for MySQL.
	$translation_tables = array(
		'windows-1255' => array(
			'0x81' => '\'\'',		'0x8A' => '\'\'',		'0x8C' => '\'\'',
			'0x8D' => '\'\'',		'0x8E' => '\'\'',		'0x8F' => '\'\'',
			'0x90' => '\'\'',		'0x9A' => '\'\'',		'0x9C' => '\'\'',
			'0x9D' => '\'\'',		'0x9E' => '\'\'',		'0x9F' => '\'\'',
			'0xCA' => '\'\'',		'0xD9' => '\'\'',		'0xDA' => '\'\'',
			'0xDB' => '\'\'',		'0xDC' => '\'\'',		'0xDD' => '\'\'',
			'0xDE' => '\'\'',		'0xDF' => '\'\'',		'0xFB' => '\'\'',
			'0xFC' => '\'\'',		'0xFF' => '\'\'',		'0xC2' => '0xFF',
			'0x80' => '0xFC',		'0xE2' => '0xFB',		'0xA0' => '0xC2A0',
			'0xA1' => '0xC2A1',		'0xA2' => '0xC2A2',		'0xA3' => '0xC2A3',
			'0xA5' => '0xC2A5',		'0xA6' => '0xC2A6',		'0xA7' => '0xC2A7',
			'0xA8' => '0xC2A8',		'0xA9' => '0xC2A9',		'0xAB' => '0xC2AB',
			'0xAC' => '0xC2AC',		'0xAD' => '0xC2AD',		'0xAE' => '0xC2AE',
			'0xAF' => '0xC2AF',		'0xB0' => '0xC2B0',		'0xB1' => '0xC2B1',
			'0xB2' => '0xC2B2',		'0xB3' => '0xC2B3',		'0xB4' => '0xC2B4',
			'0xB5' => '0xC2B5',		'0xB6' => '0xC2B6',		'0xB7' => '0xC2B7',
			'0xB8' => '0xC2B8',		'0xB9' => '0xC2B9',		'0xBB' => '0xC2BB',
			'0xBC' => '0xC2BC',		'0xBD' => '0xC2BD',		'0xBE' => '0xC2BE',
			'0xBF' => '0xC2BF',		'0xD7' => '0xD7B3',		'0xD1' => '0xD781',
			'0xD4' => '0xD7B0',		'0xD5' => '0xD7B1',		'0xD6' => '0xD7B2',
			'0xE0' => '0xD790',		'0xEA' => '0xD79A',		'0xEC' => '0xD79C',
			'0xED' => '0xD79D',		'0xEE' => '0xD79E',		'0xEF' => '0xD79F',
			'0xF0' => '0xD7A0',		'0xF1' => '0xD7A1',		'0xF2' => '0xD7A2',
			'0xF3' => '0xD7A3',		'0xF5' => '0xD7A5',		'0xF6' => '0xD7A6',
			'0xF7' => '0xD7A7',		'0xF8' => '0xD7A8',		'0xF9' => '0xD7A9',
			'0x82' => '0xE2809A',	'0x84' => '0xE2809E',	'0x85' => '0xE280A6',
			'0x86' => '0xE280A0',	'0x87' => '0xE280A1',	'0x89' => '0xE280B0',
			'0x8B' => '0xE280B9',	'0x93' => '0xE2809C',	'0x94' => '0xE2809D',
			'0x95' => '0xE280A2',	'0x97' => '0xE28094',	'0x99' => '0xE284A2',
			'0xC0' => '0xD6B0',		'0xC1' => '0xD6B1',		'0xC3' => '0xD6B3',
			'0xC4' => '0xD6B4',		'0xC5' => '0xD6B5',		'0xC6' => '0xD6B6',
			'0xC7' => '0xD6B7',		'0xC8' => '0xD6B8',		'0xC9' => '0xD6B9',
			'0xCB' => '0xD6BB',		'0xCC' => '0xD6BC',		'0xCD' => '0xD6BD',
			'0xCE' => '0xD6BE',		'0xCF' => '0xD6BF',		'0xD0' => '0xD780',
			'0xD2' => '0xD782',		'0xE3' => '0xD793',		'0xE4' => '0xD794',
			'0xE5' => '0xD795',		'0xE7' => '0xD797',		'0xE9' => '0xD799',
			'0xFD' => '0xE2808E',	'0xFE' => '0xE2808F',	'0x92' => '0xE28099',
			'0x83' => '0xC692',		'0xD3' => '0xD783',		'0x88' => '0xCB86',
			'0x98' => '0xCB9C',		'0x91' => '0xE28098',	'0x96' => '0xE28093',
			'0xBA' => '0xC3B7',		'0x9B' => '0xE280BA',	'0xAA' => '0xC397',
			'0xA4' => '0xE282AA',	'0xE1' => '0xD791',		'0xE6' => '0xD796',
			'0xE8' => '0xD798',		'0xEB' => '0xD79B',		'0xF4' => '0xD7A4',
			'0xFA' => '0xD7AA',		'0xFF' => '0xD6B2',		'0xFC' => '0xE282AC',
			'0xFB' => '0xD792',
		),
		'windows-1253' => array(
			'0x81' => '\'\'',			'0x88' => '\'\'',			'0x8A' => '\'\'',
			'0x8C' => '\'\'',			'0x8D' => '\'\'',			'0x8E' => '\'\'',
			'0x8F' => '\'\'',			'0x90' => '\'\'',			'0x98' => '\'\'',
			'0x9A' => '\'\'',			'0x9C' => '\'\'',			'0x9D' => '\'\'',
			'0x9E' => '\'\'',			'0x9F' => '\'\'',			'0xAA' => '\'\'',
			'0xD2' => '\'\'',			'0xFF' => '\'\'',			'0xCE' => '0xCE9E',
			'0xB8' => '0xCE88',		'0xBA' => '0xCE8A',		'0xBC' => '0xCE8C',
			'0xBE' => '0xCE8E',		'0xBF' => '0xCE8F',		'0xC0' => '0xCE90',
			'0xC8' => '0xCE98',		'0xCA' => '0xCE9A',		'0xCC' => '0xCE9C',
			'0xCD' => '0xCE9D',		'0xCF' => '0xCE9F',		'0xDA' => '0xCEAA',
			'0xE8' => '0xCEB8',		'0xEA' => '0xCEBA',		'0xEC' => '0xCEBC',
			'0xEE' => '0xCEBE',		'0xEF' => '0xCEBF',		'0xC2' => '0xFF',
			'0xBD' => '0xC2BD',		'0xED' => '0xCEBD',		'0xB2' => '0xC2B2',
			'0xA0' => '0xC2A0',		'0xA3' => '0xC2A3',		'0xA4' => '0xC2A4',
			'0xA5' => '0xC2A5',		'0xA6' => '0xC2A6',		'0xA7' => '0xC2A7',
			'0xA8' => '0xC2A8',		'0xA9' => '0xC2A9',		'0xAB' => '0xC2AB',
			'0xAC' => '0xC2AC',		'0xAD' => '0xC2AD',		'0xAE' => '0xC2AE',
			'0xB0' => '0xC2B0',		'0xB1' => '0xC2B1',		'0xB3' => '0xC2B3',
			'0xB5' => '0xC2B5',		'0xB6' => '0xC2B6',		'0xB7' => '0xC2B7',
			'0xBB' => '0xC2BB',		'0xE2' => '0xCEB2',		'0x80' => '0xD2',
			'0x82' => '0xE2809A',	'0x84' => '0xE2809E',	'0x85' => '0xE280A6',
			'0x86' => '0xE280A0',	'0xA1' => '0xCE85',		'0xA2' => '0xCE86',
			'0x87' => '0xE280A1',	'0x89' => '0xE280B0',	'0xB9' => '0xCE89',
			'0x8B' => '0xE280B9',	'0x91' => '0xE28098',	'0x99' => '0xE284A2',
			'0x92' => '0xE28099',	'0x93' => '0xE2809C',	'0x94' => '0xE2809D',
			'0x95' => '0xE280A2',	'0x96' => '0xE28093',	'0x97' => '0xE28094',
			'0x9B' => '0xE280BA',	'0xAF' => '0xE28095',	'0xB4' => '0xCE84',
			'0xC1' => '0xCE91',		'0xC3' => '0xCE93',		'0xC4' => '0xCE94',
			'0xC5' => '0xCE95',		'0xC6' => '0xCE96',		'0x83' => '0xC692',
			'0xC7' => '0xCE97',		'0xC9' => '0xCE99',		'0xCB' => '0xCE9B',
			'0xD0' => '0xCEA0',		'0xD1' => '0xCEA1',		'0xD3' => '0xCEA3',
			'0xD4' => '0xCEA4',		'0xD5' => '0xCEA5',		'0xD6' => '0xCEA6',
			'0xD7' => '0xCEA7',		'0xD8' => '0xCEA8',		'0xD9' => '0xCEA9',
			'0xDB' => '0xCEAB',		'0xDC' => '0xCEAC',		'0xDD' => '0xCEAD',
			'0xDE' => '0xCEAE',		'0xDF' => '0xCEAF',		'0xE0' => '0xCEB0',
			'0xE1' => '0xCEB1',		'0xE3' => '0xCEB3',		'0xE4' => '0xCEB4',
			'0xE5' => '0xCEB5',		'0xE6' => '0xCEB6',		'0xE7' => '0xCEB7',
			'0xE9' => '0xCEB9',		'0xEB' => '0xCEBB',		'0xF0' => '0xCF80',
			'0xF1' => '0xCF81',		'0xF2' => '0xCF82',		'0xF3' => '0xCF83',
			'0xF4' => '0xCF84',		'0xF5' => '0xCF85',		'0xF6' => '0xCF86',
			'0xF7' => '0xCF87',		'0xF8' => '0xCF88',		'0xF9' => '0xCF89',
			'0xFA' => '0xCF8A',		'0xFB' => '0xCF8B',		'0xFC' => '0xCF8C',
			'0xFD' => '0xCF8D',		'0xFE' => '0xCF8E',		'0xFF' => '0xCE92',
			'0xD2' => '0xE282AC',
		),
	);

	// Make some preparations.
	if (isset($translation_tables[$_POST['src_charset']]))
	{
		$replace = '%field%';
		foreach ($translation_tables[$_POST['src_charset']] as $from => $to)
			$replace = 'REPLACE(' . $replace . ', ' . $from . ', ' . $to . ')';
	}

	// Grab a list of tables.
	if (preg_match('~^`(.+?)`\.(.+?)$~', $db_prefix, $match) === 1)
		$queryTables = wesql::query('
			SHOW TABLE STATUS
			FROM `' . strtr($match[1], array('`' => '')) . '`
			LIKE {string:table_name}',
			array(
				'table_name' => str_replace('_', '\_', $match[2]) . '%',
			)
		);
	else
		$queryTables = wesql::query('
			SHOW TABLE STATUS
			LIKE {string:table_name}',
			array(
				'table_name' => str_replace('_', '\_', $db_prefix) . '%',
			)
		);

	while ($table_info = wesql::fetch_assoc($queryTables))
	{
		// Just to make sure it doesn't time out.
		if (function_exists('apache_reset_timeout'))
			@apache_reset_timeout();

		$table_charsets = array();

		// Loop through each column.
		$queryColumns = wesql::query('
			SHOW FULL COLUMNS
			FROM ' . $table_info['Name'],
			array(
			)
		);
		while ($column_info = wesql::fetch_assoc($queryColumns))
		{
			// Only text'ish columns have a character set and need converting.
			if (strpos($column_info['Type'], 'text') !== false || strpos($column_info['Type'], 'char') !== false)
			{
				$collation = empty($column_info['Collation']) || $column_info['Collation'] === 'NULL' ? $table_info['Collation'] : $column_info['Collation'];
				if (!empty($collation) && $collation !== 'NULL')
				{
					list ($charset) = explode('_', $collation);

					if (!isset($table_charsets[$charset]))
						$table_charsets[$charset] = array();

					$table_charsets[$charset][] = $column_info;
				}
			}
		}
		wesql::free_result($queryColumns);

		// Only change the column if the data doesn't match the current charset.
		if ((count($table_charsets) === 1 && key($table_charsets) !== $charsets[$_POST['src_charset']]) || count($table_charsets) > 1)
		{
			$updates_blob = '';
			$updates_text = '';
			foreach ($table_charsets as $charset => $columns)
			{
				if ($charset !== $charsets[$_POST['src_charset']])
				{
					foreach ($columns as $column)
					{
						$updates_blob .= '
							CHANGE COLUMN ' . $column['Field'] . ' ' . $column['Field'] . ' ' . strtr($column['Type'], array('text' => 'blob', 'char' => 'binary')) . ($column['Null'] === 'YES' ? ' NULL' : ' NOT NULL') . (strpos($column['Type'], 'char') === false ? '' : ' default \'' . $column['Default'] . '\'') . ',';
						$updates_text .= '
							CHANGE COLUMN ' . $column['Field'] . ' ' . $column['Field'] . ' ' . $column['Type'] . ' CHARACTER SET ' . $charsets[$_POST['src_charset']] . ($column['Null'] === 'YES' ? '' : ' NOT NULL') . (strpos($column['Type'], 'char') === false ? '' : ' default \'' . $column['Default'] . '\'') . ',';
					}
				}
			}

			// Change the columns to binary form.
			wesql::query('
				ALTER TABLE {raw:table_name}{raw:updates_blob}',
				array(
					'table_name' => $table_info['Name'],
					'updates_blob' => substr($updates_blob, 0, -1),
				)
			);

			// Convert the character set if MySQL has no native support for it.
			if (isset($translation_tables[$_POST['src_charset']]))
			{
				$update = '';
				foreach ($table_charsets as $charset => $columns)
					foreach ($columns as $column)
						$update .= '
							' . $column['Field'] . ' = ' . strtr($replace, array('%field%' => $column['Field'])) . ',';

				wesql::query('
					UPDATE {raw:table_name}
					SET {raw:updates}',
					array(
						'table_name' => $table_info['Name'],
						'updates' => substr($update, 0, -1),
					)
				);
			}

			// Change the columns back, but with the proper character set.
			wesql::query('
				ALTER TABLE {raw:table_name}{raw:updates_text}',
				array(
					'table_name' => $table_info['Name'],
					'updates_text' => substr($updates_text, 0, -1),
				)
			);
		}

		// Now do the actual conversion (if still needed).
		if ($charsets[$_POST['src_charset']] !== 'utf8')
			wesql::query('
				ALTER TABLE {raw:table_name}
				CONVERT TO CHARACTER SET utf8',
				array(
					'table_name' => $table_info['Name'],
				)
			);
	}
	wesql::free_result($queryTables);

	// Let the settings know we have a new character set.
	updateSettings(array(
		'global_character_set' => 'UTF-8',
		'previousCharacterSet' => empty($translation_tables[$_POST['src_charset']]) ? $charsets[$_POST['src_charset']] : $translation_tables[$_POST['src_charset']]
	));

	// Store it in Settings.php too because it's needed before db connection.
	loadSource('Subs-Admin');
	updateSettingsFile(array('db_character_set' => 'utf8'));

	// The conversion might have messed up some serialized strings. Fix them!
	loadSource('Subs-Charset');
	fix_serialized_columns();

	redirectexit('action=admin;area=maintain;done=convertutf8');
}

// !!! Is this still needed now that we have TE's importer? If not, kill it.
// Convert HTML-entities to their UTF-8 character equivalents.
function ConvertEntities()
{
	global $db_character_set, $settings, $context;

	isAllowedTo('admin_forum');

	// Check to see if UTF-8 is currently the default character set.
	if ($settings['global_character_set'] !== 'UTF-8' || !isset($db_character_set) || $db_character_set !== 'utf8')
		fatal_lang_error('entity_convert_only_utf8');

	// Some starting values.
	$context['table'] = empty($_REQUEST['table']) ? 0 : (int) $_REQUEST['table'];
	$context['start'] = empty($_REQUEST['start']) ? 0 : (int) $_REQUEST['start'];

	$context['start_time'] = time();

	$context['first_step'] = !isset($_REQUEST[$context['session_var']]);
	$context['last_step'] = false;

	// The first step is just a text screen with some explanation.
	if ($context['first_step'])
	{
		wetem::load('convert_entities');
		return;
	}
	// Otherwise use the generic "not done" template.
	wetem::load('not_done');
	$context['continue_post_data'] = '';
	$context['continue_countdown'] = 3;

	// Now we're actually going to convert...
	checkSession('request');

	// A list of tables ready for conversion.
	$tables = array(
		'boards',
		'categories',
		'log_errors',
		'log_search_subjects',
		'membergroups',
		'members',
		'message_icons',
		'messages',
		'personal_messages',
		'plugin_servers',
		'pm_recipients',
		'polls',
		'poll_choices',
		'smileys',
		'themes',
	);
	$context['num_tables'] = count($tables);

	// This function will do the conversion later on.
	$entity_replace = create_function('$matches', '
		$string = $matches[1];
		$num = $string[0] === \'x\' ? hexdec(substr($string, 1)) : (int) $string;
		return $num < 0x20 || $num > 0x10FFFF || ($num >= 0xD800 && $num <= 0xDFFF) ? \'\' : ($num < 0x80 ? \'&#\' . $num . \';\' : ($num < 0x800 ? chr(192 | $num >> 6) . chr(128 | $num & 63) : ($num < 0x10000 ? chr(224 | $num >> 12) . chr(128 | $num >> 6 & 63) . chr(128 | $num & 63) : chr(240 | $num >> 18) . chr(128 | $num >> 12 & 63) . chr(128 | $num >> 6 & 63) . chr(128 | $num & 63))));');

	// Loop through all tables that need converting.
	for (; $context['table'] < $context['num_tables']; $context['table']++)
	{
		$cur_table = $tables[$context['table']];
		$primary_key = '';
		// Make sure we keep stuff unique!
		$primary_keys = array();

		if (function_exists('apache_reset_timeout'))
			@apache_reset_timeout();

		// Get a list of text columns.
		$columns = array();
		$request = wesql::query('
			SHOW FULL COLUMNS
			FROM {db_prefix}' . $cur_table,
			array(
			)
		);
		while ($column_info = wesql::fetch_assoc($request))
			if (strpos($column_info['Type'], 'text') !== false || strpos($column_info['Type'], 'char') !== false)
				$columns[] = strtolower($column_info['Field']);

		// Get the column with the (first) primary key.
		$request = wesql::query('
			SHOW KEYS
			FROM {db_prefix}' . $cur_table,
			array(
			)
		);
		while ($row = wesql::fetch_assoc($request))
		{
			if ($row['Key_name'] === 'PRIMARY')
			{
				if (empty($primary_key) || ($row['Seq_in_index'] == 1 && !in_array(strtolower($row['Column_name']), $columns)))
					$primary_key = $row['Column_name'];

				$primary_keys[] = $row['Column_name'];
			}
		}
		wesql::free_result($request);

		// No primary key, no glory.
		// Same for columns. Just to be sure we've work to do!
		if (empty($primary_key) || empty($columns))
			continue;

		// Get the maximum value for the primary key.
		$request = wesql::query('
			SELECT MAX(' . $primary_key . ')
			FROM {db_prefix}' . $cur_table,
			array(
			)
		);
		list ($max_value) = wesql::fetch_row($request);
		wesql::free_result($request);

		if (empty($max_value))
			continue;

		while ($context['start'] <= $max_value)
		{
			// Retrieve a list of rows that has at least one entity to convert.
			$request = wesql::query('
				SELECT {raw:primary_keys}, {raw:columns}
				FROM {db_prefix}{raw:cur_table}
				WHERE {raw:primary_key} BETWEEN {int:start} AND {int:start} + 499
					AND {raw:like_compare}
				LIMIT 500',
				array(
					'primary_keys' => implode(', ', $primary_keys),
					'columns' => implode(', ', $columns),
					'cur_table' => $cur_table,
					'primary_key' => $primary_key,
					'start' => $context['start'],
					'like_compare' => '(' . implode(' LIKE \'%&#%\' OR ', $columns) . ' LIKE \'%&#%\')',
				)
			);
			while ($row = wesql::fetch_assoc($request))
			{
				$insertion_variables = array();
				$changes = array();
				foreach ($row as $column_name => $column_value)
					if ($column_name !== $primary_key && strpos($column_value, '&#') !== false)
					{
						$changes[] = $column_name . ' = {string:changes_' . $column_name . '}';
						$insertion_variables['changes_' . $column_name] = preg_replace_callback('~&#(\d{1,7}|x[0-9a-fA-F]{1,6});~', $entity_replace, $column_value);
					}

				$where = array();
				foreach ($primary_keys as $key)
				{
					$where[] = $key . ' = {string:where_' . $key . '}';
					$insertion_variables['where_' . $key] = $row[$key];
				}

				// Update the row.
				if (!empty($changes))
					wesql::query('
						UPDATE {db_prefix}' . $cur_table . '
						SET
							' . implode(',
							', $changes) . '
						WHERE ' . implode(' AND ', $where),
						$insertion_variables
					);
			}
			wesql::free_result($request);
			$context['start'] += 500;

			// After ten seconds interrupt.
			if (time() - $context['start_time'] > 10)
			{
				// Calculate an approximation of the percentage done.
				$context['continue_percent'] = round(100 * ($context['table'] + ($context['start'] / $max_value)) / $context['num_tables'], 1);
				$context['continue_get_data'] = '?action=admin;area=maintain;sa=database;activity=convertentities;table=' . $context['table'] . ';start=' . $context['start'] . ';' . $context['session_query'];
				return;
			}
		}
		$context['start'] = 0;
	}

	// Make sure all serialized strings are all right.
	loadSource('Subs-Charset');
	fix_serialized_columns();

	// If we're here, we must be done.
	$context['continue_percent'] = 100;
	$context['continue_get_data'] = '?action=admin;area=maintain;sa=database;done=convertentities';
	$context['last_step'] = true;
	$context['continue_countdown'] = -1;
}

// Optimize the database's tables.
function OptimizeTables()
{
	global $db_prefix, $txt, $context;

	isAllowedTo('admin_forum');

	checkSession('post');

	ignore_user_abort(true);
	loadSource('Class-DBPackages');

	// Start with no tables optimized.
	$opttab = 0;

	$context['page_title'] = $txt['database_optimize'];
	wetem::load('optimize');

	// Only optimize the tables related to this Wedge install, not all the tables in the DB...
	$real_prefix = preg_match('~^(`?)(.+?)\\1\\.(.*?)$~', $db_prefix, $match) === 1 ? $match[3] : $db_prefix;

	// Get a list of tables, as well as how many there are.
	$temp_tables = wedbPackages::list_tables(false, $real_prefix . '%');
	$tables = array();
	foreach ($temp_tables as $table)
		$tables[] = array('table_name' => $table);

	// We're getting this for the display later. We could, theoretically, test to make sure it's non-zero, but if it's zero, we shouldn't have made it this far anyway (e.g. the settings table, the members table would have been queried)
	$context['num_tables'] = count($tables);

	// For each table....
	$context['optimized_tables'] = array();
	foreach ($tables as $table)
	{
		// Optimize the table!  We use backticks here because it might be a custom table.
		$data_freed = wedbPackages::optimize_table($table['table_name']);

		if ($data_freed > 0)
			$context['optimized_tables'][] = array(
				'name' => $table['table_name'],
				'data_freed' => $data_freed,
			);
	}

	// Number of tables, etc....
	$txt['database_numb_tables'] = sprintf($txt['database_numb_tables'], $context['num_tables']);
	$context['num_tables_optimized'] = count($context['optimized_tables']);

	// Check that we don't auto-optimize again too soon!
	loadSource('ScheduledTasks');
	CalculateNextTrigger('auto_optimize', true);
}

// Recount all the important board totals.
function AdminBoardRecount()
{
	global $txt, $context, $settings;
	global $time_start;

	isAllowedTo('admin_forum');

	checkSession('request');

	$context['page_title'] = $txt['not_done_title'];
	$context['continue_post_data'] = '';
	$context['continue_countdown'] = '3';
	wetem::load('not_done');

	// Try for as much time as possible.
	@set_time_limit(600);

	// Step the number of topics at a time so things don't time out...
	$request = wesql::query('
		SELECT MAX(id_topic)
		FROM {db_prefix}topics',
		array(
		)
	);
	list ($max_topics) = wesql::fetch_row($request);
	wesql::free_result($request);

	$increment = min(max(50, ceil($max_topics / 4)), 2000);
	if (empty($_REQUEST['start']))
		$_REQUEST['start'] = 0;

	$total_steps = 8;

	// Get each topic with a wrong reply count and fix it - let's just do some at a time, though.
	if (empty($_REQUEST['step']))
	{
		$_REQUEST['step'] = 0;

		while ($_REQUEST['start'] < $max_topics)
		{
			// Recount approved messages
			$request = wesql::query('
				SELECT /*!40001 SQL_NO_CACHE */ t.id_topic, MAX(t.num_replies) AS num_replies,
					CASE WHEN COUNT(ma.id_msg) >= 1 THEN COUNT(ma.id_msg) - 1 ELSE 0 END AS real_num_replies
				FROM {db_prefix}topics AS t
					LEFT JOIN {db_prefix}messages AS ma ON (ma.id_topic = t.id_topic AND ma.approved = {int:is_approved})
				WHERE t.id_topic > {int:start}
					AND t.id_topic <= {int:max_id}
				GROUP BY t.id_topic
				HAVING CASE WHEN COUNT(ma.id_msg) >= 1 THEN COUNT(ma.id_msg) - 1 ELSE 0 END != MAX(t.num_replies)',
				array(
					'is_approved' => 1,
					'start' => $_REQUEST['start'],
					'max_id' => $_REQUEST['start'] + $increment,
				)
			);
			while ($row = wesql::fetch_assoc($request))
				wesql::query('
					UPDATE {db_prefix}topics
					SET num_replies = {int:num_replies}
					WHERE id_topic = {int:id_topic}',
					array(
						'num_replies' => $row['real_num_replies'],
						'id_topic' => $row['id_topic'],
					)
				);
			wesql::free_result($request);

			// Recount unapproved messages
			$request = wesql::query('
				SELECT /*!40001 SQL_NO_CACHE */ t.id_topic, MAX(t.unapproved_posts) AS unapproved_posts,
					COUNT(mu.id_msg) AS real_unapproved_posts
				FROM {db_prefix}topics AS t
					LEFT JOIN {db_prefix}messages AS mu ON (mu.id_topic = t.id_topic AND mu.approved = {int:not_approved})
				WHERE t.id_topic > {int:start}
					AND t.id_topic <= {int:max_id}
				GROUP BY t.id_topic
				HAVING COUNT(mu.id_msg) != MAX(t.unapproved_posts)',
				array(
					'not_approved' => 0,
					'start' => $_REQUEST['start'],
					'max_id' => $_REQUEST['start'] + $increment,
				)
			);
			while ($row = wesql::fetch_assoc($request))
				wesql::query('
					UPDATE {db_prefix}topics
					SET unapproved_posts = {int:unapproved_posts}
					WHERE id_topic = {int:id_topic}',
					array(
						'unapproved_posts' => $row['real_unapproved_posts'],
						'id_topic' => $row['id_topic'],
					)
				);
			wesql::free_result($request);

			$_REQUEST['start'] += $increment;

			if (microtime(true) - $time_start > 3)
			{
				$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=0;start=' . $_REQUEST['start'] . ';' . $context['session_query'];
				$context['continue_percent'] = round((100 * $_REQUEST['start'] / $max_topics) / $total_steps);

				return;
			}
		}

		$_REQUEST['start'] = 0;
	}

	// Update the post count of each board.
	if ($_REQUEST['step'] <= 1)
	{
		if (empty($_REQUEST['start']))
			wesql::query('
				UPDATE {db_prefix}boards
				SET num_posts = {int:num_posts}
				WHERE redirect = {string:empty}',
				array(
					'num_posts' => 0,
					'empty' => '',
				)
			);

		while ($_REQUEST['start'] < $max_topics)
		{
			$request = wesql::query('
				SELECT /*!40001 SQL_NO_CACHE */ m.id_board, COUNT(*) AS real_num_posts
				FROM {db_prefix}messages AS m
				WHERE m.id_topic > {int:id_topic_min}
					AND m.id_topic <= {int:id_topic_max}
					AND m.approved = {int:is_approved}
				GROUP BY m.id_board',
				array(
					'id_topic_min' => $_REQUEST['start'],
					'id_topic_max' => $_REQUEST['start'] + $increment,
					'is_approved' => 1,
				)
			);
			while ($row = wesql::fetch_assoc($request))
				wesql::query('
					UPDATE {db_prefix}boards
					SET num_posts = num_posts + {int:real_num_posts}
					WHERE id_board = {int:id_board}',
					array(
						'id_board' => $row['id_board'],
						'real_num_posts' => $row['real_num_posts'],
					)
				);
			wesql::free_result($request);

			$_REQUEST['start'] += $increment;

			if (microtime(true) - $time_start > 3)
			{
				$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=1;start=' . $_REQUEST['start'] . ';' . $context['session_query'];
				$context['continue_percent'] = round((200 + 100 * $_REQUEST['start'] / $max_topics) / $total_steps);

				return;
			}
		}

		$_REQUEST['start'] = 0;
	}

	// Update the topic count of each board.
	if ($_REQUEST['step'] <= 2)
	{
		if (empty($_REQUEST['start']))
			wesql::query('
				UPDATE {db_prefix}boards
				SET num_topics = {int:num_topics}',
				array(
					'num_topics' => 0,
				)
			);

		while ($_REQUEST['start'] < $max_topics)
		{
			$request = wesql::query('
				SELECT /*!40001 SQL_NO_CACHE */ t.id_board, COUNT(*) AS real_num_topics
				FROM {db_prefix}topics AS t
				WHERE t.approved = {int:is_approved}
					AND t.id_topic > {int:id_topic_min}
					AND t.id_topic <= {int:id_topic_max}
				GROUP BY t.id_board',
				array(
					'is_approved' => 1,
					'id_topic_min' => $_REQUEST['start'],
					'id_topic_max' => $_REQUEST['start'] + $increment,
				)
			);
			while ($row = wesql::fetch_assoc($request))
				wesql::query('
					UPDATE {db_prefix}boards
					SET num_topics = num_topics + {int:real_num_topics}
					WHERE id_board = {int:id_board}',
					array(
						'id_board' => $row['id_board'],
						'real_num_topics' => $row['real_num_topics'],
					)
				);
			wesql::free_result($request);

			$_REQUEST['start'] += $increment;

			if (microtime(true) - $time_start > 3)
			{
				$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=2;start=' . $_REQUEST['start'] . ';' . $context['session_query'];
				$context['continue_percent'] = round((300 + 100 * $_REQUEST['start'] / $max_topics) / $total_steps);

				return;
			}
		}

		$_REQUEST['start'] = 0;
	}

	// Update the unapproved post count of each board.
	if ($_REQUEST['step'] <= 3)
	{
		if (empty($_REQUEST['start']))
			wesql::query('
				UPDATE {db_prefix}boards
				SET unapproved_posts = {int:unapproved_posts}',
				array(
					'unapproved_posts' => 0,
				)
			);

		while ($_REQUEST['start'] < $max_topics)
		{
			$request = wesql::query('
				SELECT /*!40001 SQL_NO_CACHE */ m.id_board, COUNT(*) AS real_unapproved_posts
				FROM {db_prefix}messages AS m
				WHERE m.id_topic > {int:id_topic_min}
					AND m.id_topic <= {int:id_topic_max}
					AND m.approved = {int:is_approved}
				GROUP BY m.id_board',
				array(
					'id_topic_min' => $_REQUEST['start'],
					'id_topic_max' => $_REQUEST['start'] + $increment,
					'is_approved' => 0,
				)
			);
			while ($row = wesql::fetch_assoc($request))
				wesql::query('
					UPDATE {db_prefix}boards
					SET unapproved_posts = unapproved_posts + {int:unapproved_posts}
					WHERE id_board = {int:id_board}',
					array(
						'id_board' => $row['id_board'],
						'unapproved_posts' => $row['real_unapproved_posts'],
					)
				);
			wesql::free_result($request);

			$_REQUEST['start'] += $increment;

			if (microtime(true) - $time_start > 3)
			{
				$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=3;start=' . $_REQUEST['start'] . ';' . $context['session_query'];
				$context['continue_percent'] = round((400 + 100 * $_REQUEST['start'] / $max_topics) / $total_steps);

				return;
			}
		}

		$_REQUEST['start'] = 0;
	}

	// Update the unapproved topic count of each board.
	if ($_REQUEST['step'] <= 4)
	{
		if (empty($_REQUEST['start']))
			wesql::query('
				UPDATE {db_prefix}boards
				SET unapproved_topics = {int:unapproved_topics}',
				array(
					'unapproved_topics' => 0,
				)
			);

		while ($_REQUEST['start'] < $max_topics)
		{
			$request = wesql::query('
				SELECT /*!40001 SQL_NO_CACHE */ t.id_board, COUNT(*) AS real_unapproved_topics
				FROM {db_prefix}topics AS t
				WHERE t.approved = {int:is_unapproved}
					AND t.id_topic > {int:id_topic_min}
					AND t.id_topic <= {int:id_topic_max}
				GROUP BY t.id_board',
				array(
					'is_unapproved' => 0,
					'id_topic_min' => $_REQUEST['start'],
					'id_topic_max' => $_REQUEST['start'] + $increment,
				)
			);
			while ($row = wesql::fetch_assoc($request))
				wesql::query('
					UPDATE {db_prefix}boards
					SET unapproved_topics = unapproved_topics + {int:real_unapproved_topics}
					WHERE id_board = {int:id_board}',
					array(
						'id_board' => $row['id_board'],
						'real_unapproved_topics' => $row['real_unapproved_topics'],
					)
				);
			wesql::free_result($request);

			$_REQUEST['start'] += $increment;

			if (microtime(true) - $time_start > 3)
			{
				$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=4;start=' . $_REQUEST['start'] . ';' . $context['session_query'];
				$context['continue_percent'] = round((500 + 100 * $_REQUEST['start'] / $max_topics) / $total_steps);

				return;
			}
		}

		$_REQUEST['start'] = 0;
	}

	// Get all members with wrong number of personal messages.
	if ($_REQUEST['step'] <= 5)
	{
		$request = wesql::query('
			SELECT /*!40001 SQL_NO_CACHE */ mem.id_member, COUNT(pmr.id_pm) AS real_num,
				MAX(mem.instant_messages) AS instant_messages
			FROM {db_prefix}members AS mem
				LEFT JOIN {db_prefix}pm_recipients AS pmr ON (mem.id_member = pmr.id_member AND pmr.deleted = {int:is_not_deleted})
			GROUP BY mem.id_member
			HAVING COUNT(pmr.id_pm) != MAX(mem.instant_messages)',
			array(
				'is_not_deleted' => 0,
			)
		);
		while ($row = wesql::fetch_assoc($request))
			updateMemberData($row['id_member'], array('instant_messages' => $row['real_num']));
		wesql::free_result($request);

		$request = wesql::query('
			SELECT /*!40001 SQL_NO_CACHE */ mem.id_member, COUNT(pmr.id_pm) AS real_num,
				MAX(mem.unread_messages) AS unread_messages
			FROM {db_prefix}members AS mem
				LEFT JOIN {db_prefix}pm_recipients AS pmr ON (mem.id_member = pmr.id_member AND pmr.deleted = {int:is_not_deleted} AND pmr.is_read = {int:is_not_read})
			GROUP BY mem.id_member
			HAVING COUNT(pmr.id_pm) != MAX(mem.unread_messages)',
			array(
				'is_not_deleted' => 0,
				'is_not_read' => 0,
			)
		);
		while ($row = wesql::fetch_assoc($request))
			updateMemberData($row['id_member'], array('unread_messages' => $row['real_num']));
		wesql::free_result($request);

		if (microtime(true) - $time_start > 3)
		{
			$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=6;start=0;' . $context['session_query'];
			$context['continue_percent'] = round(700 / $total_steps);

			return;
		}
	}

	// Any messages pointing to the wrong board?
	if ($_REQUEST['step'] <= 6)
	{
		while ($_REQUEST['start'] < $settings['maxMsgID'])
		{
			$request = wesql::query('
				SELECT /*!40001 SQL_NO_CACHE */ t.id_board, m.id_msg
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic AND t.id_board != m.id_board)
				WHERE m.id_msg > {int:id_msg_min}
					AND m.id_msg <= {int:id_msg_max}',
				array(
					'id_msg_min' => $_REQUEST['start'],
					'id_msg_max' => $_REQUEST['start'] + $increment,
				)
			);
			$boards = array();
			while ($row = wesql::fetch_assoc($request))
				$boards[$row['id_board']][] = $row['id_msg'];
			wesql::free_result($request);

			foreach ($boards as $board_id => $messages)
				wesql::query('
					UPDATE {db_prefix}messages
					SET id_board = {int:id_board}
					WHERE id_msg IN ({array_int:id_msg_array})',
					array(
						'id_msg_array' => $messages,
						'id_board' => $board_id,
					)
				);

			$_REQUEST['start'] += $increment;

			if (microtime(true) - $time_start > 3)
			{
				$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=6;start=' . $_REQUEST['start'] . ';' . $context['session_query'];
				$context['continue_percent'] = round((700 + 100 * $_REQUEST['start'] / $settings['maxMsgID']) / $total_steps);

				return;
			}
		}

		$_REQUEST['start'] = 0;
	}

	// Update the latest message of each board.
	$request = wesql::query('
		SELECT m.id_board, MAX(m.id_msg) AS local_last_msg
		FROM {db_prefix}messages AS m
		WHERE m.approved = {int:is_approved}
		GROUP BY m.id_board',
		array(
			'is_approved' => 1,
		)
	);
	$realBoardCounts = array();
	while ($row = wesql::fetch_assoc($request))
		$realBoardCounts[$row['id_board']] = $row['local_last_msg'];
	wesql::free_result($request);

	$request = wesql::query('
		SELECT /*!40001 SQL_NO_CACHE */ id_board, id_parent, id_last_msg, child_level, id_msg_updated
		FROM {db_prefix}boards',
		array(
		)
	);
	$resort_me = array();
	while ($row = wesql::fetch_assoc($request))
	{
		$row['local_last_msg'] = isset($realBoardCounts[$row['id_board']]) ? $realBoardCounts[$row['id_board']] : 0;
		$resort_me[$row['child_level']][] = $row;
	}
	wesql::free_result($request);

	krsort($resort_me);

	$lastModifiedMsg = array();
	foreach ($resort_me as $rows)
		foreach ($rows as $row)
		{
			// The latest message is the latest of the current board and its children.
			if (isset($lastModifiedMsg[$row['id_board']]))
				$curLastModifiedMsg = max($row['local_last_msg'], $lastModifiedMsg[$row['id_board']]);
			else
				$curLastModifiedMsg = $row['local_last_msg'];

			// If what is and what should be the latest message differ, an update is necessary.
			if ($row['local_last_msg'] != $row['id_last_msg'] || $curLastModifiedMsg != $row['id_msg_updated'])
				wesql::query('
					UPDATE {db_prefix}boards
					SET id_last_msg = {int:id_last_msg}, id_msg_updated = {int:id_msg_updated}
					WHERE id_board = {int:id_board}',
					array(
						'id_last_msg' => $row['local_last_msg'],
						'id_msg_updated' => $curLastModifiedMsg,
						'id_board' => $row['id_board'],
					)
				);

			// Parent boards inherit the latest modified message of their children.
			if (isset($lastModifiedMsg[$row['id_parent']]))
				$lastModifiedMsg[$row['id_parent']] = max($row['local_last_msg'], $lastModifiedMsg[$row['id_parent']]);
			else
				$lastModifiedMsg[$row['id_parent']] = $row['local_last_msg'];
		}

	// Update all the basic statistics.
	updateStats('member');
	updateStats('message');
	updateStats('topic');

	// Finally, update the latest event times.
	loadSource('ScheduledTasks');
	CalculateNextTrigger();

	redirectexit('action=admin;area=maintain;sa=routine;done=recount');
}

// Perform a detailed version check. A very good thing ;)
function VersionDetail()
{
	global $txt, $context, $theme;

	isAllowedTo('admin_forum');

	// Call the function that'll get all the version info we need.
	loadSource('Subs-Admin');

	// Because we use pretty images from here.
	$theme['images_aeva'] = file_exists($theme['theme_dir'] . '/images/aeva') ? $theme['images_url'] . '/aeva' : $theme['default_images_url'] . '/aeva';

	// Get a list of current server versions.
	$checkFor = array(
		'left' => array(
			'server',
			'php',
			'db_server',
		),
		'right_top' => array(
			'safe_mode',
			'gd',
			'ffmpeg',
			'imagick',
		),
		'right_bot' => array(
			'phpa',
			'apc',
			'memcache',
			'xcache',
		),
	);
	foreach ($checkFor as $key => $list)
		$context['current_versions'][$key] = getServerVersions($list);

	// Then we need to prepend some stuff into the left column - Wedge versions etc.
	$context['current_versions']['left'] = array(
		'yourVersion' => array(
			'title' => $txt['support_versions_forum'],
			'version' => WEDGE_VERSION,
		),
		'wedgeVersion' => array(
			'title' => $txt['support_versions_current'],
			'version' => '??',
		),
		'sep1' => '',
	) + $context['current_versions']['left'];

	// And combine the right
	$context['current_versions']['right'] = $context['current_versions']['right_top'] + array('sep2' => '') + $context['current_versions']['right_bot'];
	unset($context['current_versions']['right_top'], $context['current_versions']['right_bot']);

	// Now the file versions.
	$versionOptions = array(
		'include_ssi' => true,
		'include_subscriptions' => true,
		'sort_results' => true,
	);
	$version_info = getFileVersions($versionOptions);

	// Add the new info to the template context.
	$context += array(
		'file_versions' => $version_info['file_versions'],
		'default_template_versions' => $version_info['default_template_versions'],
		'template_versions' => $version_info['template_versions'],
		'default_language_versions' => $version_info['default_language_versions'],
		'default_known_languages' => array_keys($version_info['default_language_versions']),
	);

	// Make it easier to manage for the template.
	$context['forum_version'] = WEDGE_VERSION;

	wetem::load('view_versions');
	$context['page_title'] = $txt['admin_version_check'];
}

// Removing old posts doesn't take much as we really pass through.
function MaintainReattributePosts()
{
	global $context, $txt;

	checkSession();

	// Find the member.
	loadSource('Subs-Auth');
	$members = !empty($_POST['to']) ? findMembers($_POST['to']) : 0;

	if (empty($members))
		fatal_lang_error('reattribute_cannot_find_member');

	$memID = array_shift($members);
	$memID = $memID['id'];

	$email = $_POST['type'] == 'email' ? $_POST['from_email'] : '';
	$membername = $_POST['type'] == 'name' ? $_POST['from_name'] : '';

	if ($_POST['type'] == 'from')
	{
		$members = !empty($_POST['from_id']) ? findMembers($_POST['from_id']) : 0;
		if (empty($members))
			fatal_lang_error('reattribute_cannot_find_member_from');
		$from_id = array_shift($members);
		$from_id = $from_id['id'];
		// We want to get the destination details. Let reattributePosts do that for us.
		$email = $membername = false;
	}
	else
		$from_id = 0;

	// Now call the reattribute function.
	loadSource('Subs-Members');
	reattributePosts($memID, $from_id, $email, $membername, !empty($_POST['posts']));

	// If we're merging, now we need to clean out that old account.
	if (!empty($from_id))
		deleteMembers($from_id, false, $memID);

	$context['maintenance_finished'] = $txt['maintain_reattribute_posts'];
}

// Removing old members?
function MaintainPurgeInactiveMembers()
{
	global $context, $txt;

	$_POST['maxdays'] = empty($_POST['maxdays']) ? 0 : (int) $_POST['maxdays'];
	if (!empty($_POST['groups']) && $_POST['maxdays'] > 0)
	{
		checkSession();

		$groups = array();
		foreach ($_POST['groups'] as $id => $dummy)
			$groups[] = (int) $id;
		$time_limit = (time() - ($_POST['maxdays'] * 24 * 3600));
		$where_vars = array(
			'time_limit' => $time_limit,
		);

		if ($_POST['del_type'] == 'activated')
		{
			$where = 'mem.date_registered < {int:time_limit} AND mem.is_activated = {int:is_activated}';
			$where_vars['is_activated'] = 0;
		}
		else
			$where = 'mem.last_login < {int:time_limit}';

		// Need to get *all* groups then work out which (if any) we avoid.
		$request = wesql::query('
			SELECT id_group, group_name, min_posts
			FROM {db_prefix}membergroups',
			array(
			)
		);
		while ($row = wesql::fetch_assoc($request))
		{
			// Avoid this one?
			if (!in_array($row['id_group'], $groups))
			{
				// Post group?
				if ($row['min_posts'] != -1)
				{
					$where .= ' AND mem.id_post_group != {int:id_post_group_' . $row['id_group'] . '}';
					$where_vars['id_post_group_' . $row['id_group']] = $row['id_group'];
				}
				else
				{
					$where .= ' AND mem.id_group != {int:id_group_' . $row['id_group'] . '} AND FIND_IN_SET({int:id_group_' . $row['id_group'] . '}, mem.additional_groups) = 0';
					$where_vars['id_group_' . $row['id_group']] = $row['id_group'];
				}
			}
		}
		wesql::free_result($request);

		// If we have ungrouped unselected we need to avoid those guys.
		if (!in_array(0, $groups))
		{
			$where .= ' AND (mem.id_group != 0 OR mem.additional_groups != {string:blank_add_groups})';
			$where_vars['blank_add_groups'] = '';
		}

		// Select all the members we're about to murder/remove...
		$request = wesql::query('
			SELECT mem.id_member, IFNULL(m.id_member, 0) AS is_mod
			FROM {db_prefix}members AS mem
				LEFT JOIN {db_prefix}moderators AS m ON (m.id_member = mem.id_member)
			WHERE ' . $where,
			$where_vars
		);
		$members = array();
		while ($row = wesql::fetch_assoc($request))
		{
			if (!$row['is_mod'] || !in_array(3, $groups))
				$members[] = $row['id_member'];
		}
		wesql::free_result($request);

		loadSource('Subs-Members');
		deleteMembers($members);
	}

	$context['maintenance_finished'] = $txt['maintain_members'];
}

function MaintainRecountPosts()
{
	global $txt, $context, $settings;

	isAllowedTo('admin_forum');
	checkSession('request');

	// Throttling attempt. There will be this many + 2-4 queries per run of this function.
	$items_per_request = 100;

	// Set up to the context.
	$context['page_title'] =  $txt['not_done_title'];
	$context['continue_countdown'] = '3';
	$context['continue_post_data'] = '';
	$context['continue_get_data'] = '';
	wetem::load('not_done');
	$context['start'] = isset($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0;
	$context['start_time'] = time();

	// This might take a while. Let's give ourselves 5 minutes this run (hopefully we won't trip the webserver timeout)
	@set_time_limit(300);

	if (($temp = cache_get_data('recount_boards_info', 600)) !== null)
		list ($boards, $member_count) = $temp;
	else
	{
		// What boards are we interested in?
		$boards = array();
		$request = wesql::query('
			SELECT id_board
			FROM {db_prefix}boards AS b
			WHERE count_posts = {int:post_count_enabled}',
			array(
				'post_count_enabled' => 0,
			)
		);
		while ($row = wesql::fetch_row($request))
			$boards[] = (int) $row[0];
		wesql::free_result($request);

		if (!empty($settings['recycle_enable']))
			$boards = array_diff($boards, (array) $settings['recycle_board']);

		$request = wesql::query('
			SELECT COUNT(DISTINCT id_member)
			FROM {db_prefix}messages
			WHERE id_member != 0
				AND id_board IN ({array_int:boards})',
			array(
				'boards' => $boards,
			)
		);
		list ($member_count) = wesql::fetch_row($request);
		wesql::free_result($request);

		// Interesting. There are no boards set to count posts, fine let's do this the quick way.
		if (empty($boards))
		{
			wesql::query('
				UPDATE {db_prefix}members
				SET posts = 0');
			clean_cache();
			updateStats('postgroups');
			redirectexit('action=admin;area=maintain;sa=members;done=recountposts');
		}

		if (!empty($settings['cache_enable']))
			cache_put_data('recount_boards_info', array($boards, $member_count), 600);
	}

	// Get the people we want. No sense calling upon the entire posts table every single time, eh?
	// If we were to select member+post count from messages, we'd be making much bigger queries.
	$request = wesql::query('
		SELECT id_member, posts
		FROM {db_prefix}members
		ORDER BY id_member
		LIMIT {int:start}, {int:max}',
		array(
			'start' => $_REQUEST['start'],
			'max' => $items_per_request,
		));

	$old = array();
	while ($row = wesql::fetch_row($request))
		$old[(int) $row[0]] = (int) $row[1];
	wesql::free_result($request);

	$request = wesql::query('
		SELECT id_member, COUNT(id_msg) AS posts
		FROM {db_prefix}messages
		WHERE id_member IN ({array_int:members})
			AND id_board IN ({array_int:boards})
			AND icon != {literal:moved}
		GROUP BY id_member',
		array(
			'members' => array_keys($old),
			'boards' => $boards,
		)
	);

	$new = array();
	while ($row = wesql::fetch_assoc($request))
		$new[$row['id_member']] = $row['posts'];

	foreach ($old as $id => $postcount)
	{
		// Has the member disappeared from the messages table..? Reset their post count...
		if (!isset($new[$id]))
			$new[$id] = 0;

		// Was the original number incorrect..?
		if ($new[$id] !== $postcount)
		{
			// Update the post count.
			wesql::query('
				UPDATE {db_prefix}members
				SET posts = {int:posts}
				WHERE id_member = {int:id_member}',
				array(
					'posts' => $new[$id],
					'id_member' => $id,
				)
			);
		}
	}
	wesql::free_result($request);

	$context['start'] += $items_per_request;

	// Continue?
	if ($context['start'] < $member_count)
	{
		$context['continue_get_data'] = '?action=admin;area=maintain;sa=members;activity=recountposts;start=' . $context['start'] . ';' . $context['session_query'];
		$context['continue_percent'] = round(100 * $context['start'] / $member_count);

		return;
	}

	// We've not only stored stuff in the cache, we've also updated things that may need to be uncached.
	clean_cache();
	updateStats('postgroups');
	redirectexit('action=admin;area=maintain;sa=members;done=recountposts');
}

// Removing old posts doesn't take much as we really pass through.
function MaintainRemoveOldPosts()
{
	// Actually do what we're told!
	loadSource('RemoveTopic');
	RemoveOldTopics2();
}

function MaintainMassMoveTopics()
{
	global $context, $txt;

	// Only admins.
	isAllowedTo('admin_forum');

	checkSession('request');

	// Set up to the context.
	$context['page_title'] = $txt['not_done_title'];
	$context['continue_countdown'] = '3';
	$context['continue_get_data'] = '';
	$context['continue_post_data'] = '';
	wetem::load('not_done');
	$context['start'] = empty($_REQUEST['start']) ? 0 : (int) $_REQUEST['start'];
	$context['start_time'] = time();

	// First time we do this?
	$id_board_from = array();
	$id_board_to = isset($_POST['id_board_to']) ? (int) $_POST['id_board_to'] : (int) $_REQUEST['id_board_to'];

	if (isset($_POST['boards']) && is_array($_POST['boards']))
		$boards = array_keys($_POST['boards']);
	elseif (isset($_REQUEST['id_board_from']))
		$boards = explode(',', $_REQUEST['id_board_from']);
	else
		$boards = array();

	foreach ($boards as $k => $v)
	{
		$v = (int) $v;
		if ($v > 0 && $v != $id_board_to)
			$id_board_from[] = $v;
	}

	// No boards then this is your stop.
	if (empty($id_board_from) || empty($id_board_to))
		return;

	// Custom conditions.
	$condition = '';
	$condition_params = array(
		'boards' => $id_board_from,
		'poster_time' => time() - 3600 * 24 * (isset($_POST['maxdays']) ? (int) $_POST['maxdays'] : 999),
		'num_topics' => 10,
	);

	// Just moved notice topics?
	$_POST['move_type'] = isset($_POST['move_type']) ? $_POST['move_type'] : 'nothing';
	if ($_POST['move_type'] == 'moved')
	{
		$condition .= '
			AND m.icon = {string:icon}
			AND t.locked = {int:locked}';
		$condition_params['icon'] = 'moved';
		$condition_params['locked'] = 1;
	}
	// Otherwise, maybe locked topics only?
	elseif ($_POST['move_type'] == 'locked')
	{
		$condition .= '
			AND t.locked != {int:unlocked}';
		$condition_params['unlocked'] = 0; // There are two kinds of locked topics.
	}
	else
		$_POST['move_type'] = 'nothing';
	$context['continue_post_data'] = '<input type="hidden" name="move_type" value="' . $_POST['move_type'] . '">';

	// Exclude pinned?
	if (isset($_POST['move_old_not_pinned']))
	{
		$condition .= '
			AND t.is_pinned = {int:is_pinned}';
		$condition_params['is_pinned'] = 0;
		$context['continue_post_data'] .= '<input type="hidden" name="move_old_not_pinned" value="1">';
	}

	// How many topics are we converting?
	if (!isset($_REQUEST['totaltopics']))
	{
		$request = wesql::query('
			SELECT COUNT(*)
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_last_msg)
			WHERE
				m.poster_time < {int:poster_time}' . $condition . '
				AND t.id_board IN ({array_int:boards})',
			$condition_params
		);
		list($total_topics) = wesql::fetch_row($request);
		wesql::free_result($request);
	}
	else
		$total_topics = (int) $_REQUEST['totaltopics'];

	// Seems like we need this here.
	$context['continue_get_data'] = '?action=admin;area=maintain;sa=topics;activity=massmove;id_board_from=' . implode(',', $id_board_from) . ';id_board_to=' . $id_board_to . ';totaltopics=' . $total_topics . ';start=' . $context['start'] . ';' . $context['session_query'];

	// We have topics to move so start the process.
	if (!empty($total_topics))
	{
		while ($context['start'] <= $total_topics)
		{
			// Let's get the topics.
			$request = wesql::query('
				SELECT t.id_topic
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_last_msg)
				WHERE
					m.poster_time < {int:poster_time}' . $condition . '
					AND t.id_board IN ({array_int:boards})
				LIMIT {int:num_topics}',
				$condition_params
			);
			$topics = array();
			while ($row = wesql::fetch_assoc($request))
				$topics[] = $row['id_topic'];
			wesql::free_result($request);

			// Just return if we don't have any topics left to move.
			if (empty($topics))
			{
				foreach ($id_board_from as $id_board)
					cache_put_data('board-' . $id_board, null, 120);
				cache_put_data('board-' . $id_board_to, null, 120);
				redirectexit('action=admin;area=maintain;sa=topics;done=massmove');
			}

			// Let's move them.
			loadSource('MoveTopic');
			moveTopics($topics, $id_board_to);

			// We've done at least ten more topics.
			$context['start'] += 10;

			// Let's wait a while.
			if (time() - $context['start_time'] > 3)
			{
				// What's the percent?
				$context['continue_percent'] = round(100 * ($context['start'] / $total_topics), 1);
				$context['continue_get_data'] = '?action=admin;area=maintain;sa=topics;activity=massmove;id_board_from=' . implode(',', $id_board_from) . ';id_board_to=' . $id_board_to . ';totaltopics=' . $total_topics . ';start=' . $context['start'] . ';' . $context['session_query'];

				// Let the template system do it's thang.
				return;
			}
		}
	}

	// Don't confuse admins by having an out of date cache.
	foreach ($id_board_from as $id_board)
		cache_put_data('board-' . $id_board, null, 120);
	cache_put_data('board-' . $id_board_to, null, 120);

	redirectexit('action=admin;area=maintain;sa=topics;done=massmove');
}
