// This file contains javascript associated with the captcha visual verification stuff.

function smfCaptcha(imageURL, uniqueID, useLibrary, letterCount)
{
	// By default the letter count is five.
	if (!letterCount)
		letterCount = 5;

	uniqueID = uniqueID ? '_' + uniqueID : '';
	autoCreate();

	// Automatically get the captcha event handlers in place and the like.
	function autoCreate()
	{
		// Is there anything to cycle images with - if so attach the refresh image function?
		$('#visual_verification' + uniqueID + '_refresh').click(refreshImages);

		// Maybe a voice is here to spread light?
		$('#visual_verification' + uniqueID + '_sound').click(playSound);
	}

	// Change the images.
	function refreshImages()
	{
		// Make sure we are using a new rand code.
		var new_url = String(imageURL);
		new_url = new_url.substr(0, new_url.indexOf('rand=') + 5);

		// Quick and dirty way of converting decimal to hex
		var hexstr = '0123456789abcdef';
		for (var i = 0; i < 32; i++)
			new_url += hexstr.substr(Math.floor(Math.random() * 16), 1);

		if (useLibrary)
			$('#verification_image' + uniqueID)).attr('src', new_url);
		else if ($('#verification_image' + uniqueID).length)
			for (i = 1; i <= letterCount; i++)
				$('#verification_image' + uniqueID + '_' + i).attr('src', new_url + ';letter=' + i);

		return false;
	}

	// Request a sound... play it Mr Soundman...
	function playSound(ev)
	{
		return reqWin(this, 400, 120);
	}
}
