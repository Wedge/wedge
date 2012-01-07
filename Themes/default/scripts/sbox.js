/**
 * Wedge
 *
 * Selectbox replacement plugin for Wedge.
 * Original code by RevSystems (.sb) and Maarten Baijs (.sc),
 * Fixed, customized and optimized by Nao.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
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
			fixed = $orig.hasClass('fixed'), // should dropdown expand to widest and display conform to whatever is selected?
			resizeTimeout,
			via_keyboard,
			has_scrollbar,
			has_changed,
			$selected,
			$display,
			$label,
			$dd,
			$sb,
			$items,
			$orig_item,

		loadSB = function ()
		{
			$label = $orig.attr('id') ? $('label[for="' + $orig.attr('id') + '"]:first') : '';
			if ($label.length == 0)
				$label = $orig.closest('label');

			// create the new sb
			$sb = $('<div class="sbox ' + $orig.attr('class') + '" id="sb' + ++unique + '" role=listbox></div>')
				.attr('aria-labelledby', $label.attr('id') || '')
				.attr('aria-haspopup', true);

			$display = $('<div class="display" id="sbd' + unique + '"></div>')
				// generate the display markup
				.append(optionFormat($orig.find('option:selected'), '&nbsp;'))
				.append('<div class="btn"><div></div></div>');

			// generate the dropdown markup
			$dd = $('<ul class="items" id="sbdd' + unique + '" role=menu></ul>')
				.attr('aria-hidden', true);

			// for accessibility/styling, and an easy custom .trigger('close') shortcut.
			$sb.append($display, $dd)
				.bind('close', closeSB)
				.attr('aria-owns', $dd.attr('id'))
				.find('.details').remove();

			if ($orig.children().length == 0)
				$dd.append(createOption().addClass('selected'));
			else
				$orig.children().each(function ()
				{
					var $og = $(this), $optgroup;
					if ($og.is('optgroup'))
					{
						$dd.append($optgroup = $('<li class="optgroup"><div class="label">' + $og.attr('label') + '</div></li>'));
						$og.find('option').each(function () { $dd.append(createOption($(this)).addClass('sub')); });
						if ($og.is(':disabled'))
							$optgroup.nextAll().andSelf()
								.addClass('disabled')
								.attr('aria-disabled', true);
					}
					else
						$dd.append(createOption($og));
				});

			// cache all sb items
			$items = $dd.children('li').not('.optgroup');
			setSelected($orig_item = $items.filter('.selected'));

			$dd.children(':first').addClass('first');
			$dd.children(':last').addClass('last');

			// place the new markup in its semantic location
			$orig
				.addClass('sb')
				.before($sb);

			// The 'apply' call below will return the widest width from a list of elements.
			// Note: add .details to the list to ensure they're as long as possible. Not sure if this is best though...
			if (fixed)
				$sb.width(Math.max.apply(0, $dd.find('.text,.optgroup').map(function () { return $(this).outerWidth(true); }).get()) + extraWidth($display) + extraWidth($('.text', $display)));

			// hide the dropdown now that it's initialized
			$dd.hide();

			// Attach the original select box's event attributes to our display area.
			if ($orig.attr('data-eve'))
				for (var eve = 0, elis = $orig.attr('data-eve').split(' '), eil = elis.length; eve < eil; eve++)
					$display.bind(eves[elis[eve]][0], eves[elis[eve]][1]);

			// bind events
			if (!$orig.is(':disabled'))
			{
				// causes focus if original is focused
				$orig.bind('focus.sb', function () {
					blurAllButMe();
					focusSB();
				});

				$display
					.blur(blurSB)
					.focus(focusSB)
					.mousedown(false) // prevent double clicks
					.hover(function () { $(this).toggleClass('hover'); })
					// when the user explicitly clicks the display
					.click(function ()
					{
						// closeAndUnbind does the same job as closeSB, only it cancels the current selection.
						$sb.hasClass('open') ? closeAndUnbind(1) : openSB(0, 1);
						return false; // avoid bubbling to $(document).click('.sb')
					});
				$items.not('.disabled')
					.hover(
						// use selectItem() instead of setSelected() to do the display animation on hover.
						function () { if ($sb.hasClass('open')) setSelected($(this)); },
						function () { $(this).removeClass('selected'); $selected = $orig_item; }
					)
					.click(clickSBItem);
				$dd.children('.optgroup')
					.click(false);
				$items.filter('.disabled')
					.click(false);

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

		// create new markup from an <option>
		createOption = function ($option)
		{
			$option = $option || $('<option>&nbsp;</option>');

			return $('<li id="sbo' + ++unique + '" role=option></li>')
				.data('orig', $option)
				.data('value', $option.attr('value') || '')
				.attr('aria-disabled', !!$option.is(':disabled'))
				.toggleClass('disabled', $option.is(':disabled'))
				.toggleClass('selected', $option.is(':selected'))
				.append(
					$('<div class="item"></div>')
						.attr('style', $option.attr('style') || '')
						.addClass($option.attr('class'))
						.append(optionFormat($option))
				);
		},

		// formatting for the display
		optionFormat = function ($dom, empty)
		{
			return '<div class="text">' + ($dom.text().replace(/\|/g, '</div><div class="details">') || empty || '') + '</div>';
		},

		/* Alternate version that takes care of horizontal lines. @todo: Needs a class, rather than a <hr>.

		optionFormat = function ($dom, txt)
		{
			txt = $dom.text().replace(/\|/g, '</div><div class="details">') || txt || '';
			if (txt.match(/^--+$/g))
				txt = '<hr>';
			return '<div class="text">' + txt + '</div>';
		}, */

		// destroy then load, maintaining open/focused state if applicable
		reloadSB = function ()
		{
			var wasOpen = $sb.hasClass('open'), wasFocused = $sb.hasClass('focused');

			closeSB(1);

			// destroy existing data
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

		// hide and reset dropdown markup
		closeSB = function (instantClose)
		{
			if ($sb.hasClass('open'))
			{
				$display.blur();
				$(document).unbind('.sb');
				$sb.removeClass('open');
				$dd
					.animate({ height: 'toggle', opacity: 'toggle' }, instantClose == 1 ? 0 : 150)
					.attr('aria-hidden', true);
			}
		},

		// when the user clicks outside the sb
		closeAndUnbind = function (clicked_on_display)
		{
			$sb.removeClass('focused');
			closeSB();
			if ($selected.data('value') !== $orig.val())
				selectItem($orig_item, true);
			if (clicked_on_display === 1)
				focusSB();
		},

		// trigger all select boxes to blur
		blurAllButMe = function ()
		{
			$('.sbox.focused').not($sb).find('.display').blur();
		},

		// reposition the scroll of the dropdown so the selected option is centered (or appropriately onscreen)
		centerOnSelected = function ()
		{
			if (has_scrollbar)
				has_scrollbar.scTo($selected.position().top, $selected.height());
			else
				$dd.scrollTop($dd.scrollTop() + $selected.offset().top - $dd.offset().top - $dd.height() / 2 + $selected.outerHeight(true) / 2);
		},

		extraWidth = function ($dom)
		{
			return $dom.outerWidth(true) - $dom.width();
		},

		// show, reposition, and reset dropdown markup.
		openSB = function (instantOpen, doFocus)
		{
			blurAllButMe();

			// Focus the element before we actually open it. If called through a click,
			// we'll also actually simulate the focus to trigger any related events.
			doFocus ? $orig.triggerHandler('focus') : focusSB();

			// modify dropdown css for getting values
			$dd.stop(true, true).show().css({
				maxHeight: 'none',
				visibility: 'hidden'
			}).width(Math.max($dd.width(), $display.outerWidth() - extraWidth($dd) + 1));

			var
				// figure out if we should show above/below the display box, first by calculating the free space around it.
				ddHeight = $dd.outerHeight(),
				bottomSpace = $(window).scrollTop() + $(window).height() - $display.offset().top - $display.outerHeight(),
				topSpace = $display.offset().top - $(window).scrollTop(),

				// show scrollbars if the dropdown is taller than 250 pixels (or the viewport height).
				// Touch-enabled phones have poor usability and shouldn't bother -- let them stretch all the way.
				ddMaxHeight = Math.min(is_iphone || is_tablet ? 9e9 : 250, ddHeight, Math.max(bottomSpace + 50, topSpace)),

				// if we have enough space below the button, or if we don't have enough room above either, show a dropdown.
				// otherwise, show a drop-up, but only if there's enough size, or the space above is more comfortable.
				showDown = (ddMaxHeight <= bottomSpace) || ((ddMaxHeight >= topSpace) && (bottomSpace + 50 >= topSpace));

			// modify dropdown css for display
			$dd.hide().css({
				marginTop: showDown ? 0 : -ddMaxHeight - $display.outerHeight(),
				maxHeight: ddMaxHeight - ddHeight + $dd.height(),
				visibility: 'visible'
			}).toggleClass('above', !showDown);

			if (ddMaxHeight < ddHeight)
			{
				// create a custom scrollbar for our select box.
				if (has_scrollbar)
					has_scrollbar.update();
				else
					has_scrollbar = new ScrollBar($dd);
			}

			$selected.addClass('selected');

			$dd.attr('aria-hidden', false);
			if ($sb.hasClass('open'))
			{
				$dd.show();
				centerOnSelected();
			}
			else
			{
				// If opening via a key stroke, simulate a click.
				if (via_keyboard)
					$orig.triggerHandler('click');
				if (showDown)
					$dd.animate({ height: 'toggle', opacity: 'toggle' }, instantOpen ? 0 : 150, centerOnSelected);
				else
					$dd.fadeIn(instantOpen ? 0 : 150, centerOnSelected);
			}
			$sb.addClass('open');
		},

		// when the user selects an item in any manner
		selectItem = function ($item, no_open)
		{
			var $newtex = $item.find('.text'), $oritex = $display.find('.text'), oriwi = $oritex.width();

			// if we're selecting an item and the box is closed, open it.
			if (!no_open && !$sb.hasClass('open'))
				openSB();

			setSelected($item);

			// update the title attr and the display markup
			$oritex
				.html($newtex.html() || '&nbsp;')
				.attr('title', $newtex.text().php_unhtmlspecialchars());
			if (!fixed)
				$oritex.stop(true, true).width(oriwi).animate({ width: $newtex.width() });
		},

		setSelected = function ($item)
		{
			// If the select box has just been rebuilt, reset its selection.
			// Good to know: !$items.has($item).length is 60 times slower.
			// !$items.filter($item).length is about 30 times slower.
			// !in_array($item[0], $items.get()) is 5 to 6 times slower.
			if (!$item[0].parentNode === $dd[0])
				$item = $orig_item;

			// change the selection to this item
			$selected = $item.addClass('selected');
			$items.not($selected).removeClass('selected');
			$sb.attr('aria-activedescendant', $selected.attr('id'));
		},

		updateOriginal = function ()
		{
			// trigger change on the old <select> if necessary
			has_changed = $orig.val() !== $selected.data('value');

			// update the original <select>
			$orig.find('option').attr('selected', false);
			$selected.data('orig').attr('selected', true);
			$orig_item = $selected;
		},

		// when the user explicitly clicks an item
		clickSBItem = function ()
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

			return false;
		},

		// iterate over all the options to see if any match the search term.
		// if we get a match for any options, select it.
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

		// go up/down using arrows or attempt to autocomplete based on string
		keyPress = function (e)
		{
			if (e.altKey || e.ctrlKey)
				return;

			var $enabled = $items.not('.disabled');
			via_keyboard = true;

			// user pressed tab? If the list is opened, confirm the selection and close it. Then either way, switch to the next DOM element.
			if (e.keyCode == 9)
			{
				if ($sb.hasClass('open'))
				{
					updateOriginal();
					closeSB();
				}
				blurSB();
			}
			// spaces should open or close the dropdown, cancelling the latest selection. Requires e.which instead of e.keyCode... confusing.
			else if (e.which == 32)
			{
				$sb.hasClass('open') ? closeAndUnbind() : openSB();
				focusSB();
				e.preventDefault();
			}
			// backspace or return (with the select box open) will do the same as pressing tab, but will keep the current item focused.
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
			// also, try finding the next element that starts with the pressed letter. if found, select it.
			else if (selectMatchingItem(String.fromCharCode(e.which)))
				e.preventDefault();

			if (has_changed)
			{
				$orig.triggerHandler('change');
				has_changed = false;
			}

			via_keyboard = false;
		},

		// when the sb is focused (by tab or click), allow hotkey selection and kill all other selectboxes
		focusSB = function ()
		{
			// close all select boxes but this one, to prevent multiple selects open at once.
			$('.sbox.open').not($sb).trigger('close');

			$sb.addClass('focused');
			$(document)
				.unbind('.sb')
				.bind(keyfunc, keyPress)
				.bind('click.sb', closeAndUnbind);
		},

		// when the sb is blurred (by tab or click), disable hotkey selection
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

	ScrollBar = function (root)
	{
		var
			startPos = 0, iMouse, iScroll,
			thumbAxis, viewportAxis, contentAxis,
			oContent, oScrollbar, oTrack, oThumb,
			curPos, scrollbarRatio,

		start = function (e)
		{
			iMouse = e.pageY;
			startPos = parseInt(oThumb.css('top')) || 0;
			$(document)
				.bind('mousemove.sc', drag)
				.bind('mouseup.sc', end);
			oThumb.bind('mouseup.sc', end);
			return false;
		},

		wheel = function (e)
		{
			e = $.event.fix(e || window.event);
			// Below: (e.wheelDelta * 40/120) or (-e.detail * 40/3) = 40 pixels per wheel movement
			iScroll = Math.min(contentAxis - viewportAxis, Math.max(0, iScroll - (e.wheelDelta || -e.detail * 40) / 3));
			oThumb.css('top', iScroll / scrollbarRatio);
			oContent.css('top', -iScroll);

			e.preventDefault();
		},

		end = function ()
		{
			$(document).unbind('.sc');
			oThumb.unbind('.sc');
			return false;
		},

		drag = function (e)
		{
			scTo(startPos + e.pageY - iMouse, 0);
			return false;
		},

		scTo = function (iTop, iHeight)
		{
			// Still buggy when moving the wheel after navigating the select box with the keyboard.
			if (iHeight)
				iTop = (iTop - viewportAxis / 2 + iHeight / 2) / scrollbarRatio;

			curPos = Math.min(viewportAxis - thumbAxis, Math.max(0, iTop));
			oContent.css('top', -curPos * scrollbarRatio);
			oThumb.css('top', curPos);
		};

		this.scTo = scTo;
		this.update = function ()
		{
			viewportAxis = root.find('.viewport').height();
			oContent = root.find('.overview');
			oScrollbar = root.find('.scrollbar');
			oTrack = oScrollbar.find('.track');
			oThumb = oScrollbar.find('.thumb');
			iScroll = 0;

			contentAxis = oContent.height();
			scrollbarRatio = contentAxis / viewportAxis;
			thumbAxis = Math.min(viewportAxis, viewportAxis / scrollbarRatio);

			// set size
			iMouse = oThumb.offset().top;
			oThumb.css('top', iScroll / scrollbarRatio);
			oContent.css('top', -iScroll);
			oScrollbar.css('height', viewportAxis);
			oTrack.css('height', viewportAxis);
			oThumb.css('height', thumbAxis);
		};

		if (root.find('.viewport').length)
			return;

		root
			.css({ display: 'block', visibility: 'hidden' })
			.contents()
			.wrapAll('<div class="viewport"><div class="overview"></div></div>');

		root.append('<div class="scrollbar"><div class="track"><div class="thumb"></div></div></div>');

		root
			.find('.scrollbar')
			.css({
				marginTop: -root.height(),
				marginLeft: root.width() + 3,
				height: root.height()
			});
		root
			.find('.viewport')
			.css({
				height: root.height(),
				marginRight: 15
			});
		root.width(root.width() + 15);
		this.update();

		// set events
		oTrack.bind('mouseup.sc', drag);
		oThumb.bind('mousedown.s2', start);
		if (root[0].addEventListener)
		{
			root[0].addEventListener('DOMMouseScroll', wheel, false);
			root[0].addEventListener('mousewheel', wheel, false);
		}
		else
			root[0].onmousewheel = wheel;

		root.css({ display: 'none', visibility: 'visible' });
	};

	// .sb() takes a select box and restyles it.
	// a normal <select> will be displayed for old browsers
	$.fn.sb = function (arg)
	{
		// chain methods!
		return this.each(function ()
		{
			var $e = $(this), obj = $e.data('sb');

			// if it is already created, then reload it.
			if (obj)
				obj.re(arg);

			// if the object is not defined for this element, and it's a drop-down, then create and initialize it.
			else if (!$e.attr('size'))
				$e.data('sb', new SelectBox($e, arg));
		});
	};

})();

// Only run through this if we are at the end of the page.
if (window.eves)
	$('select').sb();
