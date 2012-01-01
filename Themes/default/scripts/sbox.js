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
			keyfunc = is_opera ? "keypress.sb" : "keydown.sb",
			resizeTimeout,
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
			// set the various options
			o = $.extend({
				anim: 150,						// animation duration: time to open/close dropdown in ms
				maxHeight: 500,					// show scrollbars if the dropdown is taller than 500 pixels (or the viewport height)
				fixed: $orig.hasClass("fixed")	// fixed width; if true, dropdown expands to widest and display conforms to whatever is selected
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

			// for accessibility/styling, and an easy custom .trigger("close") shortcut.
			$sb.append($display, $dd)
				.bind("close", closeSB)
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
			setSelected($orig_item = $items.filter(".selected"));

			$dd.children(":first").addClass("first");
			$dd.children(":last").addClass("last");

			// place the new markup in its semantic location
			$orig
				.addClass("sb")
				.before($sb);

			// The 'apply' call below will return the widest width from a list of elements.
			// Note: add .details to the list to ensure they're as long as possible. Not sure if this is best though...
			if (o.fixed)
				$sb.width(Math.max.apply(0, $dd.find(".text,.optgroup").map(function () { return $(this).outerWidth(true); }).get()) + extraWidth($display) + extraWidth($(".text", $display)));

			// hide the dropdown now that it's initialized
			$dd.hide();

			// Attach the original select box's events, such as onclick, to our display area.
			$display.attr("data-eve", $orig.data("eve"));

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
						$sb.is(".open") ? closeSB() : openSB();
						return false; // avoid bubbling to $(document).click(".sb")
					});
				$items.not(".disabled")
					.hover(
						// use selectItem() instead of setSelected() to do the display animation on hover.
						function () { if ($sb.is(".open")) setSelected($(this)); },
						function () { $(this).removeClass("selected"); $selected = $orig_item; }
					)
					.click(clickSBItem);
				$dd.children(".optgroup")
					.click(false);
				$items.filter(".disabled")
					.click(false);

				if (!is_ie8down)
					$(window).bind("resize.sb", function ()
					{
						clearTimeout(resizeTimeout);
						resizeTimeout = setTimeout(function () { if ($sb.is(".open")) openSB(); }, 50);
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
			var wasOpen = $sb.is(".open"), can_focus = opt !== "focus";
			if (can_focus)
				o = $.extend(o, opt);

			closeSB(1);

			// destroy existing data
			$sb.remove();
			$orig.removeClass("sb")
				.unbind(".sb");
			$(window)
				.unbind(".sb");

			loadSB();

			if (wasOpen)
				openSB(!can_focus);
		},

		// hide and reset dropdown markup
		closeSB = function (instantClose)
		{
			if ($sb.is(".open"))
			{
				$display.blur();
				$(document).unbind(".sb");
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
			if ($selected.data("value") !== $orig.val())
				selectItem($orig_item, true);
		},

		// trigger all select boxes to blur
		blurAllButMe = function ()
		{
			$(".sbox.focused").not($sb).find(".display").blur();
		},

		// reposition the scroll of the dropdown so the selected option is centered (or appropriately onscreen)
		centerOnSelected = function ()
		{
			$dd.scrollTop($dd.scrollTop() + $selected.offset().top - $dd.offset().top - $dd.height() / 2 + $selected.outerHeight(true) / 2);
		},

		extraWidth = function ($dom)
		{
			return $dom.outerWidth(true) - $dom.width();
		},

		// show, reposition, and reset dropdown markup.
		openSB = function (no_focus)
		{
			blurAllButMe();
			var showDown = positionSB(), is_open = $sb.is(".open");
			$sb.addClass("open");
			$dd.attr("aria-hidden", false);
			if (is_open)
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
			}
			// if calling from within the onfocus event, prevent an infinite loop.
			no_focus ? focusSB() : $orig.focus();
		},

		// position dropdown based on collision detection
		positionSB = function ()
		{
			// modify dropdown css for getting values
			$dd.stop(true, true).show().css({
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

			$selected.addClass("selected");

			return showDown;
		},

		// when the user selects an item in any manner
		selectItem = function ($item, no_open)
		{
			var $newtex = $item.find(".text"), $oritex = $display.find(".text"), oriwi = $oritex.width();

			// if we're selecting an item and the box is closed, open it.
			if (!no_open && !$sb.is(".open"))
				openSB();

			setSelected($item);

			// update the title attr and the display markup
			$oritex
				.html($newtex.html() || "&nbsp;")
				.attr("title", $newtex.text().php_unhtmlspecialchars());
			if (!o.fixed)
				$oritex.stop(true, true).width(oriwi).animate({ width: $newtex.width() });
		},

		setSelected = function ($item)
		{
			// change the selection to this item
			$items.removeClass("selected");
			$selected = $item.addClass("selected");
			$sb.attr("aria-activedescendant", $selected.attr("id"));
		},

		focusOriginal = function ()
		{
			// trigger focus on the old <select>, BUT without triggering its events, because
			// they might refresh the box and enter an infinite loop.
			var $focuses = $.extend({}, $orig.data("events").focus), i;
			$orig.unbind("focus").focus();
			focusSB();
			for (i in $focuses)
				$orig.bind("focus", $focuses[i].handler);
		},

		updateOriginal = function ()
		{
			// trigger change on the old <select> if necessary
			has_changed = $orig.val() !== $selected.data("value");

			// update the original <select>
			$orig.find("option").attr("selected", false);
			$selected.data("orig").attr("selected", true);
			$orig_item = $selected;
		},

		// when the user explicitly clicks an item
		clickSBItem = function ()
		{
			selectItem($(this));
			updateOriginal();
			closeAndUnbind();
			focusOriginal();

			if (has_changed)
			{
				$orig.triggerHandler("change");
				has_changed = false;
			}

			return false;
		},

		// iterate over all the options to see if any match the search term.
		// if we get a match for any options, select it.
		selectMatchingItem = function (term)
		{
			var $available = $items.not(".disabled"), from = $available.index($selected) + 1, to = $available.length, i = from;

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

			var $enabled = $items.not(".disabled");

			// user pressed tab? If the list is opened, confirm the selection and close it. Then either way, switch to the next DOM element.
			if (e.keyCode == 9)
			{
				if ($sb.is(".open"))
				{
					updateOriginal();
					closeSB();
				}
				blurSB();
			}
			// spaces should open or close the dropdown, cancelling the latest selection. Requires e.which instead of e.keyCode... confusing.
			else if (e.which == 32)
			{
				// closeAndUnbind does the same job as closeSB, only it cancels the current selection.
				$sb.is(".open") ? closeAndUnbind() : openSB();
				focusSB();
				e.preventDefault();
			}
			// backspace or return (with the select box open) will do the same as pressing tab, but will keep the current item focused.
			else if ((e.keyCode == 8 || e.keyCode == 13) && $sb.is(".open"))
			{
				updateOriginal();
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
			// also, try finding the next element that starts with the pressed letter. if found, select it.
			else if (selectMatchingItem(String.fromCharCode(e.which)))
				e.preventDefault();

			if (has_changed)
			{
				$orig.triggerHandler("change");
				has_changed = false;
			}
		},

		// when the sb is focused (by tab or click), allow hotkey selection and kill all other selectboxes
		focusSB = function ()
		{
			// close all select boxes but this one, to prevent multiple selects open at once.
			$(".sbox.open").not($sb).trigger("close");

			$sb.addClass("focused");
			$(document)
				.unbind(".sb")
				.bind(keyfunc, keyPress)
				.bind("click.sb", closeAndUnbind);
		},

		// when the sb is blurred (by tab or click), disable hotkey selection
		blurSB = function ()
		{
			$sb.removeClass("focused");
			$(document).unbind(keyfunc);
		};

		loadSB();
		this.re = reloadSB;
	};

}());

$("select").sb();
