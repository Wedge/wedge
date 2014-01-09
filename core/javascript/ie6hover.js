/*!
 * jQuery IE6 hover support plug-in v1.1.0
 * Add support for the :hover CSS pseudo-selector to IE6
 * http://github.com/gilmoreorless/jquery-ie6hover
 *
 * @requires jQuery v1.3 or later
 *
 * Copyright (c) 2010 Gilmore Davidson
 *
 * Licensed under the MIT license:
 *   http://www.opensource.org/licenses/mit-license.php
 */
(function($){
	$.extend({
		ie6hover: function (future) {
			if (!is_ie6)
				return;

			var func = future ? 'live' : 'bind',
				sheets = document.styleSheets,
				rCheck = /(.*?)(:hover)\b/g,
				rIgnore = /\bA([#\.].*)*:hover\b/ig,
				rClass = /\.(\S+?)\b/ig,
				defaultClass = 'hover-ie6',
				selectors = [],
				selectorClasses = {
					_default: defaultClass
				},
				textIndex, selMatch;

			if (!sheets.length)
				return;

			for (var i = 0, slen = sheets.length; i < slen; i++)
			{
				var sheet = sheets[i];
				// Gracefully handle any cross-domain security errors
				try {
					var rules = sheet.rules;
				} catch (e) {
					continue;
				}

				if (!rules || !rules.length)
					continue;

				for (var j = 0, len = rules.length; j < len; j++)
				{
					var rule = rules[j], text = (rule && rule.selectorText) || '', newText = [], newTextChunk = '';
					// Reset regexps to make sure we're matching at the start of the selector
					rCheck.lastIndex = 0;
					rIgnore.lastIndex = 0;
					if (rCheck.test(text) && !rIgnore.test(text))
					{
						var currentClass = defaultClass, selector = '';
						rCheck.lastIndex = 0;
						// Add the CSS selector in a way that jQuery can handle (ie. no ":hover")
						// Needs to loop through to handle multiple ":hover" instances per selector
						// (odd use case, but still plausible)
						while (selMatch = rCheck.exec(text))
						{
							textIndex = rCheck.lastIndex;
							selector += selMatch[1];
							selectors.push(selector);
							// Build new CSS rule bit-by-bit, allows for fine-grained class replacement
							newTextChunk = selMatch[1];
							// Check which class to add new rule for - default to ".hover-ie6"
							// IE6 can't handle .class1.class2 (it reads as just .class2), so if there's
							// a class already in the selector, generate a new custom class (eg .class1-class2)
							rClass.lastIndex = 0;
							newTextChunk = newTextChunk.replace(rClass, function (match, className) {
								currentClass = className + '-' + defaultClass;
								return '';
							}) + '.' + currentClass;
							// If the replacement class is not standard, add it to the selector class map for jQuery
							if (currentClass !== defaultClass)
								selectorClasses[selector] = currentClass;
							newText.push(newTextChunk);
						}

						// Make sure to catch any remaining bit of CSS text that wasn't matched
						if (textIndex < text.length)
							newText.push(text.slice(textIndex));

						// Add a new CSS rule at the same place as the existing rule to keep CSS inheritance working
						text = newText.join('');
						if (rule.style.cssText)
							sheet.addRule(text, rule.style.cssText, j);

						// Increase the counters due to the new rule being inserted
						j++;
						len++;

						// Add new rule to public object to aid debugging
						$.ie6hover.selectors.css.push([text, rule.style.cssText]);
					}
				}
			}

			if (selectors.length)
			{
				// Quick function to de-duplicate selector array before sending to jQuery, to save finding duplicate DOM nodes
				if (selectors.length > 1)
				{
					selectors = (function (oldArr) {
						for (var newArr = [], map = {}, i = 0, l = oldArr.length, val; i < l; i++)
						{
							val = oldArr[i];
							if (!map[val])
							{
								map[val] = true;
								newArr.push(val);
							}
						}
						return newArr;
					})(selectors);
				}

				// Add selectors to public object to aid debugging
				$.ie6hover.selectors.jQuery = selectors;

				// Add hover event handlers to selectors
				// Skips over form elements to make IE6 faster, without breaking the UI.
				// Also skips over .css, as it's only used to make menus work on browser with JS disabled...
				$(function () {
					$.each(selectors, function (i, selector) {
						if (selector.match(/(INPUT|SELECT|TEXTAREA|.css)/g))
							return;
						var klass = selectorClasses[selector] || selectorClasses._default;
						$(selector)[func]('mouseenter', function () {
							$(this).addClass(klass);
						})[func]('mouseleave', function () {
							$(this).removeClass(klass);
						});
					});
				});
			}
		}
	});
	$.ie6hover.selectors = {
		css: [],
		jQuery: []
	};
})(jQuery);
