/**
 * Wedge
 *
 * Selectbox replacement plugin customized for Wedge.
 * Original code by RevSystems, modified by Nao.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

/*
	This product includes software developed
	by RevSystems, Inc (http://www.revsystems.com/) and its contributors

	Copyright (c) 2010 RevSystems, Inc

	The original license can be found in the file 'license.txt' at:
	https://github.com/revsystems/jQuery-SelectBox
*/

(function ()
{
	// This plugin is not compatible with IE6 and below;
	// a normal <select> will be displayed for old browsers
	$.fn.sb = function (arg)
	{
		// chain methods!
		return this.each(function ()
		{
			var $e = $(this), obj = $e.data("sb");

			// if it is already created, then reload it.
			if (obj)
				obj.re(arg);

			// if the object is not defined for this element, and it's a drop-down, then create and initialize it.
			else if (!$e.attr("size"))
				$e.data("sb", new SelectBox($e, arg));
		});
	};

	var unique = 0,

	SelectBox = function ($orig, o)
	{
		var
			resizeTimeout,
			$label,
			$display,
			$dd,
			$sb,
			$items,

		loadSB = function ()
		{
			// set the various options
			o = $.extend({
				anim: 150,			// animation duration: time to open/close dropdown in ms
				maxHeight: 500,		// show scrollbars if the dropdown is taller than 500 pixels (or the viewport height)
				maxWidth: false,	// if an integer, prevent the display/dropdown from growing past this width; longer items will be clipped
				fixed: true			// fixed width; if false, dropdown expands to widest and display conforms to whatever is selected
			}, o);

			$label = $orig.attr("id") ? $("label[for='" + $orig.attr("id") + "']:first") : '';
			if ($label.length == 0)
				$label = $orig.closest("label");

			// create the new sb
			$sb = $("<div class='sbox " + $orig.attr("class") + "' id='sb" + ++unique + "' role=listbox></div>")
				.attr("aria-labelledby", $label.attr("id") || "")
				.attr("aria-haspopup", true);

			$display = $("<div class='display " + $orig.attr("class") + "' id='sbd" + unique + "'></div>")
				// generate the display markup
				.append(optionFormat($orig.find("option:selected"), "&nbsp;"))
				.append("<div class='btn'><div></div></div>");

			// generate the dropdown markup
			$dd = $("<ul class='items " + $orig.attr("class") + "' id='sbdd" + unique + "' role=menu></ul>")
				.attr("aria-hidden", true);

			$sb.append($display, $dd)
				.attr("aria-owns", $dd.attr("id"));

			if ($orig.children().length == 0)
				$dd.append(createOption().addClass("selected"));
			else
				$orig.children().each(function ()
				{
					var $og = $(this), $optgroup;
					if ($og.is("optgroup"))
					{
						$dd.append($optgroup = $("<li class='optgroup'><div class='label'>" + $og.attr("label") + "</div></li>"));
						$og.find("option").each(function () { $dd.append(createOption($(this)).addClass("sub")); });
						if ($og.is(":disabled"))
							$optgroup.nextAll().andSelf()
								.addClass("disabled")
								.attr("aria-disabled", true);
					}
					else
						$dd.append(createOption($og));
				});

			// cache all sb items
			$items = $dd.children("li").not(".optgroup");

			$dd.children(":first").addClass("first");
			$dd.children(":last").addClass("last");

			// place the new markup in its semantic location
			$orig
				.addClass("sb")
				.before(
					// for accessibility/styling, and an easy custom .trigger("close") shortcut.
					$sb.attr("aria-activedescendant", $items.filter(".selected").attr("id")).bind("close", closeSB)
				);

			// modify width based on fixed/maxWidth options
			if (!o.fixed)
				$sb.width(Math.min(
					o.maxWidth || 9e9,
					// The 'apply' call below will return the widest width from a list of elements.
					// Note: add .details to the list to ensure they're as long as possible. Not sure if this is best though...
					Math.max.apply(0, $dd.find(".text,.optgroup").map(function () { return $(this).outerWidth(true); }).get()) + extraWidth($display) + extraWidth($(".text", $display)) - 2
				));
			else if (o.maxWidth && $sb.width() > o.maxWidth)
				$sb.width(o.maxWidth);

			// hide the dropdown now that it's initialized
			$dd.hide();

			// bind events
			if (!$orig.is(":disabled"))
			{
				// causes focus if original is focused
				$orig.bind("focus.sb", function () {
					blurAllButMe();
					focusSB();
				});

				$display
					.blur(blurSB)
					.focus(focusSB)
					.mousedown(false) // prevent double clicks
					.hover(function () { $(this).toggleClass("hover"); })
					// when the user explicitly clicks the display
					.click(function ()
					{
						$sb.toggleClass("focused");
						$sb.is(".open") ? closeSB() : openSB();
						return false;
					});
				$items.not(".disabled")
					.hover(function () { if ($sb.is(".open")) $(this).toggleClass("hover"); })
					.click(clickSBItem);
				$dd.children(".optgroup")
					.click(false);
				$items.filter(".disabled")
					.click(false);

				if (!is_ie8down)
					$(window).bind("resize.sb", function ()
					{
						clearTimeout(resizeTimeout);
						resizeTimeout = setTimeout(function () {
							if ($sb.is(".open"))
							{
								positionSB();
								openSB(1);
							}
						}, 50);
					});
			}
			else
			{
				$sb.addClass("disabled").attr("aria-disabled", true);
				$display.click(false);
			}
		},

		// create new markup from an <option>
		createOption = function ($option)
		{
			$option = $option || $("<option>&nbsp;</option>");

			return $("<li id='sbo" + ++unique + "' role=option></li>")
				.data("orig", $option)
				.data("value", $option.attr("value") || "")
				.attr("aria-disabled", !!$option.is(":disabled"))
				.toggleClass("disabled", $option.is(":disabled"))
				.toggleClass("selected", $option.is(":selected"))
				.append(
					$("<div class='item'></div>")
						.attr("style", $option.attr("style") || "")
						.addClass($option.attr("class"))
						.append(optionFormat($option))
				);
		},

		// formatting for the display
		optionFormat = function ($dom, empty)
		{
			return "<div class='text'>" + ($dom.text().replace(/\|/g, "</div><div class='details'>") || empty || "") + "</div>";
		},

		// destroy then load, maintaining open/focused state if applicable
		reloadSB = function (opt)
		{
			var wasOpen = $sb.is(".open"), wasFocused = $sb.is(".focused");
			o = $.extend(o, opt);

			closeSB(1);

			// destroy existing data
			$sb.remove();

			$orig
				.removeClass("sb")
				.unbind(".sb");
			$(window)
				.unbind(".sb");

			loadSB();

			if (wasOpen)
				openSB(1);
			else if (wasFocused)
				$orig.focus();
		},

		// hide and reset dropdown markup
		closeSB = function (instantClose)
		{
			if ($sb.is(".open"))
			{
				$display.blur();
				$(document).unbind(".sb");
				$items.removeClass("hover");
				$sb.removeClass("open");
				$dd
					.animate({ height: "toggle", opacity: "toggle" }, instantClose == 1 ? 0 : o.anim)
					.attr("aria-hidden", true);
			}
		},

		// when the user clicks outside the sb
		closeAndUnbind = function ()
		{
			$sb.removeClass("focused");
			closeSB();
		},

		// trigger all select boxes to blur
		blurAllButMe = function ()
		{
			$(".sbox.focused").not($sb).find(".display").blur();
		},

		// reposition the scroll of the dropdown so the selected option is centered (or appropriately onscreen)
		centerOnSelected = function ()
		{
			$dd.scrollTop($dd.scrollTop() + $items.filter(".selected").offset().top - $dd.offset().top - $dd.height() / 2 + $items.filter(".selected").outerHeight(true) / 2);
		},

		extraWidth = function ($dom)
		{
			return $dom.outerWidth(true) - $dom.width();
		},

		// show, reposition, and reset dropdown markup
		openSB = function (reloading)
		{
			blurAllButMe();
			$sb.addClass("open").append($dd.attr("aria-hidden", false));
			var showDown = positionSB();
			if (reloading)
			{
				$dd.show();
				centerOnSelected();
			}
			else
			{
				if (showDown)
					$dd.animate({ height: "toggle", opacity: "toggle" }, o.anim, centerOnSelected);
				else
					$dd.fadeIn(o.anim, centerOnSelected);
				$orig.focus();
			}
		},

		// position dropdown based on collision detection
		positionSB = function ()
		{
			// modify dropdown css for getting values
			$dd.show().css({
				maxHeight: "none",
				visibility: "hidden"
			}).width(Math.max($dd.width(), $display.outerWidth() - extraWidth($dd) + 1));

			var
				// figure out if we should show above/below the display box, first by calculating the free space around it.
				bottomSpace = $(window).scrollTop() + $(window).height() - $display.offset().top - $display.outerHeight(),
				topSpace = $display.offset().top - $(window).scrollTop(),

				// if we have enough space below the button, or if we don't have enough room above either, show a dropdown.
				// otherwise, show a drop-up, but only if there's enough size, or the space above is more comfortable.
				showDown = ($dd.outerHeight() <= bottomSpace) || (($dd.outerHeight() >= topSpace) && (bottomSpace + 50 >= topSpace)),
				ddMaxHeight = Math.min(o.maxHeight, $dd.outerHeight(), showDown ? bottomSpace : topSpace);

			// modify dropdown css for display
			$dd.hide().css({
				marginTop: showDown ? 0 : -ddMaxHeight - $display.outerHeight(),
				maxHeight: ddMaxHeight - $dd.outerHeight() + $dd.height(),
				visibility: "visible"
			}).toggleClass("above", !showDown);

			return showDown;
		},

		// when the user selects an item in any manner
		selectItem = function ($item)
		{
			// trigger change on the old <select> if necessary
			var has_changed = $orig.val() !== $item.data("value");

			// if we're selecting an item and the box is closed, open it.
			if (!$sb.is(".open"))
				openSB();

			// update the original <select>
			$orig.find("option").each(function () { this.selected = false; });
			$item.data("orig").each(function () { this.selected = true; });

			// change the selection to this item
			$items.removeClass("selected");
			$item.addClass("selected");
			$sb.attr("aria-activedescendant", $item.attr("id"));

			// update the title attr and the display markup
			$display.find(".text")
				.replaceWith(optionFormat($item.data("orig")))
				.attr("title", $item.find(".text").html().php_unhtmlspecialchars());

			if (has_changed)
				$orig.change();
		},

		// when the user explicitly clicks an item
		clickSBItem = function ()
		{
			selectItem($(this));
			closeAndUnbind();
			$orig.focus();
			return false;
		},

		// iterate over all the options to see if any match the search term.
		// if we get a match for any options, select it.
		selectMatchingItem = function (term)
		{
			var $available = $items.not(".disabled"), from = $available.index($items.filter(".selected")) + 1, to = $available.length, i = from;

			while (true)
			{
				for (; i < to; i++)
					if ($available.eq(i).text().toLowerCase().match("^" + term.toLowerCase()))
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

			var $selected = $items.filter(".selected"), $enabled = $items.not(".disabled");

			if (e.keyCode == 9) // tab on an unopened select box?
			{
				if ($sb.is(".open"))
					closeSB();
				blurSB();
			}
			else if ((e.keyCode == 8 || e.keyCode == 13) && $sb.is(".open")) // backspace or return (with the select box open)
			{
				closeSB();
				focusSB();
				e.preventDefault();
			}
			else if (e.keyCode == 35) // end
			{
				selectItem($enabled.filter(":last"));
				centerOnSelected();
				e.preventDefault();
			}
			else if (e.keyCode == 36) // home
			{
				selectItem($enabled.filter(":first"));
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
			// prevent spaces from triggering the original -- requires e.which instead of e.keyCode... confusing.
			// also, try finding the next element that starts with the pressed letter. if found, select it.
			else if (e.which == 32 || selectMatchingItem(String.fromCharCode(e.which)))
				e.preventDefault();
		},

		// when the sb is focused (by tab or click), allow hotkey selection and kill all other selectboxes
		focusSB = function ()
		{
			// close all select boxes but this one, to prevent multiple selects open at once.
			$(".sbox.open").not($sb).trigger("close");

			$sb.addClass("focused");
			$(document)
				.unbind(".sb")
				.bind("keypress.sb", keyPress)
				.bind("click.sb", closeAndUnbind);
		},

		// when the sb is blurred (by tab or click), disable hotkey selection
		blurSB = function ()
		{
			$sb.removeClass("focused");
			$(document).unbind("keypress.sb");
		};

		loadSB();
		this.re = reloadSB;
	};

}());

$('select').sb();
