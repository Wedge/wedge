
/*!
 * Zoomedia is the lightbox component for all media Wedge.
 * Developed by Nao.
 * Uses portions by Steve Smith (http://orderedlist.com/)
 *
 * This file is released under the Wedge license.
 * More details at http://wedge.org/license/
 */

(function ($) {

	$.fn.zoomedia = function (options, onComplete) {

		var
			double_clicked, img, $img, $fullsize, $anchor,
			img_width, img_height, show_loading,
			padding, double_padding,
			css_width = 'width',
			css_auto = 'auto',

			zoom = '#zoom', $zoom, $zoom_desc, $zoom_close,
			$zoom_content, $zoom_desc_contain,

			options = options || {},
			lang = options.lang || {},
			outline = options.outline || '',

			zooming = active = false,
			original_size = {},
			win = $(window);

		$(this).each(function () {
			$(this).click(show).dblclick(function (e) {
				if (zooming)
					double_clicked = true;
				return false;
			});
			$('<div id="shim_' + this.id + '">').appendTo(this).css({
				position: 'absolute',
				left: 0,
				top: 0,
				width: $(this).width(),
				height: $(this).height(),
				zIndex: 2
			}).mousedown(false);
		});

		return this;

		function show(e)
		{
			if (zooming)
				return false;
			zooming = true;

			// In case we already have a zoomed item...
			$(zoom).remove();

			$('body').append('\
				<div id="zoom" class="zoom-' + outline + '">\
					<div id="zoom-content"></div>\
					<div class="zoom-desc-contain">\
						<div id="zoom-desc" class="nodrag"></div>\
					</div>\
					<a href="#" title="Close" id="zoom-close"></a>\
				</div>');

			$zoom = $(zoom);
			$zoom_desc = $(zoom + '-desc');
			$zoom_content = $(zoom + '-content').dblclick(false);
			$zoom_close = $(zoom + '-close').click(hide);
			$zoom_desc_contain = $('.zoom-desc-contain', $zoom);

			// Use namespaces on any events not inside our zoom box.
			$(document).bind({
				'click.zoom': function (e) {
					if (active && e.target.id != 'zoom' && !$(e.target).parents(zoom + ':visible').length)
						hide();
				},
				'keyup.zoom': function (e) {
					if (active && e.keyCode == 27)
						hide();
				}
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
			var whenReady = function () {

				img = this;
				$img = $(img);
				// Width set by options, or natural image width, or div width if it's HTML.
				img_width = options.width || img.width || $img.width();
				img_height = options.height || img.height || $img.height();

				var
					win_width = win.width(),
					win_height = win.height(),
					is_html = !!$frame.length,
					scrollTop = is_ie8down ? document.documentElement.scrollTop : window.pageYOffset,
					scrollLeft = is_ie8down ? document.documentElement.scrollLeft : window.pageXOffset,
					desc = $anchor.next('.zoom-overlay').html() || '';

				done_loading();
				$zoom_desc_contain.toggle(desc != '');

				$zoom_content.html(options.noScale ? '' : $img.addClass('scale'));

				double_padding = $zoom.width() - img_width;
				padding = double_padding / 2;

				if (desc)
				{
					// Force width on description, to see what height we end up with.
					$zoom.css('visibility', 'visible').show().css('top', scrollTop);
					$zoom_desc.css(css_width, is_ie7 ? img_width - 20 + double_padding : $zoom_desc_contain.width()).html(desc);

					// If the viewport is too small, keep only the links in the description, reduce the picture height
					// to match the maximum height, and resize the description to match the new picture width.
					// (This code could be much simpler, but it wouldn't work on IE6/7. Known song.)
					if (!is_html && $zoom.height() > win_height)
					{
						$zoom_desc.html($zoom_desc.find('.aelink').addClass('lonely')).css(css_width, css_auto);
						if ($zoom.height() > win_height)
							$img.css({ width: css_auto, height: $img.height() + win_height - $zoom.height() });
						$zoom_desc.css(css_width, $zoom_desc_contain.width());
						$zoom.css({ width: $zoom.width(), height: $zoom.height() });
						img_width = $img.width();
						img_height = $img.height();
					}
					// Is it a narrow element with a long description? If yes, enlarge its parent to at least 500px.
					else if (!is_html && img_width < 500 && ($zoom_desc.outerWidth(true) > img_width || $zoom_desc.outerHeight(true) > 200))
					{
						var resized = true;
						$img.css({
							maxWidth: width,
							maxHeight: height
						});
						$zoom.css(css_width, Math.max($zoom_desc.outerWidth(), 500));
						$zoom_desc.css(css_width, css_auto);
					}
				}

				// Target size -- make sure it's within the browser viewport.
				var
					width = $zoom.width(),
					height = $zoom.height(),
					on_width = !is_html && height > win_height ? width * (win_height / height) : width,
					on_height = !is_html ? Math.min(win_height, height) : height,
					on_top = Math.max(0, (win_height - on_height) / 2 + scrollTop),
					on_left = (win_width - on_width) / 2 + scrollLeft;

				if (!resized && !is_html)
					$img.css({ width: '100%', height: css_auto });

				$zoom.css({
					left: original_size.x - padding,
					top: original_size.y - padding,
					width: original_size.w + double_padding,
					height: original_size.h + double_padding
				});

				if (options.closeOnClick)
					$zoom.click(hide);

				$zoom.toggle(is_html).css('visibility', 'visible').animate(
					{
						left: on_left,
						top: on_top,
						width: on_width,
						height: on_height,
						opacity: is_html ? '+=0' : 'show'
					},
					800,
					null,
					function () {
						if (options.noScale)
							$zoom_content.html(img);

						if (!is_ie7)
							$zoom_desc_contain.css('overflow', 'visible');
						$zoom.css('zIndex', 999);

						// Now that our animation is finished, let's check whether
						// we double-clicked that thumbnail to request a full version!
						if (double_clicked)
							double_click(e);
						else
						{
							$zoom_content.one('dblclick', double_click);
							$zoom_close.show();
						}
						zooming = false;
						active = true;
					}
				).dragslide();
			};

			var $frame = $anchor.next('.zoom-html'), fw = $frame.width(), fh = $frame.height();
			if (!fw)
				$('<img>').bind('load.zoom', whenReady).attr('src', url);
			else
				whenReady.call($frame.clone().addClass('nodrag').appendTo($zoom_content).show()[0]);

			return false;
		}

		function double_click(e)
		{
			double_clicked = false;
			$fullsize = $zoom_desc.find('.fullsize').attr('href'); // $zoom_desc or $anchor.next('.zoom-overlay')
			if ($fullsize && img && img.src != $fullsize)
			{
				var pos = $img.offset();
				loading(pos.left + $img.width() / 2, pos.top + $img.height() / 2);
				$img.unbind('load.zoom').load(function () {
					var
						wt = img.naturalWidth,
						ht = img.naturalHeight,
						rezoom = function () {
							done_loading();
							$zoom_desc.css(css_width, css_auto);
							$zoom.animate({
								left: '-=' + (wt - $img.width()) / 2,
								top: '-=' + (ht - $img.height()) / 2,
								width: '+=' + (wt - $img.width()),
								height: '+=' + (ht - $img.height())
							}, 500, null, function () {
								$zoom_close.show();
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
			return false;
		}

		// Add the 'Loading' label at the center of our current object. If the item is already cached,
		// it'll hide it immediately, so we only show it if it's actually loading something.
		function loading(x, y)
		{
			show_loading = setTimeout(function () {
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
		}

		function done_loading()
		{
			$zoom_close.hide();
			clearTimeout(show_loading);
			$('.zoom-loading').hide();
		}

		function hide()
		{
			if (zooming || !active)
				return false;
			zooming = true;
			$zoom.unbind();
			$zoom_content.unbind();
			$('html').unbind('click.zoom');
			$(document).unbind('keyup.zoom');

			if (options.noScale)
				$zoom_content.html('');

			$zoom_close.hide();
			$zoom.animate(
				{
					left: original_size.x - padding,
					top: original_size.y - padding,
					width: original_size.w + double_padding,
					height: original_size.h + double_padding,
					opacity: 'hide'
				},
				800,
				null,
				function () {
					zooming = false;
					active = false;
					$zoom.remove();
				}
			);
			return false;
		}
	};

})(jQuery);