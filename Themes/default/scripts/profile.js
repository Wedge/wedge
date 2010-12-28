
var localTime = new Date();

function autoDetectTimeOffset(currentTime)
{
	var serverTime = typeof currentTime != 'string' ? currentTime : new Date(currentTime);

	// Something wrong?
	if (!localTime.getTime() || !serverTime.getTime())
		return 0;

	// Get the difference between the two, set it up so that the sign will tell us who is ahead of whom.
	// Currently only supports timezones in hourly increments. Our apologies to India.
	var diff = Math.round((localTime.getTime() - serverTime.getTime())/3600000);

	// Make sure we are limiting this to one day's difference.
	return diff % 24;
}

// Prevent Chrome from auto completing fields when viewing/editing other members' profiles
function disableAutoComplete()
{
	if (!is_chrome)
		return;

	for (var i = 0, n = document.forms.length; i < n; i++)
	{
		var die = document.forms[i].elements;
		for (var j = 0, m = die.length; j < m; j++)
			// Only bother with text/password fields?
			if (die[j].type == "text" || die[j].type == "password")
				die[j].setAttribute("autocomplete", "off");
	}
}
