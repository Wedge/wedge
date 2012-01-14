/**
 * Wedge
 *
 * Functions required by the current theme.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

// The purpose of this code is to fix the height of overflow: auto blocks,
// because some browsers can't figure it out for themselves. (Opera FTW!)
if (is_ie || is_webkit || is_ff)
{
	$('code').each(function () {
		var cliwid = this.clientWidth, scrwid = this.scrollWidth, offhei = this.offsetHeight, sty = this.currentStyle, hei = sty ? sty.height : '';

		if (is_webkit && offhei < 20)
			this.style.height = (offhei + 20) + 'px';

		else if (is_ff && (scrwid > cliwid || cliwid == 0))
			this.style.overflow = 'scroll';

		else if (sty && sty.overflow == 'auto' && (hei == '' || hei == 'auto') && (scrwid > cliwid || cliwid == 0) && (offhei != 0))
			this.style.height = (offhei + 24) + 'px';
	});
}

// Toggles the element height and width styles of an image.
$('img.resized').css('cursor', 'pointer').click(function () {
	this.style.width = this.style.height = (this.style.width == 'auto' ? null : 'auto');
});
