<?php
// Version: 2.0; ManageMedia

$txt['embed_enabled'] = '<strong>Enable Auto-embedding</strong>';
$txt['embed_enabled_desc'] = 'Master setting';
$txt['embed_lookups'] = 'Enable Lookups';
$txt['embed_lookup_success'] = 'This feature WILL work on your server';
$txt['embed_lookup_fail'] = 'This feature will NOT work on your server';
$txt['embed_max_per_post'] = 'Max Embedding per Post';
$txt['embed_max_per_page'] = 'Max Embedding per Page';
$txt['embed_max_warning'] = 'Too much Flash is bad for your browser\'s health';
$txt['embed_quotes'] = 'Enable Embedding in Quotes';
$txt['embed_mov'] = 'MOV Files (via Quicktime)';
$txt['embed_real'] = 'RAM/RM Files (via Real Media)';
$txt['embed_wmp'] = 'WMV/WMA Files (via Windows Media)';
$txt['embed_swf'] = 'SWF Flash animations';
$txt['embed_flv'] = 'FLV Flash videos';
$txt['embed_divx'] = 'DivX files (.divx)';
$txt['embed_avi'] = 'AVI files (via DivX player)';
$txt['embed_mp3'] = 'MP3 files (via Flash player)';
$txt['embed_mp4'] = 'MP4 files (via Flash player)';
$txt['embed_ext'] = 'Allowed file extensions';
$txt['embed_fix_html'] = 'Fix uses of the embed HTML with an embeddable link';
$txt['embed_includeurl'] = 'Include the Original Link';
$txt['embed_includeurl_desc'] = '(for sites that don\'t have it in the player)';
$txt['embed_local'] = 'Embed Local Files [Excludes attachments]';
$txt['embed_local_desc'] = 'Local means on the same server. But this doesn\'t allow ANY file of this type to be embedded from anywhere.';
$txt['embed_denotes'] = '(Sites marked with * require lookups)';
$txt['embed_fish'] = '(Sites marked with * require lookups, however lookups will NOT work on your server.<br>Therefore, unless you manually fish for an embeddable url yourself, embedding will NOT work for these sites.)';
$txt['embed_pop_sites'] = 'Popular Sites';
$txt['embed_video_sites'] = 'Video Sites';
$txt['embed_audio_sites'] = 'Audio Sites';
$txt['embed_other_sites'] = 'Other Sites';
$txt['embed_adult_sites'] = 'Adult Sites';
$txt['embed_custom_sites'] = 'Custom Sites';
$txt['embed_disable'] = 'Disable Embedding';
$txt['embed_titles'] = 'Store &amp; show video titles';
$txt['embed_titles_desc'] = '(if site is supported by Wedge)';
$txt['embed_titles_yes'] = 'Yes, lookup and show';
$txt['embed_titles_yes2'] = 'Yes, but don\'t store anything new';
$txt['embed_titles_no'] = 'No, but keep looking them up for later';
$txt['embed_titles_no2'] = 'No, don\'t store and don\'t show';
$txt['embed_inlinetitles'] = 'Show title inside video thumbnails';
$txt['embed_inlinetitles_desc'] = '(for supported sites, such as YouTube and Vimeo)';
$txt['embed_inlinetitles_yes'] = 'Yes';
$txt['embed_inlinetitles_maybe'] = 'Only if the title isn\'t already stored';
$txt['embed_inlinetitles_no'] = 'No';
$txt['embed_noscript'] = 'Use earlier, JavaScript-less, embedding system';
$txt['embed_noscript_desc'] = 'Only use if you have compatibility issues';
$txt['embed_lookups_desc'] = 'Most of the auto-embedder\'s features require a lookup';
$txt['embed_center'] = 'Center videos horizontally';
$txt['embed_center_desc'] = 'Or add "-center" to any video\'s anchor settings (e.g. #ws-center)';
$txt['embed_lookup_titles'] = 'Try to find titles in all sites';
$txt['embed_lookup_titles_desc'] = '(even when they\'re not supported - you never know)';
$txt['embed_incontext'] = 'Enable Embedding in Sentences';
$txt['embed_nonlocal'] = 'Accept external websites in addition to local embeds';
$txt['embed_nonlocal_desc'] = 'In case it isn\'t clear: this isn\'t a recommended setting, at least security-wise.';
$txt['embed_max_width'] = 'Maximum width for embedded videos';
$txt['embed_max_width_desc'] = 'Leave empty to disable. Enter 600 for a maximum width of 600 pixels. Larger videos will be resized, while smaller videos will add a link to let you resize them to the maximum width.';
$txt['embed_yq'] = 'Default YouTube quality';
$txt['embed_yq_default'] = 'Default';
$txt['embed_yq_hd'] = 'HD where available';
$txt['embed_small'] = 'Small';
$txt['embed_large'] = 'Large';

$txt['media_admin_settings_title_main'] = 'Main settings';
$txt['media_admin_settings_title_security'] = 'Security settings';
$txt['media_admin_settings_title_limits'] = 'Limits';
$txt['media_admin_settings_title_tag'] = 'Embed code and [media] tags';
$txt['media_admin_settings_title_misc'] = 'Miscellaneous';
$txt['media_admin_settings_welcome'] = 'Welcome message';
$txt['media_admin_settings_welcome_desc'] = 'Leave empty to use the default welcome message.';
$txt['media_admin_settings_data_dir'] = 'Data folder';
$txt['media_admin_settings_data_dir_desc'] = 'For instance, "media" for /home/www/wedge/media';
$txt['media_admin_settings_max_dir_files'] = 'Max number of files in a directory';
$txt['media_admin_settings_enable_re-rating'] = 'Enable Re-Rating';
$txt['media_admin_settings_use_metadata_date'] = 'Set upload date to Exif datetime if available';
$txt['media_admin_settings_use_metadata_date_desc'] = 'If the file has Exif data available, the upload date will use its datetime setting instead of the current time.';
$txt['media_admin_settings_title_files'] = 'File settings';
$txt['media_admin_settings_title_previews'] = 'Preview size settings';
$txt['media_admin_settings_max_file_size'] = 'Max file size';
$txt['media_admin_settings_max_file_size_desc'] = 'Set to 0 and use the Quotas section to finetune.';
$txt['media_admin_settings_max_width'] = 'Max width';
$txt['media_admin_settings_max_height'] = 'Max height';
$txt['media_admin_settings_allow_over_max'] = 'Allow resizing of large pictures';
$txt['media_admin_settings_allow_over_max_desc'] = 'If uploaded pictures are over the max width or height, the server will attempt to resize them to match the max size specifications. Not recommended on overloaded servers. Select &quot;No&quot; to reject such pictures.';
$txt['media_admin_settings_upload_security_check'] = 'Enable security check at upload time';
$txt['media_admin_settings_upload_security_check_desc'] = 'Prevents users from uploading malicious files, but may also rarely reject some healthy files. It isn\'t recommended to enable this, unless you have some really, really stupid IE users looking for trouble.';
$txt['media_admin_settings_log_access_errors'] = 'Log access errors';
$txt['media_admin_settings_log_access_errors_desc'] = 'If enabled, all <em>Access denied</em> errors within the Media area will show up in your general error log.';
$txt['media_admin_settings_ftp_file'] = 'File path to the Safe Mode file';
$txt['media_admin_settings_ftp_file_desc'] = 'Read the MGallerySafeMode.php for more details. Required if your server has Safe Mode enabled!';
$txt['media_admin_settings_jpeg_compression'] = 'Jpeg Compression';
$txt['media_admin_settings_jpeg_compression_desc'] = 'Determines the quality of resized pictures, including previews and thumbnails. Choose between 0 (bad quality, small file) and 100 (high quality, large file). The default value (80) is recommended. Values between 65 and 85 are the best compromise.';
$txt['media_admin_settings_show_extra_info'] = 'Show metadata';
$txt['media_admin_settings_show_info'] = 'Metadata fields to show';
$txt['media_admin_settings_show_info_desc'] = 'Pictures taken by digital devices often embed useful information, such as the day the picture was taken. Here you can choose what information you want to show or not.';
$txt['media_admin_settings_num_items_per_page'] = 'Max items per page';
$txt['media_admin_settings_max_thumbs_per_page'] = 'Max [media] tags per page';
$txt['media_admin_settings_max_thumbs_per_page_desc'] = 'Maximum number of [media] tags that will get processed on a page (they will get converted to thumbnails).';
$txt['media_admin_settings_recent_item_limit'] = 'Recent items limit';
$txt['media_admin_settings_random_item_limit'] = 'Random items limit';
$txt['media_admin_settings_recent_comments_limit'] = 'Recent comments limit';
$txt['media_admin_settings_recent_albums_limit'] = 'Recent albums limit';
$txt['media_admin_settings_max_thumb_width'] = 'Max thumbnail width';
$txt['media_admin_settings_max_thumb_height'] = 'Max thumbnail height';
$txt['media_admin_settings_max_preview_width'] = 'Max preview width';
$txt['media_admin_settings_max_preview_width_desc'] = 'The preview is a clickable picture that is displayed on the full-size picture\'s page, to speed up loading. Set to 0 to disable. <b>Warning</b>: if disabled, large pictures might break your template layout.';
$txt['media_admin_settings_max_preview_height'] = 'Max preview height';
$txt['media_admin_settings_max_preview_height_desc'] = 'Same. If width or height is set to 0, preview images are disabled.';
$txt['media_admin_settings_max_bigicon_width'] = 'Max icon width';
$txt['media_admin_settings_max_bigicon_width_desc'] = 'Album icons have a thumbnail (which uses the size set in the Max thumbnail size section), and a regular size, which is used only on album pages. This sets the width for a regular size album icon.';
$txt['media_admin_settings_max_bigicon_height'] = 'Max icon height';
$txt['media_admin_settings_max_bigicon_height_desc'] = 'Same. This sets the height for a regular size album icon.';
$txt['media_admin_settings_max_title_length'] = 'Maximum title length';
$txt['media_admin_settings_max_title_length_desc'] = 'Maximum number of characters to show for titles above thumbnails. If cut, they can still be read when hovering over the thumbnail.';
$txt['media_admin_settings_image_handler'] = 'Image handler';
$txt['media_admin_settings_show_sub_albums_on_index'] = 'Show sub albums on index';
$txt['media_admin_settings_use_zoom'] = 'Use Zoomedia (animated transitions)';
$txt['media_admin_settings_use_zoom_desc'] = 'Zoomedia is a JavaScript-powered lightbox module that adds animated transitions when clicking on previews (zoom and fade-in/out). Disable to prevent the use of Zoomedia on all albums. If enabled, album owners may still disable Zoomedia per-album in the album settings.';
$txt['media_admin_settings_album_edit_unapprove'] = 'Unapprove albums when editing them';
$txt['media_admin_settings_item_edit_unapprove'] = 'Unapprove items when editing them';
$txt['media_admin_settings_show_linking_code'] = 'Show item linking code';
$txt['media_admin_settings_ffmpeg_installed'] = 'FFMPEG was found on this server, its features will be used for video files. If enabled, it will be used to create thumbnails and show extra info.';
$txt['media_admin_settings_prev_next'] = 'Show Previous and Next links?';
$txt['media_admin_settings_prev_next_desc'] = 'Enable this feature to show text or thumbnail shortcuts to previous and next items in the current item page.';
$txt['media_admin_settings_default_tag_type'] = 'Default size within [media] tags?';
$txt['media_admin_settings_default_tag_type_desc'] = 'Choose the image type that should be shown by default when no type is specified on [media id=xxx type=xxx] tags.';
$txt['media_admin_settings_my_docs'] = 'Allowed Document files';
$txt['media_admin_settings_my_docs_desc'] = 'You can choose the extensions allowed for uploaded Documents. Use comma as a separator (eg. "zip,pdf"). The default list of supported file types, in case you want to reset it, is: %s';
$txt['media_admin_settings_audio_player_width'] = 'Audio player\'s width';
$txt['media_admin_settings_audio_player_width_desc'] = 'In pixels. By default, 400';
$txt['media_admin_settings_phpini_desc'] = 'This server-side variable limits upload sizes. You can set it via a php.ini file, see details on the right';
$txt['media_admin_settings_clear_thumbnames'] = 'Leave thumbnail URLs in clear view';
$txt['media_admin_settings_clear_thumbnames_desc'] = 'If enabled, thumbnails will be linked by their direct URL. Saves much server processing time but slightly less secure.';
$txt['media_admin_settings_album_columns'] = 'Max sub-albums per line';
$txt['media_admin_settings_album_columns_desc'] = 'Default is 1. If you have a lot of sub-albums, you may want to set this to 2 or 3 so that more albums are shown per row.';
$txt['media_admin_settings_icons_only'] = 'Use icon shortcuts in item boxes';
$txt['media_admin_settings_icons_only_desc'] = 'If this is enabled, item boxes, such as the ones that show lists of items in album pages, will only show icons next to relevant information, rather than full text such as <em>Posted by</em>.';
$txt['media_admin_settings_disable_playlists'] = 'Disable User Playlists';
$txt['media_admin_settings_disable_playlists_desc'] = 'This entirely disables the Playlists feature.';
$txt['media_admin_settings_disable_comments'] = 'Disable item comments';
$txt['media_admin_settings_disable_comments_desc'] = 'Don\'t show existing comments on items, and disallow posting comments.';
$txt['media_admin_settings_disable_ratings'] = 'Disable ratings';
$txt['media_admin_settings_disable_ratings_desc'] = 'Don\'t show existing ratings on items, and disallow item rating.';

$txt['media_admin_moderation'] = 'Moderation';
$txt['media_admin_moving_album'] = 'Moving album';
$txt['media_admin_cancel_moving'] = 'Cancel moving';
$txt['media_admin_type'] = 'Type';
$txt['media_admin_edit'] = 'Edit';
$txt['media_admin_delete'] = 'Delete';
$txt['media_admin_approve'] = 'Approve';
$txt['media_admin_unapprove'] = 'Unapprove';
$txt['media_admin_before'] = 'Before';
$txt['media_admin_after'] = 'After';
$txt['media_admin_child_of'] = 'Child of';
$txt['media_admin_target'] = 'Target';
$txt['media_admin_position'] = 'Position';
$txt['media_admin_membergroups'] = 'Membergroups';
$txt['media_admin_membergroups_desc'] = 'Select the membergroups that should be allowed to use the album and its contents.<br>
<ul class="aevadesc">
	<li>If all <strong>primary groups</strong> (which are bolded for your convenience) are checked, all forum members will be given access, so you don\'t need to check other groups (except for Guests).</li>
	<li><strong>Read</strong> access: membergroup can view the album and its items, and use existing permissions if enabled (commenting, rating, etc.)</li>
	<li><strong>Write</strong> access: membergroup can upload items to the album.</li>
</ul>';
$txt['media_admin_membergroups_primary'] = 'This group is used as a primary group by one or more members.';
$txt['media_admin_passwd'] = 'Password';
$txt['media_admin_move'] = 'Move';
$txt['media_admin_total_submissions'] = 'Total submissions';
$txt['media_maintenance_done'] = 'Maintenance done';
$txt['media_pruning'] = 'Pruning';
$txt['media_admin_maintenance_prune_days'] = ' Minimum number of days';
$txt['media_admin_maintenance_prune_last_comment_age'] = 'Last comment older than';
$txt['media_admin_maintenance_prune_max_coms'] = 'Comments less than';
$txt['media_admin_maintenance_prune_max_views'] = 'Views less than';
$txt['media_admin_maintenance_checkfiles_desc'] = 'Checks for unused files (not found in the media_items table); if any are found, the system will allow removal.';
$txt['media_admin_maintenance_checkorphans'] = 'Check for orphan files';
$txt['media_admin_maintenance_checkorphans_desc'] = 'Checks for orphan files (not found in the media_files table); if any are found, the system will allow removal. <strong>Warning</strong>: launching this task will render your gallery <strong>unusable</strong> until its 3 phases are completed. It can take a long time on a large gallery.';
$txt['media_admin_maintenance_regen_all'] = 'Regenerate thumbnails and previews';
$txt['media_admin_maintenance_regen_embed'] = 'Regenerate video thumbnails';
$txt['media_admin_maintenance_regen_thumb'] = 'Regenerate thumbnails';
$txt['media_admin_maintenance_regen_preview'] = 'Regenerate previews';
$txt['media_admin_maintenance_regen_all_desc'] = 'This will delete and regenerate all thumbnails and previews that can be rebuilt from their original source.';
$txt['media_admin_maintenance_regen_embed_desc'] = 'This will delete and regenerate all current thumbnails, but only for <b>embedded (remote)</b> items. (YouTube, etc.)';
$txt['media_admin_maintenance_regen_thumb_desc'] = 'This will delete and regenerate all thumbnails that can be rebuilt from their original source.';
$txt['media_admin_maintenance_regen_preview_desc'] = 'This will delete and regenerate all previews that can be rebuilt from their original source.';
$txt['media_admin_maintenance_operation_pending'] = 'The current task has been paused to prevent server time outs, it will automatically resume in a second. So far, %s of %s items done.';
$txt['media_admin_maintenance_operation_pending_raw'] = 'The current task has been paused to prevent server time outs, it will automatically resume in a second.';
$txt['media_admin_maintenance_operation_phase'] = 'Phase %d of %d';
$txt['media_admin_maintenance_recount_desc'] = 'Recounts totals and statistics and updates them, can be used to fix incorrect stats.';
$txt['media_admin_maintenance_finderrors_desc'] = 'Tries to find some common errors like missing file (either from DB or physically) or incorrect id of last comment or item.';
$txt['media_admin_maintenance_prune_desc'] = 'Purge items/comments based on specific parameters.';
$txt['media_admin_maintenance_browse_desc'] = 'Browse gallery files and show the disk usage of each directory/file.';
$txt['media_admin_labels_modlog'] = 'Moderation Log';
$txt['media_admin_action_type'] = 'Action type';
$txt['media_admin_reported_item'] = 'Reported item';
$txt['media_admin_reported_by'] = 'Reported by';
$txt['media_admin_reported_on'] = 'Reported on';
$txt['media_admin_del_report'] = 'Delete report';
$txt['media_admin_del_report_item'] = 'Delete reported item';
$txt['media_admin_report_reason'] = 'Reported reason';
$txt['media_admin_banned_on'] = 'Banned on';
$txt['media_admin_expires_on'] = 'Expires on';
$txt['media_never'] = 'Never';
$txt['media_admin_ban_type'] = 'Ban type';
$txt['media_admin_ban_type_1'] = 'Full';
$txt['media_admin_ban_type_2'] = 'Posting items';
$txt['media_admin_ban_type_3'] = 'Posting comments';
$txt['media_admin_ban_type_4'] = 'Posting items and comments';
$txt['media_unapproved_items_notice'] = 'There are %2$d unapproved item(s), <a href="%1$s">Click here to view them</a>';
$txt['media_unapproved_coms_notice'] = 'There are %2$d unapproved comment(s), <a href="%1$s">Click here to view them</a>';
$txt['media_unapproved_albums_notice'] = 'There are %2$d unapproved album(s), <a href="%1$s">Click here to view them</a>';
$txt['media_reported_items_notice'] = 'There are %2$d reported item(s). <a href="%1$s">Click here to view them</a>';
$txt['media_reported_comments_notice'] = 'There are %2$d reported comment(s). <a href="%1$s">Click here to view them</a>';
$txt['media_admin_modlog_approval_item'] = 'Approved item <a href="%s">%s</a>';
$txt['media_admin_modlog_approval_ua_item'] = 'Unapproved item <a href="%s">%s</a>';
$txt['media_admin_modlog_approval_del_item'] = 'Deleted item %s (was awaiting approval)';
$txt['media_admin_modlog_approval_com'] = 'Approved comment <a href="%s">%s</a>';
$txt['media_admin_modlog_approval_del_com'] = 'Deleted comment from item %s (was awaiting approval)';
$txt['media_admin_modlog_approval_album'] = 'Approved album <a href="%s">%s</a>';
$txt['media_admin_modlog_approval_del_album'] = 'Deleted album %s (was awaiting approval)';
$txt['media_admin_modlog_delete_item'] = 'Deleted item %s';
$txt['media_admin_modlog_delete_album'] = 'Deleted album %s';
$txt['media_admin_modlog_delete_comment'] = 'Deleted a comment from item %s';
$txt['media_admin_modlog_delete_report_item_report'] = 'Deleted a report on item #%s';
$txt['media_admin_modlog_delete_report_comment_report'] = 'Deleted a report on comment #%s';
$txt['media_admin_modlog_delete_item_item_report'] = 'Deleted reported item #%s';
$txt['media_admin_modlog_delete_item_comment_report'] = 'Deleted reported comment #%s';
$txt['media_admin_modlog_ban_add'] = 'Banned <a href="%s">%s</a>';
$txt['media_admin_modlog_ban_delete'] = 'Lifted ban on <a href="%s">%s</a>';
$txt['media_admin_modlog_prune_item'] = 'Pruned %s item(s)';
$txt['media_admin_modlog_prune_comment'] = 'Pruned %s comment(s)';
$txt['media_admin_modlog_move'] = 'Moved <a href=%s">%s</a> from album <a href="%s">%s</a> to <a href="%s">%s</a>';
$txt['media_admin_modlog_qsearch'] = 'Quick search by member';
$txt['media_admin_modlog_filter'] = 'Moderation logs filtered by <a href="%s">%s</a>';
$txt['media_admin_view_image'] = 'View image';
$txt['media_admin_ftp_files'] = 'Files inside the FTP folder';
$txt['media_admin_profile_add'] = 'Add profile';
$txt['media_admin_prof_name'] = 'Profile name';
$txt['media_admin_create_prof'] = 'Create profile';
$txt['media_admin_members'] = 'Members';
$txt['media_admin_prof_del_switch'] = 'Profile to switch albums to';
$txt['media_quota_profile'] = 'Membergroup quota profile';
$txt['media_album_hidden'] = 'Disable browsing';
$txt['media_album_hidden_desc'] = 'Enable this to prevent anyone but you from browsing this album. Its items can STILL be viewed by authorized membergroups. You may want to use this if you want to use it as a container for blog or forum post illustrations.';
$txt['media_allowed_members'] = 'Allowed members (read access)';
$txt['media_allowed_members_desc'] = 'Enter one or more names for members that you wish to allow viewing this album, regardless of their membergroup permissions.';
$txt['media_allowed_write'] = 'Allowed members (write access)';
$txt['media_allowed_write_desc'] = 'Enter one or more names for members that you wish to allow uploading rights for the album, regardless of their membergroup permissions.';
$txt['media_denied_members'] = 'Denied members (read access)';
$txt['media_denied_members_desc'] = 'Enter one or more names for members that you do NOT wish to allow viewing this album, regardless of their membergroup permissions.';
$txt['media_denied_write'] = 'Denied members (write access)';
$txt['media_denied_write_desc'] = 'Enter one or more names for members that you do NOT wish to allow posting rights for this album, regardless of their membergroup permissions.';
$txt['media_admin_wselected'] = 'With selected';
$txt['media_admin_select_or'] = 'Or...';
$txt['media_admin_apply_perm'] = 'Add permission';
$txt['media_admin_clear_perm'] = 'Clear permission';
$txt['media_admin_set_mg_perms'] = 'Set permissions like this group';

// Admin error strings
$txt['media_admin_album_confirm'] = 'Are you sure you want to delete this album? This will also remove all items and comments inside the album.';
$txt['media_admin_name_left_empty'] = 'Name was left empty';
$txt['media_admin_invalid_target'] = 'Invalid target specified';
$txt['media_admin_invalid_position'] = 'Invalid position specified';
$txt['media_admin_prune_invalid_days'] = 'Invalid &quot;days&quot; data specified';
$txt['media_admin_no_albums'] = 'No albums specified';
$txt['media_admin_rm_selected'] = 'Remove selected';
$txt['media_admin_rm_all'] = 'Remove all';
$txt['media_report_not_found'] = 'Report not found';
$txt['media_admin_bans_mems_empty'] = 'No members were specified';
$txt['media_admin_bans_mems_not_found'] = 'Members specified were not found';
$txt['media_ban_not_found'] = 'Ban not found';
$txt['media_admin_already_banned'] = 'User is already banned!';
$txt['media_admin_unique_permission'] = 'You must select only one option';
$txt['media_admin_quick_none'] = 'No option selected';
$txt['media_admin_invalid_groups'] = 'An invalid group selection was supplied, either the group does not exist or if you\'re copying permissions, make sure you have not selected the group you\'re copying permissions from or you have simply not selected any group.';

// Admin help strings
$txt['media_admin_desc'] = 'Media Admin';
$txt['media_admin_settings_desc'] = 'This is your settings admin panel. From here you can manage the settings for the Media area.';
$txt['media_admin_embed_desc'] = 'This is the auto-embedder admin panel. From here you can enable and disable auto-embedding of external multimedia links, such as YouTube. You can also view the site list and manage allowed websites.';
$txt['media_admin_albums_desc'] = 'This is your albums admin panel. From here you can manage your albums and do tasks like adding, removing, editing as well as moving the albums. Clicking on the <strong>+</strong> button will give you more info about that particular album.';
$txt['media_admin_subs_desc'] = 'This is your submissions admin panel. From here you can see, delete and approve unapproved items, comments and albums';
$txt['media_admin_maintenance_desc'] = 'This is your maintenance area; it contains some useful functions.';
$txt['media_admin_modlog_desc'] = 'This is your moderation log; it holds information about any moderation activity performed in your gallery.';
$txt['media_admin_reports_desc'] = 'This is your reports admin panel, here you can see and delete reported items and comments, or delete the report itself.';
$txt['media_admin_bans_desc'] = 'This is your bans admin panel where you can manage your gallery bans.';
$txt['media_admin_about_desc'] = 'Welcome to the Media Administration Area!';
$txt['media_admin_passwd_desc'] = 'Send it to users you want to share the album with. Otherwise, leave empty.';
$txt['media_admin_maintenance_finderror_pending'] = 'The script is still working. Currently %s out of %s items are done.<br><br><a href="%s">Please click here to continue.</a> Make sure you wait 1-2 seconds to avoid overload.';
$txt['media_admin_finderrors_1'] = 'The following errors were discovered when searching for errors';
$txt['media_admin_finderrors_missing_db_file'] = 'The DB entry of file #%s, used with item #<a href="%s">%s</a>, is missing.';
$txt['media_admin_finderrors_missing_db_thumb'] ='The DB entry of thumbnail #%s, used with item #<a href="%s">%s</a>, is missing.';
$txt['media_admin_finderrors_missing_db_preview'] ='The DB entry of preview #%s, used with item <a href="%s">%s</a>, is missing.';
$txt['media_admin_finderrors_missing_physical_file'] = 'The physical file #%s, used with item <a href="%s">%s</a>, is missing.';
$txt['media_admin_finderrors_missing_physical_thumb'] = 'The physical thumbnail #%s, used with item <a href="%s">%s</a>, is missing.';
$txt['media_admin_finderrors_missing_physical_preview'] = 'The physical preview file #%s, used with item <a href="%s">%s</a>, is missing.';
$txt['media_admin_finderrors_missing_album'] = 'The album #%s, associated with the item <a href="%s">%s</a>, is missing.';
$txt['media_admin_finderrors_missing_last_comment'] = 'The comment #%s, associated with item <a href="%s">%s</a> as its last comment, is missing.';
$txt['media_admin_finderrors_parent_album_access'] = 'Album #%s has been updated to remove groups that don\'t have access to its parent album.';
$txt['media_admin_finderrors_done'] = 'Checking for errors is done. No errors found!';
$txt['media_admin_prune_done_items'] = 'Pruning of items completed! %s items, %s comments and %s files deleted';
$txt['media_admin_prune_done_comments'] = 'Pruning of comments completed! %s comments deleted';
$txt['media_admin_maintenance_prune_item_help'] = 'Pruning items, you can prune items which are older than &quot;x&quot; days which you can define below. There are several other options which can be used as parameters <b>but are optional</b>. Albums would be either specifically selected or all.';
$txt['media_admin_maintenance_prune_com_help'] = 'Pruning comments, you can prune comments here which are &quot;x&quot; days old from all or specific albums.';
$txt['media_admin_maintenance_checkfiles_done'] = 'Unneeded files have been deleted, for a total of %s files, freeing %s kilobytes of space.';
$txt['media_admin_maintenance_checkfiles_no_files'] = 'No extra files found';
$txt['media_admin_maintenance_checkfiles_found'] = 'Found %s unneeded files using up %s kilobytes of extra space. <a href="%s">Click here</a> to remove them.';
$txt['media_admin_maintenance_checkorphans_done'] = 'All orphan files have been deleted, for a total of %s files:';
$txt['media_admin_maintenance_checkorphans_no_files'] = 'No orphan files found';
$txt['media_admin_maintenance_clear_pending'] = 'The script is still working. Currently %s out of %s items are done.<br><br>Please <a href="%s">click here</a> to continue. Make sure you wait 1-2 seconds to avoid overload.';
$txt['media_admin_maintenance_clear_done'] = 'All files have been successfully renamed.';
$txt['media_admin_installed_on'] = 'Installed on';
$txt['media_admin_icon_edit_desc'] = 'If you re-upload the icon, the old one will be overwritten. Leave empty to keep the current icon.';
$txt['media_admin_bans_mems_empty'] = 'No members were specified';
$txt['media_admin_expires_on_help'] = 'Should be entered in &quot;days&quot; from now';
$txt['media_admin_modlog_desc'] = 'This is the moderation log, here you will find the log of all the moderation action that took place. Please remember that deleting a moderation log will make it lost forever.';
$txt['media_admin_ftp_desc'] = 'This section allows you to import items into albums via a remote folder on the server. This can be helpful to upload very large files that PHP won\'t accept in a regular upload process.';
$txt['media_admin_ftp_help'] = 'Here is the file listing inside the {Data_dir}/ftp folder. Please select the target album for each folder, and start importing.';
$txt['media_admin_ftp_halted'] = 'The script is taking a short break to avoid server overload, currently completed %s of %s. The import will resume automatically.';
$txt['media_admin_perms_desc'] = 'Here you can manage the different permission profiles, which allow you to control per-album accesses.';
$txt['media_admin_prof_del_switch_help'] = 'If you want to delete a profile that is currently being used, the albums using it will require another profile to be assigned to them.';
$txt['media_admin_quotas_desc'] = 'Here you can manage the Membergroup Quota profiles';
$txt['media_admin_perms_warning'] = '<strong>Warning</strong>: this page is only for album permissions. General access permissions for the Media area are to be set membergroup by membergroup, in the regular <a href="%s">administration area</a>.';

// Per-album Permissions
$txt['permissionname_media_download_item'] = 'Download items';
$txt['permissionname_media_add_videos'] = 'Add video files';
$txt['permissionname_media_add_audios'] = 'Add audio files';
$txt['permissionname_media_add_docs'] = 'Add documents';
$txt['permissionname_media_add_embeds'] = 'Add embedded files';
$txt['permissionname_media_add_images'] = 'Add pictures';
$txt['permissionname_media_rate_items'] = 'Rate items';
$txt['permissionname_media_edit_own_com'] = 'Edit own comments';
$txt['permissionname_media_report_com'] = 'Report comment';
$txt['permissionname_media_edit_own_item'] = 'Edit own items';
$txt['permissionname_media_comment'] = 'Comment in items';
$txt['permissionname_media_report_item'] = 'Report items';
$txt['permissionname_media_auto_approve_com'] = 'Auto-approve comments';
$txt['permissionname_media_auto_approve_item'] = 'Auto-approve items';
$txt['permissionname_media_multi_upload'] = 'Mass Upload';
$txt['permissionname_media_whoratedwhat'] = 'View who rated what';
$txt['permissionname_media_multi_download'] = 'Mass Download';

// Custom fields
$txt['media_cf_invalid'] = 'The value submitted for %s is invalid';
$txt['media_cf_empty'] = 'Field %s was left empty';
$txt['media_cf_bbc'] = 'This field can have BBCode';
$txt['media_cf_required'] = 'This field is required';
$txt['media_cf_desc'] = 'Here you can manage the custom fields';
$txt['media_admin_labels_fields'] = 'Custom fields';
$txt['media_cf_name'] = 'Field name';
$txt['media_cf_type'] = 'Field type';
$txt['media_cf_req'] = 'Required';
$txt['media_cf_searchable'] = 'Searchable';
$txt['media_cf_bbcode'] = 'BBC';
$txt['media_cf_editing'] = 'Adding/editing a custom field';
$txt['media_cf_text'] = 'Text';
$txt['media_cf_radio'] = 'Radio buttons';
$txt['media_cf_checkbox'] = 'Checkboxes';
$txt['media_cf_textbox'] = 'Text box';
$txt['media_cf_select'] = 'Select dropdown';
$txt['media_cf_options'] = 'Field options';
$txt['media_cf_options_desc'] = 'Add options for the fields, only valid if the field types are checkbox, select or radio. Use comma (,) as a separator';
