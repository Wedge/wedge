<?php
/**
 * Wedge
 *
 * Displays generic lists according to createList() instructions.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

function template_show_list($list_id = null)
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	// Get a shortcut to the current list.
	$list_id = $list_id === null ? $context['default_list'] : $list_id;
	$cur_list =& $context[$list_id];

	if (isset($cur_list['form']))
		echo '
	<form action="', $cur_list['form']['href'], '" method="post"', empty($cur_list['form']['name']) ? '' : ' name="' . $cur_list['form']['name'] . '" id="' . $cur_list['form']['name'] . '"', ' accept-charset="UTF-8">
		<div class="generic_list">';

	// Show the title or category of the table (if any.)
	if (!empty($cur_list['title']))
		echo '
			<div class="clear_right">
				<we:title>
					', $cur_list['title'], '
				</we:title>
			</div>';
	if (!empty($cur_list['cat']))
		echo '
			<div class="clear_right">
				<we:cat>
					', $cur_list['cat'], '
				</we:cat>
			</div>';

	if (isset($cur_list['additional_rows']['top_of_list']))
		template_additional_rows('top_of_list', $cur_list);

	if (isset($cur_list['additional_rows']['after_title']))
		echo '
			<div class="information flow_hidden">',
				template_additional_rows('after_title', $cur_list), '
			</div>';

	if (!empty($cur_list['items_per_page']) || isset($cur_list['additional_rows']['bottom_of_list']))
	{
		echo '
			<div class="pagesection">';

		// Show the page index (if this list doesn't intend to show all items).
		if (!empty($cur_list['items_per_page']))
			echo '
				<nav>', $txt['pages'], ': ', $cur_list['page_index'], '</nav>';

		if (isset($cur_list['additional_rows']['above_column_headers']))
			echo '
				<div class="floatright">',
					template_additional_rows('above_column_headers', $cur_list), '
				</div>';

		echo '
			</div>';
	}

	echo '
			<table class="table_grid cs0" style="width: ', !empty($cur_list['width']) && $cur_list['width'] != '100%' ? $cur_list['width'] . 'px' : '100%', '">';

	// Show the column headers.
	$header_count = count($cur_list['headers']);
	if (!($header_count < 2 && empty($cur_list['headers'][0]['label'])))
	{
		echo '
			<thead>
				<tr class="catbg">';

		// Loop through each column and add a table header.
		$i = 0;
		foreach ($cur_list['headers'] as $col_header)
		{
			$i++;
			if (empty($col_header['class']) && $i == 1)
				$col_header['class'] = 'first_th';
			elseif (empty($col_header['class']) && $i == $header_count)
				$col_header['class'] = 'last_th';

			echo '
					<th scope="col"', empty($col_header['class']) ? '' : ' class="' . $col_header['class'] . '"', empty($col_header['style']) ? '' : ' style="' . $col_header['style'] . '"', empty($col_header['colspan']) ? '' : ' colspan="' . $col_header['colspan'] . '"', '>', empty($col_header['href']) ? '' : '<a href="' . $col_header['href'] . '" rel="nofollow">', empty($col_header['label']) ? '&nbsp;' : $col_header['label'], empty($col_header['href']) ? '' : '</a>', empty($col_header['sort_image']) ? '' : ' <img src="' . $settings['images_url'] . '/sort_' . $col_header['sort_image'] . '.gif">', '</th>';
		}

		echo '
				</tr>
			</thead>
			<tbody>';
	}

	// Show a nice message informing there are no items in this list.
	if (empty($cur_list['rows']) && !empty($cur_list['no_items_label']))
		echo '
				<tr>
					<td class="windowbg" colspan="', $cur_list['num_columns'], '" style="text-align: ', !empty($cur_list['no_items_align']) ? $cur_list['no_items_align'] : 'center', '"><div class="padding">', $cur_list['no_items_label'], '</div></td>
				</tr>';

	// Show the list rows.
	elseif (!empty($cur_list['rows']))
	{
		$alternate = false;
		foreach ($cur_list['rows'] as $id => $row)
		{
			echo '
				<tr class="windowbg', $alternate ? '2' : '', '" id="list_', $list_id, '_', $id, '">';

			foreach ($row as $row_data)
				echo '
					<td', empty($row_data['class']) ? '' : ' class="' . $row_data['class'] . '"', empty($row_data['style']) ? '' : ' style="' . $row_data['style'] . '"', '>', $row_data['value'], '</td>';

			echo '
				</tr>';

			$alternate = !$alternate;
		}
	}

	echo '
			</tbody>
			</table>';

	if (!empty($cur_list['items_per_page']) || isset($cur_list['additional_rows']['below_table_data']) || isset($cur_list['additional_rows']['bottom_of_list']))
	{
		echo '
			<div class="pagesection">';

		// Show the page index (if this list doesn't intend to show all items.)
		if (!empty($cur_list['items_per_page']))
			echo '
				<nav>', $txt['pages'], ': ', $cur_list['page_index'], '</nav>';

		if (isset($cur_list['additional_rows']['below_table_data']))
			echo '
				<div class="floatright">',
					template_additional_rows('below_table_data', $cur_list), '
				</div>';

		if (isset($cur_list['additional_rows']['bottom_of_list']))
			echo '
				<div class="floatright">',
					template_additional_rows('bottom_of_list', $cur_list), '
				</div>';

		echo '
			</div>';
	}

	if (isset($cur_list['form']))
	{
		foreach ($cur_list['form']['hidden_fields'] as $name => $value)
			echo '
			<input type="hidden" name="', $name, '" value="', $value, '">';

		echo '
		</div>
	</form>';
	}

	if (isset($cur_list['javascript']))
		add_js($cur_list['javascript']);
}

function template_additional_rows($row_position, $cur_list)
{
	global $context, $settings, $options;

	foreach ($cur_list['additional_rows'][$row_position] as $row)
		echo '
		<div class="additional_row', empty($row['class']) ? '' : ' ' . $row['class'], '"', empty($row['style']) ? '' : ' style="' . $row['style'] . '"', '>', $row['value'], '</div>';
}

?>