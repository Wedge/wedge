/*!
 * Wedge
 *
 * This file contains javascript associated with the captcha visual verification stuff.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

function weCaptcha(imageURL, uniqueID)
{
	// Change the images.
	function refreshImages()
	{
		// Make sure we are using a new rand code.
		var
			new_url = imageURL.substr(0, imageURL.indexOf('rand=') + 5),
			hexstr = '0123456789abcdef';

		// Quick and dirty way of converting decimal to hex
		for (var i = 0; i < 32; i++)
			new_url += hexstr.substr(Math.floor(Math.random() * 16), 1);

		$('#verification_image_' + uniqueID).attr('src', new_url);

		return false;
	}

	// Request a sound... play it Mr Soundman...
	function playSound(ev)
	{
		return reqWin(this, 400, 120);
	}

	// Is there anything to cycle images with - if so attach the refresh image function?
	$('#visual_verification_' + uniqueID + '_refresh').click(refreshImages);

	// Maybe a voice is here to spread light?
	$('#visual_verification_' + uniqueID + '_sound').click(playSound);
}
