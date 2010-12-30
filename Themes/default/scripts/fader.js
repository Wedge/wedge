
function wedge_NewsFader(oOptions)
{
	this.opt = oOptions;
	this.sControlId = '#' + oOptions.sFaderControlId;

	// Put some nice HTML around the items?
	this.sTemplate = 'sItemTemplate' in oOptions ? oOptions.sItemTemplate : '%1$s';

	// Fade speeds, in ms. FadeDelay is how long to leave an item on screen before fading. FadeSpeed is how long a fade in/out takes.
	this.fadeDelay = 'iFadeDelay' in oOptions ? oOptions.iFadeDelay : 5000;
	this.fadeSpeed = 'iFadeSpeed' in oOptions ? oOptions.iFadeSpeed : 650;

	// Load the items from the DOM.
	var aFaderItems = [];
	$(this.sControlId + ' li').each(function (i) {
		aFaderItems[aFaderItems.length] = $(this).html();
	});

	if (aFaderItems.length < 1)
		return;

	this.fadeIndex = 0;

	// Well, we are replacing the contents of a list, it *really* should be a list item we add in to it...
	$(this.sControlId).html('<li>' + this.sTemplate.replace('%1$s', aFaderItems[0]) + '</li>').show();
	this.aFaderItems = aFaderItems;
	this.fadeOut(this);
}

wedge_NewsFader.prototype.fadeIn = function (obj)
{
	obj.fadeIndex++;
	if (obj.fadeIndex >= obj.aFaderItems.length)
		obj.fadeIndex = 0;

	$(obj.sControlId + ' li').html(obj.sTemplate.replace('%1$s', obj.aFaderItems[obj.fadeIndex])).fadeTo(obj.fadeSpeed, 0.99, function() {
		// Remove alpha filter for IE, to restore ClearType.
		this.style.filter = '';
		obj.fadeOut(obj);
	});
};

wedge_NewsFader.prototype.fadeOut = function (obj)
{
	setTimeout(function() {
		$(obj.sControlId + ' li').fadeTo(obj.fadeSpeed, 0, function() {
			obj.fadeIn(obj);
		});
	}, obj.fadeDelay);
};

/* Optimize:
.opt = .o
.sTemplate = .st
.sControlId = .ci
.fadeDelay = .fd
.fadeSpeed = .fs
.fadeIndex = .fx
.aFaderItems = .fi
*/
