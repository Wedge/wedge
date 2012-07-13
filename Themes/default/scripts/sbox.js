/**
 * Wedge
 *
 * Selectbox replacement plugin for Wedge.
 *
 * Developed and customized/optimized for Wedge by Nao.
 * Contains portions by RevSystems (SelectBox)
 * and Maarten Baijs (ScrollBar).
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
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
			keyfunc = is_opera ? 'keypress.sb' : 'keydown.sb',
			fixed = $orig.hasClass('fixed'), // Should dropdown expand to widest and display conform to whatever is selected?
			resizeTimeout,
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
			$sb = $('<div class="sbox ' + $orig.attr('class') + '" id="sb' + ($orig.attr('id') || ++unique) + '" role="listbox"></div>')
				.attr('aria-haspopup', true);

			$display = $('<div class="display" id="sbd' + ++unique + '"></div>')
				// Generate the display markup
				.append(optionFormat($orig.data('default') || $orig.find('option:selected')))
				.append('<div class="btn"><div></div></div>');

			// Generate the dropdown markup
			$dd = $('<div class="items" id="sbdd' + unique + '" role="menu" onselectstart="return false;"></div>')
				.attr('aria-hidden', true);

			// For accessibility/styling, and an easy custom .trigger('close') shortcut.
			$sb.append($display, $dd)
				.bind('close', closeSB)
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
						$dd.append($optgroup = $('<div class="optgroup"><div class="label">' + $og.attr('label') + '</div></div>'));
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
			setSelected($orig_item = $items.filter('.selected'));

			$dd.children(':first').addClass('first');
			$dd.children(':last').addClass('last');

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
					$display.bind(eves[this][0], eves[this][1]);
				});

			// Bind events
			if (!$orig.is(':disabled'))
			{
				// Causes focus if original is focused
				$orig.bind('focus.sb', function () {
					blurAllButMe();
					focusSB();
				});

				$display
					.blur(blurSB)
					.focus(focusSB)
					.mousedown(false) // Prevent double clicks
					.hover(function () { $(this).toggleClass('hover'); })
					// When the user explicitly clicks the display. closeAndUnbind calls closeSB and cancels the current selection.
					.click(function () { $sb.hasClass('open') ? closeAndUnbind(1) : openSB(0, 1); });
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
					$(window).bind('resize.sb', function ()
					{
						clearTimeout(resizeTimeout);
						resizeTimeout = setTimeout(function () { if ($sb.hasClass('open')) openSB(1); }, 50);
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
			$option = $option || $('<option></option>');

			// If you want to hide an option (e.g. placeholder),
			// you can use the magic word: <option data-hide>
			var visible = $option.attr('data-hide') !== '';

			return $('<div id="sbo' + ++unique + '" role="option"></div>')
				.data('orig', $option)
				.data('value', $option.attr('value') || '')
				.attr('aria-disabled', !!$option.is(':disabled'))
				.toggleClass('disabled', !visible || $option.is(':disabled,.hr'))
				.toggleClass('selected', $option.is(':selected'))
				.toggle(visible)
				.append(
					$('<div class="item"></div>')
						.attr('style', $option.attr('style') || '')
						.addClass($option.attr('class'))
						.append(optionFormat($option))
				);
		},

		// Formatting for the display
		optionFormat = function ($dom)
		{
			return '<div class="text">' + (($dom.text ? $dom.text().replace(/\|/g, '</div><div class="details">') : $dom + '') || '&nbsp;') + '</div>';
		},

		// Destroy then load, maintaining open/focused state if applicable
		reloadSB = function ()
		{
			var wasOpen = $sb.hasClass('open'), wasFocused = $sb.hasClass('focused');

			closeSB(1);

			// Destroy existing data
			$sb.remove();
			$orig.removeClass('sb')
				.unbind('.sb');
			$(window)
				.unbind('.sb');

			loadSB();

			if (wasOpen)
				openSB(1);
			else if (wasFocused)
				focusSB();
		},

		// Hide and reset dropdown markup
		closeSB = function (instantClose)
		{
			if ($sb.hasClass('open'))
			{
				$display.blur();
				$sb.removeClass('open');
				$dd
					.animate(is_opera ? { opacity: 'toggle' } : { opacity: 'toggle', height: 'toggle' }, instantClose == 1 ? 0 : 100)
					.attr('aria-hidden', true);
			}
			$(document).unbind('.sb');
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

		// Show, reposition, and reset dropdown markup.
		openSB = function (instantOpen, doFocus)
		{
			blurAllButMe();

			// Focus the element before we actually open it. If called through a click,
			// we'll also actually simulate the focus to trigger any related events.
			doFocus ? $orig.triggerHandler('focus') : focusSB();

			// Stop dropdown animation (if any), and hack into its visibility to get its (.details-free) width.
			$dd.stop(true, true).show().css({ visibility: 'hidden' }).find('.details').toggle();

			// Set dropdown width to at least the display area's width.
			$dd.width(Math.max($dd.width(), $display.outerWidth() - $dd.outerWidth(true) - $dd.width() + 1)).find('.details').toggle();

			var
				// Figure out if we should show above/below the display box, first by calculating the free space around it.
				ddHeight = $dd.outerHeight(),
				bottomSpace = $(window).scrollTop() + $(window).height() - $display.offset().top - $display.outerHeight(),
				topSpace = $display.offset().top - $(window).scrollTop(),

				// Show scrollbars if the dropdown is taller than 250 pixels (or the viewport height).
				// Touch-enabled phones have poor usability and shouldn't bother -- let them stretch all the way.
				ddMaxHeight = Math.min(250, ddHeight, Math.max(bottomSpace + 50, topSpace)),

				// If we have enough space below the button, or if we don't have enough room above either, show a dropdown.
				// Otherwise, show a drop-up, but only if there's enough size, or the space above is more comfortable.
				showDown = (ddMaxHeight <= bottomSpace) || ((ddMaxHeight >= topSpace) && (bottomSpace + 50 >= topSpace));

			// Modify dropdown css for display
			$dd.css('marginTop', showDown ? 0 : -ddMaxHeight - $display.outerHeight())
				.toggleClass('above', !showDown);

			// Create a custom scrollbar for our select box?
			if (ddMaxHeight < ddHeight)
			{
				$dd.height(ddMaxHeight - ddHeight + $dd.height());

				if (!scrollbar)
				{
					scrollbar = new ScrollBar($dd);
					centerOnSelected();
				}
			}

			$selected.addClass('selected');

			$dd.hide().css({ visibility: 'visible' })
				.attr('aria-hidden', false);

			// If opening via a key stroke, simulate a click.
			if (via_keyboard)
				$orig.triggerHandler('click');
			// Animate height, except for Opera where issues with the inline-block status may lead to glitches.
			$dd.animate(!showDown || is_opera ? { opacity: 'toggle' } : { opacity: 'toggle', height: 'toggle' }, instantOpen ? 0 : 150);
			$sb.addClass('open');
		},

		// When the user selects an item in any manner
		selectItem = function ($item, no_open)
		{
			var $newtex = $item.find('.text'), $oritex = $display.find('.text'), oriwi = $oritex.width(), newwi;

			// If we're selecting an item and the box is closed, open it.
			if (!no_open && !$sb.hasClass('open'))
				openSB();

			setSelected($item);

			// Update the title attr and the display markup
			$oritex
				.width('auto')
				.html($newtex.html() || '&nbsp;')
				.attr('title', $newtex.text().php_unhtmlspecialchars());
			newwi = $oritex.width();
			if (!fixed)
				$oritex.stop(true, true).width(oriwi).delay(100).animate({ width: newwi });
		},

		setSelected = function ($item)
		{
			// If the select box has just been rebuilt, reset its selection.
			// Good to know: !$items.has($item).length is 60 times slower.
			// !$items.filter($item).length is about 30 times slower.
			// !in_array($item[0], $items.get()) is 5 to 6 times slower.
			if (!$item[0].parentNode === $dd[0])
				$item = $orig_item;

			// Change the selection to this item
			$selected = $item.addClass('selected');
			$items.not($selected).removeClass('selected');
			$sb.attr('aria-activedescendant', $selected.attr('id'));
		},

		updateOriginal = function ()
		{
			// Trigger change on the old <select> if necessary
			has_changed = $orig.val() !== $selected.data('value');

			// Update the original <select>
			$orig.find('option').attr('selected', false);
			$selected.data('orig').attr('selected', true);
			$orig_item = $selected;
		},

		// When the user explicitly clicks an item
		clickSBItem = function (e)
		{
			if (e.which == 1)
			{
				selectItem($(this));
				updateOriginal();
				closeAndUnbind();
				focusSB();

				if (has_changed)
				{
					$orig.triggerHandler('change');
					has_changed = false;
				}
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
				$sb.hasClass('open') ? closeAndUnbind() : openSB();
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
				selectItem($enabled.filter(':last'));
				centerOnSelected();
				e.preventDefault();
			}
			else if (e.keyCode == 36) // home
			{
				selectItem($enabled.filter(':first'));
				centerOnSelected();
				e.preventDefault();
			}
			else if (e.keyCode == 38) // up
			{
				selectItem($enabled.eq($enabled.index($selected) - 1));
				centerOnSelected();
				e.preventDefault();
			}
			else if (e.keyCode == 40) // down
			{
				selectItem($enabled.eq(($enabled.index($selected) + 1) % $enabled.length));
				centerOnSelected();
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
				.unbind('.sb')
				.bind(keyfunc, keyPress)
				.bind('mousedown.sb', closeAndUnbind);
		},

		// When the sb is blurred (by tab or click), disable hotkey selection
		blurSB = function ()
		{
			$sb.removeClass('focused');
			$(document).unbind(keyfunc);
		};

		loadSB();
		this.re = reloadSB;
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
			startPos = 0, iMouse, iScroll = 0,
			thumbAxis, viewportAxis, contentAxis,
			$content, $scrollbar, $thumb,
			scrollbarRatio, newwi, iTouch,

		wheel = function (e)
		{
			e = $.event.fix(e || window.event);
			// Below: (e.wheelDelta * 40/120) or (-e.detail * 40/3) = 40 pixels per wheel movement
			iScroll = Math.min(contentAxis - viewportAxis, Math.max(0, iScroll - (e.wheelDelta || -e.detail * 40) / 3));
			$thumb.css('top', iScroll / scrollbarRatio);
			$content.css('top', -iScroll);

			e.preventDefault();
		},

		drag = function (e)
		{
			scrollTo(startPos + e.pageY - iMouse);
			return false;
		},

		scrollTo = function (iTop, iHeight)
		{
			if (iHeight)
				iTop = (iTop - viewportAxis / 2 + iHeight / 2) / scrollbarRatio;

			iScroll = Math.min(viewportAxis - thumbAxis, Math.max(0, iTop)) * scrollbarRatio;
			$thumb.css('top', iScroll / scrollbarRatio);
			$content.css('top', -iScroll);
		};

		this.st = scrollTo;
		this.update = function ()
		{
			viewportAxis = $dd.height();
			$scrollbar = $dd.find('.scrollbar').height(viewportAxis);
			$content = $dd.find('.overview');
			contentAxis = $content.height();
			$thumb = $scrollbar.find('div');

			scrollbarRatio = contentAxis / viewportAxis;
			thumbAxis = Math.min(viewportAxis, viewportAxis / scrollbarRatio);

			// Set size.
			iMouse = $thumb.offset().top;
			$thumb.height(thumbAxis);
		};

		if ($dd.find('.viewport').length)
			return;

		$dd.width('auto').contents().wrapAll('<div class="viewport"><div class="overview"></div></div>');

		newwi = $dd.width();

		$dd.append('<div class="scrollbar"><div></div></div>');

		$dd.find('.scrollbar')
			.height($dd.height());

		$dd.width(newwi + 15)
			.find('.viewport').height($dd.height());

		this.update();

		// Set events
		$scrollbar.mousedown(drag);
		$thumb.mousedown(function (e)
		{
			iMouse = e.pageY;
			startPos = parseInt($thumb.css('top')) || 0;
			$(document)
				.bind('mousemove.sc', drag)
				.bind('mouseup.sc', function () { $(document).unbind('.sc'); return false; });
			return false;
		});

		$dd
			.bind('DOMMouseScroll mousewheel', wheel)
			// This should add support for scrolling on touch devices.
			.bind('touchstart', function (e) {
				iTouch = e.originalEvent.touches[0].pageY;
				startPos = parseInt($thumb.css('top'));
			})
			.bind('touchmove', function (e) {
				scrollTo(startPos - e.originalEvent.touches[0].pageY + iTouch);
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

// Only run through this if we are at the end of the page.
if (window.weres)
	$('select').sb();
