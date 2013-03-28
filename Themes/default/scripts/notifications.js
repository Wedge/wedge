/**
 * Wedge
 *
 * UI JS for notifications
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

$(function ()
{
	var $shade = $('#notification_shade').remove().appendTo('.notifs').hide();
	$shade.find('.template').hide();

	var
		hovering = false,
		is_open = false,
		timer = 0,
		original_title = $('title').text();

	$shade.hover(function () { hovering = !hovering; });

	$(document.body).click(function ()
	{
		if (is_open && !hovering && $.now() - timer > 200)
		{
			$shade.fadeOut('fast');
			is_open = false;
		}
	});

	$('.notifs').click(function ()
	{
		$shade.fadeToggle('fast');
		is_open = !is_open;

		if (is_open)
			timer = $.now();
	});

	var updateNotification = function (data)
	{
		$shade.find('.notification_container > .notification:not(.template)').remove();

		$.each(data.notifs, function (index, item)
		{
			var $template = $shade.find('.template').clone().show();
			$template.removeClass('template');

			$template.find('.notification_text').html(item.text);
			$template.find('.notification_time').html(item.time);

			$template
				.hover(function () { $(this).toggleClass('windowbg2').find('.notification_markread').toggle(); })
				.click(function (e) { location = weUrl('action=notification;sa=redirect;in=' + item.id); })

				.find('.notification_markread')
				.hover(function () { $(this).toggleClass('windowbg'); })
				.click(function (e)
				{
					$template.hide(300, function () { $(this).remove(); });
					var count = parseInt($('.n_count:first').text()) - 1;
					$('.n_count').attr('class', 'n_count ' + (count > 0 ? 'notenice' : 'note')).text(count);

					$.post(weUrl('action=notification;sa=markread;in=' + item.id));

					// Cancel the implied clink on the parent.
					e.stopImmediatePropagation();
					return false;
				});

			$template.appendTo($shade.find('.template').parent());
		});

		$('.n_count').attr('class', 'n_count ' + (data.count > 0 ? 'notenice' : 'note')).text(data.count);
		$('title').text((data.count > 0 ? '(' + data.count + ') ' : '') + original_title);
	};

	updateNotification(we_notifs);

	// Update the notification every 3 minutes
	var auto_update = function ()
	{
		$.post(weUrl('action=notification;sa=unread'), function (data) {
			updateNotification(data);
			setTimeout(auto_update, 180000); // 1000 * 60 * 3
		});
	};

	setTimeout(auto_update, 180000);
});
