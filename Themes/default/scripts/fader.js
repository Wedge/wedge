/*!
 * Wedge
 *
 * All code related to showing rotating elements on a page, e.g. news fader.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

function weFader(opt)
{
	var
		aFaderItems = [],
		fadeIndex = 0,
		fadeDelay = opt.delay || 5000,		// How long until the next item transition?
		fadeSpeed = opt.speed || 650,		// How long should the transition effect last?
		sTemplate = opt.template || '%1$s',	// Put some nice HTML around the items?
		sControlId = '#' + opt.control,

		fadeIn = function ()
		{
			fadeIndex++;
			if (fadeIndex >= aFaderItems.length)
				fadeIndex = 0;

			$(sControlId + ' li').html(sTemplate.replace('%1$s', aFaderItems[fadeIndex])).fadeTo(fadeSpeed, 0.99, function () {
				// Remove alpha filter for IE, to restore ClearType.
				this.style.filter = '';
				fadeOut();
			});
		},
		fadeOut = function ()
		{
			setTimeout(function () { $(sControlId + ' li').fadeTo(fadeSpeed, 0, fadeIn); }, fadeDelay);
		};

	// Load the items from the DOM.
	$(sControlId + ' li').each(function () {
		aFaderItems.push($(this).html());
	});

	if (aFaderItems.length)
	{
		// Well, we are replacing the contents of a list, it *really* should be a list item we add in to it...
		$(sControlId).html('<li>' + sTemplate.replace('%1$s', aFaderItems[0]) + '</li>').show();
		fadeOut();
	}
}
