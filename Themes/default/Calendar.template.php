<?php
/**
 * Wedge
 *
 * Displays the main calendar, plus the previous and next month mini-calendars, as well as the event-posting interface.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

// The main calendar - January, for example.
function template_main()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	echo '
		<div id="calendar">
			<div id="main_grid">', $context['view_week'] ? template_show_week_grid('main') : template_show_month_grid('main');

	// Build the calendar button array.
	$calendar_buttons = array(
		'post_event' => array('test' => 'can_post', 'text' => 'calendar_post_event', 'image' => 'calendarpe.gif', 'lang' => true, 'url' => $scripturl . '?action=calendar;sa=post;month=' . $context['current_month'] . ';year=' . $context['current_year'] . ';' . $context['session_query']),
	);

	template_button_strip($calendar_buttons, 'right');

	// Show some controls to allow easy calendar navigation.
	echo '
				<form id="calendar_navigation" action="', $scripturl, '?action=calendar" method="post" accept-charset="UTF-8">
					<select name="month">';

	// Show a select box with all the months.
	foreach ($txt['months'] as $number => $month)
		echo '
						<option value="', $number, '"', $number == $context['current_month'] ? ' selected' : '', '>', $month, '</option>';
	echo '
					</select>
					<select name="year">';

	// Show a link for every year.....
	for ($year = $modSettings['cal_minyear']; $year <= $modSettings['cal_maxyear']; $year++)
		echo '
						<option value="', $year, '"', $year == $context['current_year'] ? ' selected' : '', '>', $year, '</option>';
	echo '
					</select>
					<input type="submit" value="', $txt['view'], '">
				</form>
				<br class="clear">
			</div>
		</div>';
}

function template_sidebar()
{
	echo '
		<div id="month_grid">',
			template_show_month_grid('prev'), template_show_month_grid('current'), template_show_month_grid('next'), '
		</div>';
}

// Template for posting a calendar event.
function template_event_post()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	// Start the javascript for drop down boxes...
	add_js('
	var monthLength = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

	function generateDays()
	{
		var days = 0, selected = 0, dayElement = $("#day")[0], year = $("#year").val(), monthElement = ("#month")[0];

		monthLength[1] = (year % 4 == 0 && (year % 100 != 0 || year % 400 == 0)) ? 29 : 28;

		selected = dayElement.selectedIndex;
		while (dayElement.options.length)
			dayElement.options[0] = null;

		days = monthLength[monthElement.value - 1];

		for (i = 1; i <= days; i++)
			dayElement.options.push(new Option(i, i));

		if (selected < days)
			dayElement.selectedIndex = selected;
	}

	function toggleLinked(form)
	{
		form.board.disabled = !form.link_to_board.checked;
	}');

	echo '
		<form action="', $scripturl, '?action=calendar;sa=post" method="post" name="postevent" accept-charset="UTF-8" onsubmit="submitonce(this); smc_saveEntities(\'postevent\', [\'evtitle\']);" style="margin: 0;">';

	if (!empty($context['event']['new']))
		echo '
			<input type="hidden" name="eventid" value="', $context['event']['eventid'], '">';

	// Start the main table.
	echo '
			<div id="post_event">
				<we:cat>
					', $context['page_title'], '
				</we:cat>';

	if (!empty($context['post_error']['messages']))
	{
		echo '
				<div class="errorbox">
					<dl class="event_error">
						<dt>
							', $context['error_type'] == 'serious' ? '<strong>' . $txt['error_while_submitting'] . '</strong>' : '', '
						</dt>
						<dt class="error">
							', implode('<br>', $context['post_error']['messages']), '
						</dt>
					</dl>
				</div>';
	}

	echo '
				<div class="windowbg roundframe">
					<fieldset id="event_main">
						<legend><span', isset($context['post_error']['no_event']) ? ' class="error"' : '', '>', $txt['calendar_event_title'], '</span></legend>
						<input type="text" name="evtitle" maxlength="80" size="70" value="', $context['event']['title'], '">
						<div class="smalltext">
							<input type="hidden" name="calendar" value="1">', $txt['calendar_year'], '
							<select name="year" onchange="generateDays();">';

	// Show a list of all the years we allow...
	for ($year = $modSettings['cal_minyear']; $year <= $modSettings['cal_maxyear']; $year++)
		echo '
								<option value="', $year, '"', $year == $context['event']['year'] ? ' selected' : '', '>', $year, '&nbsp;</option>';

	echo '
							</select>
							', $txt['calendar_month'], '
							<select name="month" id="month" onchange="generateDays();">';

	// There are 12 months per year - ensure that they all get listed.
	for ($month = 1; $month <= 12; $month++)
		echo '
								<option value="', $month, '"', $month == $context['event']['month'] ? ' selected' : '', '>', $txt['months'][$month], '&nbsp;</option>';

	echo '
							</select>
							', $txt['calendar_day'], '
							<select name="day" id="day">';

	// This prints out all the days in the current month - this changes dynamically as we switch months.
	for ($day = 1; $day <= $context['event']['last_day']; $day++)
		echo '
								<option value="', $day, '"', $day == $context['event']['day'] ? ' selected' : '', '>', $day, '&nbsp;</option>';

	echo '
							</select>
						</div>
					</fieldset>';

	if (!empty($modSettings['cal_allowspan']) || $context['event']['new'])
		echo '
					<fieldset id="event_options">
						<legend>', $txt['calendar_event_options'], '</legend>
						<div class="event_options smalltext">
							<ul class="event_options">';

	// If events can span more than one day then allow the user to select how long it should last.
	if (!empty($modSettings['cal_allowspan']))
	{
		echo '
								<li>
									', $txt['calendar_numb_days'], '
									<select name="span">';

		for ($days = 1; $days <= $modSettings['cal_maxspan']; $days++)
			echo '
										<option value="', $days, '"', $context['event']['span'] == $days ? ' selected' : '', '>', $days, '&nbsp;</option>';

		echo '
									</select>
								</li>';
	}

	// If this is a new event let the user specify which board they want the linked post to be put into.
	if ($context['event']['new'])
	{
		echo '
								<li>
									', $txt['calendar_link_event'], '
									<input type="checkbox" style="vertical-align: middle" name="link_to_board" checked onclick="toggleLinked(this.form);">
								</li>
								<li>
									', $txt['calendar_post_in'], '
									<select id="board" name="board" onchange="this.form.submit();">';
		foreach ($context['event']['categories'] as $category)
		{
			echo '
										<optgroup label="', $category['name'], '">';
			foreach ($category['boards'] as $board)
				echo '
											<option value="', $board['id'], '"', $board['selected'] ? ' selected' : '', '>', $board['child_level'] > 0 ? str_repeat('==', $board['child_level'] - 1) . '=&gt;' : '', ' ', $board['name'], '&nbsp;</option>';
			echo '
										</optgroup>';
		}
		echo '
									</select>
								</li>';
	}

	if (!empty($modSettings['cal_allowspan']) || $context['event']['new'])
		echo '
							</ul>
						</div>
					</fieldset>';

	echo '
					<div class="righttext">
						<input type="submit" value="', empty($context['event']['new']) ? $txt['save'] : $txt['post'], '" class="save">';

	// Delete button?
	if (empty($context['event']['new']))
		echo '
						<input type="submit" name="deleteevent" value="', $txt['event_delete'], '" onclick="return confirm(', JavaScriptEscape($txt['calendar_confirm_delete']), ');" class="delete">';

	echo '
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
						<input type="hidden" name="eventid" value="', $context['event']['eventid'], '">
					</div>
				</div>
			</div>
		</form>
		<br class="clear">';
}

// Display a monthly calendar grid.
function template_show_month_grid($grid_name)
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	if (!isset($context['calendar_grid_' . $grid_name]))
		return false;

	$calendar_data = &$context['calendar_grid_' . $grid_name];
	$colspan = !empty($calendar_data['show_week_links']) ? 8 : 7;

	if (empty($calendar_data['disable_title']))
	{
		echo '
			', $grid_name == 'main' ? '<we:cat>' : '<we:title2>', '
				<div class="centertext" style="font-size: ', $calendar_data['size'] == 'large' ? 'large' : 'small', ';">';

		if (empty($calendar_data['next_calendar']['disabled']) && $calendar_data['show_next_prev'])
			echo '
					<span class="floatright"><a href="', $calendar_data['next_calendar']['href'], '">&#187;</a></span>';

		if (empty($calendar_data['previous_calendar']['disabled']) && $calendar_data['show_next_prev'])
			echo '
					<span class="floatleft"><a href="', $calendar_data['previous_calendar']['href'], '">&#171;</a></span>';

		if ($calendar_data['show_next_prev'])
			echo '
					', $txt['months_titles'][$calendar_data['current_month']], ' ', $calendar_data['current_year'];
		else
			echo '
					<a href="', $scripturl, '?action=calendar;year=', $calendar_data['current_year'], ';month=', $calendar_data['current_month'], '">', $txt['months_titles'][$calendar_data['current_month']], ' ', $calendar_data['current_year'], '</a>';

		echo '
				</div>
			', $grid_name == 'main' ? '</we:cat>' : '</we:title2>';
	}

	echo '
			<table class="calendar_table cs1">';

	// Show each day of the week.
	if (empty($calendar_data['disable_day_titles']))
	{
		echo '
				<tr class="titlebg">';

		if (!empty($calendar_data['show_week_links']))
			echo '
					<th>&nbsp;</th>';

		foreach ($calendar_data['week_days'] as $day)
			echo '
					<th class="days" scope="col" ', $calendar_data['size'] == 'small' ? 'style="font-size: x-small;"' : '', '>', !empty($calendar_data['short_day_titles']) ? (westr::substr($txt['days'][$day], 0, 1)) : $txt['days'][$day], '</th>';

		echo '
				</tr>';
	}

	/* Each week in weeks contains the following:
		days (a list of days), number (week # in the year.) */
	foreach ($calendar_data['weeks'] as $week)
	{
		echo '
				<tr>';

		if (!empty($calendar_data['show_week_links']))
			echo '
					<td class="windowbg2 weeks">
						<a href="', $scripturl, '?action=calendar;viewweek;year=', $calendar_data['current_year'], ';month=', $calendar_data['current_month'], ';day=', $week['days'][0]['day'], '">&#187;</a>
					</td>';

		/* Every day has the following:
			day (# in month), is_today (is this day *today*?), is_first_day (first day of the week?),
			holidays, events, birthdays. (last three are lists.) */
		foreach ($week['days'] as $day)
		{
			// If this is today, make it a different color and show a border.
			echo '
					<td style="height: ', $calendar_data['size'] == 'small' ? '20' : '100', 'px; padding: 2px;', $calendar_data['size'] == 'small' ? 'font-size: x-small;' : '', '" class="', $day['is_today'] ? 'calendar_today' : 'windowbg', ' days">';

			// Skip it if it should be blank - it's not a day if it has no number.
			if (!empty($day['day']))
			{
				// Should the day number be a link?
				if (!empty($modSettings['cal_daysaslink']) && $context['can_post'])
					echo '
						<a href="', $scripturl, '?action=calendar;sa=post;month=', $calendar_data['current_month'], ';year=', $calendar_data['current_year'], ';day=', $day['day'], ';', $context['session_query'], '">', $day['day'], '</a>';
				else
					echo '
						', $day['day'];

				// Is this the first day of the week? (and are we showing week numbers?)
				if ($day['is_first_day'] && $calendar_data['size'] != 'small')
					echo '<span class="smalltext"> - <a href="', $scripturl, '?action=calendar;viewweek;year=', $calendar_data['current_year'], ';month=', $calendar_data['current_month'], ';day=', $day['day'], '">', $txt['calendar_week'], ' ', $week['number'], '</a></span>';

				// Are there any holidays?
				if (!empty($day['holidays']))
					echo '
						<div class="smalltext holiday">', $txt['calendar_prompt'], ' ', implode(', ', $day['holidays']), '</div>';

				// Show any birthdays...
				if (!empty($day['birthdays']))
				{
					echo '
						<div class="smalltext">
							<span class="birthday">', $txt['birthdays'], '</span>';

					/* Each of the birthdays has:
						id, name (person), age (if they have one set?), and is_last. (last in list?) */
					$use_js_hide = empty($context['show_all_birthdays']) && count($day['birthdays']) > 15;
					$count = 0;
					foreach ($day['birthdays'] as $member)
					{
						echo '
								<a href="', $scripturl, '?action=profile;u=', $member['id'], '">', $member['name'], isset($member['age']) ? ' (' . $member['age'] . ')' : '', '</a>', $member['is_last'] || ($count == 10 && $use_js_hide)? '' : ', ';

						// Stop at ten?
						if ($count == 10 && $use_js_hide)
							echo '<span class="hidelink" id="bdhidelink_', $day['day'], '">...<br><a href="', $scripturl, '?action=calendar;month=', $calendar_data['current_month'], ';year=', $calendar_data['current_year'], ';showbd" onclick="$(\'#bdhide_', $day['day'], '\').show(); $(\'#bdhidelink_', $day['day'], '\').hide(); return false;">(', sprintf($txt['calendar_click_all'], count($day['birthdays'])), ')</a></span><span id="bdhide_', $day['day'], '" style="display: none;">, ';

						$count++;
					}
					if ($use_js_hide)
						echo '
							</span>';

					echo '
						</div>';
				}

				// Any special posted events?
				if (!empty($day['events']))
				{
					echo '
						<div class="smalltext">
							<span class="event">', $txt['events'], '</span>';

					/* The events are made up of:
						title, href, is_last, can_edit (are they allowed to?), and modify_href. */
					foreach ($day['events'] as $event)
					{
						// If they can edit the event, show a star they can click on....
						if ($event['can_edit'])
							echo '
							<a class="modify_event" href="', $event['modify_href'], '"><img src="' . $settings['images_url'] . '/icons/modify_small.gif"></a>';

						echo '
							', $event['link'], $event['is_last'] ? '' : ', ';
					}

					echo '
						</div>';
				}
			}

			echo '
					</td>';
		}

		echo '
				</tr>';
	}

	echo '
			</table>';
}

// Or show a weekly one?
function template_show_week_grid($grid_name)
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	if (!isset($context['calendar_grid_' . $grid_name]))
		return false;

	$calendar_data = &$context['calendar_grid_' . $grid_name];

	// Loop through each month (At least one) and print out each day.
	foreach ($calendar_data['months'] as $month_data)
	{
		echo '
			<we:cat>
				<div class="weekly">';

		if (empty($calendar_data['previous_calendar']['disabled']) && $calendar_data['show_next_prev'] && empty($done_title))
			echo '
					<span class="floatleft"><a href="', $calendar_data['previous_week']['href'], '">&#171;</a></span>';

		if (empty($calendar_data['next_calendar']['disabled']) && $calendar_data['show_next_prev'] && empty($done_title))
			echo '
					<span class="floatright"><a href="', $calendar_data['next_week']['href'], '">&#187;</a></span>';

		echo '
					<a href="', $scripturl, '?action=calendar;month=', $month_data['current_month'], ';year=', $month_data['current_year'], '">', $txt['months_titles'][$month_data['current_month']], ' ', $month_data['current_year'], '</a>', empty($done_title) && !empty($calendar_data['week_number']) ? (' - ' . $txt['calendar_week'] . ' ' . $calendar_data['week_number']) : '', '
				</div>
			</we:cat>';

		$done_title = true;

		echo '
			<table class="calendar_table weeklist w100 cs1 cp0">';

		foreach ($month_data['days'] as $day)
		{
			echo '
				<tr>
					<td colspan="2">
						<we:title2>
							', $txt['days'][$day['day_of_week']], '
						</we:title2>
					</td>
				</tr>
				<tr>
					<td class="windowbg">';

			// Should the day number be a link?
			if (!empty($modSettings['cal_daysaslink']) && $context['can_post'])
				echo '
						<a href="', $scripturl, '?action=calendar;sa=post;month=', $month_data['current_month'], ';year=', $month_data['current_year'], ';day=', $day['day'], ';', $context['session_query'], '">', $day['day'], '</a>';
			else
				echo '
						', $day['day'];

			echo '
					</td>
					<td class="', $day['is_today'] ? 'calendar_today' : 'windowbg2', ' weekdays">';

			// Are there any holidays?
			if (!empty($day['holidays']))
				echo '
						<div class="smalltext holiday">', $txt['calendar_prompt'], ' ', implode(', ', $day['holidays']), '</div>';

			// Show any birthdays...
			if (!empty($day['birthdays']))
			{
				echo '
						<div class="smalltext">
							<span class="birthday">', $txt['birthdays'], '</span>';

				/* Each of the birthdays has:
					id, name (person), age (if they have one set?), and is_last. (last in list?) */
				foreach ($day['birthdays'] as $member)
					echo '
							<a href="', $scripturl, '?action=profile;u=', $member['id'], '">', $member['name'], isset($member['age']) ? ' (' . $member['age'] . ')' : '', '</a>', $member['is_last'] ? '' : ', ';
				echo '
						</div>';
			}

			// Any special posted events?
			if (!empty($day['events']))
			{
				echo '
						<div class="smalltext">
							<span class="event">', $txt['events'], '</span>';

				/* The events are made up of:
					title, href, is_last, can_edit (are they allowed to?), and modify_href. */
				foreach ($day['events'] as $event)
				{
					// If they can edit the event, show a star they can click on....
					if ($event['can_edit'])
						echo '
							<a href="', $event['modify_href'], '"><img src="' . $settings['images_url'] . '/icons/modify_small.gif"></a> ';

					echo '
							', $event['link'], $event['is_last'] ? '' : ', ';
				}

				echo '
						</div>';
			}

			echo '
					</td>
				</tr>';
		}

		echo '
			</table>';
	}
}

?>