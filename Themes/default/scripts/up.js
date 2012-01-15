/*!
 * Wedge
 *
 * Mass uploader code for Wedge. Handler for uploading
 * files using Yahoo UI's Uploader module.
 * Uses portions written by Shitiz Garg.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

var Yup = {

	init: function(areas)
	{
		YUI = YAHOO.util;
		Yup.currentProgress = $('#current_progress');
		Yup.currentProgressText = $('#current_title');
		Yup.currentProgressText2 = $('#current_text');
		Yup.currentProgressText3 = $('#current_prog_perc');
		Yup.overallProgress = $('#overall_progress');
		Yup.overallProgressText = $('#overall_title');
		Yup.overallProgressText2 = $('#overall_prog_perc');
		Yup.list = $('#current_list');
		Yup.browseBtn = $('#browseBtn');
		Yup.php_limit = areas.php_limit;
		Yup.quotas = areas.quotas;
		Yup.txt = areas.text;
		Yup.tabindex = 0;
		Yup.overAllTotal = 0;
		Yup.overAllProg = 0;
		Yup.tempAllProg = 0;
		Yup.currProg = 0;
		Yup.curOverallText = Yup.overallProgressText.html();
		Yup.postURL = areas.postURL;
		Yup.files = [];
		Yup.lastDone = 0;
		Yup.done = [];
		Yup.fileFilters = areas.filters;
		$('#upload').click(function () { Yup.startNext(); return false; });
		$('#browse').css({
			width: Yup.browseBtn.css('width'),
			height: Yup.browseBtn.css('height')
		});

		YAHOO.widget.Uploader.SWFURL = areas.swfurl;
		Yup.uploader = new YAHOO.widget.Uploader('browse');

		Yup.uploader.addListener('contentReady', Yup.onContentReady);
	},

	onContentReady: function()
	{
		var ul = Yup.uploader;
		ul.setAllowMultipleFiles(true);
		ul.setFileFilters(Yup.fileFilters);
		ul.addListener('fileSelect', Yup.onFilesSelect);
		ul.addListener('uploadError', Yup.onUploadError);
		ul.addListener('uploadStart', Yup.onUploadStart);
		ul.addListener('uploadProgress', Yup.onUploadProgress);
		ul.addListener('uploadCompleteData', Yup.onCompleteData);

		if (is_webkit)
			$('#mu_container').css('textAlign', '');
	},

	setProg: function(which, prog)
	{
		Yup[which == 'overall' ? 'overallProgress' : 'currentProgress'].css('backgroundPosition', (100 - prog) + '%');
		Yup[which == 'current' ? 'currentProgressText3' : 'overallProgressText2'].html(prog + '%');
	},

	setOverallTotal: function(toIncrement)
	{
		Yup.overAllTotal += toIncrement;
		Yup.overallProgressText.html(Yup.curOverallText + ' (' + Yup.bytesToSize(Yup.overAllTotal) + ')');
	},

	setOverallProg: function(toProg)
	{
		Yup.setProg('overall', Math.round(((toProg + Yup.overAllProg) / Yup.overAllTotal) * 100));
	},

	bytesToSize: function (size)
	{
		if (size < 1024)
			return size + ' ' + Yup.txt.bytes;
		else if (size > 1024 && size < (1024 * 1024))
			return Math.round(size / 1024) + ' ' + Yup.txt.kb;
		else
			return Math.round(size / (1024 * 1024)) + ' ' + Yup.txt.mb;
	},

	onFilesSelect: function(event)
	{
		$('#remove_me').remove();
		var sorter = $('#sort_order').val(), myFiles = [];

		for (var i in event.fileList)
			if (!document.getElementById(i))
				myFiles.push([i, event.fileList[i].name.toLowerCase(), event.fileList[i].mDate.getTime(), event.fileList[i].size]);

		myFiles.sort(function (a, b) {
			return a[sorter] < b[sorter] ? -1 : (a[sorter] > b[sorter] ? 1 : 0);
		});

		for (i in myFiles)
		{
			var file = event.fileList[myFiles[i][0]];
			var exten = file.name.substr(file.name.lastIndexOf('.') + 1, file.name.length), err = '';

			if (file.size >  Yup.php_limit)
				err = 'tl_php';
			else if (file.size > Yup.quotas[exten] * 1024 && Yup.fileFilters[0].extensions.indexOf(exten) > 0)
				err = 'tl_img';
			else if (file.size > Yup.quotas[exten] * 1024)
				err = 'tl_quota';

			var mainEl = $('<li id="' + file.id + '" class="file"></li>')
				.append($('<span class="file-size"></span>').html(Yup.bytesToSize(file.size)))
				.append($('<a href="javascript:Yup.removeFile(\'' + file.id + '\');" id="rem_' + file.id + '" class="file-remove"></a>').html(Yup.txt.cancel))
				.append($('<span class="file-name"></span>').html(file.name));

			if (err != '')
				mainEl.append($('<div></div>').addClass(err == 'tl_img' ? 'file-warning' : 'file-error').html(Yup.txt[err]));

			if (err == '' || err == 'tl_img')
			{
				Yup.setOverallTotal(file.size);
				Yup.files.push(file);
			}

			Yup.list.append(mainEl);
		}
	},

	onUploadStart: function(event)
	{
		var file = Yup.files[Yup.lastDone];
		$('#' + file.id).addClass('file-uploading');
		Yup.currentProgressText2.html(file.name);
		Yup.currProg = 0;
	},

	onUploadError: function(event)
	{
		var err = 'The Flash upload module sent the following error.<br><br>Error type: ' + event.type + '<br>Error ID: ' + event.id + '<br><br>Error message: ' + event.status + '<br><br>';
		try
		{
			$('#' + Yup.files[Yup.lastDone].id)
				.append($('<div class="file-error"></div>').html(err))
				.removeClass('file-success')
				.addClass('file-failed');
		}
		catch (e)
		{
			alert(err.replace(/<br>/g, "\n"));
		}
	},

	onCompleteData: function(event)
	{
		if (typeof Yup.files[Yup.lastDone] == 'undefined')
			return false;

		Yup.overAllProg += Yup.tempAllProg;
		Yup.tempAllProg = 0;
		var file = Yup.files[Yup.lastDone], element = $('#' + file.id);

		if (!YUI.Dom.get('submit_title_update'))
			$('<input type="hidden" id="submit_title_update" name="submit_title_update" value="dummy"></input>').appendTo(Yup.list);

		var
			ret = event.data.split('|'),
			items = ret[0].split(';'),
			errors = ret[1].split(';');

		element
			.removeClass('file-uploading')
			.addClass('file-success');

		$('#rem_' + file.id).hide();

		if (typeof items == 'object')
		{
			for (i = 0; i < items.length; i++)
			{
				var this_ret = items[i].split(','), mid;

				if (this_ret[0].length == 0 || this_ret[0] == '')
					continue;
				mid = this_ret[0].match(/\d+/);
				if (mid == null)
					continue;

				$('<li class="succ_item"></li>')
					.append($('<img src="' + we_prepareScriptUrl() + 'action=media;sa=media;in=' + mid + ';thumb" class="right">'))
					.append($('<input type="text" name="item_title_' + mid + '" tabindex="' + (Yup.tabindex * 2) + '"></input>').val(this_ret[1]))
					.append($('<textarea rows="5" name="item_desc_' + mid + '" tabindex="' + (Yup.tabindex * 2 + 1) + '"></textarea>'))
					.appendTo(element);

				$('#mu_items input').show();
			}
		}

		if (typeof errors == 'object')
		{
			for (i = 0; i < errors.length; i++)
			{
				if (!errors[i] || errors[i].length == 0)
					continue;

				element
					.append($('<div class="file-error"></div>').html(errors[i]))
					.removeClass('file-success')
					.addClass('file-failed');
			}
		}

		Yup.done.push(file.id);
		Yup.tabindex++;
		Yup.lastDone++;

		Yup.startNext();

		return true;
	},

	onUploadProgress: function(event)
	{
		var prog = Math.round((event.bytesLoaded / event.bytesTotal) * 100);
		Yup.setProg('current', prog);
		Yup.currProg += prog;
		Yup.tempAllProg = event.bytesLoaded;
		Yup.setOverallProg(event.bytesLoaded);
	},

	startNext: function()
	{
		if (Yup.files.length != Yup.lastDone && typeof Yup.files[Yup.lastDone] != 'undefined')
			Yup.uploader.upload(Yup.files[Yup.lastDone].id, Yup.postURL, 'POST', null, 'Filedata', {});
	},

	removeFile: function(file_id)
	{
		Yup.uploader.removeFile(file_id);
		$('#' + file_id).hide();

		var file_key = 0, i;
		for (i in Yup.files)
			if (Yup.files[i].id == file_id)
				file_key = i;

		if (typeof Yup.files[file_key] != 'undefined')
		{
			Yup.setOverallTotal(Yup.files[file_key].size * -1);
			delete Yup.files[file_key];
		}

		var files = Yup.files, inc = 0;
		Yup.files = [];
		for (i in files)
			Yup.files[inc++] = files[i];
	}
};
