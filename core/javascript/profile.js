/*!
 * Helper functions for the profile area.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

// For ignore boards
function selectBoards(ids)
{
	var toggle = true, i;

	for (i = 0; i < ids.length; i++)
		toggle &= document.forms.creator['ignore_brd' + ids[i]].checked;

	for (i = 0; i < ids.length; i++)
		document.forms.creator['ignore_brd' + ids[i]].checked = !toggle;
}

// For the avatar configuration area
function showAvatar()
{
	if (file.selectedIndex == -1)
		return;

	$('#avatar').attr({
		src: avatardir + $(file).val(),
		alt: file.options[file.selectedIndex].text
	}).css({
		width: '',
		height: ''
	});
}

function changeSel(selected)
{
	if (cat.selectedIndex == -1)
		return;

	var val = $(cat).val(), i, count = 0;
	if (val.indexOf('/') > 0)
	{
		$(file).css('display', 'inline').prop('disabled', false);

		for (i = file.length; i >= 0; i--)
			file.options[i] = null;

		for (i = 0; i < files.length; i++)
			if (files[i].indexOf(val) == 0)
			{
				var filename = files[i].slice(files[i].indexOf('/') + 1);
				var showFilename = filename.slice(0, filename.lastIndexOf('.'));
				showFilename = showFilename.replace(/[_]/g, ' ');

				file.options[count] = new Option(showFilename, files[i]);

				if (filename == selected)
				{
					if (file.options.defaultSelected)
						file.options[count].defaultSelected = true;
					else
						file.options[count].selected = true;
				}
				count++;
			}

		if (file.selectedIndex == -1 && file.options[0])
			file.options[0].selected = true;

		showAvatar();
	}
	else
	{
		$(file).hide().prop('disabled', true);
		$('#avatar').attr('src', avatardir + val).css({ width: '', height: '' });
	}
}

// Infractions
function nl2br(str)
{
	return str.replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1<br>$2');
}

function updateInf()
{
	var inf = $('#infraction').val();
	if (inf == 'custom')
	{
		$('#no_notifications, #has_notification').hide();
		$('#raw_notification').show();
		$('#points').html('<input type="number" name="points" value="' + adhoc_stuff.points + '" min="0" max="' + max_points + '">');
		// Duration is modestly tricky.
		var thishtml = '<input type="number" id="infraction_duration_number" name="infraction_duration_number" value="' + adhoc_stuff.duration.number + '" min="0" max="50" required' + (adhoc_stuff.duration.unit == i ? ' class="hide"' : '') + '> <select name="infraction_duration_unit" id="infraction_duration_unit" onchange="$(\'#infraction_duration_number\').toggle(this.value != \'i\');">';
		for (var i in durations)
			thishtml += '<option value="' + i + '"' + (adhoc_stuff.duration.unit == i ? ' selected' : '') + '>' + durations[i] + '</option>';
		thishtml += '</select>';

		$('#duration').html(thishtml);
		$('#infraction_duration_unit').sb();

		// Sanction types is a bit trickier still.
		var thishtml = '', i, l = sanction_types.length;
		for (i = 0; i < l; i++)
			if (infraction_levels[sanction_types[i]])
				thishtml += '<label><input type="checkbox" name="sanctions[]" value="' + sanction_types[i] + '"' + (in_array(sanction_types[i], adhoc_stuff.sanctions) ? ' checked' : '') + '> ' + infraction_levels[sanction_types[i]][0] + '</label><br>';

		$('#sanctions').html(thishtml == '' ? no_punish : thishtml);

		$('#text_subject input').val(adhoc_stuff.note_subject.php_unhtmlspecialchars().replace(/&#039;/g, "'"));
		$('#text_body textarea').val(adhoc_stuff.note_body.php_unhtmlspecialchars().replace(/&#039;/g, "'"));
	}
	else if (infractions[inf])
	{
		var infraction = infractions[inf];
		if (infraction.infraction_msg && infraction.infraction_msg.subject)
		{
			$('#no_notifications, #raw_notification').hide();
			$('#has_notification').show();
			$('#note_subject').html(infraction.infraction_msg.subject);
			$('#note_body').html(nl2br(infraction.infraction_msg.body));
		}
		else
		{
			$('#no_notifications').show();
			$('#has_notification, #raw_notification').hide();
		}

		if (infraction.duration == 'i')
			$('#duration').html(durations['i']);
		else
		{
			var strlen = infraction.duration.length;
			var number = infraction.duration.substr(0, strlen-1);
			var units = infraction.duration.substr(strlen-1, 1);
			$('#duration').html(number + ' ' + durations[units]);
		}

		$('#points').html(infraction.points);

		if (infraction.sanctions)
		{
			var sanctions = infraction.sanctions.split(',');
			if (sanctions.length == 0)
				$('#sanctions').html(no_punish);
			else
			{
				$('#sanctions').html('<ul></ul>');
				for (var i in sanctions)
					if (infraction_levels[sanctions[i]])
						$('#sanctions ul').append('<li>' + infraction_levels[sanctions[i]][0] + '</li>');
			}
		}
		else
			$('#sanctions').html(no_punish);
	}
}

function selectInfractionWording()
{
	var inf = $('#template_wording').val();
	if (infractions[inf])
	{
		var infraction = infractions[inf];
		if (infraction.infraction_msg && infraction.infraction_msg.subject)
		{
			$('#text_subject input').val(infraction.infraction_msg.subject.php_unhtmlspecialchars().replace(/&#039;/g, "'"));
			$('#text_body textarea').val(infraction.infraction_msg.body.php_unhtmlspecialchars().replace(/&#039;/g, "'"));
		}
	}
}
