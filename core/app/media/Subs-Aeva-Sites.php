<?php
/**
 * These are all of the websites available for automatic embedding in posts.
 * In most circumstances you will however use a GENERATED php file containing only
 * ENABLED sites and based on your settings. It's more efficient this way.
 * Uses portions written by Karl Benson.
 *
 * @package Wedge
 * @copyright 2010 René-Gilles Deberdt, wedge.org
 * @license http://wedge.org/license/
 * @author see contributors.txt
 */

// Prevent attempts to access this file directly
if (!defined('WEDGE'))
	die('Hacking attempt...');

// Create a global variable to store all the sites in.
global $sites;

$sites = array(
	// Local files
	array(
		'id' => 'local_mp3',
		'title' => 'MP3 Files',
		'type' => 'local',
		'plugin' => 'flash',
		'disabled' => true,
		'pattern' => '({local}[\w/ &;%.-]+\.mp3)(?=")',
		'movie' => 'http://www.flash-mp3-player.net/medias/player_mp3_maxi.swf?mp3=$2&width=250&showstop=1&showinfo=1&showvolume=1&volumewidth=35&sliderovercolor=ff0000&buttonovercolor=ff0000',
		'size' => array(250, 20, true),
		'show-link' => true,
	),
	array(
		'id' => 'local_mp4',
		'title' => 'MP4 Files',
		'type' => 'local',
		'plugin' => 'flash',
		'disabled' => true,
		'pattern' => '({local}[\w/ &;%.-]+\.mp4)(?=")',
		'movie' => 'http://www.archive.org/flow/FlowPlayerLight.swf?config=%7Bembedded%3Atrue%2CshowFullScreenButton%3Atrue%2CshowMuteVolumeButton%3Atrue%2CshowMenu%3Atrue%2CautoBuffering%3Afalse%2CautoPlay%3Afalse%2CinitialScale%3A%27fit%27%2CmenuItems%3A%5Bfalse%2Cfalse%2Cfalse%2Cfalse%2Ctrue%2Ctrue%2Cfalse%5D%2CusePlayOverlay%3Afalse%2CshowPlayListButtons%3Atrue%2CplayList%3A%5B%7Burl%3A%27$2%27%7D%5D%2CcontrolBarGloss%3A%27high%27%2CshowVolumeSlider%3Atrue%2Cloop%3Afalse%2CcontrolBarBackgroundColor%3A%270x808080%27%7D',
		'size' => array(480, 360),
		'show-link' => true,
	),
	array(
		'id' => 'local_flv',
		'title' => 'FLV Files',
		'type' => 'local',
		'plugin' => 'flash',
		'disabled' => true,
		'pattern' => '({local}[\w/ &;%.-]+\.flv)(?=")',
		// For security reasons, we use a player that's not on your domain
		'movie' => 'http://www.archive.org/flow/FlowPlayerLight.swf?config=%7Bembedded%3Atrue%2CshowFullScreenButton%3Atrue%2CshowMuteVolumeButton%3Atrue%2CshowMenu%3Atrue%2CautoBuffering%3Afalse%2CautoPlay%3Afalse%2CinitialScale%3A%27fit%27%2CmenuItems%3A%5Bfalse%2Cfalse%2Cfalse%2Cfalse%2Ctrue%2Ctrue%2Cfalse%5D%2CusePlayOverlay%3Afalse%2CshowPlayListButtons%3Atrue%2CplayList%3A%5B%7Burl%3A%27$2%27%7D%5D%2CcontrolBarGloss%3A%27high%27%2CshowVolumeSlider%3Atrue%2Cloop%3Afalse%2CcontrolBarBackgroundColor%3A%270x808080%27%7D',
		// Possible alternative player: http://www.trtube.com/mediaplayer_3_15.swf?file={url}.flv
		'size' => array(480, 360),
		'show-link' => true,
	),
	array(
		'id' => 'local_swf',
		'title' => 'SWF Files',
		'type' => 'local',
		'plugin' => 'flash',
		'disabled' => true,
		'pattern' => '({local}[\w/ &;%.-]+\.swf)(?=")',
		'movie' => '$2',
		'size' => array(425, 355),
		'show-link' => true,
	),
	array(
		'id' => 'local_divx',
		'title' => 'DivX Files',
		'type' => 'local',
		'plugin' => 'divx',
		'disabled' => true,
		'pattern' => '({local}[\w/ &;%.-]+\.divx)(?=")',
		'movie' => '$2',
		'size' => array(500, 360),
		'show-link' => true,
	),
	array(
		'id' => 'local_mov',
		'title' => 'QuickTime (.mov) Files',
		'type' => 'local',
		'plugin' => 'quicktime',
		'disabled' => true,
		'pattern' => '({local}[\w/ &;%.-]+\.mov)(?=")',
		'movie' => '$2',
		'size' => array(500, 360),
		'show-link' => true,
	),
	array(
		'id' => 'local_real',
		'title' => 'RealMedia (.ram/.rm) Files',
		'type' => 'local',
		'plugin' => 'realmedia',
		'disabled' => true,
		'pattern' => '({local}[\w/ &;%.-]+\.ra?m)(?=")',
		'movie' => '$2',
		'size' => array(500, 360),
		'show-link' => true,
	),
	array(
		'id' => 'local_wmp',
		'title' => 'RealMedia (.wmv/.wma) Files',
		'type' => 'local',
		'plugin' => 'wmp',
		'disabled' => true,
		'pattern' => '({local}[\w/ &;%.-]+\.wm[va])(?=")',
		'movie' => '$2',
		'size' => array(500, 360),
		'show-link' => true,
	),
	array(
		'id' => 'local_avi',
		'title' => 'AVI files with DivX player',
		'type' => 'local',
		'plugin' => 'divx',
		'disabled' => true,
		'pattern' => '({local}[\w/ &;%.-]+\.avi)(?=")',
		'movie' => '$2',
		'size' => array(500, 360),
		'show-link' => true,
	),

	// Biggest most popular sites first for speed purposes
	array(
		'id' => 'ytb',
		'title' => 'YouTube',
		'website' => 'http://www.youtube.com',
		'type' => 'pop',
		'plugin' => 'html',
		'pattern' => 'http://(?:video\.google\.(?:com|com?\.[a-z]{2}|[a-z]{2})/[^"]*?)?(?:(?:www|[a-z]{2})\.)?youtu(?:be\.com/[^"#[]*?(?:[&/?;]|&amp;|%[23]F)(?:video_id=|v(?:/|=|%3D|%2F))|\.be/)([\w-]{11})',
		// If you want to force the use of Flash, remove the 'plugin' line above, set ui-height to 25 and replace the 'movie' line with the one below:
		// 'movie' => 'http://www.youtube-nocookie.com/v/$2?version=3',
		'movie' => '<div class="ytb"><iframe class="aext" src="http://www.youtube-nocookie.com/embed/$2?theme=light" type="text/html" scrolling="no" marginheight="0" marginwidth="0" frameborder="0"></iframe></div>',
		'ui-height' => 0,
		// http://www.youtube.com/watch?v=-X8mD76W4F0 or v=MxGofCFHYCc (all hail Jochen Hippel!)
		// On Google - http://video.google.co.uk/url?docid=-8978185459530152475&ev=v&len=91&srcurl=http%3A%2F%2Fwww.youtube.com%2Fwatch%3Fv%3Dg2cT5J0gxeU
		// http://www.youtube.com/watch?v=M29NUeffJNA - Example of "Embedding Disabled By Request"
		'fix-html-pattern' => '<object [^>]*><param name="movie" value="http://www\.youtube\.com/v/([\w-]{11})(?:&[^"]*)?">.*?</object>',
		'fix-html-url' => 'http://www.youtube.com/watch?v=$1',
		'lookup-url' => 'http://(?:video\.google\.(?:com|com?\.[a-z]{2}|[a-z]{2})/[^"]*?)?(?:(?:www|[a-z]{2})\.)?youtube\.com/[^"#[]*?(?:[&/?;]|&amp;|%[23]F)(?:video_id=|v(?:/|=|%3D|%2F))([\w-]{11})[^]#[]*',
		'lookup-actual-url' => 'http://gdata.youtube.com/feeds/api/videos/$1?v=2',
		'lookup-final-url' => 'http://www.youtube.com/watch?v=$1',
		'lookup-title-skip' => true,
		'lookup-pattern' => array(
			'id' => '<id>.*?:([\w-]+)</id>',
			'error' => '<internalReason>(.*?)</internalReason>',
			'noexternalembed' => '<yt:accessControl\saction=\'embed\'\spermission=\'denied\'/>',
			'ws' => '<yt:aspectRatio>widescreen</yt:aspectRatio>',
		),
	),
	array(
		'id' => 'ytp',
		'title' => 'YouTube (Playlists)',
		'website' => 'http://www.youtube.com',
		'type' => 'pop',
		'plugin' => 'html',
		// http://www.youtube.com/playlist?list=PL550C8549B4CCBF8A
		'pattern' => 'http://(?:(?:www|[a-z]{2})\.)?youtube\.com/[^"]*?(?:[&/?;]|&amp;)(?:list=PL|p=|p/)([0-9a-fA-F]{16})',
		'movie' => '<div class="ytb"><iframe class="aext" src="http://www.youtube-nocookie.com/embed?listType=playlist&list=PL$2&theme=light" type="text/html" scrolling="no" marginheight="0" marginwidth="0" frameborder="0"></iframe></div>',
		'ui-height' => 25,
		'fix-html-pattern' => '<object [^>]*><param name="movie" value="$1" />.*?</object>',
	),
	array(
		'id' => 'dam',
		'title' => 'Dailymotion',
		'website' => 'http://www.dailymotion.com',
		'type' => 'pop',
		'plugin' => 'html',
		// http://www.dailymotion.com/video/xx1bs_numa-numa-dance_fun
		// http://dailymotion.alice.it/it/featured/cluster/news/video/x6k9g6_hillary-clinton-uniti-per-barack-ob_news
		// http://www.dailymotion.com/related/x5hsal_b2oba_music/video/xxq49_booba-on-nest-pas-couche-france-2_music?from=rss
		'pattern' => 'http://(?:www\.)?dailymotion\.(?:com|[a-z]{2}|alice\.it)/(?:[^"]*?video|swf)/([a-z0-9]{1,18})',
		'movie' => '<iframe class="aext" width="{int:width}" height="{int:height}" src="http://www.dailymotion.com/embed/video/$2" type="text/html" scrolling="no" marginheight="0" marginwidth="0" frameborder="0"></iframe>',
		'size' => array(480, 270),
		'ui-height' => 0,
		'lookup-title-skip' => true,
		'fix-html-pattern' => '(?:<div\>)?<object [^>]*><param name="movie" value="$1">.*?</object>(?:<br /><b><a href="$1">[^<]*</a></b>(?:<br /><i>[^<]*<a href="[^"]*">[^<]*</a></i>)?</div\>)?',
	),
	array(
		'id' => 'gmap',
		'title' => 'Google Maps',
		'website' => 'http://maps.google.com',
		'type' => 'pop',
		'plugin' => 'html',
		'pattern' => '(https?://maps\.google\.[^">]+/\w*?\?[^">]+)',
		'movie' => '<iframe class="aext" width="{int:width}" height="{int:height}" src="$1&amp;output=embed" scrolling="no" marginheight="0" marginwidth="0" frameborder="0"></iframe>',
		'size' => array(425, 350),
		'ui-height' => 0,
		'fix-html-pattern' => '<iframe [^>]+src="$1"></iframe>(?:<br /><small>.*?</small>)?',
		'fix-html-url' => '$1',
	),
	array(
		'id' => 'mcf',
		'title' => 'MetaCafe',
		'website' => 'http://www.metacafe.com',
		'type' => 'pop',
		// http://www.metacafe.com/watch/1669953/the_transporter_3/
		'pattern' => 'http://(?:www\.)?metacafe\.com/(?:watch|fplayer)/([\w-]{1,20})/',
		'movie' => 'http://www.metacafe.com/fplayer/$2/metacafe.swf',
		'size' => array(400, 345),
		'lookup-title' => '<h2>(.*?)</h2>',
		'fix-html-pattern' => '<embed src="$1"[^>]*>\s</embed>(?:<br><font size = 1>.*?</font>)?',
	),
	array(
		'id' => 'veo',
		'title' => 'Veoh',
		'website' => 'http://www.veoh.com',
		'type' => 'pop',
		// http://www.veoh.com/browse/videos/category/animation/watch/v994922AAmajfc7
		'pattern' => 'http://(?:www\.)?veoh\.com/(?:[\w/]*?|videodetails2\.swf\?permalinkId=)(v\d[0-9a-z]*)',
		'movie' => 'http://www.veoh.com/veohplayer.swf?permalinkId=$2&id=anonymous&player=videodetailsembedded&videoAutoPlay=0',
		'size' => array(460, 345),
		'lookup-title' => true,
	),
	array(
		'id' => 'vimeo',
		'title' => 'Vimeo',
		'website' => 'http://www.vimeo.com',
		'type' => 'pop',
		// http://vimeo.com/45084
		'pattern' => 'http://(?:www\.|player\.)?vimeo\.com/(?:video/)?(\d{1,12})',
		'plugin' => 'html',
		'movie' => '<iframe class="aext" src="http://player.vimeo.com/video/$2" width="{int:width}" height="{int:height}" frameborder="0" webkitAllowFullScreen mozallowfullscreen allowFullScreen></iframe>',
		'size' => array(500, 281),
		'fix-html-pattern' => '<object [^>]*>\s{0,3}<param name="allowfullscreen" value="true" />\s{0,3}<param name="allowscriptaccess" value="always" />\s{0,3}<param name="movie" value="http://vimeo\.com/moogaloop\.swf\?clip_id=(\d{1,12})[^<>]*?>.*?</object>(?:<p><a href="http://vimeo\.com.*?</a>.*?</a>.*?</a>\.</p>)?',
		'fix-html-url' => 'http://www.vimeo.com/$1',
		'lookup-title-skip' => true,
	),

	// Now the rest in alphabetical/numerical order
	array(
		'id' => 'abc',
		'title' => 'ABC News',
		'website' => 'http://www.abcnews.go.com',
		'type' => 'video',
		// http://video-cdn.abcnews.com/080911_wn_palin1_noslate.flv
		'pattern' => '(http://video-cdn\.abcnews\.com/[\w-]*?\.flv)',
		'movie' => 'http://www.archive.org/flow/FlowPlayerLight.swf?config=%7Bembedded%3Atrue%2CshowFullScreenButton%3Atrue%2CshowMuteVolumeButton%3Atrue%2CshowMenu%3Atrue%2CautoBuffering%3Afalse%2CautoPlay%3Afalse%2CinitialScale%3A%27fit%27%2CmenuItems%3A%5Bfalse%2Cfalse%2Cfalse%2Cfalse%2Ctrue%2Ctrue%2Cfalse%5D%2CusePlayOverlay%3Afalse%2CshowPlayListButtons%3Atrue%2CplayList%3A%5B%7Burl%3A%27$2%27%7D%5D%2CcontrolBarGloss%3A%27high%27%2CshowVolumeSlider%3Atrue%2Cloop%3Afalse%2CcontrolBarBackgroundColor%3A%270x808080%27%7D',
		'size' => array(480, 388),
		// http://abcnews.go.com/Video/playerIndex?id=5782791
		'lookup-url' => 'http://abcnews\.go\.com/[^">]*?video(?:/playerIndex\?id=|/|\?catId=)(\d{1,10})',
		'lookup-actual-url' => 'http://abcnews.go.com/widgets/mediaplayer/premiumPlayerPlaylist?mid=$1',
		'lookup-pattern' => '<video streamurl="" streamurl_low="" progurl="(http://video-cdn\.abcnews\.com/[\w-]*?\.flv)"',
	),
	array(
		'id' => 'adu',
		'title' => 'AdultSwim',
		'website' => 'http://www.adultswim.com',
		'type' => 'video',
		// http://www.adultswim.com/video/?episodeID=8a2505951bc80ed4011c2e6c015b0421
		'pattern' => 'http://www\.adultswim\.com/video/(?:vplayer/index\.html\?id=|\?episodeID=|ASVPlayer\.swf\?id=)([0-9a-f]{32})',
		'movie' => 'http://www.adultswim.com/video/ASVPlayer.swf?id=$2',
		'size' => array(425, 355),
		'fix-html-pattern' => '(?:<style>div#main{overflow:visible;}</style><div[^<>]*?><a href="http://www\.adultswim\.com/video/index\.html" style="display:block;"><img[^<>]*?></a>)?<object[^<>]*?><param name="allowFullScreen" value="true" /><param name="movie" value="http://www\.adultswim\.com/video/vplayer/index\.html"/><param name="FlashVars" value="id=([0-9a-f]{32})" />.*?</object>(?:</div>)?',
		'fix-html-url' => 'http://www.adultswim.com/video/?episodeID=$1',
	),
	array(
		'id' => 'allo',
		'title' => 'AlloCiné',
		'website' => 'http://www.allocine.fr',
		'type' => 'video',
		'pattern' => 'http://www\.allocine\.fr/video.*?gen_cmedia=(\d+).*?\.html',
		'movie' => 'http://www.allocine.fr/blogvision/$2',
		'size' => array(442, 370),
		'show-link' => true,
		// http://www.allocine.fr/video/player_gen_cmedia=18823831.html
		'fix-html-pattern' => '<div id="allocine_blog" [^>]*><object [^>]*><param name="movie" value="http://www\.allocine\.fr/blogvision/(\d+)[">].*?</object></div>(?:<a style=.*?</a>)?',
		'fix-html-url' => 'http://www.allocine.fr/video/default_gen_cmedia=$1.html',
	),
	array(
		'id' => 'ani',
		'title' => 'AniBoom',
		'website' => 'http://www.aniboom.com',
		'type' => 'video',
		// http://www.aniboom.com/video/270647/Monday-Morning/
		// http://www.aniboom.com/Player.aspx?v=254822
		'pattern' => 'http://(?:www\.|api\.)?aniboom\.com/(?:Player.aspx\?[^"]*?v=|video/|e/)(\d{1,10})',
		'movie' => 'http://api.aniboom.com/e/$2',
		'size' => array(425, 355),
		'fix-html-pattern' => '<object [^>]*><param name="movie" value="$1" /><param name="allowScriptAccess" value="sameDomain" /><param name="quality" value="high" /><embed src="$1" quality="high"  width="\d*"  height="\d*" allowscriptaccess="sameDomain" type="application/x-shockwave-flash"></embed></object>(?:<br><br><span style="text-align:center;font-size:12px;"><a href="http://www\.aniboom\.com">[^<]*</a></span>)?',
		'fix-html-url' => 'http://www.aniboom.com/Player.aspx?v=$2',
	),
	array(
		'id' => 'apple',
		'title' => 'Apple Trailers',
		'website' => 'http://www.apple.com/trailers/',
		'type' => 'pop',
		'plugin' => 'quicktime',
		'pattern' => 'http://(?:movies\.apple\.com/movies/(\w+/\w+/[\w\.-]+\.mov)|(?:www\.)?apple\.com/trailers/(\w+/\w+)/?#id(.*?\.mov))',
		'movie' => 'http://movies.apple.com/movies/($2|$3/$4)',
		'size' => array(640, 272),
		'show-link' => true,
		'fix-html-pattern' => '(http://movies\.apple\.com/movies/\w+/\w+/[\w\.-]+\.mov)\?width=(\d+)&(?:amp;)?height=(\d+)',
		'fix-html-url' => '$1#w$2-h$3+16',
		'lookup-url' => 'http://(?:www\.)?apple\.com/trailers/\w+/\w+/?',
		'lookup-pattern' => array(
			'id' => '<a href="http://movies\.apple\.com/movies/\w+/\w+/([\w\.-]+\.mov)(?:\?width=\d+&(?:amp;)?height=\d+)?" class="large">',
			'w' => '\?width=(\d+)&(?:amp;)?height=\d+" class="large">',
			'h' => '\?width=\d+&(?:amp;)?height=(\d+)" class="large">',
		),
		'lookup-title' => '<h1>(.*?)</h1>',
	),
	array(
		'id' => 'arc',
		'title' => 'Archive.org',
		'website' => 'http://www.archive.org',
		'type' => 'pop',
		// http://www.archive.org/download/Sita04_BattleofLanka/Sita04_BattleofLanka_sorensen.flv
		'pattern' => 'http://(?:www\.)?archive\.org/download/([\w-]*?/[\w-]*?\.(?:flv|mp\w|ogv))',
		'movie' => 'http://www.archive.org/flow/FlowPlayerLight.swf?config=' . urlencode('{embedded:true,showFullScreenButton:true,showMuteVolumeButton:true,showMenu:true,autoBuffering:false,autoPlay:false,initialScale:"fit",menuItems:[false,false,false,false,true,true,false],usePlayOverlay:false,showPlayListButtons:true,playList:[{url:"') . '$2' . urlencode('"}],controlBarGloss:"high",showVolumeSlider:true,baseURL:"http://www.archive.org/download/",loop:false,controlBarBackgroundColor:"0x000000"}'),
		'size' => array(480, 360),
		'fix-html-pattern' => '<object [^>]*>\s*<param name="movie" value="http://www\.archive\.org/flow/flowplayer\.commercial-3\.0\.3\.swf" />.*?"url":"([^"]*)".*?</object>',
		// http://www.archive.org/details/FinalFantasy3_SS_448
		'lookup-url' => 'http://(?:www\.)?archive\.org/details/(?:[\w%-]{5,50})',
		'lookup-pattern' => 'IAD\.(?:flv|mp4)s\s=\s\["([\w-]*?/[\w-]*?\.(?:flv|mp\w|ogv))"[],]',
		'lookup-final-url' => 'http://www.archive.org/download/$1',
	),
	array(
		'id' => 'ato',
		'title' => 'Atom',
		'website' => 'http://www.atom.com',
		'type' => 'video',
		// http://media.mtvnservices.com/mgid:hcx:content:atom.com:c857bc81-8a75-4527-901d-9492b860f29c
		'pattern' => '(http://media\.mtvnservices\.com/mgid:hcx:content:atom\.com:[0-9a-z]{8}(?:-[0-9a-z]{4}){3}-[0-9a-z]{12})',
		'movie' => '$2',
		'size' => array(425, 354),
		'fix-html-pattern' => '<embed src="$1"[^<>]*?></embed>(?:<div[^<>]*?><a[^<>]*?><img[^<>]*?></a> <a[^<>]*?>[^<>]*?</a>[^<>]*?<a[^<>]*?>[^<>]*?</a>[^<>]*?<a[^<>]*?>[^<>]*?</a></div>)?',
		'fix-html-url' => '$2',
		// http://www.atom.com/funny_videos/mccaingels_103/
		// http://www.atom.com/funny_videos/3EFBFFFF01859D7E0017009CF056/ (user content)
		'lookup-url' => 'http://(?:www\.)?atom\.com/[\w-]*?/[\w-]*?/',
		'lookup-pattern' => 'mgid:hcx:content:atom\.com:([0-9a-z]{8}(?:-[0-9a-z]{4}){3}-[0-9a-z]{12})',
		'lookup-final-url' => 'http://media.mtvnservices.com/mgid:hcx:content:atom.com:$1',
	),
	array(
		'id' => 'ipl',
		'title' => 'BBC iPlayer (UK)',
		'website' => 'http://www.bbc.co.uk/iplayer',
		'type' => 'video',
		// http://www.bbc.co.uk/iplayer/episode/b00dk77f/b00dk779/
		'pattern' => 'http://(?:www\.)?bbc\.co\.uk/iplayer/(?:page/item|episode)/([a-z0-9]{8})(?:\.shtml|/)',
		'movie' => 'http://www.bbc.co.uk/emp/player.swf?playlist=http://www.bbc.co.uk/iplayer/playlist/$2&config=http://www.bbc.co.uk/emp/iplayer/config.xml&domId=emp1',
		'size' => array(640, 385),
	),
	array(
		'id' => 'beb',
		'title' => 'Bebo',
		'website' => 'http://www.bebo.com',
		'type' => 'video',
		// http://bebo.161.download.videoegg.com/gid329/cid1124/LC/D8/1211993690XyKXuBnaZtd9ZRftvoVo
		'pattern' => '(http://bebo\.(?:\d{1,4})\.download\.videoegg\.com(?:(?:/(?:[0-9a-z]*)){5}))',
		'movie' => 'http://static.videoegg.com/videoegg/loader.swf?file=$2',
		'size' => array(425, 350),
		'fix-html-pattern' => '<embed [^>]*src="http://static\.videoegg\.com/videoegg/loader\.swf" FlashVars="bgColor=FFFFFF&(?:amp;)?file=$1"[^<>]*?>(?:<br><a href="http://www\.bebo\.com/.*?</a>[^<>]*?<a href="http://www\.bebo\.com/.*?</a>)?',
		// http://www.bebo.com/FlashBox.jsp?FlashBoxId=6854827115
		// http://bebo.com/watch/6854827115 - this redirects so we need to use the above when looking up
		'lookup-url' => 'http://(?:www\.)?bebo\.com/(?:watch/|FlashBox\.jsp\?FlashBoxId=)(\d{1,12})',
		'lookup-actual-url' => 'http://www.bebo.com/FlashBox.jsp?FlashBoxId=$1',
		'lookup-pattern' => 'file=(http://bebo\.(?:\d{1,4})\.download\.videoegg\.com(?:(?:/[0-9a-z]*?){5}))&amp;',
	),
	array(
		'id' => 'bli',
		'title' => 'Blip',
		'website' => 'http://www.blip.tv',
		'type' => 'video',
		// http://blip.tv/play/gsVc6fBfk6Zt
		'pattern' => 'http://(?:www\.|[a-z0-9]*?\.)?blip\.tv/(?:play/(\w{1,15})|file/\d+/?#id(\d+))',
		'movie' => '(http://blip.tv/play/$2|http://e.blip.tv/scripts/flash/showplayer.swf?file=http%3A%2F%2Fblip.tv/rss/flash/$3&showplayerpath=http%3A%2F%2Fblip.tv/scripts/flash/showplayer.swf&feedurl=http://JosephK.blip.tv/rss/flash&brandname=blip.tv&brandlink=http://blip.tv/%3Futm_source%3Daeva&enablejs=false)',
		'size' => array(400, 330),
		'fix-html-pattern' => '<embed src="$1"[^>]*></embed>',
		// http://blip.tv/file/1726038
		'lookup-url' => 'http://(?:www\.|[a-z0-9]*?\.)?blip\.tv/file/(?:\d{1,10})([^"]*?)',
		'lookup-pattern' => array('id' => 'setPostsId\((\d+)\)'),
		'lookup-title' => true,
	),
	array(
		'id' => 'bof',
		'title' => 'BoFunk',
		'website' => 'http://www.bofunk.com',
		'type' => 'video',
		// http://www.bofunk.com/e/nockbcznzgghpdaeakupibegbqgmdaothtyuwxy
		'pattern' => '(http://www.bofunk.com/e/(?:[0-9a-z]{39}))',
		'movie' => '$2',
		'size' => array(446, 370),
		'fix-html-pattern' => '<embed src="$1"[^<>]*?>(?:<br><a href="http://www\.bofunk\.com[^<>]*?>[^<>]*?</a>(?:[\s-]{0,3})?<a href="http://www\.bofunk\.com[^<>]*?>[^<>]*?</a>)?',
		// http://www.bofunk.com/video/6959/naughty_mummy.html
		// http://www.bofunk.com/video/6957/cellphone_in_the_microwave.html
		'lookup-url' => 'http://(?:www\.)?bofunk\.com/video/\d{1,10}/[\w-]*?\.html',
		'lookup-pattern' => '<embed\ssrc="/w/([0-9a-z]{39})"',
		'lookup-final-url' => 'http://www.bofunk.com/e/$1',
	),
	array(
		'id' => 'bom',
		'title' => 'BombayTV',
		'website' => 'http://www.grapheine.com/bombaytv/',
		'type' => 'video',
		// http://www.grapheine.com/bombaytv/index.php?module=see&lang=uk&code=b14302b376e473c5ca0dead6b9a1f483
		'pattern' => 'http://(?:www\.)?grapheine\.com/bombaytv/(?:index\.php)?\?[^#"]*?code=(\w+)',
		'movie' => 'http://www.grapheine.com/bombaytv/bt.swf?code=$2',
		'size' => array(400, 370),
		'show-link' => true,
		'fix-html-pattern' => '<object width="(\d+)" height="(\d+)"><param name="movie" value="http://www\.grapheine\.com/bombaytv/bt\.swf\?code=(\w+)">.*?</object>',
		'fix-html-url' => 'http://www.grapheine.com/bombaytv/index.php?module=see&lang=uk&code=$3#w$1-h$2',
	),
	array(
		'id' => 'boo',
		'title' => 'BooMp3',
		'website' => 'http://www.boomp3.com',
		'type' => 'audio',
		// http://boomp3.com/listen/c0zjau3_p
		'pattern' => 'http://(?:www\.|static\.)?boomp3\.com/(?:listen/|player\.swf\?song=)([\w-]{1,11})',
		'movie' => 'http://static.boomp3.com/player.swf?song=$2&noinfo=1',
		'size' => array(200, 20),
	),
	array(
		'id' => 'bre',
		'title' => 'Break',
		'website' => 'http://www.break.com',
		'type' => 'pop',
		// http://www.break.com/index/planet-freaking-earth.html
		// http://www.break.com/usercontent/2008/5/Danica-Patrick-or-Ashley-Force-Video-501947.html
		'pattern' => '(?:http://embed\.break\.com/(\w+)|http://(?:www\.)?break\.com/[^"]*?\.html#id(\d+))',
		'movie' => 'http://embed.break.com/$2$3',
		'size' => array(464, 392),
		'fix-html-pattern' => '<object [^>]*><param name="movie" value="$1">.*?</object>(?:<br><font size=1>.*?</font>)?',
		'fix-html-url' => '$2',
		'lookup-url' => 'http://(?:www\.)?break\.com/[^"]*?\w+\.html',
		'lookup-pattern' => array('id' => '<meta name="embed_video_url" content="http://embed\.break\.com/(\d+)"'),
	),
	array(
		'id' => 'bri',
		'title' => 'Brightcove network',
		'website' => 'http://www.brightcove.com',
		'type' => 'pop',
		'pattern' => 'http://link\.brightcove\.com/services/(?:link|player)/bcpid(\d+)[^">]*?bctid=?(\d+)',
		'movie' => 'http://services.brightcove.com/services/viewer/federated_f8/$2?videoId=$3&playerId=$2&viewerSecureGatewayURL=https://console.brightcove.com/services/amfgateway&servicesURL=http://services.brightcove.com/services&cdnURL=http://admin.brightcove.com&domain=embed&autoStart=false&',
		'size' => array(486, 412),
		'fix-html-pattern' => '<embed src="http://services.brightcove.com/services/viewer/federated_f8/(\d+)" bgcolor="#FFFFFF" flashVars="videoId=(\d+)[^"]*"[^>]*></embed>',
		'fix-html-url' => 'http://link.brightcove.com/services/link/bcpid$1/bctid$2',
		// http://link.brightcove.com/services/link/bcpid1640183817/bctid1747278475
	),
	array(
		'id' => 'bro',
		'title' => 'Broadcaster',
		'website' => 'http://www.broadcaster.com',
		'type' => 'video',
		'pattern' => '(http://(?:www\.)?broadcaster\.com/video/external/player\.swf\?clip=([\w-]*?)\.flv)',
		'movie' => '$2',
		'size' => array(425, 340),
		// http://www.broadcaster.com/video/external/player.swf?clip=916814_624724428_Zombies_20new.flv
		'fix-html-pattern' => '<embed src="$1"[^<>]*?> ?</embed>',
		'fix-html-url' => '$2',
		'lookup-url' => 'http://www.broadcaster.com/clip/(?:\d{1,12})',
		'lookup-pattern' => '&lt;embed src="(http://(?:www\.)?broadcaster\.com/video/external/player\.swf\?clip=([\w-]*?)\.flv)"',
		// http://www.broadcaster.com/clip/28934
	),
	array(
		'id' => 'spo',
		'title' => 'CBS Sports',
		'website' => 'http://www.cbssports.com',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)?cbssports\.com/video/player/(?:play|embed)/([a-z0-9]*?/([\w-]{32}))',
		'movie' => 'http://www.cbs.com/thunder/swf30can10cbssports/rcpHolderCbs-3-4x3.swf?link=http://www.cbssports.com/video/player/play/$2&partner=cbssports&vert=Sports&autoPlayVid=false&releaseURL=http://release.theplatform.com/content.select?pid=$3&name=cbsPlayer&embedded=y&rv=n&salign=tl',
		'size' => array(497, 379),
		'fix-html-pattern' => '<embed src=\'http://www\.cbs\.com/thunder/swf30can10cbssports/rcpHolderCbs-3-4x3\.swf\' FlashVars=\'link=[\w%]*(play|embed)%2F([^%]+)%2F([^&]+)&[^>]*></embed>(?:<br/><a href=\'http://www.cbs.com\'>.*?</a>)?',
		'fix-html-url' => 'http://www.cbssports.com/video/player/$1/$2/$3',
		'fix-html-urldecode' => true,
		// http://www.cbssports.com/video/player/play/nfl/Iv6DfSSscYBAXmubr_lYnxLQ_UP8NC_R
		// http://www.cbssports.com/video/player/embed/golf/pUV31MGSZh_dEZnCh9g8DHDQH3ZXGl_g
	),
	array(
		'id' => 'cel',
		'title' => 'Cellfish',
		'website' => 'http://www.cellfish.com',
		'type' => 'video',
		'pattern' => 'http://(cellfish\.|www\.)?cellfish\.com/(?:video|ringtone|multimedia)/(\d{1,10})/',
		'movie' => 'http://$2cellfish.com/static/swf/player8.swf?Id=$3',
		'size' => array(420, 315),
		// http://cellfish.com/video/351705/Numa
		// http://cellfish.com/ringtone/143603/Let-It-Go-Missy-Elliot-hook
		'fix-html-pattern' => '<object [^>]*><param name="movie" value="http://cellfish\.com/static/swf/player\d*\.swf\?Id=(\d*)" />.*?</object>',
		'fix-html-url' => 'http://cellfish.com/video/$2',
	),
	array(
		'id' => 'cla',
		'title' => 'Clarin',
		'website' => 'http://www.videos.clarin.com',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)videos\.clarin\.com/index\.html\?id=(\d{1,12})',
		'movie' => 'http://www.clarin.com/shared/v9/swf/clarinvideos/player.swf?id=$2&RUTAS=http://www.clarin.com/shared/v9/swf/clarinvideos/rutas.xml&SEARCH=http://www.servicios.clarin.com/decoder/buscador_getMtmYRelacionados/$2|CLARIN_VIDEOS|VIDEO|null|100|1|10|null.xml',
		'size' => array(533, 438),
		// http://www.videos.clarin.com/index.html?id=950515
		'fix-html-pattern' => '<script[^<>]*?></script><script type="text/javascript">cargarVideo\((\d{1,12})\);</script>',
		'fix-html-url' => 'http://www.videos.clarin.com/index.html?id=$1',
	),
	array(
		'id' => 'cli',
		'title' => 'Clip.vn',
		'website' => 'http://www.clip.vn',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)?clip\.vn/w(?:atch/[\w-]*?)?/([a-z0-9_-]{1,5}),vn',
		'movie' => 'http://www.clip.vn/w/$2,vn,0,,hq',
		'size' => array(448, 361),
		// http://clip.vn/watch/Viet-Nam-Idol-2008-tai-TPHCM-15-09-2008-/WLik,vn?fm=1
		'fix-html-pattern' => '<object [^>]*><param name="movie" value="http://(?:www\.)?clip\.vn/w/([a-z0-9_-]{1,5}),vn,0,,hq".*?</object>',
		'fix-html-url' => 'http://www.clip.vn/w/$1,vn,0,,hq',
	),
	array(
		'id' => 'clo',
		'title' => 'ClipFish (Old)',
		'website' => 'http://www.clipfish.de',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)?clipfish\.de/(?:(?:player\.php|videoplayer\.swf)\?[^"]*?vid=|video/)(\d{1,20})',
		'movie' => 'http://www.clipfish.de/videoplayer.swf?as=0&vid=$2&r=1',
		'size' => array(464, 380),
		// http://clipfish.de/video/2651310/britney-singt-schief
		'fix-html-pattern' => '<object [^>]*><param name=\'allowScriptAccess\' value=\'always\' /><param name=\'movie\' value=\'$1\' />.*?</object>(?:<a href=\'http://www.clipfish.de.*?</a>)?',
		'fix-html-url' => 'http://www.clipfish.de/video/$2',
	),
	array(
		'id' => 'cln',
		'title' => 'ClipFish (New)',
		'website' => 'http://www.clipfish.de',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)?clipfish\.de/(?:video)?player\.(?:swf|php)[^"]*?videoid=([a-z0-9]{18}(?:==)?|[a-z0-9]{6}(?:==)?)',
		'movie' => 'http://www.clipfish.de/videoplayer.swf?as=0&videoid=$2%3D%3D&r=1',
		'size' => array(464, 380),
		// http://clipfish.de/video/2651310/britney-singt-schief
		'fix-html-pattern' => '<object [^>]*>\s{0,3}<param name="allowScriptAccess" value="always" />\s{0,3}<param name="movie" value="$1" />.*?</object>(?:\s{0,3}<a href="http://www.clipfish.de.*?</a>)?',
		'fix-html-url' => 'http://www.clipfish.de/videoplayer.swf?as=0&videoid=$2%3D%3D&r=1',
	),
	array(
		'id' => 'clj',
		'title' => 'ClipJunkie',
		'website' => 'http://www.clipjunkie.com',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)?clipjunkie\.com/([^"]*?-vid\d{1,10})\.html',
		'movie' => 'http://www.clipjunkie.com/flvplayer/flvplayer.swf?flv=http://videos.clipjunkie.com/videos/$2.flv&themes=http://www.clipjunkie.com/flvplayer/themes.xml&playList=http://www.clipjunkie.com/playlist.php&config=http://www.clipjunkie.com/skin/config.xml',
		'size' => array(460, 357),
		// http://www.clipjunkie.com/Mythbusters-Fun-With-Air-vid3976.html
	),
	array(
		'id' => 'clm',
		'title' => 'ClipMoon',
		'website' => 'http://www.clipmoon.com',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)?clipmoon\.com/(?:videos/|[^"]*?viewkey=)([0-9a-z]{1,10})',
		'movie' => 'http://www.clipmoon.com/flvplayer.swf?config=http://www.clipmoon.com/flvplayer.php?viewkey=$2&external=yes',
		'size' => array(460, 357),
		// http://www.clipmoon.com/videos/7110d8/-car-accidents-terrible-crash-mclaren-.html
		'fix-html-pattern' => '<embed src="http://www\.clipmoon\.com/flvplayer\.swf" FlashVars="config=$1"[^>]*> </embed>',
		'fix-html-url' => 'http://www.clipmoon.com/videos/$2/',
	),
	array(
		'id' => 'clg',
		'title' => 'CollegeHumor',
		'website' => 'http://www.collegehumour.com',
		'type' => 'video',
		'title' => 'CollegeHumor',
		'pattern' => 'http://(?:www\.)?collegehumor\.com/video:(\d{1,12})',
		'movie' => 'http://www.collegehumor.com/moogaloop/moogaloop.swf?clip_id=$2',
		'size' => array(480, 360),
		// http://www.collegehumor.com/video:1828310
		'fix-html-pattern' => '(?:<object [^>]*>(?:<param [^>]*>)+<param name="movie" quality="best" value="http://www\.collegehumor\.com/moogaloop/moogaloop\.swf\?clip_id=(\d*)[^"]*" /></object>)(?:<div style="padding:5px 0;.*?</div>)?',
		'fix-html-url' => 'http://www.collegehumor.com/video:$1',
	),
	array(
		'id' => 'coc',
		'title' => 'ComedyCentral/TDS',
		'website' => 'http://www.comedycentral.com',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)?(?:comedycentral|thedailyshow)\.com/(?:[a-z0-9]{5,20}/)?video(?:s)?(?:/index)?\.jhtml\?videoId=(\d{1,10})',
		'movie' => 'http://www.comedycentral.com/sitewide/video_player/view/default/swf.jhtml?videoId=$2',
		'size' => array(332, 316),
		// http://www.thedailyshow.com/video/index.jhtml?videoId=171030&title=terry-mcauliffe
		// http://www.comedycentral.com/colbertreport/videos.jhtml?videoId=171133
		// http://www.comedycentral.com/videos/index.jhtml?videoId=168158&title=tommy-davidson-terrorism
		'fix-html-pattern' => '<embed FlashVars=\'videoId=(\d{1,12})\'.*?</embed>',
		'fix-html-url' => 'http://www.comedycentral.com/videos/index.jhtml?videoId=$1',
	),
	array(
		'id' => 'cra',
		'title' => 'Crackle',
		'website' => 'http://www.crackle.com',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)?crackle\.com/c/([a-z0-9_]*?)/([a-z0-9_]*?)/(\d{1,10})',
		'movie' => 'http://www.crackle.com/p/$2/$3.swf?id=$4',
		'size' => array(400, 328),
		// http://www.crackle.com/c/Jace_Hall/Jace_Hall_Ep_13_SEASON_FINALE_/2366278#ml=o%3d12%26fpl%3d297691%26fx%3d
		'fix-html-pattern' => '(?:<div style=\'font-family:arial;font-size:12px;text-align:center;\'>)?<embed allowFullScreen="true" src="http://(?:www\.)?crackle\.com/p/([a-z0-9_]*?)/([a-z0-9_]*?)\.swf" width="\d*" height="\d*" quality="high" scale="noScale" FlashVars="id=(\d{1,10})[^"]*"[^>]*></embed>(?:<p>From Crackle:.*?</p></div>)?',
		'fix-html-url' => 'http://www.crackle.com/c/$1/$2/$3',
	),
	array(
		'id' => 'cru',
		'title' => 'CrunchyRoll',
		'website' => 'http://www.crunchyroll.com',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)?crunchyroll\.com/media-(\d+)/[\w-]*?\.html\?oldplayer=1#id(\d+)',
		'movie' => 'http://www.crunchyroll.com/flash/20090126131528.9c04de8f74a443808b1df4c1f36c3757/oldplayer2.swf?file=http%3A%2F%2Fwww.crunchyroll.com%2Fgetitem%3Fvideoid%3D$3%26mediaid%3D$2&autostart=false',
		'size' => array(576, 325),
		'show-link' => true,
		// http://www.crunchyroll.com/getitem?ih=opq8213o979nq084so5&videoid=7732&mediaid=3802&hash=opq8213o979nq084so5
		'lookup-url' => 'http://(?:www\.)?crunchyroll\.com/(media-\d+/[\w-]*?\.html)',
		'lookup-actual-url' => 'http://www.crunchyroll.com/$1?oldplayer=1',
		'lookup-pattern' => array(
			'id' => '%3Fvideoid%3D(\d+)',
			'w' => 'width="(\d+)" height="\d+"><param',
			'h' => 'width="\d+" height="(\d+)"><param',
		),
		'lookup-urldecode' => 1,
		// http://www.crunchyroll.com/media-48996/Magical-Angel-Creamy-Mami-Episode-26.html
	),
	array(
		'id' => 'cult',
		'title' => 'Culture Pub',
		'website' => 'http://www.culturepub.fr',
		'type' => 'video',
		'pattern' => 'http://(www\.culturepub\.fr/videos)/[\w-]+\.html#id(\d+)-hd([01])',
		'movie' => 'http://www.culturepub.fr/player.swf?RPath=$2&HD=$4&Media=$3&Ref=&TypeRef=&SeuilBD=600&Rld=1&SauveBP=200&NoCache=0',
		'size' => array('normal' => array(442, 370), 'hd1' => array(730, 586)),
		'show-link' => true,
		// http://www.culturepub.fr/videos/inpes-anti-tabac-france-naissance.html (you gotta love that song...)
		'lookup-url' => 'http://(?:www\.)?culturepub\.fr/videos/[\w-]+\.html',
		'lookup-pattern' => array(
			'id' => '&Media=(\d+)&Ref',
			'hd' => '&HD=([01])&'
		),
	),
	array(
		'id' => 'cur',
		'title' => 'Current',
		'website' => 'http://www.current.com',
		'type' => 'video',
		'pattern' => '(http://(?:www\.)?current\.com/e/\d{8})',
		'movie' => '$2',
		'size' => array(400, 400),
		// http://current.com/e/88997642
		'fix-html-pattern' => '<object [^>]*>\s{0,3}<param name="movie" value="$1">.*?</object>',
		'fix-html-url' => '$2',
		'lookup-url' => 'http://(?:www\.)?current\.com/items/\d{8}_[\w-]*',
		'lookup-pattern' => 'so\.addVariable\(\'permalink\',\s\'http://current\.com/items/(\d{8})_[\w-]*?\'\);',
		'lookup-final-url' => 'http://www.current.com/e/$1',
		// The videos share the same link as the other pages, so we're only supporting it with a lookup.
		// http://current.com/items/88997642_away_in_uk
	),
	array(
		'id' => 'dah',
		'title' => 'Dailyhaha',
		'website' => 'http://www.dailyhaha.com',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)?dailyhaha\.com/_vids/(?:Whohah\.swf\?Vid=)?([a-z0-9_-]*?)\.(?:htm|flv)',
		'movie' => 'http://www.dailyhaha.com/_vids/Whohah.swf?Vid=$2.flv',
		'size' => array(425, 350),
		// http://www.dailyhaha.com/_vids/girl_scount_thiefs.htm
		'fix-html-pattern' => '(?:<div style="text-align:center;">)?<embed src="$1".*?</embed>(?:<br /> More <a href="http://www\.dailyhaha\.com">Funny Videos</a></div>)?',
		'fix-html-url' => 'http://www.dailyhaha.com/_vids/$2.htm',
	),
	array(
		'id' => 'dee',
		'title' => 'Deezer',
		'website' => 'http://www.deezer.com',
		'type' => 'audio',
		'pattern' => 'http://(?:www\.)?deezer\.com/track/(?:[\w-]+\-T)?(\d{1,12})',
		'movie' => 'http://www.deezer.com/embedded/small-widget-v2.swf?idSong=$2&colorBackground=0x525252&textColor1=0xFFFFFF&colorVolume=0x39D1FD&autoplay=0',
		'size' => array(220, 55),
		// http://www.deezer.com/track/histoires-sans-paroles-T2677404 (Best-folk-song-ever)
		'fix-html-pattern' => '(?:<div style="[^>]*">)?<object [^>]*><param name="movie" value="http://www\.deezer\.com/embedded/small-widget-v2\.swf\?idSong=(\d+).*?</object>(?:<br><font size=\'1\' .*?!</font></div>)?',
		'fix-html-url' => 'http://www.deezer.com/track/$1',
		'lookup-title' => '<title>(.*?)(?:\| Deezer)?</title>',
	),
	array(
		'id' => 'deep',
		'title' => 'Deezer (Playlists)',
		'website' => 'http://www.deezer.com',
		'type' => 'audio',
		'pattern' => 'http://(?:www\.)?deezer\.com/[^/]*#music/playlist/(\d+)/(\d+)',
		'movie' => 'http://www.deezer.com/embedded/widget_450x345.swf?path=$2&id=$3&colorBack=0x525252&colorVolume=0x00CCFF&colorScrollbar=0x666666&colorText=0xFFFFFF&autoplay=0&autoShuffle=0',
		'size' => array(450, 345),
		// http://www.deezer.com/#music/playlist/21448960/7235736
		'fix-html-pattern' => '<div [^>]+><object [^>]+><param name="movie" value="http://www\.deezer\.com/embedded/widget[^\.]*\.swf\?path=(\d+)[^"]*&id=(\d+)"></param>.*?</object>.*?</div>',
		'fix-html-url' => 'http://www.deezer.com/#music/playlist/$1/$2',
		'lookup-title' => false,
	),
	array(
		'id' => 'deer',
		'title' => 'Deezer (Radio)',
		'website' => 'http://www.deezer.com',
		'type' => 'audio',
		'pattern' => 'http://(?:www\.)?deezer\.com/[^/]*#music/radio/(\d+)',
		'movie' => 'http://www.deezer.com/embedded/widgetRadio.swf?rid=$2&colorBackground=0x525252&colorButtons=0xDDDDDD&textColor1=0xFFFFFF&textColor2=0xCCCCCC',
		'size' => array(180, 220),
		// http://www.deezer.com/#music/radio/6
		'fix-html-pattern' => '<div [^>]+><object [^>]+><param name="movie" value="http://www\.deezer\.com/embedded/widgetRadio\.swf\?rid=(\d+)[^"]*"></param>.*?</object>.*?</div>',
		'fix-html-url' => 'http://www.deezer.com/#music/radio/$1',
		'lookup-title' => false,
	),
	array(
		'id' => 'demo',
		'title' => 'Demoscene.tv',
		'website' => 'http://www.demoscene.tv',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)?demoscene\.tv/(?:page|prod)\.php\?.*?id_prod=\d+[^#]*#id(\w+)',
		'movie' => 'http://www.demoscene.tv/mediaplayer.swf?id=$2',
		'size' => array(512, 404),
		'show-link' => true,
		// http://www.demoscene.tv/page.php?id=172&lang=uk&vsmaction=viewgroup&id_group=166 (ST oldskool rocks!)
		// http://www.demoscene.tv/page.php?id=172&lang=uk&vsmaction=view_prod&id_prod=4197 (excellent dance track, watch in HD!)
		'fix-html-pattern' => '<object [^>]*>\s{0,3}<param name="movie" value="$1">.*?</object>',
		'fix-html-url' => '$2',
		'lookup-url' => 'http://(?:www\.)?demoscene\.tv/(?:page|prod)\.php\?.*?id_prod=\d+',
		'lookup-pattern' => array(
			'id' => '&lt;embed src=&quot;http://www\.demoscene\.tv/mediaplayer\.swf\?id=(\w+)&quot;',
			'w' => 'width=&quot;(\d+)&quot;',
			'h' => 'height=&quot;(\d+)&quot;',
		),
		'lookup-title' => '<title>(?:DTV :: All the demoscene on a web TV ! - )?(.*?)</title>',
	),
	array(
		'id' => 'dot',
		'title' => 'DotSub',
		'website' => 'http://www.dotsub.com',
		'type' => 'video',
		'plugin' => 'html',
		'pattern' => 'http://(?:www\.)?dotsub\.com/(?:media/|view/)([0-9a-z]{8}(?:-[0-9a-z]{4}){3}-[0-9a-z]{12})',
		'movie' => '<iframe class="aext" width="{int:width}" height="{int:height}" src="http://dotsub.com/media/$2/embed/" type="text/html" scrolling="no" marginheight="0" marginwidth="0" frameborder="0"></iframe>',
		'size' => array(480, 392),
		// http://dotsub.com/view/0c504c81-cebc-4370-bf94-b20fce57c38f
		'fix-html-pattern' => '<iframe src="$1" frameborder="0" width="\d*" height="\d*"></iframe>',
	),
	array(
		'id' => 'dbl',
		'title' => 'DoubleViking',
		'website' => 'http://www.doubleviking.com',
		'type' => 'video',
		'pattern' => '(http://doubleviking\.cachefly\.net/videos/doubleviking/\d{4}/\d{1,2}/\d{1,2}/[0-9a-z-]*?\.flv)',
		'movie' => 'http://www.doubleviking.com/mediaplayer.swf?file=$2',
		'size' => array(400, 340),
		// http://doubleviking.cachefly.net/videos/doubleviking/2008/06/03/french-girl.flv
		'lookup-url' => '(http://(?:www\.)?doubleviking\.com/videos/page\d{1,2}\.html/[0-9a-z-]*?-\d{1,12}\.html)',
		'lookup-pattern' => '(?:so.addVariable\("file", "|var\smediaUrl\s=\s")(http://doubleviking\.cachefly\.net/videos/doubleviking/\d{4}/\d{1,2}/\d{1,2}/[0-9a-z-]*?\.flv)"',
		// http://www.doubleviking.com/videos/page0.html/hot-drunk-french-chick-9406.html
		// http://www.doubleviking.com/videos/page0.html/the-greatest-inspirational-speech-of-all-time-10130.html
	),
	array(
		'id' => 'dro',
		'title' => 'Dropshots',
		'website' => 'http://www.dropshots.com',
		'type' => 'video',
		'title' => 'dropshots.com',
		'pattern' => '(http://media\d{0,2}\.dropshots\.com/photos(?:/\d{1,10}){1,3}\.flv)',
		'movie' => 'http://www.dropshots.com/dropshotsplayer.swf?url=$2',
		'size' => array(480, 385),
		// http://media1.dropshots.com/photos/99384/20061116/181618.flv
	),
	array(
		'id' => 'dvs',
		'title' => 'Divshare',
		'website' => 'http://www.divshare.com',
		'type' => 'audio',
		'pattern' => 'http://www\.divshare\.com/download/([^"]*)',
		'movie' => 'http://www.divshare.com/flash/playlist?myId=$2',
		'size' => array(335, 28),
		// http://www.divshare.com/download/5925984-2b2
	),
	array(
		'id' => 'eas',
		'title' => 'EASportsWorld',
		'website' => 'http://www.easportsworld.com',
		'type' => 'video',
		'pattern' => '(http://videocdn\.easw\.easports\.com/easportsworld/media/\d{1,12}/[\w-]*?\.flv)',
		'movie' => 'http://ll-999.ea.com/sonet-easw/2.2.4.0/flash/sw/videos/mediaplayer.swf?file=$2&image=http://ll-999.ea.com/sonet-easw/2.2.4.0/images/sw/videos/preview.jpg&backcolor=0x000000&frontcolor=0x006BCC&lightcolor=0x006BCC',
		'size' => array(566, 355),
		// http://videocdn.easw.easports.com/easportsworld/media/7499/912A0001_1_FLV_VIDEO_OUh.flv
		'fix-html-pattern' => '\s{0,8}<object [^>]*>\s{0,8}<param name="movie" value="[^"]*?file=$1[^<>]*?/>.*?</object>(?:\s{0,9}<div style="font-family: Arial, sans-serif; font-size: 11px;">.*?</div>(?:\s{0,8}<img[^<>]*?/>)?)?',
		'lookup-url' => 'http://(?:www\.)?easportsworld\.com/[\w-]{1,12}/video/\d{1,12}',
		'lookup-pattern' => 'flashvars\.file\s=\s\'(http://videocdn\.easw\.easports\.com/easportsworld/media/\d{1,12}/[\w-]*?\.flv)\';',
		// http://www.easportsworld.com/en_US/video/342
	),
	array(
		'id' => 'eba',
		'title' => 'EbaumsWorld Audio',
		'website' => 'http://www.ebaumsworld.com/audio/',
		'type' => 'audio',
		'pattern' => '(?:http://www\.ebaumsworld\.com/mediaplayer\.swf\?file=)?(http://media\.ebaumsworld\.com/(?:mediaFiles/)?(?:audio/(?!play/)|[0-9/]{8})[\w-]*?/[\w-]*?\.mp3)',
		'movie' => 'http://www.ebaumsworld.com/mediaplayer.swf?file=$2&showeq=true&displayheight=50',
		'size' => array(440, 70),
		'show-link' => true,
		// http://media.ebaumsworld.com/audio/mikeyp/Armaggedon.mp3
		// http://media.ebaumsworld.com/2007/10/john-madden-impressons.mp3
		'fix-html-pattern' => '<embed src="http://www\.ebaumsworld\.com/mediaplayer\.swf" flashvars="pageurl=http://www\.ebaumsworld\.com/audio/play/\d{1,12}&(?:amp;)?file=$1[^<>]*?>',
		'lookup-url' => 'http://(?:www\.)?ebaumsworld\.com/audio/play/\d{1,12}/?',
		'lookup-pattern' => '<link rel="video_src" href="http://www\.ebaumsworld\.com/mediaplayer\.swf\?[^"\>]*?file=(http://media\.ebaumsworld\.com/(?:mediaFiles/)?(?:audio/|[0-9/]{8})[\w-]*?/[\w-]*?\.mp3)',
		// http://www.ebaumsworld.com/audio/play/386708/
	),
	array(
		'id' => 'ebv',
		'title' => 'EbaumsWorld Videos',
		'website' => 'http://www.ebaumsworld.com',
		'type' => 'video',
		'pattern' => '(?:http://www\.ebaumsworld\.com/mediaplayer\.swf\?file=)?(?:http://(?:www\.)?ebaumsworld\.com/video/watch/\d+#file=)?(http://media\.ebaumsworld\.com/(?:mediaFiles/)?videos?/[0-9/]+\.(?:flv|mp4))(?:-thumb=(.*?\.jpg))?',
		'movie' => 'http://www.ebaumsworld.com/mediaplayer.swf?file=$2&displayheight=325&image=($3|http://media.ebaumsworld.com/img/logobar.jpg)',
		'size' => array(425, 345),
		// http://www.ebaumsworld.com/mediaplayer.swf?file=http://media.ebaumsworld.com/mediaFiles/video/526693/475057.flv
		// http://media.ebaumsworld.com/mediaFiles/video/526693/475057.flv
		'fix-html-pattern' => '<embed src="http://www\.ebaumsworld\.com/mediaplayer\.swf" flashvars="pageurl=(http://www\.ebaumsworld\.com/video/watch/\d+)/&[^>]*/>',
		'lookup-url' => 'http://(?:www\.)?ebaumsworld\.com/video/watch/\d+',
		'lookup-pattern' => array(
			'file=' => '<link rel="video_src" href="[^"]*file=([^&]+)',
			'thumb=' => '<link rel="videothumbnail" href="([^"]+)"'
		),
		'lookup-title' => true,
		// http://www.ebaumsworld.com/video/watch/774350/
	),
	array(
		'id' => 'esp',
		'title' => 'ESPN',
		'website' => 'http://www.espn.com',
		'type' => 'pop',
		'pattern' => 'http://(?:sports\.)?espn\.go\.com/(?:broadband/)?(?:player\.swf\?mediaId=|video/videopage\?[^"]*?videoId=|video/clip\?[^"]*?id=)(\d{1,10})',
		'movie' => 'http://sports.espn.go.com/broadband/player.swf?mediaId=$2',
		'size' => array(440, 361),
		'show-link' => true,
		// http://sports.espn.go.com/broadband/video/videopage?categoryId=2521705&brand=null&videoId=3557384&n8pe6c=2
		'fix-html-pattern' => '<object [^>]*><param name="movie" value="$1"/><param name="wmode" value="transparent"/><param name="allowScriptAccess" value="always"/><embed src="$1"[^>]*></embed></object>',
		'fix-html-url' => 'http://sports.espn.go.com/broadband/video/videopage?videoId=$2',
	),
	array(
		'id' => 'face',
		'title' => 'Facebook',
		'website' => 'http://www.facebook.com',
		'type' => 'pop',
		'pattern' => 'http://(?:www.)?facebook\.com/video/video\.php\?v=(\w+)',
		'movie' => 'http://www.facebook.com/v/$2',
		'size' => array(480, 360),
		// http://www.facebook.com/video/video.php?v=34528240817&oid=16668492047
		'lookup-url' => 'http://(?:www.)?facebook\.com/video/video\.php\?v=(\w+)(?:&oid=\d+)?',
		'lookup-pattern' => array('w' => '"video_width", "(\d+)"', 'h' => '"video_height", "(\d+)"'),
		'lookup-title' => false,
	),
	array(
		'id' => 'flk',
		'title' => 'Flickr',
		'website' => 'http://www.flickr.com',
		'type' => 'pop',
		'pattern' => 'http://www.flickr.com/photos/[^/]+/(\d+)/?#secret(\w+)',
		'movie' => 'http://www.flickr.com/apps/video/stewart.swf?v=66164&photo_secret=$3&photo_id=$2',
		'size' => array(425, 344),
		// http://www.flickr.com/photos/huwp/2403154912/
		// No embed code fix, sorry! Flickr isn't very helpful, as it requires the owner's name but doesn't provide it here...
		'lookup-url' => 'http://www.flickr.com/photos/[^/]+/(\d+)/?',
		'lookup-pattern' => array(
			'secret' => "photo_secret: '(\w+)'",
			'w' => 'stewart_go_go_go\((\d+),',
			'h' => 'stewart_go_go_go\(\d+, (\d+),'
		),
	),
	array(
		'id' => 'fod',
		'title' => 'FunnyOrDie',
		'website' => 'http://www.funnyordie.com',
		'type' => 'video',
		'pattern' => 'http://(?:www\.|www2\.)?funnyordie\.com/(?:videos/|public/flash/fodplayer\.swf\?key=)([0-9a-z]{8,12})',
		'movie' => 'http://www2.funnyordie.com/public/flash/fodplayer.swf?key=$2',
		'size' => array(464, 388),
		// http://www.funnyordie.com/videos/dfef96ec31
		'fix-html-pattern' => '<object [^>]*><param name="movie" value="http://www2\.funnyordie\.com/public/flash/fodplayer\.swf[^"]*" /><param name="flashvars" value="key=([0-9a-z]{8,12})" />.*?</object>(?:<div style="text-align:center;width: \d*px;">[^<]*<a href="http://www\.funnyordie\.com/[^"]*">[^<]*</a>[^<]*</div>)?',
		'fix-html-url' => 'http://www.funnyordie.com/videos/$1',
	),
	array(
		'id' => 'g4t',
		'title' => 'G4TV',
		'website' => 'http://www.g4tv.com',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)?g4tv\.com/(?:xplay/videos/|lv3/|sv3/)(\d{1,10})',
		'movie' => 'http://www.g4tv.com/lv3/$2',
		'size' => array(480, 418),
		// http://www.g4tv.com/xplay/videos/28510/Rock_Band_2_Launch_Party_All_Access.html
		'fix-html-pattern' => '<object [^>]*><param name="movie" value="$1" />.*?</object>',
		'fix-html-url' => 'http://www.g4tv.com/xplay/videos/28510/$2',
	),
	array(
		'id' => 'gko',
		'title' => 'GameKyo', // Formerly Jeux-France
		'website' => 'http://www.gamekyo.com',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)?gamekyo\.com/(?:video|flash/flvplayer\.swf\?videoid=)(\d{1,8})',
		'movie' => 'http://www.gamekyo.com/flash/flvplayer.swf?videoid=$2',
		'size' => array(512, 307),
		// http://www.gamekyo.com/video12982_tales-of-hearts-cg-new-video.html
		'fix-html-pattern' => '<object [^>]*><param name=movie value="$1">.*?</object>',
		'fix-html-url' => 'http://www.gamekyo.com/flash/flvplayer.swf?videoid=$2',
	),
	array(
		'id' => 'gam',
		'title' => 'GameSpot',
		'website' => 'http://www.gamespot.com',
		'type' => 'pop',
		'pattern' => 'http://(?:[a-z]*?\.)?gamespot\.com/[^"]*?video/(?:\d{1,12}/)?(\d{1,12})',
		'movie' => 'http://image.com.com/gamespot/images/cne_flash/production/media_player/proteus/one/proteus2.swf?playerMode=embedded&movieAspect=4.3&flavor=EmbeddedPlayerVersion&skin=http://image.com.com/gamespot/images/cne_flash/production/media_player/proteus/one/skins/gamespot.png&paramsURI=http%3A%2F%2Fwww.gamespot.com%2Fpages%2Fvideo_player%2Fxml.php%3Fid%3D$2%26mode%3Dembedded%26width%3D432%26height%3D362',
		'size' => array(432, 362),
		'show-link' => true,
		// Old style http://uk.gamespot.com/video/929198/6191975/videoplayerpop?
		// New style http://uk.gamespot.com/pc/rpg/fallout3/video/6198384/fallout-3-postmortem-interview
		'fix-html-pattern' => '<embed [^>]*http%3A%2F%2Fwww\.gamespot\.com%2Fpages%2Fvideo_player%2Fxml\.php%3Fid%3D(\d{1,12})[^>]*/>',
		'fix-html-url' => 'http://uk.gamespot.com/video/$1/',
	),
	array(
		'id' => 'gat',
		'title' => 'GameTrailers',
		'website' => 'http://www.gametrailers.com',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)?gametrailers\.com/(?:(?:\w+/)*(?:(u)ser-?movies?/)?[^"<>]*/|remote_wrap\.php\?(u)?mid=)(\d{1,10})',
		'movie' => 'http://www.gametrailers.com/remote_wrap.php?$2$3mid=$4', //Either $2 or $3 will be empty
		'size' => array(480, 392),
		// http://www.gametrailers.com/player/usermovies/258940.html
		// http://www.gametrailers.com/player/33251.html
		'fix-html-pattern' => '<object [^>]*id="gtembed"[^>]*>.*?<param name="movie" value="$1"/>.*?</object>',
		'fix-html-url' => 'http://www.gametrailers.com/remote_wrap.php?$2$3mid=$4',
		'lookup-title' => true,
		// http://www.gametrailers.com/remote_wrap.php?mid=34648
		// http://www.gametrailers.com/remote_wrap.php?umid=34649
	),
	array(
		'id' => 'gab',
		'title' => 'GameTube',
		'website' => 'http://www.gametube.org',
		'type' => 'video',
		'title' => 'Gametube.org',
		'pattern' => 'http://(?:www\.)?gametube\.org/(?:\#/video/|htmlVideo\.jsp\?id=|miniPlayer\.swf\?vidId=)([0-9a-z]{1,3}/(?:[a-z0-9_-]{26})=)',
		'movie' => 'http://www.gametube.org/miniPlayer.swf?vidId=$2',
		'size' => array(425, 335),
		// http://www.gametube.org/#/video/C/66yt67h3i00TvlCxFLd_m16dEY=
		// http://www.gametube.org/htmlVideo.jsp?id=C/66yt67h3i00TvlCxFLd_m16dEY=
		'fix-html-pattern' => '<object [^>]*><param name="movie" value="$1">.*?</object>',
		'fix-html-url' => 'http://www.gametube.org/#/video/$2',
	),
	array(
		'id' => 'gav',
		'title' => 'GameVideos.1up',
		'website' => 'http://gamevideos.1up.com',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)?gamevideos(?:\.1up)?\.com/(?:video/id/|video/embed\?[^"]*?video=)(\d{1,8})',
		'movie' => 'http://gamevideos.1up.com/swf/gamevideos11.swf?embedded=1&fullscreen=1&autoplay=0&src=http://gamevideos.1up.com/video/videoListXML%3Fid%3D$2%26adPlay%3Dfalse',
		'size' => array(500, 319),
		// http://gamevideos.1up.com/video/id/21213
		'fix-html-pattern' => '(?:<div style="width:500px; text-align:center">)?<embed [^>]*http://gamevideos\.1up\.com/video/videoListXML%3Fid%3D(\d{1,10}).*?</embed>(?:<a href="http://gamevideos\.1up\.com/[^"]*" target="_blank">[^<]*</a></div>)?',
		'fix-html-url' => 'http://gamevideos.1up.com/video/id/$1',
	),
	array(
		'id' => 'gar',
		'title' => 'GarageTv',
		'website' => 'http://www.garagetv.be',
		'type' => 'video',
		'pattern' => '(http://www\.garagetv\.be/v/[\w\!-]*?/v\.aspx)',
		'movie' => '$2',
		'size' => array(430, 369),
		// http://www.garagetv.be/v/S5cyfREd!EvQBVM8-ZXkwFPSjFFxoVnf0LSagycZ8pH--wBBPzAyy!usb-Wfkfbas5/v.aspx
		// With Lookup only
		'fix-html-pattern' => '<object [^>]*>\s{0,2}<param name="movie" value="$1" />.*?</object>(?:<noscript>[^<]*<a href="http://www\.garagetv\.be/[^"]*">[^<]*</a>[^<]*</noscript>)?',
		'lookup-url' => 'http://(?:www\.)?garagetv\.be/video-galerij(?:(?:/[\w-]*?){2})\.aspx',
		'lookup-pattern' => '<param\sname=&quot;movie&quot;\svalue=&quot;(http://www\.garagetv\.be/v/[\w\!-]*?/v\.aspx)&quot;\s/>',
		// http://www.garagetv.be/video-galerij/fiedelewie/Cristiano_Ronaldo.aspx
	),
	array(
		'id' => 'goe',
		'title' => 'GoEar',
		'website' => 'http://www.goear.com',
		'type' => 'audio',
		'pattern' => 'http://(?:www\.)?goear\.com/listen(?:\.php\?v=|/)([a-z0-9]{7})',
		'movie' => 'http://www.goear.com/files/external.swf?file=$2',
		'size' => array(353, 132),
		// http://www.goear.com/listen.php?v=31cfa62
		'fix-html-pattern' => '<object [^>]*><embed src="http://www\.goear\.com/files/external\.swf\?file=([a-z0-9]{7})"[^>]*></embed></object>',
		'fix-html-url' => 'http://www.goear.com/listen.php?v=$1',
		'lookup-title' => false,
	),
	array(
		'id' => 'got',
		'title' => 'GotGame',
		'website' => 'http://www.gotgame.com',
		'type' => 'video',
		'pattern' => '(http://tv\.gotgame\.com/flvideo/\d{1,10}\.flv)',
		'movie' => 'http://tv.gotgame.com/player/FlowPlayerDark.swf?config=%7Bembedded%3Atrue%2CshowFullScreenButton%3Atrue%2CshowMuteVolumeButton%3Atrue%2CshowMenu%3Atrue%2CautoBuffering%3Afalse%2CautoPlay%3Afalse%2CinitialScale%3A%27fit%27%2CmenuItems%3A%5Bfalse%2Cfalse%2Cfalse%2Cfalse%2Ctrue%2Ctrue%2Cfalse%5D%2CusePlayOverlay%3Afalse%2CshowPlayListButtons%3Atrue%2CplayList%3A%5B%7Burl%3A%27$2%27%7D%5D%2CcontrolBarGloss%3A%27high%27%2CshowVolumeSlider%3Atrue%2Cloop%3Afalse%2CcontrolBarBackgroundColor%3A%270x808080%27%7D',
		'size' => array(516, 320),
		// http://tv.gotgame.com/flvideo/52.flv
		'fix-html-pattern' => '<iframe src=http://tv\.gotgame\.com/index_embed\.php\?vkey=([a-f0-9]{20})[^<>]*?></iframe>',
		'fix-html-url' => 'http://tv.gotgame.com/view_video.php?vkey=$1',
		'lookup-url' => 'http://tv.gotgame.com/view_video.php?vkey=[a-f0-9]{20}',
		'lookup-pattern' => 'config={videoFile: \'(http://tv\.gotgame\.com/flvideo/(\d{1,10})\.flv)\',',
		// http://tv.gotgame.com/view_video.php?vkey=a42caddbd0694a4e9bdc
	),
	array(
		'id' => 'hub',
		'title' => 'TheHub',
		'website' => 'http://hub.witness.org',
		'type' => 'video',
		'pattern' => 'http://hub\.witness\.org/(?:en|fr|es)/node/(\d{1,10})',
		'movie' => 'http://hub.witness.org/sites/hub.witness.org/modules/contrib-5/flvmediaplayer/mediaplayer.swf?file=http://hub.witness.org/xspf/node/$2&overstretch=fit&repeat=false&logo=http://hub.witness.org/sites/hub.witness.org/themes/witness/images/hub_wm.png',
		'size' => array(400, 280),
		'show-link' => true,
		// http://hub.witness.org/en/node/8754
		// Does this need the (?:amp;)? treatment?
		'fix-html-pattern' => '<object [^>]*><param name="movie"[^<>]*?></param><embed[^<>]*?flashvars="width=\d*&height=\d*&file=http://hub.witness.org/xspf/node/(\d{1,10})[^<>]*?></embed></object>',
		'fix-html-url' => 'http://hub.witness.org/en/node/$1',
		'lookup-title' => true,
	),
	array(
		'id' => 'hul',
		'title' => 'Hulu (Usa Only)',
		'website' => 'http://www.hulu.com',
		'type' => 'pop',
		'pattern' => '(http://(?:www\.)?hulu\.com/embed/[\w-]{10,32})',
		'movie' => '$2',
		'size' => array(512, 296),
		// http://www.hulu.com/embed/OzYt2MT63f4K9G2lmnb3OQ
		'fix-html-pattern' => '<object [^>]*><param name="movie" value="$1"></param><embed src="$1"[^<>]*?></embed></object>',
		'lookup-url' => 'http://(?:www\.)?hulu\.com/watch/\d{1,12}/[\w-]{5,50}',
		'lookup-pattern' => '<link\srel="video_src"\shref="(http://(?:www\.)?hulu\.com/embed/[\w-]{10,50})',
		'lookup-title' => true,
		// http://www.hulu.com/watch/32750/nbc-today-show-in-speech-obama-blasts-mccain
	),
	array(
		'id' => 'hum',
		'title' => 'Humour',
		'website' => 'http://www.humour.com',
		'type' => 'video',
		'pattern' => 'http://video\.humour\.com/videos-comiques/videos/([0-9a-f]{32})\.flv',
		'movie' => 'http://www.humour.com/videos-comiques/player/mediaplayer.swf?file=http://video.humour.com/videos-comiques/videos/$2.flv&http://video.humour.com/videos-comiques/printscreen/large/$2.jpg',
		'size' => array(425, 355),
		'lookup-url' => 'http://(?:www\.)?humour\.com/(?:videos-comiques/videos\.asp\?VIDvideo=|videos\.asp\?num=)(\d{1,12})',
		'lookup-actual-url' => 'http://www.humour.com/videos-comiques/videos.asp?VIDvideo=$1',
		'lookup-pattern' => '<link\srel="image_src"\shref="http://video\.humour\.com/videos-comiques/printscreen/small/([0-9a-f]{32}).jpg"',
		'lookup-final-url' => 'http://video.humour.com/videos-comiques/videos/$1.flv',
		'lookup-title' => false,
		// http://video.humour.com/videos-comiques/videos/01218423cfb3716cd9fb2d43a35a5df5.flv
		// http://www.humour.com/videos-comiques/videos.asp?VIDvideo=8876
		// http://www.humour.com/videos.asp?num=8876 - redirects to the above
	),
	array(
		'id' => 'iua',
		'title' => 'Video.i.ua',
		'website' => 'http://video.i.ua',
		'type' => 'video',
		'pattern' => '(http://i1\.i\.ua/video/vp3\.swf\?9&(?:amp;)?userID=\d{1,20}&(?:amp;)?videoID=\d{1,20}&(?:amp;)?playTime=\d{1,20}&(?:amp;)?repeat=0&(?:amp;)?autostart=0&(?:amp;)?videoSize=\d{1,20}&(?:amp;)?userStatus=\d{1,2}&(?:amp;)?notPreview=\d{1,2}&(?:amp;)?mID=m?\d{1,2})',
		'movie' => '$2',
		'size' => array(450, 349),
		// http://i1.i.ua/video/vp2.swf?9&userID=247839&videoID=57480&playTime=226&repeat=0&autostart=0&videoSize=21006136&userStatus=0&notPreview=0&mID=2
		'lookup-url' => 'http://video\.i\.ua/user/(\d{1,20})/(?:\d{1,20}/)(\d{1,20})/',
		'lookup-pattern' => '"(userID=\d{1,20}&videoID=\d{1,20}&playTime=\d{1,20}&repeat=0&autostart=0&videoSize=\d{1,20}&userStatus=\d{1,2}&notPreview=\d{1,2}&mID=m?\d{1,2})"',
		'lookup-final-url' => 'http://i1.i.ua/video/vp3.swf?9&$1',
		'lookup-title' => false,
		// http://video.i.ua/user/247839/7737/57480/
	),
	array(
		'id' => 'ign',
		'title' => 'IGN',
		'website' => 'http://www.ign.com',
		'type' => 'pop',
		'pattern' => 'http://(?:[a-z0-9]*?\.){0,3}ign\.com/[\w-]*?/objects/(\d{1,10})/(?:[\w-]*?/)?videos/',
		'movie' => 'http://videomedia.ign.com/ev/ev.swf?object_ID=$2',
		'size' => array(433, 360),
		// http://uk.movies.ign.com/dor/objects/826378/quantum-of-solace/videos/quantum_timdezeeuw.html
		// http://uk.ps3.ign.com/dor/objects/714044/metal-gear-solid-4/videos/odetosnake_060208.html
		'fix-html-pattern' => '<embed src=\'http://videomedia\.ign\.com/ev/ev\.swf\' flashvars=\'object_ID=(\d{1,10})[^<>]*?></embed>',
		'fix-html-url' => 'http://www.ign.com/dor/objects/$1/videos/',
	),
	array(
		'id' => 'imd',
		'title' => 'IMDB',
		'website' => 'http://www.imdb.com',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)?totaleclips\.com/Player/Bounce\.aspx\?eclipid=([0-9a-z]{1,12})&(?:amp;)?bitrateid=(\d{1,10})&(?:amp;)?vendorid=(\d{1,10})&(?:amp;)?type=\.flv',
		'movie' => 'http://www.imdb.com/images/js/app/video/mediaplayer.swf?file=http%3A%2F%2Fwww.totaleclips.com%2FPlayer%2FBounce.aspx%3Feclipid%3D$2%26bitrateid%3D$3%26vendorid%3D$4%26type%3D.flv&backcolor=0x000000&frontcolor=0xCCCCCC&lightcolor=0xFFFFCC&shuffle=false&autostart=false',
		'size' => array(480, 380),
		// Videos hosted at amazon (/wab/) won't work because they're RTMP-based.
		'lookup-url' => 'http://(?:www\.)?imdb\.com/video/screenplay/([0-9a-z]+)(?:/player)*',
		'lookup-actual-url' => 'http://www.imdb.com/video/screenplay/$1/player',
		'lookup-pattern' => 'so\.addVariable\("file",\s"([^"]+)"',
		'lookup-urldecode' => 1,
		// http://www.imdb.com/video/screenplay/vi2719285273/player
	),
	array(
		'id' => 'ima',
		'title' => 'ImageShack',
		'website' => 'http://www.imageshack.us',
		'type' => 'pop',
		'pattern' => 'http://img(\d{1,5})\.imageshack\.us/(?:my\.php\?image=|img\d{1,5}/\d{1,8}/|flvplayer\.swf\?f=T)([\w-]{1,20})\.flv',
		'movie' => 'http://img$2.imageshack.us/flvplayer.swf?f=T$3&autostart=false',
		'size' => array(424, 338),
		// http://img531.imageshack.us/my.php?image=duganja2.flv
		// http://img531.imageshack.us/img531/1232/duganja2.flv
	),
	array(
		'id' => 'ind',
		'title' => 'IndyaRocks',
		'website' => 'http://www.indyarocks.com',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)?indyarocks\.com/videos/(?:(?:[^"-]*?-){1,10}|embed-)(\d{1,8})',
		'movie' => 'http://www.indyarocks.com/videos/embed-$2',
		'size' => array(425, 350),
		// http://www.indyarocks.com/videos/Greenpeace-earth-is-breathing-Commercial-177158
		'fix-html-pattern' => '<object [^>]*><param name=\'movie\' value=\'$1\'></param><param name=\'wmode\' value=\'transparent\'></param><embed[^<>]*?></embed></object>',
		'fix-html-url' => 'http://www.indyarocks.com/videos/embed-$2',
	),
	array(
		'id' => 'izl',
		'title' => 'Izlesene',
		'website' => 'http://www.izlesene.com',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)?izlesene\.com/(?:player2\.swf\?video=|video/[\w-]*?/)(\d{1,10})',
		'movie' => 'http://www.izlesene.com/player2.swf?video=$2',
		'size' => array(425, 355),
		// http://www.izlesene.com/video/komik_videolar-chelsea-nin-maskotu/402189
		'fix-html-pattern' => '(?:<div style="width:465px;">)?<embed src="$1"[^<>]*?"></embed>(?:<div style="background:#000000; padding:7px 0px  7px 7px;">.*?</div></div>)?',
		'fix-html-url' => 'http://www.izlesene.com/player2.swf?video=$2',
	),
	array(
		'id' => 'jam',
		'title' => 'Jamendo',
		'website' => 'http://www.jamendo.com',
		'type' => 'audio',
		'pattern' => 'http://(?:www\.|widgets\.)?jamendo\.com/[a-z0-9]*?/album/(?:\?album_id=)?(\d{1,10})',
		'movie' => 'http://widgets.jamendo.com/en/album/?album_id=$2&playertype=2008',
		'size' => array(200, 300),
		// http://www.jamendo.com/en/album/20537
		'fix-html-pattern' => '(?:<div align="center">)?<object width[^<>]*?> <param name="allowScriptAccess" value="always" /><param name="wmode" value="transparent" /> <param name="movie" value="$1" />.*?</object>(?:</div>)?',
		'fix-html-url' => 'http://www.jamendo.com/en/album/$2',
	),
	array(
		'id' => 'jok',
		'title' => 'Jokeroo',
		'website' => 'http://www.jokeroo.com',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)?jokeroo\.com/(auto|educational|financial|health|howto|lawyers|politics|travel|extremesports|funnyvideos)/((?:[0-9a-z]*?/){0,3})?(\w*?)\.htm',
		'movie' => 'http://www.jokeroo.com/promotional_player2.swf?channel&vid=http://uploads.filecabin.com/flash/$4.flv&vid_url=http://www.jokeroo.com/$2/$3$4.html&adv_url',
		'size' => array(490, 425),
		// http://www.jokeroo.com/funnyvideos/bill_clinton_denied_kiss.html
		'fix-html-pattern' => '<object classid[^<>]*?><PARAM NAME=allowFlashAutoInstall VALUE=true><param name=Flashvars value="channel=Funny_Videos&(?:amp;)?vid=http://uploads\.filecabin\.com/flash/\w*?\.flv&(?:amp;)?vid_url=$1[^<>]*?>.*?</object>',
	),
	array(
		'id' => 'juv',
		'title' => 'JujuNation Video',
		'website' => 'http://www.jujunation.com',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)?jujunation.com/viewVideo\.php\?video_id=(\d{1,10})',
		'movie' => 'http://www.jujunation.com/flvplayer.swf?config=http://www.jujunation.com/videoConfigXmlCode.php?pg=video_$2_no_0',
		'size' => array(450, 370),
		// http://www.jujunation.com/viewVideo.php?video_id=4560&title=Mapouka___Nikwes___Mapouka_Cellulaire&cid=
		'fix-html-pattern' => '<embed src="http://www\.jujunation\.com/flvplayer_elite\.swf" FlashVars="config=http://www\.jujunation\.com/videoConfigXmlCode\.php\?pg=video_(\d{1,10})[^<>]*?>',
		'fix-html-url' => 'http://www.jujunation.com/viewVideo.php?video_id=$1',
	),
	array(
		'id' => 'jua',
		'title' => 'JujuNation Audio',
		'website' => 'http://www.jujunation.com',
		'type' => 'audio',
		'pattern' => 'http://(?:www\.)?jujunation.com/music\.php\?music_id=(\d{1,10})',
		'movie' => 'http://www.jujunation.com/player.swf?configXmlPath=http://www.jujunation.com/musicConfigXmlCode.php?pg=music_$2&playListXmlPath=http://www.jujunation.com/musicPlaylistXmlCode.php?pg=music_$2',
		'size' => array(220, 66),
		// http://www.jujunation.com/music.php?music_id=229&title=testing
		'fix-html-pattern' => '<object [^>]*><param name="movie" value="http://www\.jujunation\.com/player\.swf\?configXmlPath=http://www\.jujunation\.com/musicConfigXmlCode\.php\?pg=music_(\d{1,10})[^<>]*?>.*?</object>',
		'fix-html-url' => 'http://www.jujunation.com/music.php?music_id=$1',
	),
	array(
		'id' => 'kal',
		'title' => 'Kaltura network',
		'website' => 'http://www.kaltura.com',
		'type' => 'video',
		'pattern' => '(?:http://[^"]*?kaltura\.com/(?:index\.php/)?kwidget/wid/([\w-]+)|http://[^#"]*?#kaltura#id(\w+))',
		'movie' => 'http://www.kaltura.com/kwidget/wid/$2$3',
		'size' => array(400, 300),
		'show-link' => true,
		// http://arnoldspov.site40.net/?p=34#kaltura
		'fix-html-pattern' => '<object name="kaltura_player_\d+" id="kaltura_player_\d+" [^>]*height="(\d+)" width="(\d+)" data="(http://[^"]*?kaltura\.com/(?:index\.php/)?kwidget/wid/[\w-]+)">.*?</object>',
		'fix-html-url' => '$3#kaltura-w$1-h$2',
		'lookup-url' => 'http://[^#">]+#kaltura',
		'lookup-pattern' => array('id' => 'kwidget/wid/(\w+)', 'w' => 'kwidget/wid/\w+", "[^"]*?player[^"]*", "(\d+)"', 'h' => '"[^"]*?player[^"]*", "\d+", "(\d+)"'),
		'lookup-skip-empty' => true,
	),
	array(
		'id' => 'kew',
		'title' => 'Kewego network',
		'website' => 'http://www.kewego.com',
		'type' => 'pop',
		'pattern' => 'http://([^/]+)/video/([\w-]{12})\.html#kew',
		'movie' => 'http://$2/p/en/$3.html',
		'size' => array(400, 368),
		// http://videos.lefigaro.fr/video/iLyROoafY8-_.html (and dozens of other sites...)
		'fix-html-pattern' => '<object [^>]*data="http://www\.kewego\.com/swf/p3/epix\.swf\??"[^>]*>.*?</object><div [^>]*><a href="(http://[^/]+/video/[\w-]{12}\.html)">.*?</div></div>',
		'lookup-url' => 'http://[^/]+/video/([\w-]{12})\.html',
		'lookup-title' => true,
		'lookup-pattern' => array('kew' => 'kewego\.com/swf'),
		'lookup-skip-empty' => true,
	),
	array(
		'id' => 'kor',
		'title' => 'Koreus',
		'website' => 'http://www.koreus.com',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)?koreus\.com/video/([0-9a-z-]{1,50})(?:\.html)?',
		'movie' => 'http://www.koreus.com/video/$2',
		'size' => array(400, 320),
		// http://www.koreus.com/video/tortue-usb.html
		'fix-html-pattern' => '(?:<div>)<object type="application/x-shockwave-flash" data="$1"[^>]*>.*?</object>(?:<br /><a href="http://www\.koreus\.com/video/.*?</a> - <a.*?</a></div>)?',
		'fix-html-url' => 'http://www.koreus.com/video/$2.html',
	),
	array(
		'id' => 'laa',
		'title' => 'Last.fm (Audio)',
		'website' => 'http://www.last.fm',
		'type' => 'pop',
		'pattern' => 'http://(?:www\.)?(?:last\.fm|lastfm\.[a-z]{2})/music/([^/"\?]+)/_/([\w\+\*%-]*)',
		'movie' => 'http://cdn.last.fm/webclient/s12n/s/5/lfmPlayer.swf?lang=en&lfmMode=playlist&FOD=true&restype=track&artist=$2&resname=$3',
		'size' => array(300, 221),
		'show-link' => true,
		'lookup-title' => '<div id="content">.*?<h1[^>]*>(.*?)</h1>',
		// http://www.lastfm.fr/music/Harmonium/_/Histoires+Sans+Paroles
	),
	array(
		'id' => 'lav',
		'title' => 'Last.fm (Video)',
		'website' => 'http://www.last.fm',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)?(?:last\.fm|lastfm\.[a-z]{2})/music/([^/"\?]+)/\+videos/(\d{2,20})',
		'movie' => 'http://cdn.last.fm/videoplayer/33/VideoPlayer.swf?title=$2&uniqueName=$3&autoStart=false',
		'size' => array(340, 289),
		'show-link' => true,
		'lookup-title' => '<div id="content">.*?<h1[^>]*>(.*?)</h1>',
		// http://www.lastfm.fr/music/Comus/+videos/+1-RY8pJbQSmxs
	),
	array(
		'id' => 'layt',
		'title' => 'Last.fm (YouTube)',
		'website' => 'http://www.last.fm',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)?(?:last\.fm|lastfm\.[a-z]{2})/music/[^/"\?]+/\+videos/\+1-([\w-]{11})',
		'movie' => 'http://www.youtube-nocookie.com/embed/$2?theme=light',
		'size' => array(425, 344),
		'show-link' => true,
		'lookup-title' => '<div id="content">.*?<h1[^>]*>(.*?)</h1>',
		// http://www.lastfm.fr/music/Mike+Oldfield/+videos/16992255
	),
	array(
		'id' => 'lafm',
		'title' => 'Last.fm (Artist)',
		'website' => 'http://www.last.fm',
		'type' => 'audio',
		'pattern' => 'http://(?:www\.)?(?:last\.fm|lastfm\.[a-z]{2})/music/([^/"\?]+)/?',
		'movie' => 'http://cdn.last.fm/webclient/s12n/s/5/lfmPlayer.swf?lang=en&lfmMode=playlist&FOD=true&restype=artist&resname=$2',
		'size' => array(300, 221),
		'show-link' => true,
		'lookup-title' => '<div id="content">.*?<h1[^>]*>(.*?)</h1>',
		// http://www.lastfm.fr/music/J%2ADavey (* should be encoded manually to %2A)
		// http://www.lastfm.fr/music/Comus (oh, and go listen to them, really!!)
	),
	array(
		'id' => 'lib',
		'title' => 'Libero',
		'website' => 'http://www.libero.it',
		'type' => 'video',
		'pattern' => 'http://video\.libero\.it/app/play(?:/index.html)?\?[^"]*?id=([a-f0-9]{32})',
		'movie' => 'http://video.libero.it/static/swf/eltvplayer.swf?id=$2.flv&ap=0',
		'size' => array(400, 333),
		'show-link' => true,
		// http://video.libero.it/app/play?id=daed2f29e33f1ff16c91428f22f1477e
		'fix-html-pattern' => '<object [^>]*><param name="movie" value="http://video\.libero\.it/static/swf/eltvplayer\.swf\?id=([a-f0-9]{32})\.flv[^"]*" />.*?</object>',
		'fix-html-url' => 'http://video.libero.it/app/play?id=$1',
	),
	array(
		'id' => 'liv',
		'title' => 'LiveLeak',
		'website' => 'http://www.liveleak.com',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)?liveleak\.com/(?:player.swf?autostart=false&(?:amp;)?token=|view\?[^"]*?i=|e/)([0-9a-z]{3}_[0-9a-z]{10})',
		'movie' => 'http://www.liveleak.com/e/$2',
		'size' => array(450, 370),
		// http://www.liveleak.com/view?i=fa4_1172336556
		'fix-html-pattern' => '<object [^>]*><param name="movie" value="$1"></param><param name="wmode" value="transparent"></param><embed src="$1"[^<>]*?></embed></object>',
		'fix-html-url' => 'http://www.liveleak.com/view?i=$2',
	),
	array(
		'id' => 'lvv',
		'title' => 'LiveVideo',
		'website' => 'http://www.livevideo.com',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)?livevideo\.com/(?:flvplayer/embed/|video/(?:view/)?(?:[^"]*?/)?)([0-9a-f]{32})',
		'movie' => 'http://www.livevideo.com/flvplayer/embed/$2',
		'size' => array(445, 369),
		// http://www.livevideo.com/video/001AB3E1FB2440C9831782D78070503E/numa-numa-music-video.aspx
		'fix-html-pattern' => '(?:<div>)?<embed src="$1" type="application/x-shockwave-flash" quality="high" WIDTH="\d*" HEIGHT="\d*" wmode="transparent"></embed>(?:<br/><a href="$1">[^<>]*?</a></div>)?',
		'fix-html-url' => 'http://www.livevideo.com/video/$2/.aspx',
	),
	array(
		'id' => 'mac',
		'title' => 'Machinima (Old)',
		'website' => 'http://www.machinima.com',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)?machinima\.com/(?:film/view&(?:amp;)?id=|#details_)(\d{1,8})(?:_contents)?',
		'movie' => 'http://www.machinima.com/_flash_media_player/mediaplayer.swf?file=http://machinima.com/p/$2',
		'size' => array(400, 300),
		// http://machinima.com/film/view&id=28879
		// http://www.machinima.com/#details_26442_contents
	),
	array(
		'id' => 'man',
		'title' => 'Machinima (New)',
		'website' => 'http://www.machinima.com',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)?machinima\.com:80/f/([0-9a-f]{32})',
		'movie' => 'http://machinima.com:80/_flash_media_player/mediaplayer.swf?file=http://machinima.com:80/f/$2',
		'size' => array(400, 300),
		// http://machinima.com:80/f/f37abee44d63333bbe3c96ace97751a2
		'fix-html-pattern' => '<embed src="http://machinima.com:80/_flash_media_player/mediaplayer.swf" width="\d*" height="\d*" flashvars="&file=$1&height=\d*&width=\d*" />',
	),
	array(
		'id' => 'mai',
		'title' => 'Video.mail.ru',
		'website' => 'http://video.mail.ru',
		'type' => 'video',
		'pattern' => 'http://video\.mail\.ru/mail/([\w-]*?)/(\d{1,4})/(\d{1,4})\.html',
		'movie' => 'http://img.mail.ru/r/video2/player_v2.swf?par=http://content.video.mail.ru/mail/$2/$3/\$$4&page=1&username=$2&albumid=$3&id=$4',
		'size' => array(452, 385),
		// http://video.mail.ru/mail/trickfun/3/2.html
		'fix-html-pattern' => '(?:<lj-embed>\s)?\<object [^>]*><param [^>]*><param name="movie"[^<>]*?username=([\w-]*?)&albumid=(\d{1,4})&id=(\d{1,4})&[^<>]*?/>.*?</object>(?:</lj-embed>)?',
		'fix-html-url' => 'http://video.mail.ru/mail/$1$4/$2$5/$3$6.html',
	),
	array(
		'id' => 'mil',
		'title' => 'Milliyet',
		'website' => 'http://video.milliyet.com.tr',
		'type' => 'video',
		'pattern' => '(http://video\.milliyet\.com\.tr/m\.swf\?id=\d{1,12}&(?:amp;)?tarih=\d{4}/\d{2}/\d{1,2})',
		'movie' => '$2',
		'size' => array(340, 325),
		// http://video.milliyet.com.tr/m.swf?id=20732&tarih=2008/09/11
		'fix-html-pattern' => '<object [^>]*><param name="movie" value="$1">.*?</object>',
		'fix-html-url' => '$2',
		'lookup-url' => 'http://video\.milliyet\.com\.tr/default\.asp\?id=\d{1,12}',
		'lookup-pattern' => 'FlashObject\("m\.swf\?(id=\d{1,12}&(?:amp;)?tarih=\d{4}/\d{2}/\d{1,2})",',
		'lookup-final-url' => 'http://video.milliyet.com.tr/m.swf?$1',
		// http://video.milliyet.com.tr/default.asp?id=20732
	),
	array(
		'id' => 'mog',
		'title' => 'Mogulus (Livestream)',
		'website' => 'http://www.livestream.com',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)?(?:mogulus|livestream)\.com/(\w+)/?(?:(?:ondemand/pla_|#id)(\d+))?',
		'movie' => 'http://static.mogulus.com/grid/PlayerV2.swf?channel=$2&contentId=(pla_$3|null)&layout=playerEmbedDefault&backgroundColor=0xffffff&backgroundAlpha=1&backgroundGradientStrength=0&chromeColor=0x0000ff&headerBarGlossEnabled=true&controlBarGlossEnabled=true&chatInputGlossEnabled=false&uiWhite=true&uiAlpha=0.5&uiSelectedAlpha=1&cornerRadius=10&showViewers=true&embedEnabled=true&chatEnabled=true&onDemandEnabled=true&fullScreenEnabled=true&initialIsOn=false&initialIsMute=false&initialVolume=10&initThumbUrl=null&playeraspectwidth=4&playeraspectheight=3&mogulusLogoEnabled=true',
		'size' => array(400, 400),
		'show-link' => true,
		// http://www.mogulus.com/agistritv
		'fix-html-pattern' => '(?:<object [^>]+><param name="movie" value="http://static\.livestream\.com/[^"]+channel=(\w+)[^"]*&contentId=(?:pla_)?(null|\d+)[^>]*>.*?</object>|<script src="http://static\.livestream\.com/[^"]+channel=(\w+)[^"]*&contentId=(?:pla_)?(null|\d+)[^>]+></script>)',
		'fix-html-url' => 'http://www.livestream.com/$1$3/id$2$4',
		'lookup-title' => '<title>Livestream - (.*?)</title>',
	),
	array(
		'id' => 'mpo',
		'title' => 'Mpora',
		'website' => 'http://video.mpora.com',
		'type' => 'video',
		'pattern' => 'http://video\.mpora\.com/watch/(\w{9})',
		'movie' => 'http://video.mpora.com/ep/$2/',
		'size' => array(425, 350),
		// http://video.mpora.com/watch/UsubGMvST/
	),
	array(
		'id' => 'mtv',
		'title' => 'MtvU (Usa Only)',
		'website' => 'http://www.mtvu.com',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)?mtvu\.com/video/\?id=(\d{1,9})[^"]*?vid=(\d{1,9})',
		'movie' => 'http://www.mtvu.com/player/embed/?CONFIG_URL=http://www.mtvu.com/player/embed/configuration.jhtml%3Fid%3D$2%26vid%3D$3',
		'size' => array(423, 318),
		'show-link' => true,
		// http://www.mtvu.com/video/?id=1592281&vid=264273
		'fix-html-pattern' => '<embed src="http://www\.mtvu\.com/player/embed/"[^>]*configuration\.jhtml%3Fid%3D(\d{1,9})%26vid%3D(\d{1,9})&[^>]*></embed>',
		'fix-html-url' => 'http://www.mtvu.com/video/?id=$1&vid=$2',
	),
	array(
		'id' => 'mus',
		'title' => 'MusOpen',
		'website' => 'http://www.musopen.com',
		'type' => 'audio',
		'pattern' => 'http://(?:www\.)?musopensource\.com/files/([^"\'\`\<\>\@\*\$]*?)\.mp3',
		'movie' => 'http://www.musopen.com/wimpy_button.swf?theFile=http://www.musopensource.com/files/$2.mp3',
		'size' => array(35, 35),
		'html-before' => '<div style="border: 1px solid #808080; background: white; width: 350px; text-align: center"><a href="http://www.musopen.com"><img width="89px" height="25px" src="http://www.musopen.com/images/musopen_v.png" alt="" /></a> ',
		'html-after' => '<br />$2</div>', // Since it's just a button, we're making a box for it.
		// http://www.musopensource.com/files/Ludwig%20van%20Beethoven/Symphony%20No.%205%20in%20C%20Minor,%20Op.%2067%20-%20I.%20Allegro%20con%20brio.mp3
		'fix-html-pattern' => '<object type=\'text/html\' width=\'\d*\' height= \'\d*\' data=\'http://www\.musopen\.com/membed\.php\?id=(\d{1,10})[^<>]*?></object>',
		'fix-html-url' => 'http://www.musopen.com/membed.php?id=$1',
		'lookup-url' => 'http://(?:www\.)?musopen\.com/(?:mpopup\.htm(?:l)?\?|membed\.php\?id=)(\d{1,10})',
		'lookup-actual-url' => 'http://www.musopen.com/membed.php?id=$1',
		'lookup-pattern' => '<embed src="wimpy_button\.swf\?theFile=(http://www\.musopensource\.com/files/[^"\'\`<>\@\*\$]*?\.mp3)" ',
		'lookup-unencode' => 1, // The string is encoded - to prevent double encoding, we unencode
		// http://www.musopen.com/mpopup.htm?158&keepThis=true&TB_iframe=true&height=280&width=568
		// http://www.musopen.com/membed.php?id=163
	),
	array(
		'id' => 'mys',
		'title' => 'MySpace',
		'website' => 'http://vids.myspace.com',
		'type' => 'pop',
		'pattern' => 'http://(?:vids\.myspace|myspacetv)\.com/index\.cfm\?[^"]*?VideoID=(\d{1,10})',
		'movie' => 'http://mediaservices.myspace.com/services/media/embed.aspx/m=$2',
		'size' => array(425, 360),
		// http://vids.myspace.com/index.cfm?fuseaction=vids.individual&VideoID=41173982
		'fix-html-pattern' => '(?:<a href="http://vids\.myspace\.com/[^"]*">[^<>]*?</a><br/>)?<object width="\d*px" height="\d*px" >.*?myspace.com/services/media/embed.aspx/m=(\d{1,9})[^<>]*?>.*?</object>',
		'fix-html-url' => 'http://vids.myspace.com/index.cfm?fuseaction=vids.individual&videoid=$1',
	),
	array(
		'id' => 'mytara',
		'title' => 'MyTaratata',
		'website' => 'http://www.mytaratata.com',
		'type' => 'video',
		'pattern' => 'http://www\.mytaratata\.com/Pages/VIDEO_page_video\.aspx?[^"]*?sig=([\w-]{12})',
		'movie' => 'http://www.kewego.com/swf/p3/epix.swf?language_code=fr&playerKey=5861a1b51db9&skinKey=&sig=$2&autostart=false',
		'size' => array(400, 300),
		'show-link' => true,
		// http://www.mytaratata.com/Pages/VIDEO_page_video.aspx?sig=iLyROoafY2_E (and for good ol' funky Shaft: iLyROoaft5fh)
		// If you want to enable fix-html, use the code below. Disabled by default because it could break other "rogue" Kewego-powered sites
		// 'fix-html-pattern' => '<object id="([\w-]{12})"[^>]*data="http://www\.kewego\.com/swf/p3/epix\.swf"[^>]*>.*?</object>',
		// 'fix-html-url' => 'http://www.mytaratata.com/Pages/VIDEO_page_video.aspx?sig=$1',
	),
	array(
		'id' => 'myv',
		'title' => 'MyVideo',
		'website' => 'http://www.myvideo.de',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)?myvideo\.(at|be|ch|de|nl)/(?:watch|movie)/(\d{1,8})',
		'movie' => 'http://www.myvideo.$2/movie/$3',
		'size' => array(470, 406),
		// http://www.myvideo.de/watch/2480593/O_zone_Dragosta_Din_Tei_Numa_Numa
		'fix-html-pattern' => '<object [^>]*data=\'$1\'>.*?</object>(?:<br/><a href=\'$1\' title=\'[^\'<>]*?\'>[^<>]*?</a>)?',
		'fix-html-url' => 'http://www.myvideo.$2/watch/$3',
	),
	array(
		'id' => 'myi',
		'title' => 'MyVi',
		'website' => 'http://myvi.ru',
		'type' => 'video',
		'pattern' => '(http://(?:www\.)?myvi\.ru/ru/flash/player/[\w-]{45})',
		'movie' => '$2',
		'size' => array(450, 418),
		// http://myvi.ru/ru/flash/player/oYgfJNg6z-zbk2XKv_Ak6rWauEDWoOLAf6Dpkxgj0t2I1
		'fix-html-pattern' => '<object [^>]*><param name="movie" value="$1" /><param name="wmode" value="window" /><param name="allowFullScreen" value="true" /><embed[^<>]*?></embed></object>',
		'fix-html-url' => '$2',
		'lookup-url' => 'http://(?:www\.)?myvi\.ru/ru/videodetail\.aspx\?video=[\w-]{32}&(?:amp;)?ap=\d',
		'lookup-pattern' => '&lt;param name=&quot;movie&quot; value=&quot;(http://(?:www\.)?myvi\.ru/ru/flash/player/[\w-]{45})&quot; />',
		// http://myvi.ru/ru/videodetail.aspx?video=1bccf0fba502452e825f40e5bc7e2dd0&ap=0
	),
	array(
		'id' => 'mth',
		'title' => 'M Thai',
		'website' => 'http://video.mthai.com',
		'type' => 'video',
		'pattern' => 'http://video\.mthai\.com/player\.php\?[^"]*?id=([0-9a-z]{14,20})',
		'movie' => 'http://video.mthai.com/Flash_player/player.swf?idMovie=$2',
		'size' => array(407, 342),
		// http://video.mthai.com/player.php?id=14M1178699702M218
		'fix-html-pattern' => '<object classid[^<>]*?><param name="movie" value="http://video\.mthai\.com/Flash_player/player\.swf\?idMovie=([0-9a-z]{14,20})"></param><embed [^<>]*?></embed></object>(?:<br><font size=1>.*?</font><br>)?',
		'fix-html-url' => 'http://video.mthai.com/player.php?id=$1',
	),
	array(
		'id' => 'new',
		'title' => 'NewGrounds',
		'website' => 'http://www.newgrounds.com',
		'type' => 'other',
		'pattern' => '(http://uploads\.ungrounded\.net/\d{1,12}/\d{1,12}_[\w-]*?\.swf)',
		'movie' => '$2?autostart=false&autoplay=false',
		'size' => array(480, 400),
		// http://uploads.ungrounded.net/123000/123876_peonbond1.swf
		'lookup-url' => 'http://(?:www\.)?newgrounds\.com/portal/view/\d{1,10}',
		'lookup-pattern' => '\'(http://uploads\.ungrounded\.net/\d{1,12}/\d{1,12}_[\w-]*?\.swf)\'',
		// http://www.newgrounds.com/portal/view/123876
	),
	array(
		'id' => 'nha',
		'title' => 'NhacCuaTui',
		'website' => 'http://www.nhaccuatui.com',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)?nhaccuatui\.com/(?:nghe\?M=|m/)([\w-]{10})',
		'movie' => 'http://www.nhaccuatui.com/m/$2',
		'size' => array(300, 270),
		'show-link' => true,
		// http://nhaccuatui.com/nghe?M=k1WaVuxlvY
		'fix-html-pattern' => '<object [^>]*><param name="movie" value="$1" /><param name="quality" value="high" /><param name="wmode" value="transparent" /><embed[^<>]*?></embed></object>',
		'fix-html-url' => 'http://www.nhaccuatui.com/nghe?M=$2',
	),
	array(
		'id' => 'offu',
		'title' => 'OffUHuge',
		'website' => 'http://www.offuhuge.com',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)?offuhuge\.com/media/([^/]+)',
		'movie' => 'http://www.offuhuge.com/Main.swf?conf=http://www.offuhuge.com/flv_player/data/playerConfigEmbed/$2.xml&guide=http://www.offuhuge.com/flv_player/data/guide/$2.xml',
		'size' => array(464, 353),
		'show-link' => true,
		// http://www.offuhuge.com/media/198014/Obama_and_McCain_-_Dance_Off/
		'fix-html-pattern' => '<object [^>]*><param name=\'movie\' value=\'http://www\.offuhuge\.com/Main\.swf\' /> <param name=\'FlashVars\' value=\'conf=http://www\.offuhuge\.com/flv_player/data/playerConfigEmbed/([^.]+)\.xml(?:.*?)</a>',
		'fix-html-url' => 'http://www.offuhuge.com/media/$1/',
	),
	array(
		'id' => 'oni',
		'title' => 'The Onion',
		'website' => 'http://www.theonion.com',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)?theonion\.com/content/video/\w+#id(\d+)',
		'movie' => 'http://www.theonion.com/content/themes/common/assets/onn_embed/embedded_player.swf?image=http%3A%2F%2Fwww.theonion.com%2Fcontent%2Fthemes%2Fonion%2Fonn%2Fimages%2Fonn_banner.png&videoid=$2',
		'size' => array(480, 430),
		'show-link' => true,
		// http://www.theonion.com/content/video/sony_releases_new_stupid_piece_of
		'fix-html-pattern' => '<object [^>]+>(?:<param [^>]+>)+<embed [^>]+http://www\.theonion\.com/[^>]+videoid=(\d+)[^>]+></embed></object><br /><a href="(http://www\.theonion\.com/content/video/\w+)">.*?</a>',
		'fix-html-url' => '$2#id$1',
		'lookup-title' => '<title>(.*?)(?:\|\s+The Onion|</title>)',
		'lookup-url' => 'http://(?:www\.)?theonion\.com/content/video/\w+',
		'lookup-pattern' => array('id' => '<meta name="nid" content="(\d+)" />'),
	),
	array(
		'id' => 'orb',
		'title' => 'Orb',
		'website' => 'http://www.orb.com',
		'type' => 'other',
		'pattern' => 'http://mycast\.orb\.com/orb/html/qs\?mediumId=([0-9a-z]{8})&(?:amp;)?l=([\w-]{1,20})',
		'movie' => 'http://mycast.orb.com/orb/resources/common/videoplayer.swf?file=http%3A%2F%2Fmycast.orb.com%2Forb%2Fxml%2Fstream%3FstreamFormat%3Dswf%26mediumId%3D$2%26l%3D$3&showdigits=true&autostart=false&shuffle=false&showeq=true&showfsbutton=true',
		'size' => array(439, 350),
		'show-link' => true,
		// http://mycast.orb.com/orb/html/qs?mediumId=OJwKeGsP&l=sco0by
	),
	array(
		'id' => 'pho',
		'title' => 'Photobucket',
		'website' => 'http://www.photobucket.com',
		'type' => 'pop',
		'pattern' => 'http://([si](\w{1,5}))\.photobucket\.com/albums/((?:[\w%-]{1,50}/){1,10})(?:\?[^"]*?current=)?([\w%-]{1,50}\.(?:pbr|flv|mp4))',
		'movie' => 'http://$2.photobucket.com/player.swf?file=http://vid$3.photobucket.com/albums/$4$5',
		'size' => array(448, 361),
		'show-link' => true,
		// http://s101.photobucket.com/albums/m60/davehu/?action=view&current=59bf3b52.pbr
	),
	array(
		'id' => 'pro',
		'title' => 'Project Playlist',
		'website' => 'http://www.playlist.com',
		'type' => 'audio',
		'pattern' => 'http://(?:www\.)?playlist\.com/(?:playlist|standalone|node)/(\d{1,10})',
		'movie' => 'http://www.profileplaylist.net/mc/mp3player_new.swf?tomy=http%3A%2F%2Fwww.profileplaylist.net%2Fext%2Fpc%2Fconfig_black_noautostart.xml&mywidth=435&myheight=270&file=http%3A%2F%2Fwww.profileplaylist.net%2Floadplaylist.php%3Fplaylist%3D$2',
		'size' => array(435, 270),
		'link' => true,
		// http://www.playlist.com/node/2896442
	),
	array(
		'id' => 'ram',
		'title' => 'Rambler',
		'website' => 'http://vision.rambler.ru',
		'type' => 'video',
		'pattern' => 'http://vision\.rambler\.ru/(?:i/e\.swf\?id=|users/)([\w-]/\d*/\d*)',
		'movie' => 'http://vision.rambler.ru/i/e.swf?id=$2&logo=1',
		'size' => array(390, 370),
		// http://vision.rambler.ru/users/forum-bumerang/1/119/
		// http://vision.rambler.ru/i/e.swf?id=forum-bumerang/1/119&logo=1
		'fix-html-pattern' => '<object classid[^<>]*?><param name="wmode" value="transparent"></param><param name="movie" value="$1" /><embed[^<>]*?/></object>',
		'fix-html-url' => 'http://vision.rambler.ru/users/$2/',
	),
	array(
		'id' => 'rut',
		'title' => 'RuTube',
		'website' => 'http://www.rutube.ru',
		'type' => 'video',
		'pattern' => 'http://(?:www\.|video\.)?rutube\.ru/(?:tracks/\d+?\.html\?(?:(?:pos|related)=1&(?:amp;)?)?v=)?([0-9a-f]{32})',
		'movie' => 'http://video.rutube.ru/$2',
		'size' => array(470, 353),
		// http://rutube.ru/tracks/2409731.html?v=2b490d603fabc02ee2a02cebb0827906
		// http://video.rutube.ru/34a9fbbe09d9e6baaac8fe8b86db61b0
		'fix-html-pattern' => '<OBJECT width="\d*?" height="\d*?"><PARAM name="movie" value="$1">.*?</OBJECT>',
	),
	array(
		'id' => 'scri',
		'title' => 'Scribd',
		'website' => 'http://www.scribd.com',
		'type' => 'other',
		'pattern' => 'http://www\.scribd\.com/doc/(\d+)/?[\w-]*/?#key(\w+)',
		'movie' => 'http://d.scribd.com/ScribdViewer.swf?document_id=$2&access_key=key-$3&page=1&version=1&viewMode=&play=true&loop=true&scale=showall&devicefont=false&bgcolor=#ffffff&menu=true&salign=',
		'size' => array(0, 500),
		// http://www.scribd.com/doc/6245653/Asian-Journal-Sep-26-2008
		'fix-html-pattern' => '(?:<a title="[^>]*>[^<]*</a>)?\s*<object [^>]*>\s*<param name="movie"\svalue="http://d\.scribd\.com/[^>]*>.*?ScribdViewer\.swf\?document_id=(\d+)&access_key=key-(\w+).*?</object>\s*(?:<div style="margin:.*?</div>\s*)?',
		'fix-html-url' => 'http://www.scribd.com/doc/$1/#key$2',
		'lookup-title' => true,
		'lookup-url' => 'http://www\.scribd\.com/doc/(\d+)/?[\w-]*/?',
		'lookup-pattern' => array('key' => 'access_key=key-(\w+)'),
	),
	array(
		'id' => 'sev',
		'title' => 'SevenLoad',
		'website' => 'http://www.sevenload.com',
		'type' => 'video',
		'pattern' => 'http://((?:en|tr|de|www)\.)?sevenload\.com/(?:videos|videolar)/([0-9a-z]{1,8})',
		'movie' => 'http://$2sevenload.com/pl/$3/425x350/swf',
		'size' => array(425, 350),
		// http://en.sevenload.com/videos/8DbE5eku-Numanumaye
		'fix-html-pattern' => '(?:(?:<script type="text/javascript" src="http://((?:en|tr|de|www)\.|)?sevenload\.com/pl/([0-9a-z]{1,8})/[^"]*"></script>(?:<p>[^<>]*?<a href="http://[^<>]*?>(?:<img)?[^<>]*?(?:\/>)?</a></p>)?)|(?:<object type="application/x-shockwave-flash" data="http://((?:en|tr|de|www)\.|)?sevenload\.com/pl/([0-9a-z]{1,8})/[^"]*" [^>]*>.*?</object>(?:<p>[^<>]*?<a href="[^<>]*?>[^<>]*?</a></p>)?))',
		'fix-html-url' => 'http://$1$3sevenload.com/videos/$2$4',
	),
	array(
		'id' => 'yvs',
		'title' => 'Sina Podcast',
		'website' => 'http://v.sina.com.cn',
		'type' => 'video',
		'pattern' => 'http://(?:vhead\.blog|you\.video)\.sina\.com\.cn/(?:player/[^"]*?vid=|b/)(\d{5,12})(?:-|&(?:amp;)?uid=)(\d{5,12})',
		'movie' => 'http://vhead.blog.sina.com.cn/player/outer_player.swf?auto=0&vid=$2&uid=$3',
		'size' => array(480, 370),
		// http://you.video.sina.com.cn/b/15860970-1511607405.html
		// http://vhead.blog.sina.com.cn/player/outer_player.swf?auto=1&vid=15860970&uid=1511607405
		'fix-html-pattern' => '(?:<div>)<object id="ssss" width="\d*" height="\d*" ><param name="allowScriptAccess" value="always" /><embed[^<>]*?src="$1" [^<>]*?></embed></object>(?:</div>)?',
		'fix-html-url' => 'http://you.video.sina.com.cn/b/$2-$3.html',
	),
	array(
		'id' => 'smo',
		'title' => 'Smotri',
		'website' => 'http://www.smotri.com',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)?smotri\.com/video/view/\?id=v([0-9a-f]{10})',
		'movie' => 'http://pics.smotri.com/scrubber_custom8.swf?file=v$2&bufferTime=3&autoStart=false&str_lang=eng&xmlsource=http%3A%2F%2Fpics.smotri.com%2Fcskins%2Fblue%2Fskin_color_lightaqua.xml&xmldatasource=http%3A%2F%2Fpics.smotri.com%2Fskin_ng.xml',
		'size' => array(400, 330),
		// http://smotri.com/video/view/?id=v435023923d
		'fix-html-pattern' => '<object [^>]*><param name="movie" value="http://pics\.smotri\.com/scrubber_custom8\.swf\?file=v([0-9a-f]{10})[^<>]*?>.*?</object>(?:<div style="margin-left: 9px;.*?</div>)?',
		'fix-html-url' => 'http://www.smotri.com/video/view/?id=v$1',
	),
	array(
		'id' => 'sno',
		'title' => 'Snotr',
		'website' => 'http://www.snotr.com',
		'type' => 'video',
		'pattern' => 'http://(?:www\.|videos\.)?snotr\.com/(?:player\.swf\?video=|)?(?:video|embed)/(\d{1,8})',
		'movie' => 'http://www.snotr.com/player.swf?v6&video=$2&embedded=true&autoplay=false',
		'size' => array(400, 330),
		// http://www.snotr.com/video/1210
		'fix-html-pattern' => '<iframe src="$1"[^<>]*?></iframe>',
		'fix-html-url' => 'http://www.snotr.com/video/$2',
	),
	array(
		'id' => 'sou',
		'title' => 'SouthPark Studios',
		'website' => 'http://www.southparkstudios.com',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)?southparkstudios\.com/clips/(\d{1,10})',
		'movie' => 'http://media.mtvnservices.com/mgid:cms:item:southparkstudios.com:$2:',
		'size' => array(480, 360),
		// http://www.southparkstudios.com/clips/183600
		'fix-html-pattern' => '<embed src="http://media\.mtvnservices\.com/mgid:cms:item:southparkstudios\.com:(\d+)[^>]*></embed>',
		'fix-html-url' => 'http://www.southparkstudios.com/clips/$1',
		'lookup-title' => '<h1>(.*?)</h1>',
	),
	array(
		'id' => 'spi',
		'title' => 'Spike',
		'website' => 'http://www.spike.com',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)?spike\.com/(?:video/(?:[\w-]{2,30})?/|efp\?flvbaseclip=)(\d{4,12})',
		'movie' => 'http://www.spike.com/efp?flvbaseclip=$2&',
		'size' => array(448, 365),
		// http://www.spike.com/video/don-lafontaine-voice/3028523
		'fix-html-pattern' => '<embed [^>]name=\'efp\'[^>]*flashvars=\'flvbaseclip=(\d{4,12})&.*?</embed>(?:\s{0,3}<br />\s{0,3}<a href=\'http://www\.spike\.com/[^<>]*?>[^<>]*?</a>)?',
		'fix-html-url' => 'http://www.spike.com/video//$1',
	),
	array(
		'id' => 'str',
		'title' => 'Streetfire',
		'website' => 'http://www.streetfire.net',
		'type' => 'video',
		'pattern' => 'http://(?:www\.|videos\.)?streetfire\.net/(?:vidiac\.swf\?video=|video/)([0-9a-z]{8}(?:-[0-9a-z]{4}){3}-[0-9a-z]{12})(?:\.htm)?',
		'movie' => 'http://videos.streetfire.net/vidiac.swf?video=$2',
		'size' => array(428, 352),
		// http://videos.streetfire.net/video/c47d3e81-ee2a-4cc7-84d7-9b0701566c76.htm
		'fix-html-pattern' => '<object [^>]*><param name="movie" value="$1.*?</object>',
		'fix-html-url' => 'http://videos.streetfire.net/video/$2.htm',
		// http://videos.streetfire.net/video/How-To-Almost-Trash-Your_180164.htm
		'lookup-url' => 'http://(?:www\.|videos\.)?streetfire\.net/video/[0-9a-z]{8}(?:-[0-9a-z]{4}){3}-[0-9a-z]{12}[\w-]*?\.htm',
		'lookup-pattern' => '<link rel="video_src" href="http://videos\.streetfire\.net/vidiac\.swf\?video=([0-9a-z]{8}(?:-[0-9a-z]{4}){3}-[0-9a-z]{12})" />',
		'lookup-final-url' => 'http://videos.streetfire.net/video/$1.htm',
		'lookup-title' => true,
	),
	array(
		'id' => 'stu',
		'title' => 'StupidVideos',
		'website' => 'http://www.stupidvideos.com',
		'type' => 'video',
		'pattern' => 'http://(?:www\.|images\.)?stupidvideos\.com/(?:video/[^"\#]*?\#|images/player/player\.swf\?sa=1&(?:amp;)?sk=7&(?:amp;)?si=2&(?:amp;)?i=)(\d{1,10})',
		'movie' => 'http://images.stupidvideos.com/images/player/player.swf?sa=1&sk=7&si=2&i=$2',
		'size' => array(451, 433),
		// http://www.stupidvideos.com/video/Woman_Vs_Parking_Gate/#170731
		'fix-html-pattern' => '<object [^>]*><param name="movie" value="http://images\.stupidvideos\.com/images/player/player\.swf\?sa=1&(?:amp;)?sk=\d&(?:amp;)?si=\d&(?:amp;)?i=(\d{1,10})"></param><embed[^<>]*?></embed></object>',
		'fix-html-url' => 'http://images.stupidvideos.com/images/player/player.swf?sa=1&sk=7&si=2&i=$1',
		'lookup-url' => 'http://(?:www\.)?stupidvideos\.com/video/(?:[^"\]\#]*)',
		'lookup-pattern' => 'var videoID = \'(\d{1,10})\';',
		'lookup-final-url' => '$2#$1',
		// http://www.stupidvideos.com/video/stunts/Woman_Vs_Parking_Gate/ (Lookup to get the anchor id #170731)
	),
	array(
		'id' => 'tag',
		'title' => 'TagTélé',
		'website' => 'http://www.tagtele.com',
		'type' => 'video',
		'pattern' => 'http://www\.tagtele\.com/(?:v/|videos/voir/)(\d{1,12})',
		'movie' => 'http://www.tagtele.com/v/$2',
		'size' => array(425, 350),
		// http://www.tagtele.com/videos/voir/25744
		'fix-html-pattern' => '<object [^>]*><param name="movie" value="$1"></param><param name="wmode" value="transparent"></param><embed[^<>]*?></embed></object>',
		'fix-html-url' => 'http://www.tagtele.com/videos/voir/$2',
	),
	array(
		'id' => 'god',
		'title' => 'Tangle',
		'website' => 'http://www.tangle.com',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)?tangle\.com/view_video\.php\?[^"]*?viewkey=([0-9a-f]{20})',
		'movie' => 'http://www.tangle.com/flash/swf/flvplayer.swf?viewkey=$2',
		'size' => array(330, 270),
		'show-link' => true,
		// http://www.tangle.com/view_video.php?viewkey=9c69d4acc9dd49eaf89f
		'fix-html-pattern' => '<embed src="http://tangle\.com/flvplayer\.swf" FlashVars="viewkey=([0-9a-f]{20})"[^>]*/></embed>',
		'fix-html-url' => 'http://www.tangle.com/view_video.php?viewkey=$1',
	),
	array(
		'id' => 'tin',
		'title' => 'TinyPic',
		'website' => 'http://www.tinypic.com',
		'type' => 'pop',
		'pattern' => 'http://(?:www\.|\w[\w-]+\.)?tinypic\.com/(?:player\.php\?v=|[rm]/)([0-9a-z-]{1,12})(?:(?:&s=|&amp;s=|/)(\d+))?',
		'movie' => 'http://(v$3|v3).tinypic.com/player.swf?file=$2&s=$3',
		'size' => array(440, 420),
		// http://tinypic.com/player.php?v=2prd7gk&s=3
		'fix-html-pattern' => '<embed [^>]*src="http://v(\d+)\.tinypic\.com/player\.swf\?file=([0-9a-z-]{1,12})[^<>]*?>(?:</embed>)?(?:<br><font size="1"><a href="http://tinypic\.com/.*?</font>)?(?:<img style="visibility:hidden;[^<>]*?>)?',
		'fix-html-url' => 'http://tinypic.com/player.php?v=$2&s=$1',
	),
	array(
		'id' => 'tmt',
		'title' => 'Tm-Tube',
		'website' => 'http://www.tm-tube.com',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)?tm-tube\.com/video/(\d+)/',
		'movie' => 'http://www.tm-tube.com/vimp.swf?hosturl=http%3A%2F%2Fwww.tm-tube.com%2Fflashcomm.php&mediaid=$2&autolightsoff=false&webtv=false&context=embed',
		'size' => array(480, 360),
		'show-link' => true,
		'allow-script' => true,
		// http://www.tm-tube.com/video/1350/zoom-zoom
		'fix-html-pattern' => '<script type="text/javascript" src="(http://www\.tm-tube\.com/js/embed\.js\.php\?key=[0-9a-f]+)"></script>',
		'lookup-url' => 'http://www\.tm-tube\.com/js/embed\.js\.php\?key=[0-9a-f]+(?:&width=\d+&height=\d+)?',
		'lookup-pattern' => array(
			'id' => 'mediaid = .\'(\d+)',
			'w' => 'params\.width = (\d+)',
			'h' => 'params\.height = (\d+)',
		),
		'lookup-final-url' => 'http://www.tm-tube.com/video/$1/',
	),
	array(
		'id' => 'tra',
		'title' => 'TrailerAddict',
		'website' => 'http://www.traileraddict.com',
		'type' => 'video',
		'pattern' => '(http://(?:www\.)?traileraddict\.com/em[bd]/\d+)',
		'movie' => '$2',
		'size' => array(520, 317),
		// http://www.traileraddict.com/emb/5338
		'fix-html-pattern' => '<object [^>]*><param name="movie" value="$1">(?:</param>)?(?:<param name="[^"]+" value="[^"]+"></param>)*<embed [^>]*></embed></object>',
		'lookup-url' => 'http://(?:www\.)?traileraddict\.com/(?:trailer|clip)/[\w-]*?/[\w-]*',
		'lookup-pattern' => 'movie:"/em[bd]/(\d+)"',
		'lookup-final-url' => 'http://www.traileraddict.com/emb/$1',
		'lookup-title' => '<h1>(.*?)</h1>',
		// http://www.traileraddict.com/trailer/day-the-earth-stood-still/teaser-trailer
	),
	array(
		'id' => 'trt',
		'title' => 'TrTube',
		'website' => 'http://www.trtube.com',
		'type' => 'video',
		'pattern' => '(http://(?:www\.)?trtube\.com/mediaplayer_\d{1,2}_\d{1,2}\.swf\?file=http://(?:www\.)?trtube\.com/(?:playlist\.php\?v=\d+|vid\d{0,2}/\d{1,10}.flv))',
		'movie' => '$2',
		'size' => array(425, 350),
		// http://www.trtube.com/mediaplayer_3_15.swf?file=http://www.trtube.com/vid2/77983.flv
		'fix-html-pattern' => '<object [^>]*>(?:<param [^>]+>)*<embed src="$1[^<>]*></object>',
		'lookup-url' => 'http://(?:www\.)?trtube\.com/izle\.php\?v=[0-9a-z]{10}',
		'lookup-pattern' => '(http://(?:www\.)?trtube\.com/mediaplayer_\d{1,2}_\d{1,2}\.swf\?file=http://(?:www\.)?trtube\.com/(?:playlist\.php\?v=\d+|vid\d{0,2}/\d{1,10}.flv))',
		// http://trtube.com/izle.php?v=ycthrwspzp
	),
	array(
		'id' => 'tru',
		'title' => 'Trilulilu',
		'website' => 'http://www.trilulilu.ro',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)?trilulilu\.ro/([\w-]*?)/([0-9a-f]{14})(?:#(audio|video)?)?',
		'movie' => array(
			'normal' => 'http://www.trilulilu.ro/embed/flash.php?type=video&hash=$3&userid=$2',
			'video' => 'http://www.trilulilu.ro/embed/flash.php?type=video&hash=$3&userid=$2',
			'audio' => 'http://www.trilulilu.ro/embed/flash.php?type=audio&hash=$3&userid=$2',
		),
		'size' => array(448, 386),
		// http://www.trilulilu.ro/ellenita/517f63a76a6fda
		'fix-html-pattern' => '<object [^>]*><param name="movie" value="http://www\.trilulilu\.ro/embed/flash\.php\?type=video&(?:amp;)?hash=([0-9a-f]{14})&(?:amp;)?userid=([\w-]*)[^<>]*?">.*?</object>',
		'fix-html-url' => 'http://www.trilulilu.ro/$2/$1',
		'lookup-url' => 'http://(?:www\.)?trilulilu\.ro/([\w-]*?)/([0-9a-f]{14})',
		'lookup-pattern' => array(
			'video' => 'SWFObject\("http://static\.trilulilu\.ro/flash/videoplayer\d+\.swf",',
			'audio' => 'SWFObject\("http://static\.trilulilu\.ro/flash/audioplayer\d+\.swf",',
			'h' => '"viewfileswfobj", "\d+", "(\d+)"',
		),
	),
	array(
		'id' => 'truv',
		'title' => 'Truveo',
		'website' => 'http://www.truveo.com',
		'type' => 'video',
		'pattern' => '(?:http://xml\.truveo\.com/eb/i/(\d+)/a/(\w+)/p/1|http://(?:www\.)?truveo\.com/[^/]*/?id/\d+#id(\d+)-key(\w+))',
		'movie' => 'http://xml.truveo.com/eb/i/$2$4/a/$3$5/p/1',
		'size' => array(448, 386),
		'show-link' => true,
		// http://www.truveo.com/id/1240391518
		'fix-html-pattern' => '<embed [^>]*src="(http://xml\.truveo\.com/eb/i/\d+/a/\w+/p/1)"[^>]*></embed>(?:<H1 style.*?</H1>\s*)?(?:<div style="[^>]*>.*?</div>)?',
		'lookup-url' => 'http://(?:www\.)truveo\.com/[^/]*/?id/\d+',
		'lookup-pattern' => array(
			'id' => 'videourl = "http://xml\.truveo\.com/rd\?i=(\d+)&',
			'key' => 'videourl = "http://xml\.truveo\.com/rd\?i=\d+&a=(\w+)&',
			'w' => 'width="\s*(\d+)" height="\s*\d+" name="player',
			'h' => 'width="\s*\d+" height="\s*(\d+)" name="player',
		),
	),
	array(
		'id' => 'tut',
		'title' => 'Tu',
		'website' => 'http://www.tu.tv',
		'type' => 'video',
		'title' => 'Tu.tv',
		'pattern' => '(http://tu\.tv/tutvweb\.swf\?xtp=\d{1,10})',
		'movie' => '$2',
		'size' => array(425, 350),
		// http://tu.tv/tutvweb.swf?xtp=312541
		'fix-html-pattern' => '<object [^>]*><param name="movie" value="http://tu\.tv/[^"]*?xtp=(\d{1,10})"></param><param name="wmode" value="transparent"></param><embed[^<>]*?></embed></object>(?:<br /><a href="http://www\.tu\.tv"><img[^<>]*?></a><br />)?',
		'fix-html-url' => 'http://tu.tv/tutvweb.swf?xtp=$1',
		'lookup-url' => 'http://(?:www\.)?tu\.tv/videos/[\w-]{3,50}',
		'lookup-pattern' => '/videoFull/\?codVideo=(\d{1,10})',
		'lookup-final-url' => 'http://tu.tv/tutvweb.swf?xtp=$1',
		// http://tu.tv/videos/un-chico-astuto-en-un-examen
	),
	array(
		'id' => 'tud',
		'title' => 'Tudou',
		'website' => 'http://www.tudou.com',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)?tudou\.com/(?:programs/view/|v/)([a-z0-9-]{1,12})',
		'movie' => 'http://www.tudou.com/v/$2',
		'size' => array(400, 300),
		// http://www.tudou.com/programs/view/4ophzSCH_Z0/
	),
	array(
		'id' => 'ust',
		'title' => 'Ustream',
		'website' => 'http://www.ustream.tv',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)?ustream\.tv/(?:flash/live/(?:1/)?(\d+)|channel/[\w-]+/?#id(\d+))',
		'movie' => 'http://www.ustream.tv/flash/live/$2$3?viewcount=true&autoplay=false&brand=embed',
		'size' => array(400, 326),
		'show-link' => true,
		'show-flashvars' => true,
		// http://www.ustream.tv/channel/chris-pirillo-live
		'fix-html-pattern' => '<object [^>]*id="utv[^"]*\d+"[^>]*>(?:<param [^>]*>)*<param [^>]*?value="(http://www\.ustream\.tv/flash/live/\d+)"[^>]*?/>.*?</object>(?:<a href="http://www\.ustream\.tv/live" [^>]*>.*?</a>)?',
		'fix-html-url' => '$1',
		'lookup-url' => 'http://(?:www\.)?ustream\.tv/channel/[\w-]+/?',
		'lookup-pattern' => array(
			'id' => 'flashvars.cid = "(\d+)"',
		),
		'lookup-title' => 'channelTitle="(.*?)"',
	),
	array(
		'id' => 'utu',
		'title' => 'u-Tube',
		'website' => 'http://www.u-tube.ru',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)?u-tube\.ru/(?:playlist\.php\?id=|pages/video/)(\d{1,12})',
		'movie' => 'http://www.u-tube.ru/upload/others/flvplayer.swf?file=http://www.u-tube.ru/playlist.php?id=$2&width=400&height=300',
		'size' => array(400, 300),
		'show-link' => true,
		// http://www.u-tube.ru/pages/video/28512/
		// http://www.u-tube.ru/playlist.php?id=28512
		'fix-html-pattern' => '(?:<embed[^<>]*?flashvars="file=$1[^<>]*?/>|<script type="text/javascript" src="http://www\.u-tube\.ru/vs/main/js/swfobject\.js"></script><p id="player-obj">[^<>]*?<br/><br/><a href="http://www\.macromedia\.com/go/getflashplayer">[^<>]*?</a></p><script type="text/javascript">[^<>]*?$1[^<>]*?</script>)',
		'fix-html-url' => 'http://www.u-tube.ru/pages/video/$2$4/',
	),
	array(
		'id' => 'vij',
		'title' => 'VideoJug',
		'website' => 'http://www.videojug.com',
		'type' => 'video',
		'pattern' => '(http://(?:www\.)videojug\.com/film/player\?id=[0-9a-z]{8}(?:-[0-9a-z]{4}){3}-[0-9a-z]{12})',
		'movie' => '$2',
		'size' => array(400, 345),
		// http://www.videojug.com/film/player?id=6bdae9a1-d8c8-5c06-3b58-ff0008ca6bff
		'fix-html-pattern' => '<object classid[^<>]*?><param name="movie" value="$1" />.*?</object>(?:<br /><a href="http://www\.videojug\.com/film/[^<>]*?>[^<>]*?</a>)?',
		'lookup-url' => 'http://(?:www\.)?videojug\.com/film/[\w-]{3,50}',
		'lookup-pattern' => '(http://(?:www\.)videojug\.com/film/player\?id=[0-9a-z]{8}(?:-[0-9a-z]{4}){3}-[0-9a-z]{12})',
		// http://www.videojug.com/film/how-to-fake-abs-for-the-summer
	),
	array(
		'id' => 'sap',
		'title' => 'videos.sapo',
		'website' => 'http://videos.sapo.pt',
		'type' => 'video',
		'pattern' => 'http://((?:\w{3,12}\.)*)sapo\.pt/([0-9a-z]{20})',
		'movie' => 'http://$2sapo.pt/play?file=http://$2sapo.pt/$3/mov/1',
		'size' => array(400, 322),
		// http://videos.sapo.pt/bpi7lZg8Wi8nNd1mZZWF
		// http://futebol.videos.sapo.pt/LWnyYeYMseMEKM4YAs69
		'lookup-title' => true,
	),
	array(
		'id' => 'vdd',
		'title' => 'Viddler',
		'website' => 'http://www.viddler.com',
		'type' => 'video',
		'pattern' => '(http://www\.viddler\.com/(?:player|simple)/[0-9a-f]{8}/)',
		'movie' => '$2',
		'size' => array(437, 288),
		// http://www.viddler.com/player/6d7b8644/
		'fix-html-pattern' => '<object classid[^<>]*?>(?:<param name="flashvars"[^<>]*?>)?<param name="movie" value="$1" />.*?</object>',
		'lookup-url' => 'http://(?:www\.)?viddler\.com/explore/[\w-]*?/videos/\d{1,10}',
		'lookup-pattern' => '<link\srel="video_src"\shref="(http://www\.viddler\.com/player/[0-9a-f]{8}/)"/>',
		// http://www.viddler.com/explore/titlepage/videos/28/
	),
	array(
		'id' => 'videa',
		'title' => 'Videa',
		'website' => 'http://www.videa.hu',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)?videa\.hu/(?:(?:[^"]*)-|flvplayer\.swf\?v=)([0-9a-z]{16})',
		'movie' => 'http://videa.hu/flvplayer.swf?v=$2',
		'size' => array(434, 357),
		// http://videa.hu/videok/zene/kaiser-chiefs-never-miss-a-beat-dHMG1NcrrqWevOI3
		'fix-html-pattern' => '<object [^>]*><param name=\'movie\' value=\'$1\' /><embed[^<>]*?/></object>(?:<br /><a[^<>]*?>[^<>]*?</a>)?',
	),
	array(
		'id' => 'vin',
		'title' => 'VideoNuz',
		'website' => 'http://www.videonuz.com',
		'type' => 'video',
		'pattern' => '(http://(?:www\.)?videonuz\.com/videonuz\.swf\?videoLink=video_[0-9a-z]{8,12}\.flv&(?:amp;)?resim=video_([0-9a-z]{8,12})\.(?:jpg|gif))',
		'movie' => '$2',
		'size' => array(400, 325),
		// http://www.videonuz.com/videonuz.swf?videoLink=video_wejpjpofuq.flv&resim=video_gqteiqhplf.jpg
		'fix-html-pattern' => '<object[^<>]*?><param name="movie" value="$1"><embed[^<>]*?></object>',
		'fix-html-url' => '$2',
		'lookup-url' => 'http://(?:www\.)videonuz\.com/(?:medyaizle\.php\?haber_id=)?(\d{1,12})',
		'lookup-actual-url' => 'http://www.videonuz.com/medyaizle.php?haber_id=$1',
		'lookup-pattern' => '<param name="movie" value="(http://(?:www\.)?videonuz\.com/videonuz\.swf\?videoLink=video_[0-9a-z]{8,12}\.flv&(?:amp;)?resim=video_([0-9a-z]{8,12})\.(?:jpg|gif))">',
		// http://www.videonuz.com/15646_Spor_porto---fb-volkandan-super-kurtaris-guiza-sari-kart__1.htm
		// http://www.videonuz.com/medyaizle.php?haber_id=15646
	),
	array(
		'id' => 'vim',
		'title' => 'VidMax',
		'website' => 'http://www.vidmax.com',
		'type' => 'video',
		'pattern' => 'http://(www\.)?vidmax\.com/(?:index\.php/)?videos?/(?:view/)?(\d{1,10})',
		'movie' => 'http://$2vidmax.com/player/flvplayer_NOLOGO.swf?file=http://$2vidmax.com/media/video/$3.flv&autostart=false&repeat=false&bufferlength=5&backcolor=0x000000&frontcolor=0xCCCCCC',
		'size' => array(400, 300),
		// http://vidmax.com/video/56755/JoJo_from_K_Ci_and_JoJo_passes_out_while_performing_on_stage/
		'fix-html-pattern' => '<embed[^<>]*?flashvars="xml=http://vidmax\.com/index\.php/videos/playlist/&(?:amp;)?id=(\d{1,10})[^<>]*?>',
		'fix-html-url' => 'http://www.vidmax.com/video/$1/',
	),
	array(
		'id' => 'viv',
		'title' => 'Vidivodo',
		'website' => 'http://www.vidivodo.com',
		'type' => 'video',
		'pattern' => 'http://www\.vidivodo\.com/VideoPlayerShare\.swf\?lang=([0-9a-z]*?)&(?:amp;)?vidID=(\d*?)&(?:amp;)?vCode=v(\d*?)&(?:amp;)?dura=(\d*?)&(?:amp;)?File=(?:http://video\d*\.vidivodo\.com/)?(vidservers/server\d*/videos/\d{4}/\d{2}/\d{2}/\d*/v\d*\.flv)',
		'movie' => 'http://www.vidivodo.com/VideoPlayerShare.swf?lang=$2&vidID=$3&vCode=v$4&dura=$5&File=$6',
		'size' => array(425, 343),
		// http://www.vidivodo.com/VideoPlayerShare.swf?lang=en&vidID=164690&vCode=v200807062126330164690&dura=116&File=vidservers/server01/videos/2008/07/06/21/v200807062126330164690.flv
		'fix-html-pattern' => '<object [^>]*><param name="movie" value="$1"></param><param name="wmode" value="transparent"></param><embed [^<>]*?></embed></object>',
		'lookup-url' => 'http://(?:www\.)?vidivodo\.com/\d{1,12}/(?:[\w-]*)',
		'lookup-pattern' => '<link\srel="video_src"\shref="(http://www\.vidivodo\.com/VideoPlayerShare\.swf\?lang=[0-9a-z]*?&(?:amp;)?vidID=\d*&(?:amp;)?vCode=v\d*&(?:amp;)?dura=\d*&(?:amp;)?File=http://video\d*\.vidivodo\.com/vidservers/server\d*/videos/\d{4}/\d{2}/\d{2}/\d*/v\d*\.flv)"/>',
		'lookup-urldecode' => 1,
		// http://www.vidivodo.com/164690/james-bond-quantum-of-solace
	),
	array(
		'id' => 'voi',
		'title' => 'VoiceThread',
		'website' => 'http://www.voicethread.com',
		'type' => 'other',
		'pattern' => 'http://(?:www\.)?voicethread\.com/(?:share/|book\.swf\?b=)(\d{1,10})',
		'movie' => 'http://www.voicethread.com/book.swf?b=$2',
		'size' => array(480, 360),
		// http://voicethread.com/share/21651/
		'fix-html-pattern' => '<object [^>]*><param name="movie" value="$1"></param><param name="wmode" value="transparent"></param><embed[^<>]*?></embed></object>(?:<img style="visibility:hidden;width:0px;height:0px;" border=0 width=0 height=0 src="http://counters\.gigya\.com/[^<>]*?/>)?',
		'fix-html-url' => 'http://voicethread.com/share/$2/',
	),
	array(
		'id' => 'wat',
		'title' => 'Wat.tv',
		'website' => 'http://www.wat.tv',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)?wat\.tv/video/.*?\.html#id(\w+)',
		'movie' => 'http://www.wat.tv/swf2/$2',
		'size' => array(430, 358),
		// http://www.wat.tv/video/albator-84-op-end-vo-rxdy_ioig_.html
		'fix-html-pattern' => '(?:<div><object [^>]*><param name="movie" value="http://www\.wat\.tv/swf2/.*?</object></div><div [^>]*><a [^>]*href="(http://(?:www\.)?wat\.tv/.*?\.html)">.*?</div>'
							. '|<div><object [^>]*><param name=\'movie\' value=\'http://www\.wat\.tv/swf2/.*?</object><br /><b><a href=\'(http://(?:www\.)?wat\.tv/.*?\.html)\'>.*?</div>)',
		'fix-html-url' => '$1$2',
		'lookup-url' => 'http://(?:www\.)?wat\.tv/video/.*?\.html',
		'lookup-pattern' => array('id' => '<meta property="og:video" content="https?://(?:www\.)?wat\.tv/swf2/([^"]+)">'),
	),
	array(
		'id' => 'weg',
		'title' => 'WeGame',
		'website' => 'http://www.wegame.com',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)?wegame\.com/watch/([\w-]*?)/',
		'movie' => 'http://wegame.com/static/flash/player.swf?xmlrequest=http://www.wegame.com/player/video/$2&embedPlayer=true',
		'size' => array(480, 387),
		// http://www.wegame.com/watch/Trackmania_Unnamed_Meme/
		'fix-html-pattern' => '<object [^>]*><param name="movie" value="http://www\.wegame\.com/static/flash/player\.swf\?xmlrequest=http://www\.wegame\.com/player/video/([\w-]*?)">.*?</object>(?:<div style="display: block; font-size: 11px".*?</div>)?',
		'fix-html-url' => 'http://www.wegame.com/watch/$1/',
	),
	array(
		'id' => 'wip',
		'title' => 'Wipido',
		'website' => 'http://www.wipido.com',
		'type' => 'video',
		'pattern' => 'http://(?:www\.)?wipido\.com/(?:profile/)?video/(?:embedconfig/)?(\d{1,10})(?:%3Ftemplate%3Dgamersydevideo/)?',
		'movie' => 'http://www.wipido.com/static/wipidotv/mediaplayer.swf?id=$2&file=http://www.wipido.com/uploads/videos/$2_mq.mp4&image=http://www.wipido.com/uploads/videos/$2_screenie.jpg&logo=http://www.wipido.com/images/web/overlay.png&link=http://www.wipido.com/video/$2&linktarget=_blank&backcolor=0x2a2f35&frontcolor=0xffffff&lightcolor=0xff0000&screencolor=0x000000&height=320&width=480',
		'size' => array(480, 320),
		// http://www.wipido.com/video/1054
		// http://www.wipido.com/profile/video/embedconfig/1049%3Ftemplate%3Dgamersyde
		'fix-html-pattern' => '<iframe src="http://www\.wipido\.com/main/video/external/(\d{1,10})[^<>]*?></iframe>',
		'fix-html-url' => 'http://www.wipido.com/video/$1',
		'lookup-url' => 'http://(?:www\.)?gamersyde\.com/stream_\d{1,12}_en\.html',
		'lookup-pattern' => 'o\.addVariable\(\'config\',\s\'(http://www\.wipido\.com/profile/video/embedconfig/\d{1,12}%3Ftemplate%3Dgamersyde)\'\);',
		// http://gamersyde.com/stream_7398_en.html - gamershyde use wipido to host their vids.
	),
	array(
		'id' => 'yav',
		'title' => 'Yahoo Video',
		'website' => 'http://video.yahoo.com',
		'type' => 'pop',
		'pattern' => 'http://(?:(?:www|[a-z]{2})\.)?video\.yahoo\.com/watch/(\d*)/(\d+)(?:#thumb=(http[\w\./%\:]+\.jpg))?',
		'movie' => 'http://d.yimg.com/static.video.yahoo.com/yep/YV_YEP.swf?ver=2.2.40&id=$3&vid=$2&lang=en-us&intl=us&embed=1&thumbUrl=$4',
		'size' => array(512, 322),
		'show-link' => true,
		// http://video.yahoo.com/watch/3434337/9582517
		'fix-html-pattern' => '(?:<div>)?<object [^>]*><param name="movie" value="http://d\.yimg\.com/static\.video\.yahoo\.com/yep/YV_YEP\.swf[^>]*>.*?<param name="flashVars" value="id=(\d+)&(?:amp;)?vid=(\d*)[^>]*thumbUrl=(http[\w\./%]+\.jpg)?[^<>]*>.*?</object>(?:<br /><a href="[^<>]*>.*?</div>)?',
		'fix-html-url' => 'http://video.yahoo.com/watch/$2/$1#thumb=$3',
		'lookup-url' => 'http://(?:(?:www|[a-z]{2})\.)?video\.yahoo\.com/network/[^"]*?(?:v=)?(\d+)',
		'lookup-pattern' => array(
			'id' => '\<a id="nvi_comments_link" href="http://[^"]*?yahoo.com/watch/(\d+/\d+)"',
			'thumb=' => '"thumbUrl", "(.*?)"'
		),
		'lookup-title' => '<h2 id="nvi_title">(.*?)</h2>',
		'lookup-final-url' => 'http://video.yahoo.com/watch/$1',
		// http://video.yahoo.com/network/100845082?v=3434337
	),
	array(
		'id' => 'yam',
		'title' => 'Yahoo Music Videos',
		'website' => 'http://music.yahoo.com',
		'type' => 'video',
		'pattern' => 'http://(?:new\.)?(?:[a-z]{2}\.)?music\.yahoo\.com/[^"]*?--(\d+)',
		'movie' => 'http://d.yimg.com/cosmos.bcst.yahoo.com/up/fop/embedflv/swf/fop.swf?id=v$2&eID=0000000&lang=us&enableFullScreen=0&shareEnable=1',
		'size' => array(400, 255),
		'show-link' => true,
		// http://new.music.yahoo.com/J-Xavier/videos/view/Go-Tell-Your-Mama-To-Vote-For-Obama--58952262
		// http://new.music.yahoo.com/videos/TracyLawrence/Find-Out-Who-Your-Friends-Are--45173913
		// http://new.uk.music.yahoo.com/videos/Rihanna/Take-A-Bow--59812819
		'fix-html-pattern' => '<object [^>]*id="uvp_fop"[^>]*><param name="movie" value="http://d\.yimg\.com/cosmos\.bcst\.yahoo\.com/up/fop/embedflv/swf/fop\.swf"/><param name="flashVars" value="id=v(\d+)[^<>]*?>.*?</object>',
		'fix-html-url' => 'http://new.music.yahoo.com/videos/--$1',
		'lookup-title' => true,
	),
	array(
		'id' => 'yax',
		'title' => 'Yandex',
		'website' => 'http://video.yandex.com',
		'type' => 'video',
		'pattern' => '(?:http://flv\.video\.yandex\.ru/lite/([\w-]+)/(\w+\.\d+)|http://video\.yandex\.ru/users/([\w-]+)/view/\d+/?#id(\w+\.\d+))',
		'movie' => 'http://flv.video.yandex.ru/lite/$2$4/$3$5',
		'size' => array(450, 338),
		// http://video.yandex.ru/users/pugachev-alexander/view/1305/
		'fix-html-pattern' => '(?:<object width="(\d+)" height="(\d+)"><param name="video" value="http://flv\.video\.yandex\.ru/lite/([\w-]+)/(\w+\.\d+)/">.*?</object>)',
		'fix-html-url' => 'http://flv.video.yandex.ru/lite/$3/$4#w$1-h$2',
		'lookup-url' => 'http://video\.yandex\.ru/users/[\w-]+/view/\d+/?',
		'lookup-title' => true,
		'lookup-pattern' => array(
			'id' => '\[flash=\d+,\d+,http://flv\.video\.yandex\.ru/lite/[\w-]+/(\w+\.\d+)/]',
			'w' => '\[flash=(d+),d+,',
			'h' => '\[flash=d+,(d+),',
		),
	),
	array(
		'id' => 'yku',
		'title' => 'YouKu',
		'website' => 'http://www.youku.com',
		'type' => 'video',
		'pattern' => 'http://(?:v\.youku\.com/v_show/id_|player\.youku\.com/player\.php/sid/)([0-9a-z]{6,14})',
		'movie' => 'http://player.youku.com/player.php/sid/$2=/v.swf',
		'size' => array(450, 372),
		// http://v.youku.com/v_show/id_XNDE2NDI3MDA=.html
		// http://player.youku.com/player.php/sid/XNDE2NDI3MDA=/v.swf
		'fix-html-pattern' => '<embed src="$1[^<>]*?></embed>',
		'fix-html-url' => 'http://v.youku.com/v_show/id_$2=.html',
		'lookup-title' => true,
	),
);
