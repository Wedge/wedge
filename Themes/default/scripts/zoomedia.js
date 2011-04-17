
// Based upon Steve Smith's Fancy Zoom implementation.
// http://orderedlist.com/
(function ($) {

	$.fn.zoomedia = function (options, onComplete) {

		if (!$zoom)
		{
			$('body').append('<div id="zoom"><div id="zoom_content"></div><a href="#" title="Close" id="zoom_close"></a></div>');

			var
				$zoom = $('#zoom'),
				$zoom_close = $('#zoom_close'),
				$zoom_content = $('#zoom_content');

			$('html').click(function (e) {
				if (!$(e.target).parents('#zoom:visible').length)
					hide();
			});
			$(document).keyup(function (e) {
				if (e.keyCode == 27 && $('#zoom:visible').length)
					hide();
			});

			$zoom_close.click(hide);
		}

		var
			options = options || {},
			zooming = false,
			original_size = {},
			px = 'px';

		this.each(function () {
			$(this).click(show);
		});

		return this;

		function show(e)
		{
			if (zooming)
				return false;
			zooming = true;
			var
				url = this.href,
				$anchor = $(this),
				offset = $anchor.offset();

			original_size = {
				x: offset.left,
				y: offset.top,
				w: $anchor.width(),
				h: $anchor.height()
			};

			var whenReady = function () {

				var
					img = this,
					win = $(window),
					win_width = win.width(),
					win_height = win.height(),
					width = (options.width || img.width) + 32,
					height = (options.height || img.height) + 32,
					on_width = win_height < height ? width * (win_height / height) : width,
					on_height = Math.min(win_height, height);

				$zoom
					.hide()
					.css({
						top: original_size.y + px,
						left: original_size.x + px,
						width: original_size.w + px,
						height: original_size.h + px
					});

				$zoom_close.hide();

				if (options.closeOnClick)
					$zoom.click(hide);

				$zoom_content.html(options.noScale ? '' : $(img).addClass('scale'));

				$zoom.animate(
					{
						top: Math.max((win_height - on_height) / 2 + win.scrollTop(), 0) + px,
						left: ((win_width - on_width) / 2 + win.scrollLeft()) + px,
						width: on_width,
						height: on_height,
						opacity: 'show'
					},
					500,
					null,
					function () {
						if (options.noScale)
							$zoom_content.html(img);

						$zoom_close.show();
						zooming = false;
					}
				).dragslide();
			};

			$('<img>').load(whenReady).attr('src', url);
			return false;
		}

		function hide()
		{
			if (zooming)
				return false;
			zooming = true;
			$zoom.unbind();

			if (options.noScale)
				$zoom_content.html('');

			$zoom_close.hide();

			$zoom.animate(
				{
					top: original_size.y + px,
					left: original_size.x + px,
					width: original_size.w + px,
					height: original_size.h + px,
					opacity: 'hide'
				},
				500,
				null,
				function () {
					zooming = false;
					if (!options.noScale)
						$zoom_content.html('');
				}
			);
			return false;
		}
	}

})(jQuery);