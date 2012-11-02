<?php
/**
 * Wedge
 *
 * Displays the visual verification interface.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

// What's this, verification?!
function template_control_verification($verify_id, $display_type = 'all', $reset = false)
{
	global $context, $theme, $options, $txt, $settings;

	$verify_context =& $context['controls']['verification'][$verify_id];

	// Keep track of where we are.
	if (empty($verify_context['tracking']) || $reset)
		$verify_context['tracking'] = 0;

	// How many items are there to display in total.
	$total_items = count($verify_context['questions']) + ($verify_context['show_visual'] ? 1 : 0);

	// If we've gone too far, stop.
	if ($verify_context['tracking'] > $total_items)
		return false;

	// Loop through each item to show them.
	for ($i = 0; $i < $total_items; $i++)
	{
		// If we're after a single item only show it if we're in the right place.
		if ($display_type == 'single' && $verify_context['tracking'] != $i)
			continue;

		if ($display_type != 'single')
			echo '
			<div id="verification_control_', $i, '" class="verification_control">';

		// Do the actual stuff - image first?
		if ($i == 0 && $verify_context['show_visual'])
			echo '
				<img src="', $verify_context['image_href'], '" alt="', $txt['visual_verification_description'], '" id="verification_image_', $verify_id, '">
				<div class="smalltext" style="margin: 4px 0 8px 0">
					<a href="', $verify_context['image_href'], ';sound" id="visual_verification_', $verify_id, '_sound" rel="nofollow">', $txt['visual_verification_sound'], '</a> / <a href="#" id="visual_verification_', $verify_id, '_refresh">', $txt['visual_verification_request_new'], '</a>', $display_type != 'quick_reply' ? '<br>' : '', '<br>
					', $txt['visual_verification_description'], ':', $display_type != 'quick_reply' ? '<br>' : '', '
					<input type="text" name="', $verify_id, '_vv[code]" value="', !empty($verify_context['text_value']) ? $verify_context['text_value'] : '', '" size="30" tabindex="', $context['tabindex']++, '">
				</div>';
		else
		{
			// Where in the question array is this question?
			$qIndex = $verify_context['show_visual'] ? $i - 1 : $i;

			echo '
				<div class="smalltext">
					', $verify_context['questions'][$qIndex]['q'], ':<br>
					<input type="text" name="', $verify_id, '_vv[q][', $verify_context['questions'][$qIndex]['id'], ']" size="30" value="', $verify_context['questions'][$qIndex]['a'], '" ', $verify_context['questions'][$qIndex]['is_error'] ? 'style="border: 1px red solid;"' : '', 'tabindex="', $context['tabindex']++, '">
				</div>';
		}

		if ($verify_context['do_empty_field'] && empty($done_empty))
		{
			$done_empty = true;
			echo '
				<div class="smalltext vv_special">
					', $txt['visual_verification_hidden'], ':
					<input type="text" name="', $_SESSION[$verify_id . '_vv']['empty_field'], '" autocomplete="off" size="30" value="">
				</div>';
		}

		if ($display_type != 'single')
			echo '
			</div>';

		// If we were displaying just one and we did it, break.
		if ($display_type == 'single' && $verify_context['tracking'] == $i)
			break;
	}

	call_hook('verification_display', array(&$verify_id));

	// Assume we found something, always,
	$verify_context['tracking']++;

	// Tell something displaying piecemeal to keep going.
	if ($display_type == 'single')
		return true;
}

?>