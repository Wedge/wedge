/*!
 * Wedge
 *
 * Helper functions used by media playlists.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
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
		$('#foxlist' + thisPlayer).bind('selectstart', false);
	}
	else
		setTimeout(addListeners, 100);
}

function itemListener(obj)
{
	if (obj.index != currentItem[currentPlayer])
	{
		previousItem[currentPlayer] = currentItem[currentPlayer];
		currentItem[currentPlayer] = obj.index;
		setItemStyle(currentItem[currentPlayer]);
	}
}

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
	if (currentPlayer != pid)
		player[currentPlayer].sendEvent('STOP');
	currentPlayer = pid;
	if (!lnFlag && player[pid])
		player[pid].sendEvent('ITEM', fid);
}

function mover(obj, idx)
{
	obj.className = idx == currentItem[currentPlayer] ? 'playinghi' : 'playlisthi';
}

function mout(obj, idx)
{
	lnFlag = 0;
	obj.className = idx == currentItem[currentPlayer] ? 'playinglo' : 'playlistlo';
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
			file: myfile + this[0] + (mytype == 'image' ? ';preview' : '') + ';.' + this[3],
			image: myfile + this[0] + (mytype == 'image' ? ';preview' : ';thumba'),
			type: mytype, duration: this[1]
		});
	});

	var fvars = {
		file: myfile + opt.id,
		image: myfile + opt.id + (opt.type == 'image' ? ';preview' : ';thumba'),
		plugins: opt.plugins,
		showdigits: 'true',
		repeat: 'always',
		type: opt.type,
		duration: opt.duration
	};

	if (opt.bcol)
		fvars.backcolor = opt.bcol;
	if (opt.scol)
		fvars.screencolor = opt.scol;

	swfobject.embedSWF(
		opt.player,
		'aefoxy' + swo,
		'100%',
		opt.height,
		'9',
		opt.install || null,
		fvars,
		{
			allowFullscreen: 'true',
			allowScriptAccess: 'always'
		},
		{
			id: 'player' + swo,
			name: 'player' + swo
		}
	);
}
