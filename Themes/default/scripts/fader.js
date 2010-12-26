function wedge_NewsFader(oOptions)
{
	this.opt = oOptions;
	this.sControlId = '#' + this.opt.sFaderControlId;

	// Put some nice HTML around the items?
	this.sItemTemplate = 'sItemTemplate' in this.opt ? this.opt.sItemTemplate : '%1$s';

	// Fade speeds, in ms. FadeDelay is how long to leave an item on screen before fading. FadeSpeed is how long a fade in/out takes.
	this.iFadeDelay = 'iFadeDelay' in this.opt ? this.opt.iFadeDelay : 5000;
	this.iFadeSpeed = 'iFadeSpeed' in this.opt ? this.opt.iFadeSpeed : 650;

	// Load the items from the DOM.
	var aFaderItems = [];
	$(this.sControlId + ' li').each(function (i) {
		aFaderItems[aFaderItems.length] = $(this).html();
	});

	if (aFaderItems.length < 1)
		return;

	this.iFadeIndex = 0;

	// Well, we are replacing the contents of a list, it *really* should be a list item we add in to it...
	$(this.sControlId).html('<li>' + this.sItemTemplate.replace('%1$s', aFaderItems[0]) + '</li>').show();
	this.aFaderItems = aFaderItems;
	this.fadeOut(this);
}

wedge_NewsFader.prototype.fadeIn = function (obj)
{
	obj.iFadeIndex++;
	if (obj.iFadeIndex >= obj.aFaderItems.length)
		obj.iFadeIndex = 0;

	$(obj.sControlId + ' li').html(obj.sItemTemplate.replace('%1$s', obj.aFaderItems[obj.iFadeIndex])).fadeTo(obj.iFadeSpeed, 0.99, function() {
		obj.fadeOut(obj)
	});
};

wedge_NewsFader.prototype.fadeOut = function (obj)
{
	setTimeout(function() {
		$(obj.sControlId + ' li').fadeTo(obj.iFadeSpeed, 0, function() {
			obj.fadeIn(obj)
		});
	}, obj.iFadeDelay);
};