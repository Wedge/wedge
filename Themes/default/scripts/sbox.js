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
		// We could cache the following offsets to halve the execution time, but even IE7 can run this 5000 times per second,
		// so we might as well leave it that way and save 5 bytes in our gzipped file. Yes, I know, I'm crazy like that.
		return {
			x: $(this).offset().left - e.offset().left,
			y: $(this).offset().top - e.offset().top
		};
	};

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
		// formatting for the display
		optionFormat = function ($dom)
		{
			return $dom.text() || "";
		},

	SelectBox = function ()
	{
		var rand = parseInt(Math.random() * 9e9),
			cstTimeout = null,
			resizeTimeout = null,
			searchTerm = "",
			body = "body",
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
				ctx: body,			// body | self | any selector | a function that returns a selector (the original select is the context)
				fixed: false,		// fixed width; if false, dropdown expands to widest and display conforms to whatever is selected
				maxHeight: false,	// if an integer, show scrollbars if the dropdown is too tall
				maxWidth: false,	// if an integer, prevent the display/dropdown from growing past this width; longer items will be clipped
				css: "selectbox",	// class to apply our markup

				// markup appended to the display, typically for styling an arrow
				arrow: "<div class='btn'><div></div></div>"
			}, opts);

			$label = $orig.attr("id") ? $("label[for='" + $orig.attr("id") + "']:first") : '';
			if ($label.length == 0)
				$label = $orig.closest("label");

			// create the new sb
			$sb = $("<div class='sb " + o.css + " " + $orig.attr("class") + "' id='sb" + rand + "' role=listbox></div>")
				.attr("aria-labelledby", $label.attr("id") || "")
				.attr("aria-haspopup", true)
				.appendTo(body);

			$display = $("<div class='display " + $orig.attr("class") + "' id='sbd" + rand + "'></div>")
				// generate the display markup
				.append($("<div class='text'></div>").append(optionFormat($orig.find("option:selected")) || "&nbsp;"))
				.append(o.arrow)
				.appendTo($sb);

			// generate the dropdown markup
			$dd = $("<ul class='" + o.css + " items " + $orig.attr("class") + "' id='sbdd" + rand + "' role=menu></ul>")
				.attr("aria-hidden", true);
			$sb.append($dd)
				.attr("aria-owns", $dd.attr("id"));
			if ($orig.children().length == 0)
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
			$sb.bind("close.sb", closeSB);
		},

		// create new markup from an <option>
		createOption = function ($option)
		{
			$option = $option || $("<option>&nbsp;</option>");

			return $("<li id='sbo" + rand + "' role=option></li>")
				.data("orig", $option)
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
			closeSB(1);
			destroySB(1);
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
				offs = $display.offsetFrom($ddCtx),
				ddMaxHeight = 0,
				dir = 0, // 0 for drop-down, 1 for drop-up
				ddY, bottomSpace, topSpace;

			// modify dropdown css for getting values
			$dd
				.show()
				.css({ // doesn't seem to be useful on my tests... Maybe a browser hack?
					maxHeight: "none",
					position: "relative",
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
				ddY = $display.outerHeight();
				ddMaxHeight = o.maxHeight || bottomSpace;
			}
			// Otherwise, show a drop-up, but only if there's enough size, or the space above is more comfortable.
			else
			{
				ddMaxHeight = o.maxHeight || topSpace;
				ddY = -Math.min(ddMaxHeight, $dd.outerHeight());
			}

			// modify dropdown css for display
			$dd.hide().css({
				left: offs.x + ($ddCtx.is(body) ? parseInt($(body).css("marginLeft")) || 0 : 0),
				top: offs.y + ddY + ($ddCtx.is(body) ? parseInt($(body).css("marginTop")) || 0 : 0),
				maxHeight: ddMaxHeight,
				position: "absolute",
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
				.attr("title", $item.find(".text").html())
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
			var i, t, $tNode, $available = $items.not(".disabled");

			for (i = 0; i < $available.length; i++)
			{
				$tNode = $available.eq(i).find(".text");
				t = $tNode.children().length == 0 ? $tNode.text() : $tNode.find("*").text();
				if (term.length && t.toLowerCase().match("^" + term.toLowerCase()))
				{
					selectItem($available.eq(i));
					return true;
				}
			}
			return false;
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
					selectItem($available.eq(i));
					return true;
				}
			}
			return false;
		},

		// go up/down using arrows or attempt to autocomplete based on string
		keyPress = function (e)
		{
			if (e.altKey || e.ctrlKey)
				return;

			var $selected = $items.filter(".selected"), $enabled = $items.not(".disabled");

			if (e.keyCode == 8 || e.keyCode == 32) // backspace or space
				e.preventDefault();

			else if (e.keyCode == 9) // tab
			{
				closeSB();
				blurSB();
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
			else
			{
				// add to the search term
				searchTerm += String.fromCharCode(e.keyCode);

				if (selectMatchingItem(searchTerm))
				{
					// we found a match, continue with the current search term
					clearTimeout(cstTimeout);
					cstTimeout = setTimeout(function () { searchTerm = ""; }, 800);
				}
				else if (selectNextItemStartsWith(String.fromCharCode(e.keyCode)))
				{
					// we selected the next item that starts with what you just pressed
					centerOnSelected();
					clearTimeout(cstTimeout);
					cstTimeout = setTimeout(function () { searchTerm = ""; }, 800);
				}
				else
				{
					// no matches were found, clear everything
					searchTerm = "";
					clearTimeout(cstTimeout);
				}
			}
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
		this.destroy = destroySB;
	};

}(jQuery, window));

$('select').sb();
