<?php
/**
 * Wedge
 *
 * This file deals with displaying attachments, their previews, and avatars (where not in their own directory)
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

define('WEDGE_NO_LOG', 1);

/**
 * This file handles serving attachments, attachment image previews and avatars.
 *
 * - Hits here are not counted in the Who's Online log, and do not increase the overall 'hits' stat counter.
 * - If serving an attachment (not an avatar), require the view_attachments permission.
 * - Looks in $_REQUEST['id'] and $_REQUEST['attach'] for the id, flattening to $_REQUEST['attach'], and $_REQUEST['type'] for the requested type of attachment.
 * - If requesting an avatar, check that the attachment/avatar exists and that there is a user id attached (normal attachments have no user id) - and note that the file is an image.
 * - Check the view_attachments permission (for the current $board/$topic, as $topic is in the URL) and abort if not granted.
 * - Validate the topic of the attachment is the one given in the URL.
 * - If the post to which the attachment is attached is not yet approved, verify whether the user has permission to approve posts.
 * - If it's not a thumbnail, update the view counter.
 * - Get the physical filename/path of the attachment file (from {@see getAttachmentFile()})
 * - Clear any pre-existing output, then start a new buffer. If file size is 4MB or under, output compression is on, and the file type is known to be text normally, this new buffer will be gzipped.
 * - If the file doesn't exist, throw out a basic 404 error.
 * - If the browser sends a 'has been modified lately' header in the request, and the file has not been updated since the time given, send back a 304 header and abort the download.
 * - If the browser sent an ETag, check the ETag against the file, if not modified since, issue the 304 response.
 * - Send all the headers to ensure the file is sent correctly, including re-encoding the filename if necessary.
 * - If the file has an image extension but is not an image, prevent IE from erroneously caching.
 * - Fix the line endings if it's a text file and that option is enabled.
 * - Output the file and close buffers. If the file is bigger than 4MB, manually chunk the output, otherwise use whatever methods are available for quickly loading and serving the file (file_get_contents or readfile)
 */
function Dlattach()
{
	global $txt, $settings, $scripturl, $context, $topic;

	// Some defaults that we need.
	$context['no_last_modified'] = true;

	// Make sure some attachment was requested!
	if (!isset($_REQUEST['attach']) && !isset($_REQUEST['id']))
		fatal_lang_error('no_access', false);

	$_REQUEST['attach'] = isset($_REQUEST['attach']) ? (int) $_REQUEST['attach'] : (int) $_REQUEST['id'];

	if (isset($_REQUEST['type']) && $_REQUEST['type'] == 'avatar')
	{
		$request = wesql::query('
			SELECT id_folder, filename, file_hash, fileext, id_attach, attachment_type, mime_type, 1 AS approved, id_member
			FROM {db_prefix}attachments
			WHERE id_attach = {int:id_attach}
				AND id_member > {int:blank_id_member}
			LIMIT 1',
			array(
				'id_attach' => $_REQUEST['attach'],
				'blank_id_member' => 0,
			)
		);
		$_REQUEST['image'] = true;
	}
	// This is just a regular attachment...
	else
	{
		// This checks only the current board for $board/$topic's permissions.
		isAllowedTo('view_attachments');

		// Make sure this attachment is on this board.
		// NOTE: We must verify that $topic is the attachment's topic, or else the permission check above is broken.
		$request = wesql::query('
			SELECT a.id_folder, a.filename, a.file_hash, a.fileext, a.id_attach, a.attachment_type, a.mime_type, m.approved, m.id_member
			FROM {db_prefix}attachments AS a
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg AND m.id_topic = {int:current_topic})
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})
			WHERE a.id_attach = {int:attach}
			LIMIT 1',
			array(
				'attach' => $_REQUEST['attach'],
				'current_topic' => $topic,
			)
		);
	}
	if (wesql::num_rows($request) == 0)
		fatal_lang_error('no_access', false);
	list ($id_folder, $real_filename, $file_hash, $file_ext, $id_attach, $attachment_type, $mime_type, $is_approved, $id_member) = wesql::fetch_row($request);
	wesql::free_result($request);

	// If the post isn't yet approved, do they have permission to view it?
	if (!$is_approved && ($id_member == 0 || we::$id != $id_member) && ($attachment_type == 0 || $attachment_type == 3))
		isAllowedTo('approve_posts');

	// Update the download counter (unless it's a thumbnail.)
	if (!we::$user['possibly_robot'] && $attachment_type != 3)
		wesql::query('
			UPDATE LOW_PRIORITY {db_prefix}attachments
			SET downloads = downloads + 1
			WHERE id_attach = {int:id_attach}',
			array(
				'id_attach' => $id_attach,
			)
		);

	$filename = getAttachmentFilename($real_filename, $_REQUEST['attach'], $id_folder, false, $file_hash);

	// This is done to clear any output that was made before now. Remember, at this point, we are normally using compressed output so unless the result is textual and under 4MB, make sure gzhandler is OFF.
	ob_end_clean();
	if (!empty($settings['enableCompressedOutput']) && @filesize($filename) <= 4194304 && in_array($file_ext, array('txt', 'html', 'htm', 'js', 'doc', 'pdf', 'docx', 'rtf', 'css', 'php', 'log', 'xml', 'sql', 'c', 'java')))
		@ob_start('ob_gzhandler');
	else
	{
		ob_start();
		header('Content-Encoding: none');
	}

	// No point in a nicer message, because this is supposed to be an attachment anyway...
	if (!file_exists($filename))
	{
		loadLanguage('Errors');

		header('HTTP/1.0 404 ' . $txt['attachment_not_found']);
		header('Content-Type: text/plain; charset=UTF-8');

		// We need to die like this *before* we send any anti-caching headers as below.
		exit('404 - ' . $txt['attachment_not_found']);
	}

	// If it hasn't been modified since the last time this attachement was retrieved, there's no need to display it again.
	if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE']))
	{
		list ($modified_since) = explode(';', $_SERVER['HTTP_IF_MODIFIED_SINCE']);
		if (strtotime($modified_since) >= filemtime($filename))
		{
			ob_end_clean();

			// Answer the question - no, it hasn't been modified ;)
			header('HTTP/1.1 304 Not Modified');
			exit;
		}
	}

	// Check whether the ETag was sent back, and cache based on that...
	$eTag = '"' . substr($_REQUEST['attach'] . $real_filename . filemtime($filename), 0, 64) . '"';
	if (!empty($_SERVER['HTTP_IF_NONE_MATCH']) && strpos($_SERVER['HTTP_IF_NONE_MATCH'], $eTag) !== false)
	{
		ob_end_clean();

		header('HTTP/1.1 304 Not Modified');
		exit;
	}

	// Send the attachment headers.
	header('Pragma: ');
	if (!we::is('gecko'))
		header('Content-Transfer-Encoding: binary');
	header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 525600 * 60) . ' GMT');
	header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($filename)) . ' GMT');
	header('Accept-Ranges: bytes');
	header('Connection: close');
	header('ETag: ' . $eTag);

	// IE 6 just doesn't play nice. As dirty as this seems, it works.
	if (we::is('ie6') && isset($_REQUEST['image']))
		unset($_REQUEST['image']);

	// Make sure the mime type warrants an inline display.
	elseif (isset($_REQUEST['image']) && !empty($mime_type) && strpos($mime_type, 'image/') !== 0)
		unset($_REQUEST['image']);

	// Does this have a mime type?
	elseif (!empty($mime_type) && (isset($_REQUEST['image']) || !in_array($file_ext, array('jpg', 'gif', 'jpeg', 'x-ms-bmp', 'png', 'psd', 'tiff', 'iff'))))
		header('Content-Type: ' . strtr($mime_type, array('image/bmp' => 'image/x-ms-bmp')));

	else
	{
		header('Content-Type: application/octet' . (we::is('ie,opera') ? '' : '-') . 'stream');
		if (isset($_REQUEST['image']))
			unset($_REQUEST['image']);
	}

	// Set up sending the file with its headers. The filename should be in UTF-8 but of course, browsers don't always expect that...
	$fixchar = create_function('$n', '
		if ($n < 32)
			return \'\';
		elseif ($n < 128)
			return chr($n);
		elseif ($n < 2048)
			return chr(192 | $n >> 6) . chr(128 | $n & 63);
		elseif ($n < 65536)
			return chr(224 | $n >> 12) . chr(128 | $n >> 6 & 63) . chr(128 | $n & 63);
		else
			return chr(240 | $n >> 18) . chr(128 | $n >> 12 & 63) . chr(128 | $n >> 6 & 63) . chr(128 | $n & 63);');

	$disposition = !isset($_REQUEST['image']) ? 'attachment' : 'inline';

	// Different browsers like different standards...
	if (we::is('ie8down'))
		header('Content-Disposition: ' . $disposition . '; filename="' . urlencode(preg_replace('~&#(\d{3,8});~e', '$fixchar(\'$1\')', $real_filename)) . '"');
	elseif (we::is('safari'))
		header('Content-Disposition: ' . $disposition . '; filename="' . $real_filename . '"');
	else
		header('Content-Disposition: ' . $disposition . '; filename*=UTF-8\'\'' . rawurlencode(preg_replace('~&#(\d{3,8});~e', '$fixchar(\'$1\')', $real_filename)));

	// If this has an "image extension" - but isn't actually an image - then ensure it isn't cached cause of silly IE.
	if (!isset($_REQUEST['image']) && in_array($file_ext, array('gif', 'jpg', 'bmp', 'png', 'jpeg', 'tiff')))
		header('Cache-Control: no-cache');
	else
		header('Cache-Control: max-age=' . (525600 * 60) . ', private');

	if (empty($settings['enableCompressedOutput']) || filesize($filename) > 4194304)
		header('Content-Length: ' . filesize($filename));

	// Try to buy some time...
	@set_time_limit(600);

	// Recode line endings for text files, if enabled.
	if (!empty($settings['attachmentRecodeLineEndings']) && !isset($_REQUEST['image']) && in_array($file_ext, array('txt', 'css', 'htm', 'html', 'php', 'xml')))
	{
		if (strpos($_SERVER['HTTP_USER_AGENT'], 'Windows') !== false)
			$callback = create_function('$buffer', 'return preg_replace(\'~[\r]?\n~\', "\r\n", $buffer);');
		elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'Mac') !== false)
			$callback = create_function('$buffer', 'return preg_replace(\'~[\r]?\n~\', "\r", $buffer);');
		else
			$callback = create_function('$buffer', 'return preg_replace(\'~[\r]?\n~\', "\n", $buffer);');
	}

	// Since we don't do output compression for files this large...
	if (filesize($filename) > 4194304)
	{
		// Forcibly end any output buffering going on.
		while (@ob_get_level() > 0)
			@ob_end_clean();

		$fp = fopen($filename, 'rb');
		while (!feof($fp))
		{
			if (isset($callback))
				echo $callback(fread($fp, 8192));
			else
				echo fread($fp, 8192);
			flush();
		}
		fclose($fp);
	}
	// On some of the less-bright hosts, readfile() is disabled.  It's just a faster, more byte safe, version of what's in the if.
	elseif (isset($callback) || @readfile($filename) == null)
		echo isset($callback) ? $callback(file_get_contents($filename)) : file_get_contents($filename);

	obExit(false);
}

?>
