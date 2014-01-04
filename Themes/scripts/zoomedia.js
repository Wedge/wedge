/*!
 * Zoomedia is the lightbox component for all media Wedge.
 * Developed by René-Gilles Deberdt (Nao) for Wedge.
 * Uses portions by Steve Smith (http://orderedlist.com/)
 *
 * @package Wedge
 * @copyright 2010 René-Gilles Deberdt, wedge.org
 * @license http://wedge.org/license/
 * @author see contributors.txt
 */

@language Media;

$.fn.zoomedia = function (options)
{
	var
		double_clicked, img, $img, $fullsize, $anchor, $ele,
		padding, animation, supports_hardware, src, tar,
		transition_duration,

		$zoom, $zoom_desc, $zoom_close,
		$zoom_content, $zoom_desc_contain,

		// !! Temporary code. Need to implement more features! Or not!
		lang = {
/*			move: $txt['media_zoom_move'],
			close: $txt['media_close'], */
			closeTitle: $txt['media_zoom_close_title'] /*,
/*			loadingTitle: $txt['media_zoom_clicktocancel'],
			restoreTitle: $txt['media_zoom_clicktoclose'],
			focusTitle: $txt['media_zoom_focus'],
			fullExpandTitle: $txt['media_zoom_expandtoactual'],
			previousTitle: $txt['media_zoom_previous'],
			nextTitle: $txt['media_zoom_next'],
			playTitle: $txt['media_zoom_play'],
			pauseTitle: $txt['media_zoom_pause'] */
		},

		options = options || {},
		outline = options.outline || '',

		zooming = active = false,
		original_size = {},
		$win = $(window),

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
				<a href="#" title="' + (lang.closeTitle || '') + '" id="zoom-close">×</a>\
			</div>');

		$zoom = $('#zoom');
		$zoom_desc = $('#zoom-desc');
		$zoom_content = $('#zoom-content').dblclick(false);
		$zoom_close = $('#zoom-close').click(hide);
		$zoom_desc_contain = $('.zoom-desc-contain', $zoom);

		// Use namespaces on any events not inside our zoom box.
		$(document)
			.on('click.zoom', function (e) {
				if (active && e.target.id != 'zoom' && !$(e.target).closest('#zoom:visible').length)
					hide();
			})
			.on('keyup.zoom', function (e) {
				if (active && e.which == 27)
					hide();
			});

		$anchor = $(this);
		$ele = $anchor.children().first();

		var offset = $ele.offset();

		original_size = {
			x: offset.left,
			y: offset.top,
			w: $ele.width(),
			h: $ele.height()
		};

		show_ajax($ele);

		// This gets executed once the item to zoom is ready to show.
		var whenReady = function ()
		{
			$img = $(img = this);

			// Width set by options, or natural image width, or div width if it's HTML.
			var
				img_width = options.width || img.width || $img.width(),
				img_height = options.height || img.height || $img.height() || 1,
				ratio = img_width / img_height,

				// window.innerWidth is more 'correct' in mobile mode, but in desktop it also counts the scrollbar size.
				win_width = Math.min(window.innerWidth || $win.width(), $win.width()),
				win_height = Math.min(window.innerHeight || $win.height(), $win.height()),
				is_html = !!$frame.length;

			hide_ajax();
			$zoom_content.html($img.addClass('scale'));
			$zoom_desc.html($anchor.next('.zoom-overlay').html() || '');
			padding = $zoom.width() - img_width;

			// If the image is too large for our viewport, reduce it horizontally.
			if (img_width > win_width - 16)
			{
				img_width += win_width - 16 - $zoom.width();
				img_height = img_width / ratio;
				$img
					.width(img_width)
					.height(img_height);
			}

			// And/or if it's too tall, reduce it even more.
			if ($zoom.height() > win_height - 16)
			{
				img_height += win_height - 16 - $zoom.height();
				img_width = img_height * ratio;
				$img
					.width(img_width)
					.height(img_height);
			}

			var width = $zoom.width(), height = $zoom.height();
			$zoom.offset({
				left: Math.max(0, $win.scrollLeft() + (win_width - width) / 2),
				top: Math.max(0, $win.scrollTop() + (win_height - height) / 2)
			});

			if (!is_html)
				$img.width('100%').height('auto');

			src = {
				left: original_size.x + parseInt($ele.css('padding-left')),
				top: original_size.y + parseInt($ele.css('padding-top')),
				width: original_size.w,
				height: original_size.h,
				opacity: 0
			};

			compute_zoom(width, height);

			var s = $zoom[0].style, supports = function (name) {
				return name.toLowerCase() in s || ('Webkit' + name) in s || ('Moz' + name) in s || ('O' + name) in s || ('ms' + name) in s;
			};

			// We need to use both transforms and transitions. IE9, for instance, doesn't support both.
			supports_hardware = supports('Transition') && supports('Transform');

			// Translate from final to initial state.
			$zoom.css(tar).css('transform', animation).css({ opacity: 0 }).width();
			$zoom.addClass('anim');

			// This is a quick and dirty way to extract a second or millisecond-based duration.
			var css_duration = $zoom.css('transition-duration') || '.7s', duration = parseFloat(css_duration);
			transition_duration = css_duration.indexOf(duration + 'ms') > -1 ? duration : duration * 1000;

			if (supports_hardware)
				$zoom.css('transform', '').css({ opacity: 1 }); // Remove the translation; this starts the animation.
			else
				$zoom.stop(true).css(src).animate(tar, transition_duration);

			$zoom.ds();

			setTimeout(function ()
			{
				// Disable the main animation, because it would slow down dragging.
				// At this point, we set the z-index to be above the invisible layer, which
				// has served its purpose (i.e. capturing double-clicks during the animation.)
				$zoom.addClass('animdone').css('zIndex', 800).width();

				// Now that our animation is finished, let's check whether
				// we double-clicked that thumbnail to request a full version!
				if (double_clicked)
					double_click(e);
				else
				{
					// Time to show the description...
					$zoom.height('auto');
					if ($zoom_desc.html() && !$zoom_desc.is(':visible'))
						$zoom_desc_contain.slideDown(function () { $zoom.height('auto'); });

					$zoom_close.fadeIn(300, 'linear');
					$zoom_content.one('dblclick', double_click);
					$zoom_desc.find('.fullsize').click(double_click);
				}
				zooming = false;
				active = true;
			}, transition_duration);
		};

		var $frame = $anchor.next('.zoom-html');
		if (!$frame.width())
			$('<img>').on('load.zoom', whenReady).attr('src', $anchor[0].href);
		else
			whenReady.call($frame.clone().addClass('nodrag').appendTo($zoom_content).show()[0]);

		return false;
	},

	double_click = function (e)
	{
		double_clicked = false;
		$fullsize = $zoom_desc.find('.fullsize').attr('href'); // $zoom_desc or $anchor.next('.zoom-overlay')
		$zoom_desc.find('.fullsize').next().andSelf().remove();
		if ($fullsize && img && img.src != $fullsize)
		{
			show_ajax($img);
			$img.off('load.zoom').load(function ()
			{
				var
					wt = img.naturalWidth,
					ht = img.naturalHeight,
					rezoom = function () {
						var w2 = Math.min($win.width() - $zoom.width() + $img.width(), wt, wt * ($win.height() - $zoom.height() + $img.height()) / ht);
						ht = ht * w2 / wt;
						hide_ajax();
						$zoom.animate({
							left: '-=' + (w2 - $img.width() - 10) / 2,
							top: '-=' + (ht - $img.height() - 10) / 2,
							width: '+=' + (w2 - $img.width() - 10),
							height: '+=' + (ht - $img.height() - 10)
						}, 500, null, function () {
							// Time to show the description...
							$zoom.height('auto');
							if ($zoom_desc.html() && !$zoom_desc.is(':visible'))
								$zoom_desc_contain.slideDown(function () { $zoom.height('auto'); });

							$zoom_close.fadeIn(300, 'linear');
						});
					};
				if (wt > 0)
					rezoom();
				// Stupid IE forces us to emulate natural properties through a hidden img...
				else
					$('<img>').load(function () {
						wt = this.width;
						ht = this.height;
						$(this).remove();
						rezoom();
					}).attr('src', img.src);
			}).attr('src', $fullsize);
		}
		else
			$zoom_close.fadeIn(300, 'linear');

		return false;
	},

	hide = function ()
	{
		if (zooming || !active)
			return false;
		zooming = true;
		$($zoom, $zoom_content).off();
		$(document).off('.zoom');

		$zoom_close.fadeOut(300);
		$zoom_desc_contain.slideUp(100);

		setTimeout(function () {
			compute_zoom($zoom.width(), $zoom.height());
			if (supports_hardware)
				$zoom.css('transform', '').removeClass('animdone').css('transform', animation).css({ opacity: 0 });
			else
				$zoom.animate(src, transition_duration);

			setTimeout(function () {
				zooming = false;
				active = false;
				$zoom.remove();
			}, transition_duration);
		}, $zoom_desc.html() ? 100 : 1);

		return false;
	},

	compute_zoom = function (width, height)
	{
		$zoom_desc_contain.hide();
		tar = {
			left: $zoom.offset().left,
			top: $zoom.offset().top,
			width: width,
			height: height,
			opacity: 1
		};

		// The scaling ratio is calculated based on the size of the images. It'll be off by a couple of pixels if you don't
		// have equivalent padding on the thumbnail, though. But it's all right, opacity is close to 0 at that point.
		animation = 'translate3d('
			+ (src.left - tar.left - (tar.width - src.width) / 2) + 'px,'
			+ (src.top - tar.top - (tar.height - src.height) / 2) + 'px,0)'
			+ ' scale(' + $ele.width() / $img.width() + ')';

		/*
			// This animation is amusing... The scaling is done first, so we need to adjust
			// the translation, but it unexpectedly created a less linear animation.
			// It's probably not to everyone's taste, though, so it's disabled for now!

			animation = 'scale(' + $ele.width() / $img.width() + ') translate3d('
				+ (src.left - tar.left - (tar.width - src.width) / 2) / ($ele.width() / $img.width()) + 'px,'
				+ (src.top - tar.top - (tar.height - src.height) / 2) / ($ele.width() / $img.width()) + 'px,0)';
		*/
	};

	$(this).each(function ()
	{
		if (this.className.indexOf('processed') >= 0)
			return this;

		$(this).addClass('processed').click(show).dblclick(function (e) {
			if (zooming)
				double_clicked = true;
			return false;
		});
		// This child layer will overlay the initial thumbnail and catch
		// any double-clicks on it, even while it's being animated.
		$('<div>').addClass('zoom-catcher').appendTo(this).mousedown(false);
	});

	return this;
};
