// Wedge Media
// © wedge.org
// Yup.js
// Handler for uploading files using Yahoo UI Yup
//
// Users of this software are bound by the terms of the
// Wedge license. You can view it online at http://wedge.org/license/
//
// Support and updates for this software can be found at
// http://wedge.org

var Yup = {

	init: function(areas)
	{
		YUI = YAHOO.util;
		Yup.currentProgress = YUI.Dom.get(areas.cprog);
		Yup.currentProgressText = YUI.Dom.get(areas.cprogtxt);
		Yup.currentProgressText2 = YUI.Dom.get(areas.cprogtxt2);
		Yup.currentProgressText3 = YUI.Dom.get(areas.cprogtxt3);
		Yup.overallProgress = YUI.Dom.get(areas.oprog);
		Yup.overallProgressText = YUI.Dom.get(areas.oprogtxt);
		Yup.overallProgressText2 = YUI.Dom.get(areas.oprogtxt2);
		Yup.browse = YUI.Dom.get(areas.browse);
		Yup.upload = YUI.Dom.get(areas.upload);
		Yup.list = YUI.Dom.get(areas.list);
		Yup.submit = YUI.Dom.get(areas.submit);
		Yup.php_limit = areas.php_limit;
		Yup.quotas = areas.quotas;
		Yup.txt = areas.text;
		Yup.tabindex = 0;
		Yup.overAllTotal = 0;
		Yup.overAllProg = 0;
		Yup.tempAllProg = 0;
		Yup.currProg = 0;
		Yup.curOverallText = Yup.overallProgressText.innerHTML;
		Yup.browseBtn = YUI.Dom.getRegion(areas.browseBtn);
		Yup.upload.href = 'javascript:Yup.startNext();';
		Yup.postURL = areas.postURL;
		Yup.files = [];
		Yup.lastDone = 0;
		Yup.done = [];
		Yup.fileFilters = areas.filters;
		YUI.Dom.setStyle(Yup.browse, 'width', (Yup.browseBtn.right - Yup.browseBtn.left) + 'px');
		YUI.Dom.setStyle(Yup.browse, 'height', (Yup.browseBtn.bottom - Yup.browseBtn.top) + 'px');

		YAHOO.widget.Uploader.SWFURL = areas.swfurl;
		Yup.uploader = new YAHOO.widget.Uploader('browse');

		Yup.uploader.addListener('contentReady', Yup.onContentReady);
	},

	onContentReady: function()
	{
		Yup.uploader.setAllowMultipleFiles(true);
		Yup.uploader.setFileFilters(Yup.fileFilters);
		Yup.uploader.addListener('fileSelect', Yup.onFilesSelect);
		Yup.uploader.addListener('uploadError', Yup.onUploadError);
		Yup.uploader.addListener('uploadStart', Yup.onUploadStart);
		Yup.uploader.addListener('uploadProgress', Yup.onUploadProgress);
		Yup.uploader.addListener('uploadCompleteData', Yup.onCompleteData);

		if (is_safari || is_chrome)
			document.getElementById('mu_container').style.textAlign = '';
	},

	setProg: function(which, prog)
	{
		var toSet = which == 'overall' ? Yup.overallProgress : Yup.currentProgress;
		YUI.Dom.setStyle(toSet, 'backgroundPosition', (100 - prog) + '%');

		if (which == 'current')
			Yup.currentProgressText3.innerHTML = prog + '%';
		else
			Yup.overallProgressText2.innerHTML = prog + '%';
	},

	setOverallTotal: function(toIncrement)
	{
		Yup.overAllTotal += toIncrement;
		Yup.overallProgressText.innerHTML = Yup.curOverallText + ' (' + Yup.bytesToSize(Yup.overAllTotal) + ')';
	},

	setOverallProg: function(toProg)
	{
		var progPercent = Math.round(((toProg + Yup.overAllProg) / Yup.overAllTotal) * 100);
		Yup.setProg('overall', progPercent);
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

		var sorter = 1;
		var sort_select = document.getElementById('sort_order');
		if (sort_select != null)
			sorter = sort_select.value;

		var myFiles = [];
		for (var i = 0, n = event.fileList.length; i < n; i++)
		{
			var e = event.fileList[i];
			myFiles.push([i, e.name.toLowerCase(), e.mDate.getTime(), e.size]);
		}

		myFiles.sort(function (a, b) {
			return a[sorter] < b[sorter] ? -1 : (a[sorter] > b[sorter] ? 1 : 0);
		});

		for (i in myFiles)
		{
			var file = event.fileList[myFiles[i][0]];

			var fsize = document.createElement('span');
			YUI.Dom.addClass(fsize, 'file-size');
			fsize.innerHTML = Yup.bytesToSize(file.size);

			var exten = file.name.substr(file.name.lastIndexOf('.') + 1, file.name.length), err = '';
			if (file.size >  Yup.php_limit)
				err = 'tl_php';
			else if (file.size > Yup.quotas[exten] * 1024 && Yup.fileFilters[0].extensions.indexOf(exten) > 0)
				err = 'tl_img';
			else if (file.size > Yup.quotas[exten] * 1024)
				err = 'tl_quota';

			var rem = document.createElement('a');
			YUI.Dom.addClass(rem, 'file-remove');
			rem.href = 'javascript:Yup.removeFile(\'' + file.id + '\');';
			rem.innerHTML = Yup.txt.cancel;
			rem.id = 'rem_' + file.id;
			var name = document.createElement('span');
			YUI.Dom.addClass(name, 'file-name');
			name.innerHTML = file.name;

			var mainEl = document.createElement('li');
			YUI.Dom.addClass(mainEl, 'file');
			mainEl.id = file.id;

			mainEl.appendChild(fsize);
			mainEl.appendChild(rem);
			mainEl.appendChild(name);

			if (err != '')
			{
				var div = document.createElement('div');
				YUI.Dom.addClass(div, err == 'tl_img' ? 'file-warning' : 'file-error');
				div.innerHTML = Yup.txt[err];
				mainEl.appendChild(div);
			}
			if (err == '' || err == 'tl_img')
			{
				Yup.setOverallTotal(file.size);
				Yup.files.push(file);
			}

			Yup.list.appendChild(mainEl);
		}
	},

	onUploadStart: function(event)
	{
		var file = Yup.files[Yup.lastDone];
		var element = YUI.Dom.get(file.id);
		Yup.currentProgressText2.innerHTML = file.name;
		YUI.Dom.addClass(element, 'file-uploading');
		Yup.currProg = 0;
	},

	onUploadError: function(event)
	{
		var err = 'The Flash upload module sent the following error.<br /><br />Error type: ' + event.type + '<br />Error ID: ' + event.id + '<br /><br />Error message: ' + event.status + '<br /><br />';
		try
		{
			var file = Yup.files[Yup.lastDone];
			var element = YUI.Dom.get(file.id);
			var div = document.createElement('div');
			YUI.Dom.addClass(div, 'file-error');
			div.innerHTML = err;
			element.appendChild(div);
			YUI.Dom.removeClass(element, 'file-success');
			YUI.Dom.addClass(element, 'file-failed');
		}
		catch (e)
		{
			alert(err.replace(/<br \/>/g, "\n"));
		}
	},

	onCompleteData: function(event)
	{
		if (typeof(Yup.files[Yup.lastDone]) == 'undefined')
			return false;

		Yup.overAllProg += Yup.tempAllProg;
		Yup.tempAllProg = 0;
		var file = Yup.files[Yup.lastDone];
		var element = YUI.Dom.get(file.id);

		if (!YUI.Dom.get('submit_title_update'))
		{
			var sub = document.createElement('input');
			sub.type = 'hidden';
			sub.name = 'submit_title_update';
			sub.id = sub.name;
			sub.value = 'dummy';

			Yup.list.appendChild(sub);
		}

		var ret = event.data.split('|');
		var items = ret[0].split(';');
		var errors = ret[1].split(';');

		YUI.Dom.removeClass(element, 'file-uploading');
		YUI.Dom.addClass(element, 'file-success');
		YUI.Dom.setStyle(YUI.Dom.get('rem_' + file.id), 'display', 'none');
		if (typeof(items) == 'object')
		{
			for (i = 0; i < items.length; i++)
			{
				var this_ret = items[i].split(',');

				if (this_ret[0].length == 0 || this_ret[0] == '')
					continue;
				var mid = this_ret[0].match(/\d+/);
				if (mid == null)
					continue;

				var li = document.createElement('li');
				YUI.Dom.addClass(li, 'succ_item');

				var title_p = document.createElement('p');
				var title_p2 = document.createElement('p');

				var title = document.createElement('input');
				title.type = 'text';
				title.size = '30';
				title.value = this_ret[1];
				title.name = 'item_title_' + mid;
				title.tabindex = Yup.tabindex*2;

				var desc = document.createElement('textarea');
				desc.cols = '30';
				desc.rows = '3';
				desc.name = 'item_desc_' + mid;
				desc.tabindex = Yup.tabindex*2+1;

				var img = document.createElement('img');
				img.src = galurl + 'sa=media;in=' + mid + ';thumb';

				title_p.appendChild(title);
				title_p2.appendChild(desc);
				li.appendChild(title_p);
				li.appendChild(title_p2);
				li.appendChild(img);

				element.appendChild(li);

				YUI.Dom.setStyle(YUI.Dom.get('mu_items'), 'display', 'block');
			}
		}

		if (typeof(errors) == 'object')
		{
			for (i = 0; i < errors.length; i++)
			{
				if (!errors[i] || errors[i].length == 0)
					continue;

				var div = document.createElement('div');
				YUI.Dom.addClass(div, 'file-error');
				div.innerHTML = errors[i];

				element.appendChild(div);

				YUI.Dom.removeClass(element, 'file-success');
				YUI.Dom.addClass(element, 'file-failed');
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
		if (Yup.files.length != Yup.lastDone && typeof(Yup.files[Yup.lastDone]) != 'undefined')
			Yup.uploader.upload(Yup.files[Yup.lastDone].id, Yup.postURL, 'POST', null, 'Filedata', {});
	},

	removeFile: function(file_id)
	{
		Yup.uploader.removeFile(file_id);
		YUI.Dom.setStyle(YUI.Dom.get(file_id), 'display', 'none');

		var file_key = 0, i;
		for (i in Yup.files)
			if (Yup.files[i].id == file_id)
				file_key = i;

		Yup.setOverallTotal(Yup.files[file_key].size * -1);
		delete Yup.files[file_key];

		var files = Yup.files, inc = 0;
		Yup.files = [];
		for (i in files)
			Yup.files[inc++] = files[i];
	}
};
