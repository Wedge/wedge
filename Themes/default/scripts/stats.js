function smf_StatsCenter(oOptions)
{
	this.oTable = $('#' + this.opt.sTableId);

	// Is the table actually present?
	if (!this.oTable.length)
		return;

	this.opt = oOptions;
	this.oYears = {};
	this.bIsLoading = false;

	// Find all months and years defined in the table.
	var aResults = [], sYearId = null, oCurYear = null, sMonthId = null, oCurMonth = null, opt = oOptions;

	$('tr', this.oTable).data('that', this).each(function () {
		var that = $(this).data('that');
		// Check if the current row represents a year.
		if ((aResults = opt.reYearPattern.exec(this.id)) != null)
		{
			// The id is part of the pattern match.
			sYearId = aResults[1];

			// Setup the object that'll have the state information of the year.
			that.oYears[sYearId] = {
				oCollapseImage: document.getElementById(opt.sYearImageIdPrefix + sYearId),
				oMonths: {}
			};

			// Create a shortcut, makes things more readable.
			oCurYear = that.oYears[sYearId];

			// Use the collapse image to determine the current state.
			oCurYear.bIsCollapsed = oCurYear.oCollapseImage.src.indexOf(opt.sYearImageCollapsed) >= 0;

			// Setup the toggle element for the year.
			oCurYear.oToggle = new smc_Toggle({
				bToggleEnabled: true,
				bCurrentlyCollapsed: oCurYear.bIsCollapsed,
				instanceRef: that,
				sYearId: sYearId,
				funcOnBeforeCollapse: function () {
					opt.instanceRef.onBeforeCollapseYear(that);
				},
				aSwappableContainers: [
				],
				aSwapImages: [
					{
						sId: opt.sYearImageIdPrefix + sYearId,
						srcExpanded: smf_images_url + '/' + opt.sYearImageExpanded,
						altExpanded: '-',
						srcCollapsed: smf_images_url + '/' + opt.sYearImageCollapsed,
						altCollapsed: '+'
					}
				],
				aSwapLinks: [
					{
						sId: opt.sYearLinkIdPrefix + sYearId,
						msgExpanded: sYearId,
						msgCollapsed: sYearId
					}
				]
			});
		}

		// Or maybe the current row represents a month.
		else if ((aResults = opt.reMonthPattern.exec(this.id)) != null)
		{
			// Set the id to the matched pattern.
			sMonthId = aResults[1];

			// Initialize the month as a child object of the year.
			oCurYear.oMonths[sMonthId] = {
				oCollapseImage: document.getElementById(opt.sMonthImageIdPrefix + sMonthId)
			};

			// Create a shortcut to the current month.
			oCurMonth = oCurYear.oMonths[sMonthId];

			// Determine whether the month is currently collapsed or expanded..
			oCurMonth.bIsCollapsed = oCurMonth.oCollapseImage.src.indexOf(opt.sMonthImageCollapsed) >= 0;

			var sLinkText = $('#' + opt.sMonthLinkIdPrefix + sMonthId).html();

			// Setup the toggle element for the month.
			oCurMonth.oToggle = new smc_Toggle({
				bToggleEnabled: true,
				bCurrentlyCollapsed: oCurMonth.bIsCollapsed,
				instanceRef: that,
				sMonthId: sMonthId,
				funcOnBeforeCollapse: function () {
					opt.instanceRef.onBeforeCollapseMonth(that);
				},
				funcOnBeforeExpand: function () {
					opt.instanceRef.onBeforeExpandMonth(that);
				},
				aSwappableContainers: [
				],
				aSwapImages: [
					{
						sId: opt.sMonthImageIdPrefix + sMonthId,
						srcExpanded: smf_images_url + '/' + opt.sMonthImageExpanded,
						altExpanded: '-',
						srcCollapsed: smf_images_url + '/' + opt.sMonthImageCollapsed,
						altCollapsed: '+'
					}
				],
				aSwapLinks: [
					{
						sId: opt.sMonthLinkIdPrefix + sMonthId,
						msgExpanded: sLinkText,
						msgCollapsed: sLinkText
					}
				]
			});

			oCurYear.oToggle.opt.aSwappableContainers[oCurYear.oToggle.opt.aSwappableContainers.length] = this.id;
		}

		else if ((aResults = opt.reDayPattern.exec(this.id)) != null)
		{
			oCurMonth.oToggle.opt.aSwappableContainers[oCurMonth.oToggle.opt.aSwappableContainers.length] = this.id;
			oCurYear.oToggle.opt.aSwappableContainers[oCurYear.oToggle.opt.aSwappableContainers.length] = this.id;
		}
	});

	// Collapse all collapsed years!
	for (var i = 0; i < opt.aCollapsedYears.length; i++)
		this.oYears[this.opt.aCollapsedYears[i]].oToggle.toggle();
};

smf_StatsCenter.prototype.onBeforeCollapseYear = function (oToggle)
{
	// Tell SMF that all underlying months have disappeared.
	var omon = this.oYears[oToggle.opt.sYearId].oMonths;
	for (var sMonth in omon)
		if (omon[sMonth].oToggle.opt.aSwappableContainers.length > 0)
			omon[sMonth].oToggle.changeState(true);
};

smf_StatsCenter.prototype.onBeforeCollapseMonth = function (oToggle)
{
	if (!oToggle.bCollapsed)
	{
		// Tell SMF that it the state has changed.
		getXMLDocument(smf_prepareScriptUrl(smf_scripturl) + 'action=stats;collapse=' + oToggle.opt.sMonthId + ';xml');

		// Remove the month rows from the year toggle.
		var aNewContainers = [];
		var oYearToggle = this.oYears[oToggle.opt.sMonthId.substr(0, 4)].oToggle;

		for (var i = 0, n = oYearToggle.opt.aSwappableContainers.length; i < n; i++)
			if (!in_array(oYearToggle.opt.aSwappableContainers[i], oToggle.opt.aSwappableContainers))
				aNewContainers[aNewContainers.length] = oYearToggle.opt.aSwappableContainers[i];

		oYearToggle.opt.aSwappableContainers = aNewContainers;
	}
};

smf_StatsCenter.prototype.onBeforeExpandMonth = function (oToggle)
{
	// Ignore if we're still loading the previous batch.
	if (this.bIsLoading)
		return;

	if (oToggle.opt.aSwappableContainers.length == 0)
	{
		if ('ajax_indicator' in window)
			ajax_indicator(true);
		this.oXmlRequestHandle = getXMLDocument.call(this, smf_prepareScriptUrl(smf_scripturl) + 'action=stats;expand=' + oToggle.opt.sMonthId + ';xml', this.onDocReceived);
		this.bIsLoading = true;
	}
	// Silently let Wedge know this one is expanded.
	else
		getXMLDocument(smf_prepareScriptUrl(smf_scripturl) + 'action=stats;expand=' + oToggle.opt.sMonthId + ';xml');
};

smf_StatsCenter.prototype.onDocReceived = function (oXMLDoc)
{
	// Loop through all the months we got from the XML.
	var aMonthNodes = oXMLDoc.getElementsByTagName('month');
	for (var iMonthIndex = 0, iNumMonths = aMonthNodes.length; iMonthIndex < iNumMonths; iMonthIndex++)
	{
		var sMonthId = aMonthNodes[iMonthIndex].getAttribute('id');
		var iStart = document.getElementById('tr_month_' + sMonthId).rowIndex + 1;
		var sYearId = sMonthId.substr(0, 4);

		// Within the current months, check out all the days.
		var aDayNodes = aMonthNodes[iMonthIndex].getElementsByTagName('day');
		for (var iDayIndex = 0, iNumDays = aDayNodes.length; iDayIndex < iNumDays; iDayIndex++)
		{
			var oCurRow = this.oTable.insertRow(iStart + iDayIndex);
			oCurRow.className = this.opt.sDayRowClassname;
			oCurRow.id = this.opt.sDayRowIdPrefix + aDayNodes[iDayIndex].getAttribute('date');

			for (var iCellIndex = 0, iNumCells = this.opt.aDataCells.length; iCellIndex < iNumCells; iCellIndex++)
			{
				var oCurCell = oCurRow.insertCell(-1);

				if (this.opt.aDataCells[iCellIndex] == 'date')
				{
					oCurCell.style.paddingLeft = '6ex';
					oCurCell.style.textAlign = 'left';
				}
				else
					oCurCell.style.textAlign = 'center';

				var sCurData = aDayNodes[iDayIndex].getAttribute(this.opt.aDataCells[iCellIndex]);
				oCurCell.appendChild(document.createTextNode(sCurData));
			}

			// Add these day rows to the toggle objects in case of collapse.
			this.oYears[sYearId].oMonths[sMonthId].oToggle.opt.aSwappableContainers[this.oYears[sYearId].oMonths[sMonthId].oToggle.opt.aSwappableContainers.length] = oCurRow.id;
			this.oYears[sYearId].oToggle.opt.aSwappableContainers[this.oYears[sYearId].oToggle.opt.aSwappableContainers.length] = oCurRow.id;
		}
	}

	this.bIsLoading = false;
	ajax_indicator(false);
};
