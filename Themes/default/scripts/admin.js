
/*
	smf_AdminIndex(oOptions)
	{
		public setAnnouncements()
		public showCurrentVersion()
		public checkUpdateAvailable()
	}

	smf_ViewVersions(oOptions)
	{
		public swapOption(oSendingElement, sName)
		public compareVersions(sCurrent, sTarget)
		public determineVersions()
	}
*/

// Handle the JavaScript surrounding the admin and moderation center.
function smf_AdminIndex(oOptions)
{
	this.opt = oOptions;

	// Load the text box containing the latest news items.
	if (this.opt.bLoadAnnouncements)
		this.setAnnouncements();

	// Load the current SMF and your SMF version numbers.
	if (this.opt.bLoadVersions)
		this.showCurrentVersion();

	// Load the text box that says there's a new version available.
	if (this.opt.bLoadUpdateNotification)
		this.checkUpdateAvailable();
};

smf_AdminIndex.prototype.setAnnouncements = function ()
{
	if (!('smfAnnouncements' in window) || !('length' in window.smfAnnouncements))
		return;

	var sMessages = '', i;
	for (i = 0; i < window.smfAnnouncements.length; i++)
		sMessages += this.opt.sAnnouncementMessageTemplate.replace('%href%', window.smfAnnouncements[i].href).replace('%subject%', window.smfAnnouncements[i].subject).replace('%time%', window.smfAnnouncements[i].time).replace('%message%', window.smfAnnouncements[i].message);

	$('#' + this.opt.sAnnouncementContainerId).html(this.opt.sAnnouncementTemplate.replace('%content%', sMessages));
};

smf_AdminIndex.prototype.showCurrentVersion = function ()
{
	if (!('smfVersion' in window))
		return;

	$('#' + this.opt.sWedgeVersionContainerId).html(window.smfVersion);

	var
		oYourVersionContainer = $('#' + this.opt.sYourVersionContainerId),
		sCurrentVersion = oYourVersionContainer.html();

	if (sCurrentVersion != window.smfVersion)
		oYourVersionContainer.html(this.opt.sVersionOutdatedTemplate.replace('%currentVersion%', sCurrentVersion));
};

smf_AdminIndex.prototype.checkUpdateAvailable = function ()
{
	if (!('smfUpdatePackage' in window))
		return;

	// Are we setting a custom title and message?
	var
		sTitle = 'smfUpdateTitle' in window ? window.smfUpdateTitle : this.opt.sUpdateNotificationDefaultTitle,
		sMessage = 'smfUpdateNotice' in window ? window.smfUpdateNotice : this.opt.sUpdateNotificationDefaultMessage;

	$('#update_title').html(sTitle);
	$('#update_message').html(sMessage);
	$('#update_section').show();

	// Parse in the package download URL if it exists in the string.
	$('#update-link').attr('href', this.opt.sUpdateNotificationLink.replace('%package%', window.smfUpdatePackage));

	// Is it a critical update? Then make it more visible.
	if ('smfUpdateCritical' in window)
		$('#update_title').css({ color: '#ffcc99', fontSize: '1.2em' });
};



function smf_ViewVersions(oOptions)
{
	this.opt = oOptions;
	this.oSwaps = {};
	this.determineVersions();
};

smf_ViewVersions.prototype.swapOption = function (oSendingElement, sName)
{
	// If it is undefined, or currently off, turn it on - otherwise off.
	this.oSwaps[sName] = !(sName in this.oSwaps) || !this.oSwaps[sName];
	$('#' + sName).toggle(this.oSwaps[sName]);

	// Unselect the link and return false.
	oSendingElement.blur();
	return false;
};

smf_ViewVersions.prototype.compareVersions = function (sCurrent, sTarget)
{
	var
		aVersions = aParts = [],
		aCompare = [sCurrent, sTarget],
		i, sClean;

	for (i = 0; i < 2; i++)
	{
		// Clean the version and extract the version parts.
		sClean = aCompare[i].toLowerCase().replace(/ /g, '');
		aParts = sClean.match(/(\d+)(?:\.(\d+|))?(?:\.)?(\d+|)(?:(alpha|beta|rc)(\d+|)(?:\.)?(\d+|))?(?:(dev))?(\d+|)/);

		// No matches?
		if (aParts == null)
			return false;

		// Build an array of parts.
		aVersions[i] = [
			aParts[1] > 0 ? parseInt(aParts[1]) : 0,
			aParts[2] > 0 ? parseInt(aParts[2]) : 0,
			aParts[3] > 0 ? parseInt(aParts[3]) : 0,
			typeof aParts[4] == 'undefined' ? 'stable' : aParts[4],
			aParts[5] > 0 ? parseInt(aParts[5]) : 0,
			aParts[6] > 0 ? parseInt(aParts[6]) : 0,
			typeof aParts[7] != 'undefined',
		];
	}

	// Loop through each category.
	for (i = 0; i < 7; i++)
	{
		// Is there something for us to calculate?
		if (aVersions[0][i] != aVersions[1][i])
		{
			// Dev builds are a problematic exception.
			// (stable) dev < (stable) but (unstable) dev = (unstable)
			if (i == 3)
				return aVersions[0][i] < aVersions[1][i] ? !aVersions[1][6] : aVersions[0][6];
			else if (i == 6)
				return aVersions[0][6] ? aVersions[1][3] == 'stable' : false;
			// Otherwise a simple comparison.
			else
				return aVersions[0][i] < aVersions[1][i];
		}
	}

	// They are the same!
	return false;
}

smf_ViewVersions.prototype.determineVersions = function ()
{
	var
		oHighYour = {
			Sources: '??',
			Default: '??',
			Languages: '??',
			Templates: '??'
		},
		oHighCurrent = {
			Sources: '??',
			Default: '??',
			Languages: '??',
			Templates: '??'
		},
		oLowVersion = {
			Sources: false,
			Default: false,
			Languages: false,
			Templates: false
		},
		sSections = [
			'Sources',
			'Default',
			'Languages',
			'Templates'
		],
		i, sFilename, sYourVersion, sVersionType;

	for (i = 0, n = sSections.length; i < n; i++)
	{
		// Collapse all sections.
		$('#' + sSections[i]).hide();

		// Make all section links clickable.
		$('#' + sSections[i] + '-link').each(function () {
			this.instanceRef = this;
			this.sSection = sSections[i];
			this.onclick = function () {
				this.instanceRef.swapOption(this, this.sSection);
				return false;
			};
		});
	}

	if (!('smfVersions' in window))
		window.smfVersions = {};

	for (sFilename in window.smfVersions)
	{
		if (!$('#current' + sFilename).length)
			continue;

		sYourVersion = $('#your' + sFilename).html(), sCurVersionType;

		for (sVersionType in oLowVersion)
			if (sFilename.substr(0, sVersionType.length) == sVersionType)
			{
				sCurVersionType = sVersionType;
				break;
			}

		if (typeof sCurVersionType != 'undefined')
		{
			if ((this.compareVersions(oHighYour[sCurVersionType], sYourVersion) || oHighYour[sCurVersionType] == '??') && !oLowVersion[sCurVersionType])
				oHighYour[sCurVersionType] = sYourVersion;
			if (this.compareVersions(oHighCurrent[sCurVersionType], smfVersions[sFilename]) || oHighCurrent[sCurVersionType] == '??')
				oHighCurrent[sCurVersionType] = smfVersions[sFilename];

			if (this.compareVersions(sYourVersion, smfVersions[sFilename]))
			{
				oLowVersion[sCurVersionType] = sYourVersion;
				$('#your' + sFilename).css('color', 'red');
			}
		}
		else if (this.compareVersions(sYourVersion, smfVersions[sFilename]))
			oLowVersion[sCurVersionType] = sYourVersion;

		$('#current' + sFilename).html(smfVersions[sFilename]);
		$('#your' + sFilename).html(sYourVersion);
	}

	if (!('smfLanguageVersions' in window))
		window.smfLanguageVersions = {};

	for (sFilename in window.smfLanguageVersions)
	{
		for (i = 0; i < this.opt.aKnownLanguages.length; i++)
		{
			if ($('#current' + sFilename + this.opt.aKnownLanguages[i]).html(smfLanguageVersions[sFilename]).length)
				continue;

			sYourVersion = $('#your' + sFilename + this.opt.aKnownLanguages[i]).html();

			if ((this.compareVersions(oHighYour.Languages, sYourVersion) || oHighYour.Languages == '??') && !oLowVersion.Languages)
				oHighYour.Languages = sYourVersion;
			if (this.compareVersions(oHighCurrent.Languages, smfLanguageVersions[sFilename]) || oHighCurrent.Languages == '??')
				oHighCurrent.Languages = smfLanguageVersions[sFilename];

			if (this.compareVersions(sYourVersion, smfLanguageVersions[sFilename]))
			{
				oLowVersion.Languages = sYourVersion;
				$('#your' + sFilename + this.opt.aKnownLanguages[i]).css('color', 'red');
			}
		}
	}

	$('#yourSources').html(oLowVersion.Sources ? oLowVersion.Sources : oHighYour.Sources);
	$('#currentSources').html(oHighCurrent.Sources);
	if (oLowVersion.Sources)
		$('#yourSources').css('color', 'red');

	$('#yourDefault').html(oLowVersion.Default ? oLowVersion.Default : oHighYour.Default);
	$('#currentDefault').html(oHighCurrent.Default);
	if (oLowVersion.Default)
		$('#yourDefault').css('color', 'red');

	if ($('#Templates').length)
	{
		$('#yourTemplates').html(oLowVersion.Templates ? oLowVersion.Templates : oHighYour.Templates);
		$('#currentTemplates').html(oHighCurrent.Templates);
		if (oLowVersion.Templates)
			$('#yourTemplates').css('color', 'red');
	}

	$('#yourLanguages').html(oLowVersion.Languages ? oLowVersion.Languages : oHighYour.Languages);
	$('#currentLanguages').html(oHighCurrent.Languages);
	if (oLowVersion.Languages)
		$('#yourLanguages').css('color', 'red');
};
