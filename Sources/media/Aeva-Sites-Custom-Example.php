<?php
/**
 * Wedge
 *
 * Sample for a site integration using Aeva.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

// Prevent attempts to access this file directly
if (!defined('WEDGE'))
	die('Hacking attempt...');

global $sites;

/*

--------------------
-- READ ME FIRST! --
--------------------

This is the file where you can include additional websites and settings that are not provided in the Wedge package.
Its name is "Aeva-Sites-Custom-Example.php", you just need to rename it to "Aeva-Sites-Custom.php" and fill it with
your custom site definitions. They will never be deleted when upgrading Wedge.

Upload the file to your Sources folder, go to the Admin area, check out the Forum > Auto-Embedding > Site list section,
enable the custom sites at the bottom and save your choices. You can now start embedding them in your posts.

Use the 'custom' type to make it easier to find your sites in the site list.

!!!!!! DON'T FORGET TO RENAME THE FILE AND REGENERATE THE SITE LIST !!!!!!

----------------------
-- NEW CUSTOM SITES --
----------------------

This is just an example, remove it or rewrite it to match a real site.
http://www.example.com/video/AbCdEf12

*/

/* // REMOVE THIS LINE TO COMMENT OUT

$sites[] = array(
	'id' => 'example',
	'title' => 'Example Website',
	'website' => 'http://www.example.com',
	'type' => 'custom',
	'pattern' => 'http://(?:www\.)?example\.com/video/([0-9a-z]{8})',
	'movie' => 'http://www.example.com/player.swf?id=$2',
	'size' => array(425, 344),
	'ui-height' => 20,
	'show-link' => true,
	'lookup-title' => '<title>(.*?)</title>',
	'fix-html-pattern' => '<object [^>]*><param name="movie" value="$1">.*?</object>',
);

/*

Of course, you can repeat this block to add new sites ad nauseam, ad infinitum et ultra.

----------------------------------------
-- CUSTOM SETTINGS FOR EXISTING SITES --
----------------------------------------

Here, you can also change specific settings for existing sites, with a bit of code... Just uncomment the following block
(i.e. remove the "/*" line below), and replace with your own settings. Of course you'll need to know PHP a bit.

*/

/* // REMOVE THIS LINE TO COMMENT OUT

	foreach ($sites as $si => $te)
	{
		// Always hide local mp3 player's URL
		if ($te['id'] == 'local_mp3')
			$sites[$si]['show-link'] = false;

		// Set YouTube's widescreen videos to use a 4/3 format instead.
		if ($te['id'] == 'ytb')
			$sites[$si]['size']['ws'] = array(480, 385);

		// This is already done for DailyMotion, but this is an example of how you
		// can specify the user interface's height for pixel-perfect resizing
		if ($te['id'] == 'dam')
			$sites[$si]['ui-height'] = 21;
	}

/*

------------------------------------------
-- WHAT'S IN THOSE SITE ARRAYS EXACTLY? --
------------------------------------------

	Mandatory items must be specified, however if it defaults to something, it won't cause a problem if it's left off. Optional ones aren't needed.
	All [Regex] (regular expression) patterns are only partial. So don't specify delimiters, etc. The regex is completed when it's used.

	'id' =>					Unique ID for the site, 3+ characters comprising A-Z/0-9 - e.g. ytb for YouTube					(Mandatory)
	'title' =>				Website's name - shown in the Admin area for each site as hover link title						(Mandatory)
	'website' =>			Website URL (to main site) - used in Admin area only											(Optional)
	'type' =>				Category where the site will be listed in the Admin area (local|pop|video|audio|other...)		(Mandatory - defaults to video)
	'plugin' =>				Type of plugin used by the site, either flash|divx|wmp|quicktime|realmedia						(Optional - defaults to flash)
	'disabled' =>			Is this site disabled by default? (no need to edit this, use Admin area instead)				(Optional - defaults to false)

	'pattern' =>			[Regex] Match these URLs for embedding															(Mandatory)
	'movie' =>				The FINAL embed URL, (any flashVars should be added as querystring)								(Mandatory)
							$1 = FULL raw link / $2, $3... etc are any patterns you specify for parts
							Note, &ampersands don't need to be encoded. All ampersands in final 'object' are made into &amp;
	'size' =>				An array with width & height of the embedded object (in pixels, or set to 0 for 100%)			(Mandatory)
	'ui-height' =>			This integer indicates the height of the video's user interface. This is only used to
							help Aeva resize the video perfectly when switching to the maximum available width.				(Optional)
	'html-before' =>		HTML code to show before the embedded object													(Optional)
	'html-after' =>			HTML code to show after the embedded object														(Optional)
	'show-link' =>			if set to true, include the raw link in the post, if the player doesn't already provide it		(Optional - defaults to false)
	'show-flashvars' =>		if set to true, include the flashvars in the embed code, for stupid players like Justin.tv		(Optional - defaults to false)
	'allow-script' =>		if set to true, allowScriptAccess will be set to "always". This is disabled by default to		(Optional - defaults to false)
							maximize security. Only enable it for sites that won't work without it.

	'fix-html-pattern' =>	[Regex] Noobs users might post the 'embed' html rather than a link, so on posting,				(Optional)
							replace with link. $1 = gets replaced by the embed pattern
	'fix-html-url' =>		URL for the fixed URL to point to a page rather than the embed URL								(Optional)
	'fix-html-urldecode' =>	Set to true if you want to urldecode the resulting URL (only used for CBS Sports)				(Optional)
	'lookup-url' =>			[Regex] On posting match these URLs as we need to do a lookup to grab an embeddable URL			(Mandatory to do Lookups)
	'lookup-actual-url' =>	Lookup couldn't be done to the URL above, so using the variables from above, lookup here		(Optional)
	'lookup-pattern' =>		[Regex] Match this pattern to obtain the bit that we want										(Mandatory to do Lookups)
	'lookup-title' =>		[Regex] Match this pattern to obtain the video's title. May be used even if lookup-url is empty	(Optional)
							Set to true if the pattern should be the standard one (<meta name="title">)
	'lookup-title-skip' =>	Set true if the video's title already appears by default in the thumbnail and it can be skipped	(Optional - defaults to false)
	'lookup-final-url' =>	URL, if the above pattern only returns part of the URL, using the variables collected			(Optional)
							we complete the URL. $1 = gets replaced by the lookup-pattern match (first result or 'id' key)
	'lookup-unencode' =>	Set true or 1 if you want html entities to be reversed in the URL								(Optional)
	'lookup-urldecode' =>	Number of times to run urldecode on the final url. Normally 1, but one site requires 2			(Optional)
	'lookup-skip-empty' =>	Keep looking if the current site lookup didn't work. Useful for white-labelled networks			(Optional)
	// Sample URLs			I included test case URLs with every site to show the format supported and to help
							ensure that they work for future compatibility.

*/

?>