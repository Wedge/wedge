
function autoDetectTimeOffset(currentTime)
{
	var localTime = new Date(), serverTime = typeof currentTime != 'string' ? currentTime : new Date(currentTime);

	// Something wrong?
	if (!localTime.getTime() || !serverTime.getTime())
		return 0;

	// Get the difference between the two, set it up so that the sign will tell us who is ahead of whom.
	// Currently only supports timezones in hourly increments. Our apologies to India.
	// Make sure we are limiting this to one day's difference.
	return Math.round((localTime.getTime() - serverTime.getTime())/3600000) % 24;
}

// Prevent Chrome from auto completing fields when viewing/editing other members' profiles
function disableAutoComplete()
{
	if (is_chrome)
		$('input[type="text"], input[type="password"]').attr("autocomplete", "off");
}
