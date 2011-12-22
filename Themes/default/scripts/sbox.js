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

// Utility functions
$.fn.extraWidth = function ()
{
	return $(this).outerWidth(true) - $(this).width();
};

$.fn.offsetFrom = function (e)
{
	// We could cache the following offsets to halve the execution time, but even IE7 can run this 5000 times per second,
	// so we might as well leave it that way and save 5 bytes in our gzipped file. Yes, I know, I'm crazy like that.
	return {
		x: $(this).offset().left - e.offset().left,
		y: $(this).offset().top - e.offset().top
	};
};

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

			// if it is already created, then execute any functions passed to it, if they exist.
			if (obj)
				// call the method defined in the castle of...
				arg && $.isFunction(obj[arg]) && obj[arg]();
			// if the object is not defined for this element, then construct.
			// note: IE6 will use a default select box, and non-dropdowns are ignored.
			else if (!is_ie6 && !$e.attr("size"))
			{
				// create the new object, associate it with the element and initialize it.
				$e.data("sb", obj = new SelectBox);
				obj.init($e, obj, arg);
			}
		});
	};

	var
		unique = 0,

		// formatting for the display
		optionFormat = function ($dom)
		{
			return $dom.text() || "";
		},

	SelectBox = function ()
	{
		var
			resizeTimeout = null,
			$label,
			$display,
			$orig,
			$dd,
			$sb,
			$items,
			closing,
			self,
			o,

		loadSB = function ($original_select, this_object, opts)
		{
			// get the original <select>
			self = this_object;
			$orig = $original_select;

			// set the various options
			o = $.extend({
				anim: 200,			// animation duration: time to open/close dropdown in ms
				fixed: false,		// fixed width; if false, dropdown expands to widest and display conforms to whatever is selected
				maxHeight: false,	// if an integer, show scrollbars if the dropdown is too tall
				maxWidth: false,	// if an integer, prevent the display/dropdown from growing past this width; longer items will be clipped
				css: "selectbox"	// class to apply our markup
			}, opts);

			$label = $orig.attr("id") ? $("label[for='" + $orig.attr("id") + "']:first") : '';
			if ($label.length == 0)
				$label = $orig.closest("label");

			// create the new sb
			$sb = $("<div class='sb " + o.css + " " + $orig.attr("class") + "' id='sb" + ++unique + "' role=listbox></div>")
				.attr("aria-labelledby", $label.attr("id") || "")
				.attr("aria-haspopup", true)
				.appendTo("body");

			$display = $("<div class='display " + $orig.attr("class") + "' id='sbd" + unique + "'></div>")
				// generate the display markup
				.append($("<div class='text'></div>").append(optionFormat($orig.find("option:selected")) || "&nbsp;"))
				.append("<div class='btn'><div></div></div>")
				.appendTo($sb);

			// generate the dropdown markup
			$dd = $("<ul class='" + o.css + " items " + $orig.attr("class") + "' id='sbdd" + unique + "' role=menu></ul>")
				.attr("aria-hidden", true);
			$sb.append($dd)
				.attr("aria-owns", $dd.attr("id"));
			if ($orig.children().length == 0)
				$dd.append(createOption().addClass("selected"));
			else
				$orig.children().each(function ()
				{
					var $og = $(this), $optgroup;
					if ($og.is("optgroup"))
					{
						$optgroup = $("<li class='optgroup'><div class='label'>" + $og.attr("label") + "</div></li>").appendTo($dd);
						$og.find("option").each(function () { $dd.append(createOption($(this)).addClass('sub')); });
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

			// for accessibility/styling
			$sb.attr("aria-activedescendant", $items.filter(".selected").attr("id"));
			$dd.children(":first").addClass("first");
			$dd.children(":last").addClass("last");

			// modify width based on fixed/maxWidth options
			if (!o.fixed)
				$sb.width(Math.min(
					o.maxWidth || 9e9,
					// The 'apply' call below will return the widest width from a list of elements.
					Math.max.apply(0, $dd.find(".text, .optgroup").map(function () { return $(this).width(); }).get()) + $display.extraWidth() + 1
				));
			else if (o.maxWidth && $sb.width() > o.maxWidth)
				$sb.width(o.maxWidth);

			// place the new markup in its semantic location (hide/show fixes positioning bugs)
			$orig.before($sb).addClass("has_sb").hide().show();

			// these two lines fix a div/span display bug on load in ie7
			positionSB();
			if (is_ie7)
				$("." + o.css + " .display").hide().show();

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
					.mouseup(clickSB)
					.focus(focusSB)
					.blur(blurSB)
					.mousedown(false) // prevent double clicks
					.click(false)
					.hover(addHoverState, removeHoverState);
				$items.not(".disabled")
					.click(clickSBItem)
					.hover(addHoverState, removeHoverState);
				$dd.children(".optgroup")
					.click(false)
					.hover(addHoverState, removeHoverState);
				$items.filter(".disabled")
					.click(false);
				if (!is_ie8down)
					$(window).resize(delayPositionSB);
			}
			else
			{
				$sb.addClass("disabled").attr("aria-disabled", true);
				$display.click(false);
			}
			$sb.bind("close.sb", closeSB);
		},

		// create new markup from an <option>
		createOption = function ($option)
		{
			$option = $option || $("<option>&nbsp;</option>");

			return $("<li id='sbo" + ++unique + "' role=option></li>")
				.data("orig", $option)
				.data("value", $option.attr("value") || "")
				.addClass($option.is(":selected") ? "selected" : "")
				.addClass($option.is(":disabled") ? "disabled" : "")
				.attr("aria-disabled", !!$option.is(":disabled"))
				.append(
					$("<div class='item'></div>")
						.attr("style", $option.attr("style") || "")
						.addClass($option.attr("class"))
						.append(
							$("<div class='text'></div>")
								.html(optionFormat($option))
						)
				);
		},

		delayPositionSB = function ()
		{
			clearTimeout(resizeTimeout);
			resizeTimeout = setTimeout(function () {
				if ($sb.is(".open"))
				{
					positionSB();
					openSB(1);
				}
			}, 50);
		},

		// unbind and remove
		destroySB = function ()
		{
			$sb.remove();
			$orig.unbind(".sb").removeClass("has_sb");
			$(window).unbind("resize", delayPositionSB);
			$orig.removeData("sb");
		},

		// destroy then load, maintaining open/focused state if applicable
		reloadSB = function ()
		{
			closeSB(1);
			destroySB();
			loadSB($orig, self, o);
			if ($sb.is(".open"))
			{
				$orig.focus();
				openSB(1);
			}
			else if ($display.is(".focused"))
				$orig.focus();
		},

		// hide and reset dropdown markup
		closeSB = function (instantClose)
		{
			if ($sb.is(".open"))
			{
				$display.blur();
				$items.removeClass("hover");
				$(document).unbind(".sb");
				$dd.attr("aria-hidden", true);
				if (instantClose)
					$sb.removeClass("open").append($dd.hide());
				else
				{
					closing = true;
					$dd.fadeOut(o.anim, function () {
						$sb.removeClass("open").append($dd);
						closing = false;
					});
				}
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
			$(".sb.focused." + o.css).not($sb).find(".display").blur();
		},

		// reposition the scroll of the dropdown so the selected option is centered (or appropriately onscreen)
		centerOnSelected = function ()
		{
			$dd.scrollTop($dd.scrollTop() + $items.filter(".selected").offsetFrom($dd).y - $dd.height() / 2 + $items.filter(".selected").outerHeight(true) / 2);
		},

		// show, reposition, and reset dropdown markup
		openSB = function (instantOpen)
		{
			blurAllButMe();
			$sb.addClass("open").append($dd.attr("aria-hidden", false));
			var dir = positionSB();
			if (instantOpen)
			{
				$dd.show();
				centerOnSelected();
			}
			else if (dir)
				$dd.slideDown(o.anim, centerOnSelected);
			else
				$dd.fadeIn(o.anim, centerOnSelected);
			$orig.focus();
		},

		// position dropdown based on collision detection
		positionSB = function ()
		{
			var	ddMaxHeight, dir = 0, // 0 for drop-down, 1 for drop-up
				ddY, bottomSpace, topSpace;

			// modify dropdown css for getting values
			$dd
				.show()
				.css({ // doesn't seem to be useful on my tests... Maybe a browser hack?
					maxHeight: "none",
					visibility: "hidden"
				})
				.removeClass("above");
			if (!o.fixed)
				$dd.width($display.outerWidth() - $dd.extraWidth() + 1);

			// figure out if we should show above/below the display box
			bottomSpace = $(window).scrollTop() + $(window).height() - $display.offset().top - $display.outerHeight();
			topSpace = $display.offset().top - $(window).scrollTop();

			// If we have enough space below the button, or if we don't have enough room above either, show a dropdown.
			if (($dd.outerHeight() <= bottomSpace) || (($dd.outerHeight() >= topSpace) && (bottomSpace + 50 >= topSpace)))
			{
				dir = 1;
				ddY = 0;
				ddMaxHeight = o.maxHeight || bottomSpace;
			}
			// Otherwise, show a drop-up, but only if there's enough size, or the space above is more comfortable.
			else
			{
				ddMaxHeight = o.maxHeight || topSpace;
				ddY = -ddMaxHeight;
			}

			// modify dropdown css for display
			$dd.hide().css({
				marginTop: ddY,
				maxHeight: ddMaxHeight,
				visibility: "visible"
			}).addClass(dir ? "" : "above");

			return dir;
		},

		// when the user explicitly clicks the display
		clickSB = function ()
		{
			// add active class to the display
			$display.addClass("active");
			$(document).mouseup(removeActiveState);

			$sb.is(".open") ? closeSB() : openSB();
			return false;
		},

		// when the user selects an item in any manner
		selectItem = function ($item)
		{
			// update the original <select>
			$orig.find("option").each(function () { this.selected = false; });
			$item.data("orig").each(function () { this.selected = true; });

			// change the selection to this item
			$items.removeClass("selected");
			$item.addClass("selected");
			$sb.attr("aria-activedescendant", $item.attr("id"));

			// update the title attr and the display markup
			$display.find(".text")
				.attr("title", $item.find(".text").html().php_unhtmlspecialchars())
				.html(optionFormat($item.data("orig")));

			// trigger change on the old <select> if necessary
			if ($orig.val() !== $item.data("value"))
				$orig.change();
		},

		// when the user explicitly clicks an item
		clickSBItem = function ()
		{
			closeAndUnbind();
			$orig.focus();
			selectItem($(this));
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

			if (e.which == 32) // space (requires e.which instead of e.keyCode... confusing.)
			{
				if (!$sb.is(".open"))
					openSB();
				e.preventDefault();
			}
			else if (e.keyCode == 9) // tab on an unopened select box?
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
			// try matching the next element that starts with the pressed letter
			else if (selectMatchingItem(String.fromCharCode(e.keyCode)))
				e.preventDefault();
		},

		// when the sb is focused (by tab or click), allow hotkey selection and kill all other selectboxes
		focusSB = function ()
		{
			// close all select boxes but this one, to prevent multiple selects open at once.
			$(".sb.open." + o.css).not($sb).trigger("close");

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
			$display.removeClass("active");
			$(document).unbind("keypress.sb");
		},

		// add hover class to an element
		addHoverState = function ()
		{
			if (!closing)
				$(this).addClass("hover");
		},

		// remove hover class from an element
		removeHoverState = function ()
		{
			$(this).removeClass("hover");
		},

		// remove active class from an element
		removeActiveState = function ()
		{
			$display.removeClass("active");
			$(document).unbind("mouseup", removeActiveState);
		};

		// public method interface
		this.init = loadSB;
		this.open = openSB;
		this.close = closeSB;
		this.refresh = reloadSB;
	};

}());

$('select').sb();
