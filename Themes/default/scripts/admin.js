/*!
 * Wedge
 *
 * we_AdminIndex contains general-purpose helper functions for the admin area
 * we_ViewVersions contains helper functions to help comparing version numbers in the admin area
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

/*
	we_AdminIndex(oOptions)
	{
		public setAnnouncements()
		public showCurrentVersion()
		public checkUpdateAvailable()
	}

	we_ViewVersions(oOptions)
	{
		public swapOption(oSendingElement, sName)
		public compareVersions(sCurrent, sTarget)
		public determineVersions()
	}
*/

// Handle the JavaScript surrounding the admin and moderation center.
function we_AdminIndex(oOptions)
{
	this.opt = oOptions;

	// Load the text box containing the latest news items.
	if (this.opt.bLoadAnnouncements)
		this.setAnnouncements();

	// Load the current Wedge and your Wedge version numbers.
	if (this.opt.bLoadVersions)
		this.showCurrentVersion();

	// Load the text box that says there's a new version available.
	if (this.opt.bLoadUpdateNotification)
		this.checkUpdateAvailable();
};

we_AdminIndex.prototype.setAnnouncements = function ()
{
	var opt = this.opt, sMessages = '', ann = window.wedge_news, i, j, k;
	var time_replace = function (str, va, nu) {
		if (va == 'month')
			return opt.sMonths[nu - 1];
		if (va == 'shortmonth')
			return opt.sMonthsShort[nu - 1];
		if (va == 'day')
			return opt.sMonths[nu];
		if (va == 'shortday')
			return opt.sMonthsShort[nu];
	};

	if (!('wedge_news' in window) || !('length' in ann))
		return;

	for (i = 0, k = ann.length; i < k; i++)
		sMessages += opt.sAnnouncementMessageTemplate
			.replace('%href%', ann[i].href)
			.replace('%subject%', ann[i].subject)
			.replace('%time%', ann[i].time.replace(/\$(shortmonth|month|shortday|day)-(\d+)/g, time_replace))
			.replace('%message%', ann[i].message);

	$('#' + opt.sAnnouncementContainerId).html(opt.sAnnouncementTemplate.replace('%content%', sMessages));
};

we_AdminIndex.prototype.showCurrentVersion = function ()
{
	if (!('weVersion' in window))
		return;

	$('#wedgeVersion').html(window.weVersion);

	var
		oYourVersionContainer = $('#yourVersion'),
		sCurrentVersion = oYourVersionContainer.html();

	if (sCurrentVersion != window.weVersion)
		oYourVersionContainer.wrap('<span class="alert"></span>');
};

we_AdminIndex.prototype.checkUpdateAvailable = function ()
{
	if (!('weUpdatePackage' in window))
		return;

	// Show custom or generic title and message.
	// If it's a critical update, make the title more visible.
	$('#update_title')
		.html(window.weUpdateTitle || this.opt.sUpdateTitle)
		.css('weUpdateCritical' in window ? { color: '#ffcc99', fontSize: '1.2em' } : {});
	$('#update_message')
		.html(window.weUpdateNotice || this.opt.sUpdateMessage);
	$('#update_section').show();

	// Parse in the package download URL if it exists in the string.
	$('#update-link').attr('href', this.opt.sUpdateLink.replace('%package%', window.weUpdatePackage));
};



function we_ViewVersions(oOptions)
{
	this.opt = oOptions;
	this.oSwaps = {};
	this.determineVersions();
};

we_ViewVersions.prototype.swapOption = function (oSendingElement, sName)
{
	// If it is undefined, or currently off, turn it on - otherwise off.
	this.oSwaps[sName] = !(sName in this.oSwaps) || !this.oSwaps[sName];
	$('#' + sName).toggle(this.oSwaps[sName]);

	// Unselect the link and return false.
	oSendingElement.blur();
	return false;
};

we_ViewVersions.prototype.compareVersions = function (sCurrent, sTarget)
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
};

we_ViewVersions.prototype.determineVersions = function ()
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
		that = this, i, n = sSections.length, sFilename,
		sYourVersion, sVersionType, sCurVersionType;

	for (i = 0; i < n; i++)
	{
		// Collapse all sections.
		$('#' + sSections[i]).hide();

		// Make all section links clickable.
		$('#' + sSections[i] + '-link').data('section', sSections[i]).click(function () {
			that.swapOption(this, $(this).data('section'));
			return false;
		});
	}

	if (!('weVersions' in window))
		window.weVersions = {};

	for (sFilename in window.weVersions)
	{
		var sID = sFilename.replace(/\./g, '\\.');
		if (!$('#current' + sID).length)
			continue;

		sYourVersion = $('#your' + sID).html();

		for (sVersionType in oLowVersion)
			if (sFilename.substr(0, sVersionType.length) == sVersionType)
			{
				sCurVersionType = sVersionType;
				break;
			}

		if (typeof sCurVersionType != 'undefined')
		{
			if ((oHighYour[sCurVersionType] == '??' || this.compareVersions(oHighYour[sCurVersionType], sYourVersion)) && !oLowVersion[sCurVersionType])
				oHighYour[sCurVersionType] = sYourVersion;
			if (oHighCurrent[sCurVersionType] == '??' || this.compareVersions(oHighCurrent[sCurVersionType], weVersions[sFilename]))
				oHighCurrent[sCurVersionType] = weVersions[sFilename];

			if (this.compareVersions(sYourVersion, weVersions[sFilename]))
			{
				oLowVersion[sCurVersionType] = sYourVersion;
				$('#your' + sID).css('color', 'red');
			}
		}
		else if (this.compareVersions(sYourVersion, weVersions[sFilename]))
			oLowVersion[sCurVersionType] = sYourVersion;

		$('#current' + sID).html(weVersions[sFilename]);
		$('#your' + sID).html(sYourVersion);
	}

	if (!('weLanguageVersions' in window))
		window.weLanguageVersions = {};

	for (sFilename in window.weLanguageVersions)
	{
		for (i = 0, n = this.opt.aKnownLanguages.length; i < n; i++)
		{
			if ($('#current' + sFilename + this.opt.aKnownLanguages[i]).html(weLanguageVersions[sFilename]).length)
				continue;

			sYourVersion = $('#your' + sFilename + this.opt.aKnownLanguages[i]).html();

			if ((this.compareVersions(oHighYour.Languages, sYourVersion) || oHighYour.Languages == '??') && !oLowVersion.Languages)
				oHighYour.Languages = sYourVersion;
			if (this.compareVersions(oHighCurrent.Languages, weLanguageVersions[sFilename]) || oHighCurrent.Languages == '??')
				oHighCurrent.Languages = weLanguageVersions[sFilename];

			if (this.compareVersions(sYourVersion, weLanguageVersions[sFilename]))
			{
				oLowVersion.Languages = sYourVersion;
				$('#your' + sFilename + this.opt.aKnownLanguages[i]).css('color', 'red');
			}
		}
	}

	$('#currentSources').html(oHighCurrent.Sources);
	$('#currentDefault').html(oHighCurrent.Default);
	$('#currentTemplates').html(oHighCurrent.Templates);
	$('#currentLanguages').html(oHighCurrent.Languages);

	oLowVersion.Sources ? $('#yourSources').html(oLowVersion.Sources).css('color', 'red') : $('#yourSources').html(oHighYour.Sources);
	oLowVersion.Default ? $('#yourDefault').html(oLowVersion.Default).css('color', 'red') : $('#yourDefault').html(oHighYour.Default);
	oLowVersion.Templates ? $('#yourTemplates').html(oLowVersion.Templates).css('color', 'red') : $('#yourTemplates').html(oHighYour.Templates);
	oLowVersion.Languages ? $('#yourLanguages').html(oLowVersion.Languages).css('color', 'red') : $('#yourLanguages').html(oHighYour.Languages);
};
