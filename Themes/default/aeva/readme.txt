[table][tr][td][iurl=#important]Important![/iurl]
[iurl=#features1]Gallery features[/iurl]
[iurl=#features2]Auto-embedder features[/iurl]
[iurl=#license]License agreement[/iurl]
[iurl=#credits]Credits[/iurl]
[iurl=#compatibility]Compatibility[/iurl]
[iurl=#install]How to install/upgrade[/iurl]
[iurl=#addons]Foxy! add-on[/iurl]
[iurl=#converters]Gallery converters[/iurl]
[iurl=#supportedsites]Supported video/audio sites[/iurl]
[iurl=#support]Auto-embedder support[/iurl]
[iurl=#modifyaeva]ModifyAevaSettings() errors[/iurl]
[iurl=#controlling]Controlling auto-embedding[/iurl]
[iurl=#future]About Aeva Media's sitelist updates[/iurl][/td]
[td]


[right][color=#c63][size=16pt][b]Aeva Media v%1[/b][/size][/color]

A [i]free[/i] and full-featured [b]gallery system[/b]
and [b]auto-embedding mod[/b],
for SMF 1.1.x and SMF 2.0.

Developed by [b][url=http://www.simplemachines.org/community/index.php?action=profile;u=13794]Nao/Gilles[/url][/b]
&copy; Noisen.com / SMF-Media.com
[url=http://aeva.noisen.com/][b]Official Website/Demo[/b][/url] - [url=http://smf-media.com/community/index.php?action=media][b]Sandbox[/b][/url][/right][/td][/tr][/table]


[anchor=important][/anchor][b][color=#936][size=11pt]Important![/size][/color][/b][hr]
Aeva stands for "Auto-Embed Video & Audio". It used to be a mod specifically designed to automatically embed video links posted in forum messages. Now, it's no longer the case. I've merged it with my other 'big' mod, [b]SMF Media Gallery[/b], helping broaden both mods' future.
Also, although Aeva Media is starting as 1.0, it doesn't mean it's older than Aeva 7. Of course it's the opposite, but I figured it'd be easier to users of both Aeva and SMG to have a clean slate rather than having Aeva Media start at version 8.0 (new Aeva) or version 3.0 (new SMG).

Beware, if you want to install Aeva Media, [b][color=red]you need to uninstall BOTH Aeva and SMF Media Gallery![/color][/b]

[anchor=features1][/anchor][b][color=#396][size=11pt]Gallery features[/size][/color][/b][hr]
[list type=decimal]
[li]Create albums and sub-albums with unlimited levels[/li]
[li]Decide who can upload to them (per user or per membergroup), choose who can view your items, or browse your albums. Allow or ban specific users.[/li]
[li]Supports Images, Audio and Video files, and embedding videos from Youtube, Dailymotion etc.[/li]
[li]Track which items you haven't viewed yet (similar to SMF's Unread Posts)[/li]
[li]Search, preview, rate, comment and embed items! It must be worth the exclamation mark![/li]
[li]Approval/Unapproval system for comments, items and user albums[/li]
[li]Powerful permission and security features, including password protection[/li]
[li]Supports Exif data (no PHP modules required), GD2, ImageMagick and FFMpeg[/li]
[li]Highslide lightbox animations when opening items[/li]
[li]An exhaustive control panel for admins to play with[/li]
[li]Mass Upload, Profile areas, video thumbnails...[/li]
[li]Embed tag, allows you to post items into your messages[/li]
[li]Per-album permissions![/li]
[li]Membergroup quotas along with per-album quotas[/li]
[li]Per-album or gallery-wide custom fields[/li]
[li]Can upload any filetype, including documents and custom extensions[/li]
[li]Mass upload through FTP (admins only)[/li]
[li]Mass downloads (zip file with selected files from any album)[/li]
[li][i]And much, much more... Why spoil the fun?[/i][/li]
[/list]

[anchor=features2][/anchor][b][color=#396][size=11pt]Auto-embedder features[/size][/color][/b][hr]
You can now automatically embed video and audio clips by posting the clip's URL into your SMF forum posts from over [size=11pt][b]200[/b][/size] sites (hundreds if you count the supported networks), including YouTube, DailyMotion, Google Video, Google Maps, MetaCafe, MySpace, Facebook, Veoh...
No need for BBCode or messy embed HTML. And if a user posts the embed html code for a site, it'll be turned automatically into a nice little furry harmless link.

Just copy the URL from your address bar into a post (like [nobbc]http://youtube.com/watch?v=B8XC7idFyvE[/nobbc]), and Aeva Media will do the rest. It's the ultimate user-friendly way of posting clips. YouTube videos will automatically appear in HD and Widescreen if available and enabled.
[list type=decimal]
[li]Works for all languages. Will fall back to the English version if your own language is missing.[/li]
[li]Contains English and French translations. You can find more languages at [url=http://aeva.noisen.com/trans/][b]Aeva Media translations[/b][/url] or in [url=http://smf-media.com/community/index.php?action=mgallery;sa=album;id=61][b]SMF-Media[/b][/url]'s download area. You can even submit your own.[/li]
[li]Admin settings aplenty. Enable/disable embedding, allow embed code fix, use Javascript to embed videos (which is recommended), inline upgrade of Flash version, debug mode for admins, allow for video embedding into quotes or in the middle of sentences...
- Lookups will grab the actual video url/filename when posting a new link.
- Enables support for dozens of sites. Embed local or remote files (except for attachments), such as MP3, MP4, FLV, DivX, Avi, SWF, RM, WMV, MOV...
- And so on.
[/li]
[li]The site list is optimized to use only the features you enabled, and it's automatically updated when a new version is available. You only need to visit the admin area if you want to enable newly added sites. You can also force an immediate check for sitelist updates (instead of daily checks) through the admin area, or by going to a forum page where Aeva Media is in use (e.g. a video is visible), and adding ";checkaeva" to the URL. This can be restricted to admins via the admin area.[/li]
[li]Create your own custom site lists or per-site settings, such as custom video dimensions. More details in the Aeva-Custom-Example.php file.
[/li][li]Users can select on-the-fly their preferred embed size, normal or maximum (saved in a cookie)[/li]
[li]No manual edits required for custom themes. No conflicts with mods that provide support for a single website with custom BBCode and a video ID. But you'll never want to use them again. Just use the conversion script included in the package to convert old BBCode.[/li]
[li]Completely safe and secure: Aeva Media controls everything your users post. It even disables remote script access.[/li]
[li]If you're not sure, be happy -- embedding of external videos will NOT consume your bandwidth![/li]
[/list]

[anchor=license][/anchor][b][color=#369][size=11pt]License agreement[/size][/color][/b][hr]
Aeva Media is completely free to use, even on commercial websites. But if you wish to use this software, you will be bound by the Aeva Media license agreement. Please make sure to read file license_am.txt, at the root of this package. It can also be viewed online at [url=http://noisen.com/license.php]http://noisen.com/license.php[/url]. If you don't follow the license terms, you will be exposed to potential damage such as a lawsuit, complete and utter humiliation in public, and not finding your shoes when you're already late for work.

[anchor=credits][/anchor][b][color=#369][size=11pt]Credits[/size][/color][/b][hr][list]
[li]Created and Developed by [url=http://noisen.com/]Nao/Gilles[/url][/li]
[li]Gallery system: originally created by [url=http://smf-media.com/]Dragooon[/url][/li]
[li]Auto-embed system: originally created by [url=http://custom.simplemachines.org/mods/index.php?action=profile;u=63186]Karl Benson[/url][/li]
[li][b]3rd party scripts[/b] : Exifixer, getid3, Highslide, JW Player, Yahoo! UI Uploader[/li]
[/list]

[anchor=compatibility][/anchor][b][color=#396][size=11pt]Compatibility[/size][/color][/b][hr]
ANY version of SMF 1.1.x and SMF 2.0 will do. (In the case of SMF2, the only supported version is the latest. Because there are too many changes between each release.)
[b]All previous versions of this mod MUST be uninstalled BEFORE installing this version. Also, make sure to backup your database and files first.[/b]

[anchor=install][/anchor][b][color=#396][size=11pt]How to install/upgrade[/size][/color][/b][hr][list type=decimal]
[li]Uninstall older versions (if already installed).[/li]
[li]Install the new version.[/li]
[li]Go to the admin area, play with the settings. On first install, set permissions up![/li]
[li]And you are done! There will be no data loss.[/li]
[li]If you need to move your gallery files to a new server, make sure to download them in Binary format, as many FTP clients will default to ASCII for files without an extension, which will corrupt your files. Forever.[/li]
[/list]

[anchor=addons][/anchor][b][color=#396][size=11pt]Foxy! add-on[/size][/color][/b][hr]
Nao's [b]Foxy![/b] add-on adds over 70KB worth of features to your gallery. [url=http://aeva.noisen.com/5354/foxy-add-on-for-aeva-media-smg/]Click here[/url] for more details.[list type=decimal]
[li][b]User Playlists[/b]: Give membergroups the permission to create user playlists. Create playlists by simply visiting any item page and clicking the Playlists drop menu and then select "Add to Playlist". Organize your favorites easily and browse them like an album![/li]
[li][b]JavaScript + Flash Playlists[/b]: Show nice little Flash-powered playlists in these topics, or pretty much anywhere else, based on user playlists or anything you want (one or more items, one or more albums...)[/li]
[li][b]Linked topics[/b]: Create (or not) a linked notification topic automatically when adding/editing an album. You can use this to close per-item comments and have everyone comment on albums instead, or subscribe to topic notifications, or harness the power of other topic-related mods. See it in action at [url=http://fox.noisen.com]Foxprog[/url], which is entirely built on user playlists, Flash playlists and linked topics. Remember, you can do this with any file type![/li]
[li][b]Post button[/b]: Add a button to the post area, which opens a popup that will allow you to upload an item and insert it into your message in as few clicks as possible.[/li]
[li][b]Embedding of remotely hosted pictures[/b]: Picasa, Photobucket, Imageshack, anything you can think of. Thumbnails and previews will be created as needed, but the main files themselves will always stay hosted remotely.[/li]
[li][b]RSS feeds[/b]: All albums and items now offer RSS feeds to keep you updated for:
* Latest items - user profiles, albums, album with children, or the entire gallery.
* Latest comments - items, user profiles, albums, album with children, or the entire gallery.[/li]
[/list]

[anchor=converters][/anchor][b][color=#396][size=11pt]Gallery converters[/size][/color][/b][hr]
Three converters are available for making your evaluation of Aeva Media easier. You can run AM along with another gallery system, they will not conflict with each other. You can download the converters from the official mod page, under the name [b]Gallery Converters.zip[/b]. The file contains three folders. Just extract the converter.php from the gallery system you're interested in.

[b]Requirements[/b][list type=decimal]
[li]A working Coppermine (bridged with SMF), SMF Gallery Lite or SMF Gallery Pro installation to convert from.[/li]
[li]A working fresh Aeva Media installation on the same forum. Make sure it is working normally first.[/li]
[/list]

[b]How to convert[/b][list type=decimal]
[li]Extract and upload the converter.php file to your forum's root where the SSI.php and index.php files are located.[/li]
[li]Run it from your browser.[/li]
[li]Follow the steps on screen, it should do the conversion by itself.[/li]
[/list]

[b]Note: this will wipe out preexisting Aeva Media gallery items, if there are any. However, your Coppermine or SMF Gallery will remain unaffected before and after the conversion.[/b]

[b][color=#c36]Coppermine Gallery[/color][/b] to Aeva Media converter
Note: it is only tested with CPG 1.4.x.

[b]What it converts[/b][list type=decimal]
[li]Items[/li]
[li]Albums (all types)[/li]
[li]Comments[/li]
[/list]

[b][color=#c36]SMF Gallery Lite[/color][/b] to Aeva Media converter
[b][color=#c36]SMF Gallery Pro[/color][/b] to Aeva Media converter
Note: these converters are certified for use with SMF Gallery Lite 1.9 and the equivalent SMF Gallery Pro version, and theoretically should also convert later versions as well.

[b]What they convert[/b][list type=decimal]
[li]Items[/li]
[li]Categories[/li]
[li]Comments[/li]
[li]Reports[/li]
[/list]

[anchor=supportedsites][/anchor][b][color=#396][size=11pt]Supported video/audio sites[/size][/color] [* Requires lookups to be enabled][/b][hr]
Supports 173 Video Sites including:[list]
[li]YouTube, YouTube (Playlists), Dailymotion, Google Video, BBC Iplayer (UK Only), MetaCafe, Veoh, 123video, Facebook, 5min Life Videopedia, 9You, ABC News*, AdultSwim, AlloCin&eacute;, AniBoom, Apple Trailers*, Archive.org*, Atom*, Bebo*, Blip*, BoFunk*, BombayTV, Break*, Broadcaster*, CarPix Tv, Cellfish, Clarin, Clip.vn, ClipFish (Old), ClipFish (New), ClipJunkie, ClipLife, ClipMoon, Clipser, ClipShack, Cold-Link, CollegeHumor, ComedyCentral(Inc. TheDailyShow), Crackle, CrunchyRoll*, Culture Pub*, Current*, Dailyhaha, DemoScene.tv*, DotSub (w/o Captions), DoubleViking*, dropshots.com, Dv.ouou, EASportsWorld*, EbaumsWorld Videos*, ESPN, Excessif, ExposureRoom, Flickr Video, FunnyOrDie, G4TV, GameKyo, GameSpot, GameTrailers (Inc. User Movies), Gametube.org, GameVideos.1up, GarageTv*, Gloria, Glumbert, GodTube, GotGame*, GrassRoots ItvLocal, GrindTv*, Guba, TheHub, Hulu (Usa Only)*, Humour*, Video.i.ua*, IGN, IMDB*, Imeem (Video)*, ImageShack, IndyaRocks, Izlesene, Jokeroo, Joost, JujuNation Video, JumpCut, JustinTV, Koreus, Last.fm (Videos), Last.fm (YouTube videos), Libero, LiveLeak, LiveVideo, Machinima (Old), Machinima (New), Mogulus/Livestream, MyTaratata, Video.mail.ru, MegaVideo, Milliyet*, MoFile, MotionBox, Mpora, Mpora TV, MSN Live/Soapbox, MtvU (Usa Only), Multiply, MyNet, MySoccerMedia*, MyShows.cn*, MySpaceTv, MyVideo, MyVi*, M Thai, NhacCuaTui, OffUHuge, The Onion*, OnSmash, Photobucket, PikNikTube, Putfile, Rambler, RawVegas, Revver, RuTube, SevenLoad, ShareView, Sharkle*, Sina Podcast, Smotri, Snotr, SouthPark Studios, Space.tv.cctv.com, Spike, SportsLine (CBS Sports), StageHD*, Streetfire*, StupidVideos*, TagTele, TinyPic, Tm-Tube, TrailerAddict*, TrTube*, Trilulilu, Tu.tv*, Tudou, UOL VideoLog, Ustream*, UUME, u-Tube, vbox7, Vholdr, VideoJug*, videos.sapo, Vidiac, Viddler*, Videa, VideoNuz*, VidiLife*, VidMax, Vimeo, Vidivodo*, VSocial (Type1), VSocial (Type2), Wat.tv*, WeGame, Wipido*, Yahoo Video*, Yahoo Video HK, Yahoo Music Videos, Yandex*, YouKu[/li]
[li]Several networks of video-sharing sites which account for hundreds of extra video-sharing sites. These networks include Brightcove.com, Kaltura* (blog and Wikipedia embedding), Kewego*, Truveo* (meta search engine)...[/li]
[/list]
Supports 19 Audio Sites including:[list]
[li]BooMp3, Deezer, Deezer (Playlists), Deezer (Radio), Divshare (audio only), EbaumsWorld Audio*, GoEar, iJigg, Imeem (Music)*, Jamendo, JujuNation Audio, Last.fm (Audio), Last.fm (Artist pages), Mp3tube*, MusOpen*, Passionato (Single Preview), Passionato (Playlist Preview), Project Playlist, Seeqpod[/li]
[/list]
Supports 8 Other Sites including:[list]
[li]Google Maps, Imeem (Playlists)*, NewGrounds*, Orb, ScreenToaster, Scribd*, Slide*, VoiceThread[/li]
[/list]

[anchor=support][/anchor][b][color=#396][size=11pt]Auto-embedder support[/size][/color][/b][hr][list type=decimal]
[li]If a YouTube video doesn't automatically switch to Widescreen when available, make sure lookups are enabled in the admin area. If your server doesn't support them, you can still manually add #ws-hd at the end of an URL for Widescreen HD.[/li]
[li]Aeva Media only converts active links, so make sure the setting "Automatically link posted URLs" is enabled (Admin > Posts & Topics > Bulletin Board Code)[/li]
[li]A recent version of [url=http://kb.adobe.com/selfservice/viewContent.do?externalId=tn_15507][b]Adobe Flash Player[/b][/url] is required (at least 9.0).[/li]
[li]If you get a "We're sorry, this video is no longer available" message on YouTube videos, this is not an Aeva Media bug. It can either be due to the video not being embeddable (make sure lookups are enabled to check for these when posting), or simply to its streaming server being under maintenance. Try to play it again later.[/li]
[li]If you're having Error 500-type crashes on your server, try to disable SMF's "compressed output" feature in your server settings, and ask Santa Claus for a better server next Christmas.[/li]
[/list]

[anchor=modifyaeva][/anchor][b][color=#396][size=11pt]ModifyAevaSettings() errors[/size][/color][/b][hr]
Please refer to this every time someone posts an error message mentioning that function.[list type=decimal]
[li]"[b]Undefined function[/b]" error -> this is a mod screwing up your install (it could possibly be any mod, but AjaxChat is the better known one.) It doesn't follow SMF guidelines for its install script. It's not Aeva Media's fault. Quick workaround: make sure to install Aeva Media before you install that mod. It's already cost me hours in explaining the same thing again and again, I will no longer be replying to this kind of request.[/li]
[li]"[b]Already defined[/b]" error -> this is a SMF limitation. It doesn't try to check whether you already installed a mod before. As a result, if you don't uninstall a mod before reinstalling it, everything will be copied twice. This is a problem on ALL PACKAGES and can only be fixed by educating yourself on how to install packages in current versions of SMF. Quick workaround: apart from being careful next time, there is none. You'll have to uninstall Aeva manually, file by file. It's not a big mod though, so it shouldn't take more than 10 minutes.[/li]
[/list]

[anchor=controlling][/anchor][b][color=#396][size=11pt]Controlling auto-embedding[/size][/color][/b][hr]
[b]Disabling Embedding In Posts[/b]
[list]
[li]Use [b]&#91;noembed]&#91;/noembed][/b] BBCode to prevent a link from being converted. Also, if the related setting is enabled, videos inserted in a sentence are not embedded (they're only shown when they're at the beginning of a line), so you can use that to your benefit.[/li]
[/list]

[b]Disabling Embedding In Specific Areas[/b]
[list]
[li]Embedding is automatically disabled in signatures, printer pages and SMF2's WYSIWYG editor. You may want to be able to disable it for other sections, such as a Shoutbox.[/li]
[li]Just find the position, in the relevant source file, where data is put through the "parse_bbc" function. Then on the line BEFORE it, add the code below. If there's a chance the string you're going to parse is empty, make sure to unset the following call ([i]unset($context['aeva_disable']);[/i]) after the parse_bbc() that follows it.[/li]
[/list][pre][size=9pt]
		$context['aeva_disable'] = 1;[/size][/pre]

[anchor=future][/anchor][b][color=#396][size=11pt]About Aeva Media's sitelist updates[/size][/color][/b][hr]
For various reasons, which I already explained elsewhere, I put an end to the auto-embedder's frenetic development cycle in May 2009. I resumed work a few months later to fix YouTube issues and then decided to merge it with my other popular package, SMG.
Regarding the auto-embedder's features, I will only keep working on its sitelist updates. Do not expect more features to be added on the YouTube front. They did their best to prevent me from doing it, so it's not worth it.

Do [u]NOT[/u] request for a website to be integrated to the Aeva Media sitelist. I will refuse, unless you're prepared to pay, or the website is a very successful one and I'm interested in adding it. Otherwise, use the Custom file for adding sites yourself. It's not that hard. You just need to read a regex tutorial.
Feel free to post somewhere if an existing site is broken, though. At worst, I'll remove support for it. At best, I'll fix it.
Also -- no basic tech support from me.

[hr]

Support and updates for the mod can be found at http://aeva.noisen.com. Do not use its gallery system for your tests, though. The actual sandbox can be found [url=http://smf-media.com/community/index.php?action=media][b]here[/b][/url].
[color=red]If you find any issues, please make sure they have not been reported/fixed before reporting them![/color]

Thank you for using Aeva Media! Enjoy!
