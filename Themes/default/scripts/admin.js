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

	document.getElementById(this.opt.sAnnouncementContainerId).innerHTML = this.opt.sAnnouncementTemplate.replace('%content%', sMessages);
};

smf_AdminIndex.prototype.showCurrentVersion = function ()
{
	if (!('smfVersion' in window))
		return;

	var oSmfVersionContainer = document.getElementById(this.opt.sSmfVersionContainerId);
	var oYourVersionContainer = document.getElementById(this.opt.sYourVersionContainerId);

	oSmfVersionContainer.innerHTML = window.smfVersion;

	var sCurrentVersion = oYourVersionContainer.innerHTML;
	if (sCurrentVersion != window.smfVersion)
		oYourVersionContainer.innerHTML = this.opt.sVersionOutdatedTemplate.replace('%currentVersion%', sCurrentVersion);
};

smf_AdminIndex.prototype.checkUpdateAvailable = function ()
{
	if (!('smfUpdatePackage' in window))
		return;

	var oContainer = document.getElementById(this.opt.sUpdateNotificationContainerId);

	// Are we setting a custom title and message?
	var sTitle = 'smfUpdateTitle' in window ? window.smfUpdateTitle : this.opt.sUpdateNotificationDefaultTitle;
	var sMessage = 'smfUpdateNotice' in window ? window.smfUpdateNotice : this.opt.sUpdateNotificationDefaultMessage;

	oContainer.innerHTML = this.opt.sUpdateNotificationTemplate.replace('%title%', sTitle).replace('%message%', sMessage);

	// Parse in the package download URL if it exists in the string.
	document.getElementById('update-link').href = this.opt.sUpdateNotificationLink.replace('%package%', window.smfUpdatePackage);

	// If we decide to override life into "red" mode, do it.
	if ('smfUpdateCritical' in window)
	{
		document.getElementById('update_table').style.backgroundColor = '#aa2222';
		document.getElementById('update_title').style.backgroundColor = '#dd2222';
		document.getElementById('update_title').style.color = 'white';
		document.getElementById('update_message').style.backgroundColor = '#eebbbb';
		document.getElementById('update_message').style.color = 'black';
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
	document.getElementById(sName).style.display = this.oSwaps[sName] ? '' : 'none';

	// Unselect the link and return false.
	oSendingElement.blur();
	return false;
};

smf_ViewVersions.prototype.compareVersions = function (sCurrent, sTarget)
{
	// Are they equal, maybe?
	if (sCurrent == sTarget)
		return false;

	var aCurrentVersion = sCurrent.split('.');
	var aTargetVersion = sTarget.split('.');

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

		var aCurrentDev = null;
		var aTargetDev = null;

		if (aCurrentVersion[i].indexOf('Beta') != -1 || aCurrentVersion[i].indexOf('RC') != -1)
			aCurrentDev = aCurrentVersion[i].match(/(\d+)\s*(Beta|RC)\s*(\d+)/);
		if (aTargetVersion[i].indexOf('Beta') != -1 || aTargetVersion[i].indexOf('RC') != -1)
			aTargetDev = aTargetVersion[i].match(/(\d+)\s*(Beta|RC)\s*(\d+)/);

		// Did we get a dev version? This is bad...
		if (aCurrentDev != null || aTargetDev != null)
		{
			if (aCurrentDev == null)
				return (parseInt(aCurrentVersion[i], 10) < parseInt(aTargetDev[1], 10));
			else if (aTargetDev == null)
				return (parseInt(aCurrentDev[1], 10) <= parseInt(aTargetVersion[i], 10));
			else if (aCurrentDev[1] != aTargetDev[1])
				return (parseInt(aCurrentDev[1], 10) < parseInt(aTargetDev[1], 10));
			else if (aCurrentDev[2] != aTargetDev[2])
				return (aTargetDev[2] == 'RC');
			else
				return (parseInt(aCurrentDev[3], 10) < parseInt(aTargetDev[3], 10));
		}
		// Otherwise a simple comparison...
		else
			return (parseInt(aCurrentVersion[i], 10) < parseInt(aTargetVersion[i], 10));
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
		var oSection = document.getElementById(sSections[i]);
		if (typeof oSection == 'object' && oSection != null)
			oSection.style.display = 'none';

		// Make all section links clickable.
		var oSectionLink = document.getElementById(sSections[i] + '-link');
		if (typeof oSectionLink == 'object' && oSectionLink != null)
		{
			oSectionLink.instanceRef = this;
			oSectionLink.sSection = sSections[i];
			oSectionLink.onclick = function () {
				this.instanceRef.swapOption(this, this.sSection);
				return false;
			};
		}
	}

	if (!('smfVersions' in window))
		window.smfVersions = {};

	for (var sFilename in window.smfVersions)
	{
		if (!document.getElementById('current' + sFilename))
			continue;

		var sYourVersion = document.getElementById('your' + sFilename).innerHTML;

		var sCurVersionType;
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
				document.getElementById('your' + sFilename).style.color = 'red';
			}
		}
		else if (this.compareVersions(sYourVersion, smfVersions[sFilename]))
			oLowVersion[sCurVersionType] = sYourVersion;

		document.getElementById('current' + sFilename).innerHTML = smfVersions[sFilename];
		document.getElementById('your' + sFilename).innerHTML = sYourVersion;
	}

	if (!('smfLanguageVersions' in window))
		window.smfLanguageVersions = {};

	for (sFilename in window.smfLanguageVersions)
	{
		for (i = 0; i < this.opt.aKnownLanguages.length; i++)
		{
			if (!document.getElementById('current' + sFilename + this.opt.aKnownLanguages[i]))
				continue;

			document.getElementById('current' + sFilename + this.opt.aKnownLanguages[i]).innerHTML = smfLanguageVersions[sFilename];

			sYourVersion = document.getElementById('your' + sFilename + this.opt.aKnownLanguages[i]).innerHTML;
			document.getElementById('your' + sFilename + this.opt.aKnownLanguages[i]).innerHTML = sYourVersion;

			if ((this.compareVersions(oHighYour.Languages, sYourVersion) || oHighYour.Languages == '??') && !oLowVersion.Languages)
				oHighYour.Languages = sYourVersion;
			if (this.compareVersions(oHighCurrent.Languages, smfLanguageVersions[sFilename]) || oHighCurrent.Languages == '??')
				oHighCurrent.Languages = smfLanguageVersions[sFilename];

			if (this.compareVersions(sYourVersion, smfLanguageVersions[sFilename]))
			{
				oLowVersion.Languages = sYourVersion;
				document.getElementById('your' + sFilename + this.opt.aKnownLanguages[i]).style.color = 'red';
			}
		}
	}

	document.getElementById('yourSources').innerHTML = oLowVersion.Sources ? oLowVersion.Sources : oHighYour.Sources;
	document.getElementById('currentSources').innerHTML = oHighCurrent.Sources;
	if (oLowVersion.Sources)
		document.getElementById('yourSources').style.color = 'red';

	document.getElementById('yourDefault').innerHTML = oLowVersion.Default ? oLowVersion.Default : oHighYour.Default;
	document.getElementById('currentDefault').innerHTML = oHighCurrent.Default;
	if (oLowVersion.Default)
		document.getElementById('yourDefault').style.color = 'red';

	if (document.getElementById('Templates'))
	{
		document.getElementById('yourTemplates').innerHTML = oLowVersion.Templates ? oLowVersion.Templates : oHighYour.Templates;
		document.getElementById('currentTemplates').innerHTML = oHighCurrent.Templates;

		if (oLowVersion.Templates)
			document.getElementById('yourTemplates').style.color = 'red';
	}

	document.getElementById('yourLanguages').innerHTML = oLowVersion.Languages ? oLowVersion.Languages : oHighYour.Languages;
	document.getElementById('currentLanguages').innerHTML = oHighCurrent.Languages;
	if (oLowVersion.Languages)
		document.getElementById('yourLanguages').style.color = 'red';
};
