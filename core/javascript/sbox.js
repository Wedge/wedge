/*!
 * Selectbox replacement plugin for Wedge.
 *
 * Developed and customized/optimized for Wedge by Nao.
 * Contains portions by RevSystems (SelectBox)
 * and Maarten Baijs (ScrollBar).
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * License: http://wedge.org/license/
 */

(function ()
{
	var unique = 0,

	/*
		This product includes software developed
		by RevSystems, Inc (http://www.revsystems.com/) and its contributors

		Copyright (c) 2010 RevSystems, Inc

		The original license can be found in the file 'license.txt' at:
		https://github.com/revsystems/jQuery-SelectBox
	*/

	SelectBox = function ($orig)
	{
		var
			keyfunc = is_opera ? 'keypress.sb keydown.sb' : 'keydown.sb',
			fixed = $orig.hasClass('fixed'), // Should dropdown expand to widest and display conform to whatever is selected?
			resize_timeout,
			via_keyboard,
			has_changed,
			scrollbar,
			$selected,
			$display,
			$dd,
			$sb,
			$items,
			$orig_item,

		loadSB = function ()
		{
			// Create the new sb
			$sb = $('<div class="sbox ' + ($orig.attr('class') || '') + '" id="sb' + ($orig.attr('id') || ++unique) + '" role="listbox">')
				.attr('aria-haspopup', true);

			$display = $('<div class="display" id="sbd' + (++unique) + '">')
				// Generate the display markup
				.append(optionFormat($orig.data('default') || $orig.find('option:selected')).replace(/<small>.*?<\/small>/, ''))
				.append('<div class="btn">&#9660;</div>');

			// Generate the dropdown markup
			$dd = $('<div class="items" id="sbdd' + unique + '" role="menu" onselectstart="return!1;">')
				.attr('aria-hidden', true);

			// For accessibility/styling, and an easy custom .trigger('close') shortcut.
			$sb.append($display, $dd)
				.on('close', closeSB)
				.attr('aria-owns', $dd.attr('id'))
				.find('.details').remove();

			if (!$orig.children().length)
				$dd.append(createOption().addClass('selected'));
			else
				$orig.children().each(function ()
				{
					var $og = $(this), $optgroup;
					if ($og.is('optgroup'))
					{
						$dd.append($optgroup = $('<div class="optgroup"><div class="label">' + $og.attr('label') + '</div>'));
						$og.find('option').each(function () { $dd.append(createOption($(this)).addClass('sub')); });
						if ($og.is(':disabled'))
							$optgroup.nextAll().andSelf()
								.addClass('disabled').attr('aria-disabled', true);
					}
					else
						$dd.append(createOption($og));
				});

			// Cache all sb items
			$items = $dd.children().not('.optgroup');
			setSelected($orig_item = $items.filter('.selected,:has(input:checked)').first());

			$dd.children().first().addClass('first');
			$dd.children().last().addClass('last');

			// Place the new markup in its semantic location
			$orig
				.addClass('sb')
				.before($sb);

			// The 'apply' call below will return the widest width from a list of elements.
			if (fixed && $items.not('.disabled').length)
				$sb.width($dd.width() + $('.btn', $display).outerWidth() + 2); // So... I'm hardcoding button margins. Sue me.

			// Hide the dropdown now that it's initialized
			$dd.hide();

			// Attach the original select box's event attributes to our display area.
			if ($orig.attr('data-eve'))
				$.each($orig.attr('data-eve').split(' '), function () {
					$display.on(eves[this][0], eves[this][1]);
				});

			// Bind events
			if (!$orig.is(':disabled'))
			{
				// Causes focus if original is focused
				$orig.on('focus.sb', function () {
					blurAllButMe();
					focusSB();
				});

				$display
					.blur(blurSB)
					.focus(focusSB)
					.mousedown(false) // Prevent double clicks
					.hover(function () { $(this).toggleClass('hover'); })
					// When the user explicitly clicks the display. closeAndUnbind calls closeSB and cancels the current selection.
					.click(displayClick);
				$items.not('.disabled')
					.hover(
						// Use selectItem() instead of setSelected() to do the display animation on hover.
						function () { if ($sb.hasClass('open')) setSelected($(this)); },
						function () { $(this).removeClass('selected'); $selected = $orig_item; }
					)
					.mousedown(clickSBItem);
				$dd.children('.optgroup')
					.mousedown(false);
				$items.filter('.disabled')
					.mousedown(false);

				if (!is_ie8down)
					$(window).on('resize.sb', function ()
					{
						clearTimeout(resize_timeout);
						resize_timeout = setTimeout(function () { if ($sb.hasClass('open')) openSB(1); }, 50);
					});
			}
			else
			{
				$sb.addClass('disabled').attr('aria-disabled', true);
				$display.click(false);
			}
		},

		// Create new markup from an <option>
		createOption = function ($option)
		{
			$option = $option || $('<option>');

			// If you want to hide an option (e.g. placeholder),
			// you can use the magic word: <option data-hide>
			var visible = $option.attr('data-hide') !== '';

			return $('<div id="sbo' + (++unique) + '" role="option" class="pitem">')
				.data('orig', $option)
				.data('value', $option.attr('value') || '')
				.attr('aria-disabled', !!$option.is(':disabled'))
				.toggleClass('disabled', !visible || $option.is(':disabled,.hr'))
				.toggleClass('selected', $option.is(':selected') && (!$orig.attr('multiple') || $option.hasClass('single')))
				.toggle(visible)
				.append(
					$('<div class="item">')
						.attr('style', $option.attr('style') || '')
						.addClass($option.attr('class'))
						.append(optionFormat($option, !$orig.attr('multiple') || $option.hasClass('single') ? '' : '<input type="checkbox" onclick="this.checked = !this.checked;"' + ($option.is(':selected') ? ' checked' : '') + '>'))
				);
		},

		// Formatting for the display
		optionFormat = function ($dom, extra)
		{
			return '<div class="text">' + (extra || '') + (($dom.text ? $dom.text().split('|').join('</div><div class="details">') : $dom + '') || '&nbsp;') + '</div>';
		},

		// Hide and reset dropdown markup
		closeSB = function (instantClose)
		{
			if ($sb.hasClass('open'))
			{
				scrollbar = '';
				$display.blur();
				$sb.removeClass('open');
				$dd.removeClass('has_bar');
				$dd.animate(is_opera ? { opacity: 'toggle' } : { opacity: 'toggle', height: 'toggle' }, instantClose == 1 ? 0 : 100);
				$dd.attr('aria-hidden', true);
				$dd.find('.scrollbar').remove();
				$dd.find('.overview').contents().unwrap().unwrap();
			}
			$(document).off('.sb');
		},

		// When the user clicks outside the sb
		closeAndUnbind = function (clicked_on_display)
		{
			$sb.removeClass('focused');
			closeSB();
			if ($selected.data('value') !== $orig.val())
				selectItem($orig_item, true);
			if (clicked_on_display === 1)
				focusSB();
		},

		// Trigger all select boxes to blur
		blurAllButMe = function ()
		{
			$('.sbox.focused').not($sb).find('.display').blur();
		},

		// Reposition the scroll of the dropdown so the selected option is centered (or appropriately onscreen)
		centerOnSelected = function ()
		{
			if (scrollbar)
				scrollbar.st($selected.is(':hidden') ? 0 : $selected.position().top, $selected.height());
			else
				$dd.scrollTop($dd.scrollTop() + $selected.offset().top - $dd.offset().top - $dd.height() / 2 + $selected.outerHeight(true) / 2);
		},

		displayClick = function (e) {
			$sb.hasClass('open') ? closeAndUnbind(1) : openSB(0, 1);
			e && e.stopPropagation();
		},

		// Show, reposition, and reset dropdown markup.
		openSB = function (instantOpen, doFocus)
		{
			blurAllButMe();

			// Focus the element before we actually open it. If called through a click,
			// we'll also actually simulate the focus to trigger any related events.
			doFocus ? $orig.triggerHandler('focus') : focusSB();

			// Stop dropdown animation (if any), and hack into its visibility to get proper widths.
			$dd
				.stop(true, true)
				.show()
				.css({ visibility: 'hidden' })
				.width('')
				.height('')
				.removeClass('above')
				.find('.viewport')
					.height('');

			// Hide .details so that the new width won't be influenced by it.
			$dd.find('.details').toggle();
			// Set dropdown width to at least the display area's width, and at most the screen's width minus (potential) scrollbar width.
			$dd.width(Math.min($(window).width() - 25, Math.max($dd.realWidth(), $display.outerWidth() - $dd.outerWidth(true) + $dd.realWidth())));
			// Now we can reset.
			$dd.find('.details').toggle();

			var
				// Figure out if we should show above/below the display box, first by calculating the free space around it.
				ddHeight = $dd.outerHeight(),
				bottomSpace = $(window).scrollTop() + Math.min($(window).height(), $('body').height()) - $display.offset().top - $display.outerHeight(),
				topSpace = $display.offset().top - $(window).scrollTop(),

				// Show scrollbars if the dropdown is taller than 250 pixels (or the viewport height).
				// Touch-enabled phones have poor usability and shouldn't bother -- let them stretch all the way.
				ddMaxHeight = Math.max(Math.min(ddHeight, 50), Math.min(Math.max(500, ddHeight / 5), ddHeight, Math.max(bottomSpace, topSpace - 50) - 50)),

				// If we have enough space below the button, or if we don't have enough room above either, show a dropdown.
				// Otherwise, show a drop-up, but only if there's enough size, or the space above is more comfortable.
				showDown = (ddMaxHeight <= bottomSpace) || ((ddMaxHeight >= topSpace) && (bottomSpace >= topSpace - 50));

			// Create a custom scrollbar for our select box?
			if (ddMaxHeight < ddHeight)
			{
				$dd.height(ddMaxHeight - ddHeight + $dd.height());
				scrollbar = new ScrollBar($dd);
				centerOnSelected();
			}

			$selected.addClass('selected');

			// Modify dropdown css for display, and ensure it doesn't go out of bounds.
			$dd
				.attr('aria-hidden', false)
				.toggleClass('above', !showDown)
				.css({
					visibility: 'visible',
					marginTop: showDown ? 0 : -ddMaxHeight - $display.outerHeight(),
					marginLeft: Math.min(0, $(window).width() - $dd.outerWidth() - $sb.offset().left)
				})
				.hide();

			// If opening via a key stroke, simulate a click.
			if (via_keyboard)
				$orig.triggerHandler('click');

			// Animate height, except for Opera where issues with the inline-block status may lead to glitches.
			$dd.animate(!showDown || is_opera ? { opacity: 'toggle' } : { opacity: 'toggle', height: 'toggle' }, instantOpen ? 0 : 200);
			$sb.addClass('open');
		},

		// When the user selects an item in any manner
		selectItem = function ($item, no_open, is_clicking)
		{
			var $newtex = $item.find('.text'), $oritex = $display.find('.text'), oriwi = $oritex.width(), newwi;

			// If we're selecting an item and the box is closed, open it.
			if (!no_open && !$sb.hasClass('open'))
				openSB();

			setSelected($item, is_clicking);

			// Update the title attr and the display markup
			$oritex
				.width('')
				.html(($newtex.html() || '&nbsp;').replace(/<small>.*?<\/small>/, ''))
				.attr('title', $newtex.text().php_unhtmlspecialchars());

			newwi = $oritex.width();
			if (!fixed)
				$oritex.stop(true, true).width(oriwi).delay(100).animate({ width: newwi });
		},

		setSelected = function ($item, is_clicking)
		{
			// If the select box has just been rebuilt, reset its selection.
			// Good to know: !$items.has($item).length is 60 times slower.
			// !$items.filter($item).length is about 30 times slower.
			// !in_array($item[0], $items.get()) is 5 to 6 times slower.
			if (!$item[0].parentNode === $dd[0])
				$item = $orig_item;

			// Change the selection to the first selected item in the list
			if ($orig.attr('multiple') && !$item.has('>.single').length)
				$selected = $items.filter('.selected,:has(input:checked)').first();
			else
			{
				// Change the selection to this item
				$selected = $item.addClass('selected');
				$items.not($selected).removeClass('selected');
				if (is_clicking)
					$items.not($selected).find('input').prop('checked', false);
			}
			$sb.attr('aria-activedescendant', $selected.attr('id'));
		},

		updateOriginal = function ()
		{
			// Trigger change on the old <select> if necessary
			has_changed = $orig.val() !== $selected.data('value') && !$selected.hasClass('disabled');

			// Update the original <select>
			if ($orig.attr('multiple'))
				$items.each(function () {
					if ($(this).data('orig')[0].selected != $(this).find('input').prop('checked'))
						$(this).data('orig')[0].selected = $(this).find('input').prop('checked') || $(this).hasClass('selected');
				});
			else
			{
				$orig.find('option')[0].selected = false;
				$selected.data('orig')[0].selected = true;
			}

			$orig_item = $selected;
		},

		// When the user explicitly clicks an item
		clickSBItem = function (e)
		{
			if (e.which == 1 && (!$orig.attr('multiple') || $(this).has('>.single').length))
			{
				selectItem($(this), false, true);
				updateOriginal();
				closeAndUnbind();
				focusSB();
			}
			else if (e.which == 1)
			{
				$items.filter('.selected').removeClass('selected');
				$(this).find('input').prop('checked', !$(this).find('input').prop('checked'));
				setSelected($(this));
				updateOriginal();
			}
			if (has_changed)
			{
				$orig.triggerHandler('change');
				has_changed = false;
			}

			return false;
		},

		// Iterate over all the options to see if any match the search term.
		// If we get a match for any options, select it.
		selectMatchingItem = function (term)
		{
			var $available = $items.not('.disabled'), from = $available.index($selected) + 1, to = $available.length, i = from;

			while (true)
			{
				for (; i < to; i++)
					if ($available.eq(i).text().toLowerCase().match('^' + term.toLowerCase()))
						return selectItem($available.eq(i)) || true;

				if (!from)
					return false;

				// Nothing found? Try to search again from the start...
				to = from;
				from = i = 0;
			}
		},

		// Go up/down using arrows or attempt to autocomplete based on string
		keyPress = function (e)
		{
			if (e.altKey || e.ctrlKey)
				return;

			var $enabled = $items.not('.disabled');
			via_keyboard = true;

			// User pressed tab? If the list is opened, confirm the selection and close it. Then either way, switch to the next DOM element.
			if (e.keyCode == 9)
			{
				if ($sb.hasClass('open'))
				{
					updateOriginal();
					closeSB();
				}
				blurSB();
			}
			// Spaces should open or close the dropdown, cancelling the latest selection. Requires e.which instead of e.keyCode... confusing.
			else if (e.which == 32)
			{
				$sb.hasClass('open') ? closeAndUnbind() : (openSB(), centerOnSelected());
				focusSB();
				e.preventDefault();
			}
			// Backspace or return (with the select box open) will do the same as pressing tab, but will keep the current item focused.
			else if ((e.keyCode == 8 || e.keyCode == 13) && $sb.hasClass('open'))
			{
				updateOriginal();
				closeSB();
				focusSB();
				e.preventDefault();
			}
			else if (e.keyCode == 35) // end
			{
				selectItem($enabled.last(), true);
				centerOnSelected();
				updateOriginal();
				e.preventDefault();
			}
			else if (e.keyCode == 36) // home
			{
				selectItem($enabled.first(), true);
				centerOnSelected();
				updateOriginal();
				e.preventDefault();
			}
			else if (e.keyCode == 38) // up
			{
				selectItem($enabled.eq($enabled.index($selected) - 1), true);
				centerOnSelected();
				updateOriginal();
				e.preventDefault();
			}
			else if (e.keyCode == 40) // down
			{
				selectItem($enabled.eq(($enabled.index($selected) + 1) % $enabled.length), true);
				centerOnSelected();
				updateOriginal();
				e.preventDefault();
			}
			// Also, try finding the next element that starts with the pressed letter. if found, select it.
			else if (e.which < 91 && selectMatchingItem(String.fromCharCode(e.which)))
				e.preventDefault();

			if (has_changed)
			{
				$orig.triggerHandler('change');
				has_changed = false;
			}

			via_keyboard = false;
		},

		// When the sb is focused (by tab or click), allow hotkey selection and kill all other selectboxes
		focusSB = function ()
		{
			// Close all select boxes but this one, to prevent multiple selects open at once.
			$('.sbox.open').not($sb).trigger('close');

			$sb.addClass('focused');
			$(document)
				.off('.sb')
				.on(keyfunc, keyPress)
				.on('mousedown.sb', closeAndUnbind);
		},

		// When the sb is blurred (by tab or click), disable hotkey selection
		blurSB = function ()
		{
			$sb.removeClass('focused');
			$(document).off(keyfunc);
		};

		// Destroy then load, maintaining open/focused state if applicable
		this.re = function ()
		{
			var wasOpen = $sb.hasClass('open'), wasFocused = $sb.hasClass('focused');

			closeSB(1);

			// Destroy existing data
			$sb.remove();
			$orig.removeClass('sb').off('.sb');
			$(window).off('.sb');

			loadSB();

			if (wasOpen)
				openSB(1);
			else if (wasFocused)
				focusSB();
		};

		this.open = displayClick;

		loadSB();
	},

	/*
		This product includes software developed
		by Maarten Baijs (http://www.baijs.nl/tinyscrollbar/),
		and originally released under the MIT license:
		http://www.opensource.org/licenses/mit-license.php

		Copyright (c) 2010
	*/

	ScrollBar = function ($dd)
	{
		var
			that = this, startPos = 0, iMouse, iScroll = 0, iThumb = 0,
			thumbAxis, viewportAxis, contentAxis,
			$content, $scrollbar, $thumb,
			scrollbarRatio, iTouch,

		drag = function (e)
		{
			that.st(startPos + e.pageY - iMouse);
			return false;
		},

		set_hw_top = function ($where, top)
		{
			// Limiting hardware acceleration to Chrome Mobile, as it works fine there.
			// Desktop browsers are powerful enough not to need HW acceleration.
			if (is_touch && is_chrome)
				$where.css({
					transform: 'translate3d(0,' + parseInt(top) + 'px,0)'
				});
			else
				$where.css({
					top: parseInt(top)
				});
		};

		// Scroll to...
		that.st = function (iTop, iHeight)
		{
			if (iHeight)
				iTop = (iTop - viewportAxis / 2 + iHeight / 2) / scrollbarRatio;

			iScroll = Math.min(viewportAxis - thumbAxis, Math.max(0, iTop)) * scrollbarRatio;
			set_hw_top($thumb, iThumb = iScroll / scrollbarRatio);
			set_hw_top($content, -iScroll);
		};

		if ($dd.find('.viewport').length)
			return;

		// Gentlemen, start your engines.
		$dd.addClass('has_bar').width(Math.min($dd.width(), $(window).width() - 25));
		$dd.contents().wrapAll('<div class="viewport"><div class="overview">');
		$dd.append('<div class="scrollbar"><div>');

		viewportAxis = $dd.height();
		$dd.find('.viewport').height(viewportAxis);
		$scrollbar = $dd.find('.scrollbar').height(viewportAxis);
		$content = $dd.find('.overview');
		contentAxis = $content.height();
		$thumb = $scrollbar.find('div');

		scrollbarRatio = contentAxis / viewportAxis;
		thumbAxis = Math.min(viewportAxis, viewportAxis / scrollbarRatio);

		// Set size.
		iMouse = $thumb.offset().top;
		$thumb.height(thumbAxis);

		// Set events
		$scrollbar.mousedown(drag);
		$thumb.mousedown(function (e)
		{
			iMouse = e.pageY;
			startPos = iThumb;
			$(document)
				.on('mousemove.sc', drag)
				.on('mouseup.sc', function () { $(document).off('.sc'); return false; });
			return false;
		});

		$dd
			.on('DOMMouseScroll mousewheel', function (e)
			{
				// Below: (wheelDelta * 40/120) or (-detail * 40/3) = 40 pixels per wheel movement
				iScroll = Math.min(contentAxis - viewportAxis, Math.max(0, iScroll - (e.originalEvent.wheelDelta || -e.originalEvent.detail * 40) / 3));
				set_hw_top($thumb, iThumb = iScroll / scrollbarRatio);
				set_hw_top($content, -iScroll);
				e.preventDefault();
			})
			// This should add support for scrolling on touch devices.
			.on('touchstart', function (e) {
				iTouch = e.originalEvent.touches[0].pageY * 1.5 / scrollbarRatio;
				startPos = iThumb;
			})
			.on('touchmove', function (e) {
				that.st(startPos - e.originalEvent.touches[0].pageY * 1.5 / scrollbarRatio + iTouch);
				e.preventDefault();
			});
	};

	// .sb() takes a select box and restyles it.
	// A normal <select> will be displayed for old browsers
	$.fn.sb = function ()
	{
		// Chain methods!
		return this.each(function ()
		{
			var $e = $(this), obj = $e.data('sb');

			// If it is already created, then reload it.
			if (obj)
				obj.re();

			// If the object is not defined for this element, and it's a drop-down, then create and initialize it.
			else if (!$e.attr('size'))
				$e.data('sb', new SelectBox($e));
		});
	};
})();
