/*!
 * Code used in the statistics center.
 *
 * @package Wedge
 * @copyright 2010 René-Gilles Deberdt, wedge.org
 * @license http://wedge.org/license/
 * @author see contributors.txt
 */

@language index;

$(function ()
{
	var
		canvas = $('#wraph')[0],
		month_names = $txt['months'],
		sum_txt = $txt['stat_sum'],
		avg_txt = $txt['stat_average'],
		weGraph,
		number_format = function (number)
		{
			// Inspired by http://phpjs.org/functions/number_format/
			var s = ('' + (isFinite(+number) ? number : 0)).split('.');

			if (s[0].length > 3)
				s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, '<span style="width: 4px">&nbsp;</span>');

			return s.join('.');
		},
		qfs = function (str) { // quick format for statistics
			return '<strong>' + number_format(str) + '</strong>';
		},
		build_chart = function (new_data)
		{
			hide_ajax();

			var i = 0, name, labels = '', sums = [], avgs = [],
				newChartData = {
					longlabels: new_data.long_labels,
					labels: new_data.labels,
					range: new_data.range || false,
					datasets: []
				};

			delete new_data.long_labels;
			delete new_data.labels;
			delete new_data.range;

			$.each(new_data, function (key, val) {
				newChartData.datasets[i] = $.extend(true, {}, colorData[key]);
				newChartData.datasets[i].data = val;
				newChartData.datasets[i].name = nameData[key];
				// [].reduce is JS 1.8+ only, but only IE8 doesn't support that.
				sums[key] = val.reduce(function (a, b) { return a + b; });
				avgs[key] = +(sums[key] / val.length).toFixed(2);
				i++;
			});

			for (name in new_data)
				if (avgs[name]) // If no activity was recorded, no need to clutter the label list.
					labels += '<strong style="color: ' + colorData[name].strokeColor + '">' + nameData[name] + '</strong> - '
							+ (name == 'most_on' ? '' : sum_txt + ' ' + qfs(sums[name]) + ', ') + avg_txt + ' ' + qfs(avgs[name]) + '<br>';

			var old_content = $('#labels').html(), new_height;
			$('#labels').stop(true, true).height('auto').html(labels);
			new_height = $('#labels').height();
			$('#labels')
				.html(old_content)
				.animate(
					{ opacity: 0 },
					300,
					function () {
						$(this).html(labels).animate({ opacity: 1, height: new_height }, 800);
					}
				);

			delete weGraph;
			$('#wraph').attr({
				width: $('#labels').width(),
				height: is_touch ? 200 : 300
			});
			var new_canvas = $('#wraph').clone().insertAfter('#wraph');
			$('#wraph').remove();
			canvas = $('#wraph')[0];
			weGraph = new Wraph(canvas.getContext('2d'), {}, updateRangeFilter);
			weGraph.Line(newChartData);
		},
		updateRangeFilter = function (custom_start, custom_end, undef) {
			show_ajax('#wraph');
			if (custom_end !== undef)
			{
				current_range = custom_start + ',' + custom_end;
				addCustomRanges();
				$('#range').sb();
			}
			else
				current_range = $('#range').val();
			$.get(weUrl('action=stats;range=' + current_range + ';filter=' + $('#filter').val()), build_chart);
		},
		addCustomRanges = function ()
		{
			if (current_range.indexOf(',') < 0)
				return;

			var range = current_range.split(','), r1 = range[0].split('-'), r2 = range[1].split('-'), r1_str = '', r2_str = '';

			// !! Range ends are not exactly correct, as they won't show up in the stats. Needs some more work.
			r1_str = r1[0][0] == 'Q' ? month_names[r1[0][1] * 3 - 2] + ' ' + r1[1] : (r1[0] ? month_names[+r1[0]] + ' ' + r1[1] : r1[1]);
			r2_str = r2[0][0] == 'Q' ? month_names[r2[0][1] * 3]     + ' ' + r2[1] : (r2[0] ? month_names[+r2[0]] + ' ' + r2[1] : r2[1]);
			$('#range').prepend('<option value="' + current_range + '" selected>' + (r1_str == r2_str ? r1_str : r1_str + ' - ' + r2_str) + '</option>');
		};

	if (!canvas.getContext)
		return;

	$('#filter').change(updateRangeFilter);
	$('#range').change(updateRangeFilter).each(function ()
	{
		var
			month = new Date().getMonth(),
			year = new Date().getFullYear(),
			target_month = +first_stats.slice(5, 7),
			target_year = +first_stats.slice(0, 4),
			$select = $(this),
			$month_group = $('<optgroup label="' + $txt['group_daily'] + '"/>'),
			$year_group = $('<optgroup label="' + $txt['group_monthly'] + '"/>');

		addCustomRanges();
		$select.append('<option value="last_month"' + (current_range == 'last_month' ? ' selected' : '') + '>' + $txt['last_month'] + '</option>');
		$select.append('<option value="last_year"' + (current_range == 'last_year' ? ' selected' : '') + '>' + $txt['last_year'] + '</option>');
		$select.append('<option value="all"' + (current_range == 'all' ? ' selected' : '') + '>' + $txt['lifetime'] + '</option>');

		while (year > target_year || month >= target_month)
		{
			$month_group.append(
				'<option value="' + month + '-' + year + '"' + (current_range == month + '-' + year ? ' selected' : '') + '>'
				+ month_names[month] + ' ' + year + '</option>'
			);
			if (!--month)
			{
				--year;
				month = 12;
				$year_group.append(
					'<option value="0-' + year + '"' + (current_range == '0-' + year ? ' selected' : '') + '>'
					+ year + '</option>'
				);
			}
		}
		$select.append($year_group).append($month_group).sb();
	});

	build_chart(lineChartData);
});
