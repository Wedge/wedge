/*!
 * Helper functions used by the media gallery.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * License: http://wedge.org/license/
 */

/**
 * Playlist functions.
 */

var
	myfile = weUrl('action%3Dmedia;sa%3Dmedia;in%3D'),
	lnFlag = 0,
	currentPlayer = 1,
	thisPlayer = 0,

	player = [],
	myplaylist = [],
	ply = [],
	plyHeight = [],
	plyTotalHeight = [],
	currentItem = [],
	previousItem = [],
	targetScrollTop = [],
	currentState = [],
	previousState = [],
	foxp = [];

function playerReady(thePlayer)
{
	thisPlayer = thePlayer.id.slice(6);
	if (player[thisPlayer])
		return;

	player[thisPlayer] = window.document[thePlayer.id];
	ply[thisPlayer] = $('#foxlist' + thisPlayer);
	plyHeight[thisPlayer] = ply[thisPlayer].clientHeight;
	previousItem[thisPlayer] = -1;
	currentItem[thisPlayer] = -1;
	addListeners();
}

function addListeners()
{
	if (player[thisPlayer])
	{
		player[thisPlayer].addControllerListener('ITEM', 'itemListener');
		player[thisPlayer].addModelListener('STATE', 'stateListener');
		player[thisPlayer].sendEvent('LOAD', myplaylist[thisPlayer]);
		$('#foxlist' + thisPlayer).on('selectstart', false);
	}
	else
		setTimeout(addListeners, 100);
}

/*
videojs('player').on('play', function (obj)
{
	if (obj.index != currentItem[currentPlayer])
	{
		previousItem[currentPlayer] = currentItem[currentPlayer];
		currentItem[currentPlayer] = obj.index;
		setItemStyle(currentItem[currentPlayer]);
	}
});
*/

function stateListener(obj) // IDLE, BUFFERING, PLAYING, PAUSED, COMPLETED
{
	if (obj.newstate == 'PAUSED' || (currentState[currentPlayer] == 'PAUSED' && obj.newstate == 'PLAYING'))
		return;
	currentState[currentPlayer] = obj.newstate;

	if (currentState[currentPlayer] != previousState[currentPlayer])
	{
		setItemStyle(currentItem[currentPlayer]);
		previousState[currentPlayer] = currentState[currentPlayer];
	}
}

function recreatePlayer(pid, fid)
{
/*	if (currentPlayer != pid)
		player[currentPlayer].sendEvent('STOP');
	currentPlayer = pid;
	if (!lnFlag && player[pid])
		player[pid].sendEvent('ITEM', fid);*/
}

function mover(obj, idx)
{
	obj.className = videojs('video').pl.current == currentItem[currentPlayer] ? 'playinghi' : 'playlisthi';
}

function mout(obj, idx)
{
	lnFlag = 0;
	obj.className = videojs('video').pl.current == currentItem[currentPlayer] ? 'playinglo' : 'playlistlo';
}

function scrollMe()
{
	var cur = ply[currentPlayer].scrollTop;

	if (cur < targetScrollTop[currentPlayer])
		ply[currentPlayer].scrollTop += Math.max(1, Math.round((targetScrollTop[currentPlayer] - cur) / 40));
	else if (cur > targetScrollTop[currentPlayer])
		ply[currentPlayer].scrollTop -= Math.max(1, Math.round((cur - targetScrollTop[currentPlayer]) / 40));
	else
		return;

	setTimeout(scrollMe, 20);
}

function setItemStyle(idx, undefined)
{
	if (idx === undefined || (currentState[currentPlayer] != 'PLAYING' && currentState[currentPlayer] != 'IDLE'))
		return;

	var
		foxLength = foxp[currentPlayer].length,
		posTop = 0, posList = [], heiList = [];

	$.each(foxp[currentPlayer], function (i) {
		posList[i] = posTop;
		heiList[i] = $('#fxm' + this[0])
			.toggleClass('windowbg3', i == currentItem[currentPlayer] && currentState[currentPlayer] == 'PLAYING')
			.height() + 4;
		posTop += heiList[i];
	});

	if (currentItem[currentPlayer] == previousItem[currentPlayer] || plyTotalHeight[currentPlayer] - plyHeight[currentPlayer] < 2)
		return;

	if (!plyTotalHeight[currentPlayer])
		plyTotalHeight[currentPlayer] = ply[currentPlayer].scrollHeight;

	if (plyTotalHeight[currentPlayer] - plyHeight[currentPlayer] < 2)
		return;

	targetScrollTop[currentPlayer] = Math.min(
		plyTotalHeight[currentPlayer] - plyHeight[currentPlayer],
		Math.max(0, posList[idx] - Math.max(0, Math.round((plyHeight[currentPlayer] - heiList[idx]) / 2)))
	);
	setTimeout(scrollMe, 20);
}

function weplay(opt)
{
	var swo = opt.swo;

	myplaylist[swo] = [];
	$.each(foxp[swo], function () {
		var mytype = ['image', 'video', 'sound'][this[2]];
		myplaylist[swo].push({
			src: [myfile + this[0] + (mytype == 'image' ? ';preview' : '') + ';.' + this[3]],
			poster: myfile + this[0] + (mytype == 'image' ? ';preview' : ';thumba'),
			type: mytype, duration: this[1],
			title: ''
		});
	});

	var fvars = {
		file: myfile + opt.id,
		image: myfile + opt.id + (opt.type == 'image' ? ';preview' : ';thumba'),
		showdigits: 'true',
		repeat: 'always',
		type: opt.type
	};

	var player = videojs('video');
	player.playList(myplaylist[swo], {
		getVideoSource: function (vid, cb) { cb(vid.src, vid.poster); }
	});
	$('a[data-action=prev]').click(function (e) { player.prev(); });
	$('a[data-action=next]').click(function (e) { player.next(); });
}

/*!
 * Spectrum analyzer for audio files in the media gallery.
 * Code adapted from: http://ianreah.com/
 */

function spectrum(where)
{
	var $spectrum = $('<div/>').addClass('spectrum').insertAfter(where), context = new (window.AudioContext || window.webkitAudioContext || 'void')();
	if (!spectrum.length || !context)
		return;

	// Create the analyzer, ask for 128 bars if possible.
	var update, analyser = context.createAnalyser();
	analyser.fftSize = 128;

	// Build the DOM elements
	var
		frequencyData = new Uint8Array(analyser.frequencyBinCount),
		barSpacingPercent = 100 / analyser.frequencyBinCount, i;

	for (i = 0; i < analyser.frequencyBinCount; i++)
		$('<div/>').css('left', i * barSpacingPercent + '%').appendTo($spectrum);
	var bars = $spectrum.children('div'), freq_center = Math.round(bars.length / 2) - 1;

	// Hook up the audio routing...
	// player -> analyser -> speakers
	// (Do this after the player is ready to play - https://code.google.com/p/chromium/issues/detail?id=112368#c4)
	$(where).bind('canplay', function ()
	{
		var src = context.createMediaElementSource(this);
		src ? src.connect(analyser) : 0;
		analyser.connect(context.destination);
	});

	// Kick it off...
	(update = function () {
		requestAnimationFrame(update);
		// Get the frequency data
		analyser.getByteFrequencyData(frequencyData);
		// Update the spectrum bars, spread evenly.
		for (i = 0; i < frequencyData.length; i++)
			bars[freq_center + (i % 2 == 0 ? -1 : 1) * Math.round(i / 2)].style.height = frequencyData[i] + 'px';
	})();
}
