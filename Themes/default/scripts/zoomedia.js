/*!
 * Wedge
 *
 * Zoomedia is the lightbox component for all media Wedge.
 * Developed by René-Gilles Deberdt (Nao) for Wedge.
 * Uses portions by Steve Smith (http://orderedlist.com/)
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

(function ($) {

	$.fn.zoomedia = function (options)
	{
		var
			double_clicked, img, $img, $fullsize, $anchor,
			show_loading, padding,

			$zoom, $zoom_desc, $zoom_close,
			$zoom_content, $zoom_desc_contain,

			options = options || {},
			lang = options.lang || {},
			outline = options.outline || '',
			duration = options.expand || 500,

			zooming = active = false,
			original_size = {},
			win = $(window),

		show = function (e)
		{
			if (zooming)
				return false;
			zooming = true;

			// In case we already have a zoomed item...
			$('#zoom').remove();

			$('body').append('\
				<div id="zoom" class="zoom-' + outline + '">\
					<div id="zoom-content"></div>\
					<div class="zoom-desc-contain">\
						<div id="zoom-desc" class="nodrag"></div>\
					</div>\
					<a href="#" title="' + (lang.closeTitle || '') + '" id="zoom-close"></a>\
				</div>');

			$zoom = $('#zoom');
			$zoom_desc = $('#zoom-desc');
			$zoom_content = $('#zoom-content').dblclick(false);
			$zoom_close = $('#zoom-close').click(hide);
			$zoom_desc_contain = $('.zoom-desc-contain', $zoom);

			// Use namespaces on any events not inside our zoom box.
			$(document)
				.bind('click.zoom', function (e) {
					if (active && e.target.id != 'zoom' && !$(e.target).closest('#zoom:visible').length)
						hide();
				})
				.bind('keyup.zoom', function (e) {
					if (active && e.which == 27)
						hide();
				});

			$anchor = $(this);

			var
				url = this.href,
				$ele = $anchor.children().first(),
				offset = $ele.offset();

			original_size = {
				x: offset.left,
				y: offset.top,
				w: $ele.width(),
				h: $ele.height()
			};

			loading(original_size.x + original_size.w / 2, original_size.y + original_size.h / 2);

			// This gets executed once the item to zoom is ready to show.
			var whenReady = function ()
			{
				$img = $(img = this);

				// Width set by options, or natural image width, or div width if it's HTML.
				var
					img_width = options.width || img.width || $img.width(),
					img_height = options.height || img.height || $img.height() || 1,
					ratio = img_width / img_height,

					win_width = win.width(),
					win_height = win.height(),
					is_html = !!$frame.length,
					desc = $anchor.next('.zoom-overlay').html() || '';

				done_loading();
				$zoom_desc_contain.toggle(desc != '').width(img_width);
				$zoom_desc.html(desc);
				$zoom_content.html(options.noScale ? '' : $img.addClass('scale'));
				padding = $zoom.width() - img_width;

				// If the image is too large for our viewport, reduce it horizontally.
				if (img_width > win_width)
				{
					img_width += win_width - $zoom.width();
					img_height = img_width / ratio;
					$zoom_desc_contain.width('auto');
					$img
						.width(img_width)
						.height(img_height);
				}

				// And/or if it's too tall, reduce it even more.
				if ($zoom.height() > win_height)
				{
					img_height += win_height - $zoom.height();
					img_width = img_height * ratio;
					$zoom_desc_contain.width('auto');
					$img
						.width(img_width)
						.height(img_height);
				}

				var width = $zoom.width(), height = $zoom.height();

				$zoom_desc_contain
					.css('overflow', 'hidden')
					.width('auto')
					.height('auto');

				if (!is_html)
					$img
						.width('100%')
						.height('auto');

				$zoom.css({
					left: original_size.x - padding / 2,
					top: original_size.y - padding / 2,
					width: original_size.w + padding,
					height: original_size.h + padding,
					visibility: 'visible'
				})
				.toggle(is_html)
				.animate({
						left: Math.max(0, win.scrollLeft() + (win_width - width) / 2),
						top: Math.max(0, win.scrollTop() + (win_height - height) / 2),
						width: width,
						height: height,
						opacity: is_html ? '+=0' : 'show'
					},
					duration,
					'swing2',
					function ()
					{
						if (options.noScale)
							$zoom_content.html(img);

						$zoom_desc_contain.css('overflow', 'visible');
						$zoom.css('zIndex', 999);

						// Now that our animation is finished, let's check whether
						// we double-clicked that thumbnail to request a full version!
						if (double_clicked)
							double_click(e);
						else
						{
							$zoom_content.one('dblclick', double_click);
							$zoom_close.fadeIn(300, 'linear');
						}
						zooming = false;
						active = true;
					}
				)
				.dragslide();
			};

			var $frame = $anchor.next('.zoom-html');
			if (!$frame.width())
				$('<img>').bind('load.zoom', whenReady).attr('src', url);
			else
				whenReady.call($frame.clone().addClass('nodrag').appendTo($zoom_content).show()[0]);

			return false;
		},

		double_click = function (e)
		{
			double_clicked = false;
			$fullsize = $zoom_desc.find('.fullsize').attr('href'); // $zoom_desc or $anchor.next('.zoom-overlay')
			if ($fullsize && img && img.src != $fullsize)
			{
				var pos = $img.offset();
				loading(pos.left + $img.width() / 2, pos.top + $img.height() / 2);
				$img.unbind('load.zoom').load(function ()
				{
					var
						wt = img.naturalWidth,
						ht = img.naturalHeight,
						rezoom = function () {
							var w2 = Math.min(win.width() - $zoom.width() + $img.width(), wt, wt * (win.height() - $zoom.height() + $img.height()) / ht);
							ht = ht * w2 / wt;
							done_loading();
							$zoom_desc.width('auto');
							$zoom.animate({
								left: '-=' + (w2 - $img.width()) / 2,
								top: '-=' + (ht - $img.height()) / 2,
								width: '+=' + (w2 - $img.width()),
								height: '+=' + (ht - $img.height())
							}, 500, null, function () {
								$zoom_close.fadeIn(300, 'linear');
							});
						};
					if (wt > 0)
						rezoom();
					else
					{
						// Stupid IE forces us to emulate natural properties through a hidden img...
						$('<img>').load(function () {
							wt = this.width;
							ht = this.height;
							$(this).remove();
							rezoom();
						}).attr('src', img.src);
					}
				}).attr('src', $fullsize);
			}
			else
				$zoom_close.fadeIn(300, 'linear');
			return false;
		},

		// Add the 'Loading' label at the center of our current object. If the item is already cached,
		// it'll hide it immediately, so we only show it if it's actually loading something.
		loading = function (x, y)
		{
			show_loading = setTimeout(function ()
			{
				var loa = $('<div class="zoom-loading">' + (lang.loading || '') + '</div>').click(function () {
					zooming = false;
					$('img').unbind('load.zoom');
					$(this).remove();
					return false;
				}).mousedown(false);
				loa.hide().appendTo('body').css({
					left: x - loa.outerWidth() / 2,
					top: y - loa.outerHeight() / 2
				}).fadeIn(300);
			}, 200);
		},

		done_loading = function ()
		{
			$zoom_close.hide();
			clearTimeout(show_loading);
			$('.zoom-loading').hide();
		},

		hide = function ()
		{
			if (zooming || !active)
				return false;
			zooming = true;
			$($zoom, $zoom_content).unbind();
			$(document).unbind('.zoom');

			if (options.noScale)
				$zoom_content.html('');

			$zoom_close.hide();
			$zoom_desc_contain.css('overflow', 'hidden');
			$zoom.animate(
				{
					left: original_size.x - padding / 2,
					top: original_size.y - padding / 2,
					width: original_size.w + padding,
					height: original_size.h + padding,
					opacity: 'hide'
				},
				duration,
				null,
				function () {
					zooming = false;
					active = false;
					$zoom.remove();
				}
			);
			return false;
		};

		$(this).each(function () {
			$(this).click(show).dblclick(function (e) {
				if (zooming)
					double_clicked = true;
				return false;
			});
			$('<div>').appendTo(this).css({
				position: 'absolute',
				left: 0,
				top: 0,
				width: $(this).width(),
				height: $(this).height(),
				zIndex: 2
			}).mousedown(false);
		});

		return this;
	};

})(jQuery);