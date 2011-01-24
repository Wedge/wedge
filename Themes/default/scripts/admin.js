/*
	smf_AdminIndex(oOptions)
	{
		public init()
		public setAnnouncements()
		public showCurrentVersion()
		public checkUpdateAvailable()
	}

	smf_ViewVersions(oOptions)
	{
		public init()
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

	var sMessages = '';
	for (var i = 0; i < window.smfAnnouncements.length; i++)
		sMessages += this.opt.sAnnouncementMessageTemplate.replace('%href%', window.smfAnnouncements[i].href).replace('%subject%', window.smfAnnouncements[i].subject).replace('%time%', window.smfAnnouncements[i].time).replace('%message%', window.smfAnnouncements[i].message);

	$('#' + this.opt.sAnnouncementContainerId).html(this.opt.sAnnouncementTemplate.replace('%content%', sMessages));
};

smf_AdminIndex.prototype.showCurrentVersion = function ()
{
	if (!('smfVersion' in window))
		return;

	$('#' + this.opt.sSmfVersionContainerId).html(window.smfVersion);

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

	$('#' + this.opt.sUpdateNotificationContainerId).html(this.opt.sUpdateNotificationTemplate.replace('%title%', sTitle).replace('%message%', sMessage));

	// Parse in the package download URL if it exists in the string.
	$('#update-link').attr('href', this.opt.sUpdateNotificationLink.replace('%package%', window.smfUpdatePackage));

	// If we decide to override life into "red" mode, do it.
	if ('smfUpdateCritical' in window)
	{
		$('#update_table').css('backgroundColor', '#aa2222');
		$('#update_title').css({ backgroundColor: '#dd2222', color: 'white' });
		$('#update_message').css({ backgroundColor: '#eebbbb', color: 'black' });
	}
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
	// Are they equal, maybe?
	if (sCurrent == sTarget)
		return false;

	var aCurrentVersion = sCurrent.split('.'), aTargetVersion = sTarget.split('.');

	for (var i = 0, n = (aCurrentVersion.length > aTargetVersion.length ? aCurrentVersion.length : aTargetVersion.length); i < n; i++)
	{
		// Make sure both are set.
		if (typeof aCurrentVersion[i] == 'undefined')
			aCurrentVersion[i] = '0';
		else if (typeof aTargetVersion[i] == 'undefined')
			aTargetVersion[i] = '0';

		// If they are same, move to the next set.
		if (aCurrentVersion[i] == aTargetVersion[i])
			continue;

		var aCurrentDev = null, aTargetDev = null;

		if (aCurrentVersion[i].indexOf('Beta') != -1 || aCurrentVersion[i].indexOf('RC') != -1)
			aCurrentDev = aCurrentVersion[i].match(/(\d+)\s*(Beta|RC)\s*(\d+)/);
		if (aTargetVersion[i].indexOf('Beta') != -1 || aTargetVersion[i].indexOf('RC') != -1)
			aTargetDev = aTargetVersion[i].match(/(\d+)\s*(Beta|RC)\s*(\d+)/);

		// Did we get a dev version? This is bad...
		if (aCurrentDev != null || aTargetDev != null)
		{
			if (aCurrentDev == null)
				return parseInt(aCurrentVersion[i], 10) < parseInt(aTargetDev[1], 10);
			else if (aTargetDev == null)
				return parseInt(aCurrentDev[1], 10) <= parseInt(aTargetVersion[i], 10);
			else if (aCurrentDev[1] != aTargetDev[1])
				return parseInt(aCurrentDev[1], 10) < parseInt(aTargetDev[1], 10);
			else if (aCurrentDev[2] != aTargetDev[2])
				return aTargetDev[2] == 'RC';
			else
				return parseInt(aCurrentDev[3], 10) < parseInt(aTargetDev[3], 10);
		}
		// Otherwise a simple comparison...
		else
			return parseInt(aCurrentVersion[i], 10) < parseInt(aTargetVersion[i], 10);
	}

	return false;
};

smf_ViewVersions.prototype.determineVersions = function ()
{
	var oHighYour = {
		Sources: '??',
		Default: '??',
		Languages: '??',
		Templates: '??'
	};
	var oHighCurrent = {
		Sources: '??',
		Default: '??',
		Languages: '??',
		Templates: '??'
	};
	var oLowVersion = {
		Sources: false,
		Default: false,
		Languages: false,
		Templates: false
	};

	var sSections = [
		'Sources',
		'Default',
		'Languages',
		'Templates'
	];

	for (var i = 0, n = sSections.length; i < n; i++)
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

	for (var sFilename in window.smfVersions)
	{
		if (!$('#current' + sFilename).length)
			continue;

		var sYourVersion = $('#your' + sFilename).html(), sCurVersionType;

		for (var sVersionType in oLowVersion)
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
