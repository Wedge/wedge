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

 $(function()
 {
    var $shade = $("#notification_shade").remove().appendTo(document.body).hide();
    $shade.find('.template').hide();

    var $hovering = false,
        $timer = 0,
        $is_open = false,
        original_title = $('title').text();

    $shade.hover(function()
    {
        $hovering = true;
    }, function()
    {
        $hovering = false;
    });

    $(document.body).click(function()
    {
        if (!$hovering && $is_open && +new Date() - $timer > 200)
        {
            $shade.fadeOut('fast');
            $is_open = false;
            $hovering = false;
        }
    });

    $('.notification_trigger').click(function()
    {
        if ($is_open)
        {
            $is_open = false;

            $shade.fadeOut('fast');
        }
        else
        {
            $is_open = true;
            var $offset = $(this).parent().offset();
            $offset.top -= 6;
            $offset.left -= 15;
            $timer = +new Date();

            // Yeah I know I didn't need to set top and left CSS manually
            // but it was adding it to the current offset instead of overwriting it
            // hence on second or third viewing things would get weird
            $shade
                .css('top', $offset.top)
                .css('left', $offset.left)
                .fadeIn('fast');
        }
    });

    var updateNotification = function(data)
    {
        $shade.find('.notification_container > .notification:not(.template)').remove();

        $.each(data.notifications, function(index, item)
        {
            var $template = $shade.find('.template').clone().show();
            $template.removeClass('template');

            $template.find('.notification_text').html(item.text);
            $template.find('.notification_time').html(item.time);

            $template
                .hover(function()
                {
                    $(this).addClass('windowbg2');
                    $(this).find('.notification_markread').show();
                }, function()
                {
                    $(this).removeClass('windowbg2');
                    $(this).find('.notification_markread').hide();
                })
                .click({
                        url: we_script + '?action=notification;area=redirect;id=' + item.id
                    }, function(e)
                    {
                        document.location = e.data.url;
                    }
                );

            $template.find('.notification_markread')
                .click({
                        url: we_script + '?action=notification;area=markread;id=' + item.id,
                        notification: $template
                    }, function(e)
                    {
                        e.data.notification.remove();
                        var count = parseInt($('.notification_count:first').text()) - 1;
                        $('.notification_count').text(count);
                        if (count == 0)
                            $('.notification_count').removeClass('notenice').addClass('note');

                        $.get(e.data.url);

                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                    }
                )
                .hover(function()
                {
                    $(this).addClass('windowbg');
                }, function()
                {
                    $(this).removeClass('windowbg');
                });

            $template.appendTo($shade.find('.template').parent());
        });

        $('.notification_count').text(data.count);
        if (data.count > 0)
            $('.notification_count').removeClass('note').addClass('notenice');
        else
            $('.notification_count').removeClass('notenice').addClass('note');

        if (data.count > 0)
            $('title').text('(' + data.count + ') ' + original_title);
        else
            $('title').text(original_title);
    };

    updateNotification($notifications);

    // Update the notification every 3 minutes
    var auto_update = function()
    {
        $.ajax({
            url: we_script + '?action=notification;area=getunread',
            success: function(data)
            {
                updateNotification(data);
                setTimeout(auto_update, 1000 * 60 * 3);
            }
        });
    };

    setTimeout(auto_update, 1000 * 60 * 3);
 });