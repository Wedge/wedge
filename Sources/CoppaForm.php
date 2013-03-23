<?php
/**
 * Wedge
 *
 * This file deals with displaying a COPPA form to the user, for users who are under 13 to get completed by their parents.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/**
 * Displays a COPPA compliance form to the user, which includes forum contact information.
 *
 * - Uses the Login language file and the Register template.
 * - Requires the user id in $_GET['member'].
 * - Queries the user to validate they not only exist, but they are currently flagged as requiring a COPPA form.
 * - If user requests to display the form ($_GET['form'] set), prepare the form, which includes collating contact details from $settings. If $_GET['dl'] is set, output the form as a forced download purely in text format, otherwise set up $context and push it to template_coppa_form.
 * - Otherwise display the general information.
 */

// This function will display the contact information for the forum, as well a form to fill in.
function CoppaForm()
{
	global $context, $settings, $txt;

	loadLanguage('Login');
	loadTemplate('Register');

	// No User ID??
	if (!isset($_GET['member']))
		fatal_lang_error('no_access', false);

	// Get the user details...
	$request = wesql::query('
		SELECT member_name
		FROM {db_prefix}members
		WHERE id_member = {int:id_member}
			AND is_activated = {int:is_coppa}',
		array(
			'id_member' => (int) $_GET['member'],
			'is_coppa' => 5,
		)
	);
	if (wesql::num_rows($request) == 0)
		fatal_lang_error('no_access', false);
	list ($username) = wesql::fetch_row($request);
	wesql::free_result($request);

	if (isset($_GET['form']))
	{
		// Some simple contact stuff for the forum.
		$context['forum_contacts'] = (!empty($settings['coppaPost']) ? $settings['coppaPost'] . '<br><br>' : '') . (!empty($settings['coppaFax']) ? $settings['coppaFax'] . '<br>' : '');
		$context['forum_contacts'] = !empty($context['forum_contacts']) ? $context['forum_name_html_safe'] . '<br>' . $context['forum_contacts'] : '';

		// Showing template?
		if (!isset($_GET['dl']))
		{
			// Shortcut for producing underlines.
			$context['ul'] = '<u>' . str_repeat('&nbsp;', 26) . '</u>';
			wetem::hide();
			wetem::load('coppa_form');
			$context['page_title'] = str_replace('{forum_name_safe}', $context['forum_name_html_safe'], $txt['coppa_form_title']);
			$context['coppa_body'] = str_replace(array('{PARENT_NAME}', '{CHILD_NAME}', '{USER_NAME}', '{forum_name_safe}'), array($context['ul'], $context['ul'], $username, $context['forum_name_html_safe']), $txt['coppa_form_body']);
		}
		// Downloading.
		else
		{
			// The data.
			$ul = '                ';
			$crlf = "\r\n";
			$data = $context['forum_contacts'] . $crlf . $txt['coppa_form_address'] . ':' . $crlf . $txt['coppa_form_date'] . ':' . $crlf . $crlf . $crlf . $txt['coppa_form_body'];
			$data = str_replace(array('{PARENT_NAME}', '{CHILD_NAME}', '{USER_NAME}', '<br>', '<br />'), array($ul, $ul, $username, $crlf, $crlf), $data);

			// Send the headers.
			header('Connection: close');
			header('Content-Disposition: attachment; filename="approval.txt"');
			header('Content-Type: ' . (we::is('ie,opera') ? 'application/octetstream' : 'application/octet-stream'));
			header('Content-Length: ' . count($data));

			echo $data;
			obExit(false);
		}
	}
	else
	{
		wetem::load('coppa');
		$context['page_title'] = $txt['coppa_title'];

		$context['coppa'] = array(
			'body' => str_replace(array('{MINIMUM_AGE}', '{forum_name_safe}'), array($settings['coppaAge'], $context['forum_name_html_safe']), $txt['coppa_after_registration']),
			'many_options' => !empty($settings['coppaPost']) && !empty($settings['coppaFax']),
			'post' => empty($settings['coppaPost']) ? '' : $settings['coppaPost'],
			'fax' => empty($settings['coppaFax']) ? '' : $settings['coppaFax'],
			'phone' => empty($settings['coppaPhone']) ? '' : str_replace('{PHONE_NUMBER}', $settings['coppaPhone'], $txt['coppa_send_by_phone']),
			'id' => $_GET['member'],
		);
	}
}
