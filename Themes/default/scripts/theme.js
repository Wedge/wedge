/*!
 * This file is under the SMF license.
 * All code changes compared against SMF 2.0 are protected
 * by the Wedge license, http://wedgeforum.com/license/
 */

// The purpose of this code is to fix the height of overflow: auto blocks,
// because some browsers can't figure it out for themselves. Opera FTW?
function smf_codeBoxFix()
{
	$('code').each(function () {
		if (is_webkit && this.offsetHeight < 20)
			this.style.height = (this.offsetHeight + 20) + 'px';
		else if (is_ff && (this.scrollWidth > this.clientWidth || this.clientWidth == 0))
			this.style.overflow = 'scroll';
		else
		{
			var sty = this.currentStyle;
			if (sty && sty.overflow == 'auto' && (sty.height == '' || sty.height == 'auto') &&
				(this.scrollWidth > this.clientWidth || this.clientWidth == 0) && (this.offsetHeight != 0))
			this.style.height = (this.offsetHeight + 24) + 'px';
		}
	});
}

// Add a fix for code stuff?
if (is_ie || is_webkit || is_ff)
	addLoadEvent(smf_codeBoxFix);

// Toggles the element height and width styles of an image.
addLoadEvent(function () {
	$('img.resized').each(function () {
		$(this).css('cursor', 'pointer').click(function() {
			this.style.width = this.style.height = (this.style.width == 'auto' ? null : 'auto');
		});
	});
});
