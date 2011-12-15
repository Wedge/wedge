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
	jQuery-SelectBox

	Traditional select elements are very difficult to style by themselves,
	but they are also very usable and feature rich. This plugin attempts to
	recreate all selectbox functionality and appearance while adding
	animation and stylability.

	This product includes software developed
	by RevSystems, Inc (http://www.revsystems.com/) and its contributors

	Copyright (c) 2010 RevSystems, Inc

	Permission is hereby granted, free of charge, to any person obtaining a copy
	of this software and associated documentation files (the "Software"), to deal
	in the Software without restriction, including without limitation the rights
	to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
	copies of the Software, and to permit persons to whom the Software is
	furnished to do so, subject to the following conditions:

	The above copyright notice and this permission notice shall be included in
	all copies or substantial portions of the Software.

	The end-user documentation included with the redistribution, if any, must 
	include the following acknowledgment: "This product includes software developed 
	by RevSystems, Inc (http://www.revsystems.com/) and its contributors", in the 
	same place and form as other third-party acknowledgments. Alternately, this 
	acknowledgment may appear in the software itself, in the same form and location 
	as other such third-party acknowledgments.

	THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
	IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
	FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
	AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
	LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
	OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
	THE SOFTWARE.
*/

(function ($, window) {

	// Utility functions
	$.fn.extraWidth = function ()
	{
		return $(this).outerWidth(true) - $(this).width();
	};

	$.fn.offsetFrom = function (e)
	{
		var $e = e.offset(), $t = $(this).offset();
		return {
			x: $t.left - $e.left,
			y: $t.top - $e.top
		};
	};

	// Returns the widest element's width from a list of elements.
	// Here's an elegant alternative way of doing it, but compression is worse:
	// return Math.max.apply(0, this.map(function () { return $(this).width(); }).get());
	$.fn.maxWidth = function ()
	{
		var max = 0;
		this.each(function () { max = Math.max(max, $(this).width()); });
		return max;
	};

	$.fn.sb = function ()
	{
		var result, slice = Array.prototype.slice, args = arguments, undefined;

		this.each(function ()
		{
			var $e = $(this), obj = $e.data("sb");

			// if it is already created, then access
			if (obj)
			{
				// do something with the object
				if (args.length > 0)
				{
					if ($.isFunction(obj[args[0]]))
						// use the method access interface
						result = obj[args[0]].apply(obj, slice.call(args, 1));
					else if (args.length === 1)
						// just retrieve the property
						result = obj[args[0]];
					else
						// set the property
						obj[args[0]] = args[1];
				}
				else if (result === undefined)
					// return the first object if there are no args
					result = $e.data("sb");
			}
			// if the object is not defined for this element, then construct.
			// this plugin is not compatible with IE6 and below;
			// a normal <select> will be displayed for old browsers
			else if (!is_ie6)
			{
				// create the new object and restore init if necessary
				obj = new SelectBox();

				// set the elem property and initialize the object
				obj.elem = this;

				obj.init.apply(obj, slice.call(args, 0));

				// associate it with the element
				$e.data("sb", obj);
			}
		});

		// chain if no results were returned from the class's method (it's a setter)
		return result === undefined ? $(this) : result;
	};

	var
		randInt = function () {
			return Math.ceil(Math.random() * 9e9);
		},

		// formatting for the display
		optionFormat = function ($dom)
		{
			return $dom.text() || "";
		},

	SelectBox = function ()
	{
		var self = this,
			cstTimeout = null,
			resizeTimeout = null,
			searchTerm = "",
			body = "body",
			o,
			is_closing,
			$display,
			$orig,
			$dd,
			$sb,
			$items,
			$label,

		loadSB = function (opts)
		{
			// get the original <select> and <label>
			$orig = $(self.elem);

			// don't create duplicate SBs
			if ($orig.hasClass("has_sb"))
				return;

			if ($orig.attr("id"))
				$label = $("label[for='" + $orig.attr("id") + "']:first");
			if (!$label || $label.length === 0)
				$label = $orig.closest("label");

			// set the various options
			o = $.extend({
				anim: 200,			// animation duration: time to open/close dropdown in ms
				ctx: body,			// body | self | any selector | a function that returns a selector (the original select is the context)
				fixedWidth: false,	// if false, dropdown expands to widest and display conforms to whatever is selected
				maxHeight: false,	// if an integer, show scrollbars if the dropdown is too tall
				maxWidth: false,	// if an integer, prevent the display/dropdown from growing past this width; longer items will be clipped
				css: "selectbox",	// class to apply our markup

				// markup appended to the display, typically for styling an arrow
				arrow: "<div class='btn'><div></div></div>"
			}, opts);

			// create the new sb
			$sb = $("<div class='sb " + o.css + " " + $orig.attr("class") + "' id='sb" + randInt() + "' role=listbox></div>")
				.attr("aria-labelledby", $label.attr("id") || "")
				.attr("aria-haspopup", true)
				.appendTo(body);

			$display = $("<div class='display " + $orig.attr("class") + "' id='sbd" + randInt() + "'></div>")
				// generate the display markup
				.append($("<div class='text'></div>").append(optionFormat($orig.find("option:selected")) || "&nbsp;"))
				.append(o.arrow)
				.appendTo($sb);

			// generate the dropdown markup
			$dd = $("<ul class='" + o.css + " items " + $orig.attr("class") + "' id='sbdd" + randInt() + "' role=menu></ul>")
				.attr("aria-hidden", true);
			$sb.append($dd)
				.attr("aria-owns", $dd.attr("id"));
			if ($orig.children().length === 0)
				$dd.append(createOption().addClass("selected"));
			else
				$orig.children().each(function ()
				{
					var $og = $(this), $ogList;
					if ($og.is("optgroup"))
					{
						$ogList = $("<ul class='items'></ul>");
						$og.children("option").each(function ()
						{
							$ogList.append(createOption($(this))
								.addClass($og.is(":disabled") ? "disabled" : "")
								.attr("aria-disabled", !!$og.is(":disabled")));
						});
						$("<li class='optgroup'><div class='label'>" + $og.attr("label") + "</div></li>")
							.addClass($og.is(":disabled") ? "disabled" : "")
							.attr("aria-disabled", !!$og.is(":disabled"))
							.append($ogList)
							.appendTo($dd);
					}
					else
						$dd.append(createOption($og));
				});

			// cache all sb items
			$items = $dd.find("li").not(".optgroup");

			// for accessibility/styling
			$sb.attr("aria-activedescendant", $items.filter(".selected").attr("id"));
			$dd.children(":first").addClass("first");
			$dd.children(":last").addClass("last");

			// modify width based on fixedWidth/maxWidth options
			if (!o.fixedWidth)
				$sb.width(Math.min(o.maxWidth || 9e9, $dd.find(".text, .optgroup").maxWidth() + $display.extraWidth() + 1));
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
				$orig
					// loses focus if original is blurred
					.bind("blur.sb", function () {
						if (!$sb.is(".open"))
							$display.triggerHandler("blur");
					})
					// causes focus if original is focused
					.bind("focus.sb", function () {
						blurAllButMe();
						$display.triggerHandler("focus");
					});
				$display
					.mouseup(addActiveState)
					.mouseup(clickSB)
					.focus(focusSB)
					.blur(blurSB)
					.mousedown(false)
					.click(false)
					.hover(addHoverState, removeHoverState);
				$items.not(".disabled")
					.click(clickSBItem)
					.hover(addHoverState, removeHoverState);
				$dd.find(".optgroup")
					.click(false)
					.hover(addHoverState, removeHoverState);
				$items.filter(".disabled")
					.click(false);
				if (!is_ie8down)
					$(window).resize(delayPositionSB);
			}
			else
			{
				$sb.addClass("disabled").attr("aria-disabled");
				$display.click(false);
			}

			// bind custom events
			$sb.bind("close.sb", closeSB).bind("destroy.sb", destroySB);
			$orig.bind("reload.sb", reloadSB);
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

		// create new markup from an <option>
		createOption = function ($option)
		{
			$option = $option || $("<option>&nbsp;</option>");

			return $("<li id='sbo" + randInt() + "' role=option></li>")
				.data("orig", $option[0])
				.data("value", $option.attr("value") || "")
				.attr("style", $option.attr("style") || "")
				.addClass($option.is(":selected") ? "selected" : "")
				.addClass($option.is(":disabled") ? "disabled" : "")
				.attr("aria-disabled", !!$option.is(":disabled"))
				.append($("<div class='item'></div>")
					.append($("<div class='text'></div>")
					.html(optionFormat($option)))
				);
		},

		// unbind and remove
		destroySB = function (internal)
		{
			$sb.remove();
			$orig.unbind(".sb").removeClass("has_sb");
			$(window).unbind("resize", delayPositionSB);
			if (!internal)
				$orig.removeData("sb");
		},

		// destroy then load, maintaining open/focused state if applicable
		reloadSB = function ()
		{
			var isOpen = $sb.is(".open"), isFocused = $display.is(".focused");
			closeSB(1);
			destroySB(1);
			loadSB(o);
			if (isOpen)
			{
				$orig.focus();
				openSB(1);
			}
			else if (isFocused)
				$orig.focus();
		},

		// when the user clicks outside the sb
		closeAndUnbind = function ()
		{
			$sb.removeClass("focused");
			closeSB();
			unbind();
		},

		unbind = function ()
		{
			$(document)
				.unbind("click", closeAndUnbind)
				.unbind("keypress", stopPageHotkeys)
				.unbind("keydown", stopPageHotkeys)
				.unbind("keydown", keydownSB)
				.unbind("keyup", keyupSB);
		},

		// hide and reset dropdown markup
		closeSB = function (instantClose)
		{
			if ($sb.is(".open"))
			{
				$display.blur();
				$items.removeClass("hover");
				unbind();
				$dd.attr("aria-hidden", true);
				if (instantClose)
					$sb.removeClass("open").append($dd.hide());
				else
				{
					is_closing = true;
					$dd.fadeOut(o.anim, function () {
						$sb.removeClass("open").append($dd);
						is_closing = false;
					});
				}
			}
		},

		// trigger all select boxes to blur
		blurAllButMe = function ()
		{
			$(".sb.focused." + o.css).not($sb[0]).find(".display").blur();
		},

		// reposition the scroll of the dropdown so the selected option is centered (or appropriately onscreen)
		centerOnSelected = function ()
		{
			$dd.scrollTop($dd.scrollTop() + $items.filter(".selected").offsetFrom($dd).y - $dd.height() / 2 + $items.filter(".selected").outerHeight(true) / 2);
		},

		// show, reposition, and reset dropdown markup
		openSB = function (instantOpen)
		{
			var dir, $ddCtx = $(o.ctx);
			blurAllButMe();
			$sb.addClass("open");
			$dd.attr("aria-hidden", false).appendTo($ddCtx);
			dir = positionSB();
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
			var $ddCtx = $(o.ctx),
				ddMaxHeight = 0,
				dir = 0, // 0 for drop-down, 1 for drop-up
				ddY = 0,
				ddX = $display.offsetFrom($ddCtx).x,
				bottomSpace, topSpace;

			// modify dropdown css for getting values
			$dd
				// .removeClass("above")
				.show()
				.css({
					maxHeight: "none",
					position: "relative",
					visibility: "hidden"
				});
			if (!o.fixedWidth)
				$dd.width($display.outerWidth() - $dd.extraWidth() + 1);

			// figure out if we should show above/below the display box
			bottomSpace = $(window).scrollTop() + $(window).height() - $display.offset().top - $display.outerHeight();
			topSpace = $display.offset().top - $(window).scrollTop();

			// If we have enough space below the button, or if we don't have enough room above either, show a dropdown.
			if (($dd.outerHeight() <= bottomSpace) || (($dd.outerHeight() >= topSpace) && (bottomSpace + 50 >= topSpace)))
			{
				dir = 1;
				ddY = $display.offsetFrom($ddCtx).y + $display.outerHeight();
				ddMaxHeight = o.maxHeight || bottomSpace;
			}
			// Otherwise, show a drop-up, but only if there's enough size, or the space above is more comfortable.
			else
			{
				ddMaxHeight = o.maxHeight || topSpace;
				ddY = $display.offsetFrom($ddCtx).y - Math.min(ddMaxHeight, $dd.outerHeight());
			}

			// modify dropdown css for display
			$dd.hide().css({
				left: ddX + ($ddCtx.is(body) ? parseInt($(body).css("marginLeft")) || 0 : 0),
				top: ddY + ($ddCtx.is(body) ? parseInt($(body).css("marginTop")) || 0 : 0),
				maxHeight: ddMaxHeight,
				position: "absolute",
				visibility: "visible"
			});

			// We currently don't need to apply specific styles to drop-up list boxes.
			//	if (!dir)
			//		$dd.addClass("above");

			return dir;
		},

		// when the user explicitly clicks the display
		clickSB = function ()
		{
			$sb.is(".open") ? closeSB() : openSB();
			return false;
		},

		// when the user selects an item in any manner
		selectItem = function ()
		{
			var $item = $(this),
				oldVal = $orig.val(),
				newVal = $item.data("value");

			// update the original <select>
			$orig.find("option").each(function () { this.selected = false; });
			$($item.data("orig")).each(function () { this.selected = true; });

			// change the selection to this item
			$items.removeClass("selected");
			$item.addClass("selected");
			$sb.attr("aria-activedescendant", $item.attr("id"));

			// update the title attr and the display markup
			$display.find(".text")
				.attr("title", $item.find(".text").html())
				.html(optionFormat($($item.data("orig"))));

			// trigger change on the old <select> if necessary
			if (oldVal !== newVal)
				$orig.change();
		},

		// when the user explicitly clicks an item
		clickSBItem = function ()
		{
			closeAndUnbind();
			$orig.focus();
			selectItem.call(this);
			return false;
		},

		// start over for generating the search term
		clearSearchTerm = function ()
		{
			searchTerm = "";
		},

		// iterate over all the options to see if any match the search term
		findMatchingItem = function (term)
		{
			var i, t, $tNode, $available = $items.not(".disabled");
			for (i = 0; i < $available.length; i++)
			{
				$tNode = $available.eq(i).find(".text");
				t = $tNode.children().length == 0 ? $tNode.text() : $tNode.find("*").text();
				if (term.length > 0 && t.toLowerCase().match("^" + term.toLowerCase()))
					return $available.eq(i);
			}
			return null;
		},

		// if we get a match for any options, select it
		selectMatchingItem = function (text)
		{
			var $matchingItem = findMatchingItem(text);
			if ($matchingItem !== null)
			{
				selectItem.call($matchingItem[0]);
				return true;
			}
			return false;
		},

		// stop up/down/backspace/space from moving the page
		stopPageHotkeys = function (e)
		{
			if (!e.altKey && !e.ctrlKey && in_array(e.which, [8,32,38,40]))
				e.preventDefault();
		},

		// if a normal match fails, try matching the next element that starts with the pressed letter
		selectNextItemStartsWith = function (c)
		{
			var i, t, $selected = $items.filter(".selected"), $available = $items.not(".disabled");
			for (i = $available.index($selected) + 1; i < $available.length; i++)
			{
				t = $available.eq(i).find(".text").text();
				if (t !== "" && t[0].toLowerCase() === c.toLowerCase())
				{
					selectItem.call($available.eq(i)[0]);
					return true;
				}
			}
			return false;
		},

		// go up/down using arrows or attempt to autocomplete based on string
		keydownSB = function (e)
		{
			if (e.altKey || e.ctrlKey)
				return false;

			var $selected = $items.filter(".selected"), $enabled = $items.not(".disabled");
			switch (e.which)
			{
				case 9: // tab
					closeSB();
					blurSB();
					break;

				case 35: // end
					if ($selected.length > 0)
					{
						e.preventDefault();
						selectItem.call($enabled.filter(":last")[0]);
						centerOnSelected();
					}
					break;

				case 36: // home
					if ($selected.length > 0)
					{
						e.preventDefault();
						selectItem.call($enabled.filter(":first")[0]);
						centerOnSelected();
					}
					break;

				case 38: // up
					if ($selected.length > 0)
					{
						if ($enabled.filter(":first")[0] !== $selected[0])
						{
							e.preventDefault();
							selectItem.call($enabled.eq($enabled.index($selected)-1)[0]);
						}
						centerOnSelected();
					}
					break;

				case 40: // down
					if ($selected.length > 0)
					{
						if ($enabled.filter(":last")[0] !== $selected[0])
						{
							e.preventDefault();
							selectItem.call($enabled.eq($enabled.index($selected)+1)[0]);
							centerOnSelected();
						}
					}
					else if ($items.length > 1)
					{
						e.preventDefault();
						selectItem.call($items.eq(0)[0]);
					}
			}
		},

		// the user is typing -- try to select an item based on what they press
		keyupSB = function (e)
		{
			if (e.altKey || e.ctrlKey)
				return false;

			if (e.which != 38 && e.which != 40)
			{
				// add to the search term
				searchTerm += String.fromCharCode(e.keyCode);

				if (selectMatchingItem(searchTerm))
				{
					// we found a match, continue with the current search term
					clearTimeout(cstTimeout);
					cstTimeout = setTimeout(clearSearchTerm, 800);
				}
				else if (selectNextItemStartsWith(String.fromCharCode(e.keyCode)))
				{
					// we selected the next item that starts with what you just pressed
					centerOnSelected();
					clearTimeout(cstTimeout);
					cstTimeout = setTimeout(clearSearchTerm, 800);
				}
				else
				{
					// no matches were found, clear everything
					clearSearchTerm();
					clearTimeout(cstTimeout);
				}
			}
		},

		// when the sb is focused (by tab or click), allow hotkey selection and kill all other selectboxes
		focusSB = function ()
		{
			// close all select boxes but this one, to prevent multiple selects open at once.
			// triggerHandler calls the associated event without actually triggering the event itself.
			$(".sb.open." + o.css).not($sb[0]).each(function () { $(this).triggerHandler("close"); });

			$sb.addClass("focused");
			$(document)
				.click(closeAndUnbind)
				.keyup(keyupSB)
				.keypress(stopPageHotkeys)
				.keydown(stopPageHotkeys)
				.keydown(keydownSB);
		},

		// when the sb is blurred (by tab or click), disable hotkey selection
		blurSB = function ()
		{
			$sb.removeClass("focused");
			$display.removeClass("active");
			$(document)
				.unbind("keypress", stopPageHotkeys)
				.unbind("keydown", stopPageHotkeys)
				.unbind("keydown", keydownSB)
				.unbind("keyup", keyupSB);
		},

		// add hover class to an element
		addHoverState = function ()
		{
			if (!is_closing)
				$(this).addClass("hover");
		},

		// remove hover class from an element
		removeHoverState = function ()
		{
			$(this).removeClass("hover");
		},

		// add active class to the display
		addActiveState = function ()
		{
			$display.addClass("active");
			$(document).mouseup(removeActiveState);
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
		this.destroy = destroySB;
	};

}(jQuery, window));