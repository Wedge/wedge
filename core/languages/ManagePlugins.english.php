<?php
// Version: 2.0; ManagePlugins

$txt['plugin_manager_desc'] = 'In this area, all of the plugins on the server can be managed.';

$txt['fatal_not_valid_plugin'] = 'The specified plugin cannot be enabled because it appears to be missing or damaged.';
$txt['fatal_not_valid_plugin_remove'] = 'The specified plugin cannot be removed because it appears to be damaged.';
$txt['install_errors'] = 'This plugin cannot be used because:';
$txt['fatal_already_enabled'] = 'This plugin is already enabled.';
$txt['fatal_already_disabled'] = 'This plugin is already disabled.';
$txt['install_error_minphp'] = 'PHP version %1$s is required (%2$s available)';
$txt['fatal_install_error_minphp'] = 'This plugin cannot be installed, it requires PHP version %1$s, but this server only has version %2$s installed.';
$txt['install_error_minmysql'] = 'MySQL version %1$s is required (%2$s available)';
$txt['fatal_install_error_minmysql'] = 'This plugin cannot be installed, it requires MySQL version %1$s, but this server only has version %2$s installed.';
$txt['install_error_missinghook'] = 'One or more features required by this plugin are not available.';
$txt['install_error_reqfunc'] = 'This function requires the following PHP functions to be accessible: %1$s';
$txt['install_error_duplicate_id'] = 'Another plugin with the same identification is already enabled.';
$txt['fatal_duplicate_id'] = 'Another plugin with the same plugin identification is already enabled, perhaps a different version of the same plugin. It needs to be disabled before this plugin can be enabled.';
$txt['install_error_maint_mode'] = 'You need to be in maintenance mode before you can install this plugin.';
$txt['fatal_install_error_maint_mode'] = 'This plugin will make large scale database changes on your forum. To make that process easier, and faster, the plugin will not allow itself to be installed until the forum is put into maintenance mode. This is a safety feature and should not be ignored.';
$txt['fatal_remove_error_maint_mode'] = 'This plugin made large scale database changes on your forum when installing. To make it easier to process, and faster, the plugin requires that the forum be put into maintenance mode before it is removed. This is a safety feature and should not be ignored.';
$txt['fatal_install_error_reqfunc'] = 'This plugin makes use of features not currently supported by your PHP installation, that have the following function names: %1$s. You should contact your web-host for more information.';
$txt['fatal_install_error_missinghook'] = 'This plugin makes use of features not currently available in this install, that have the references: %1$s. You should probably contact the plugin\'s author for support.';
$txt['fatal_install_enable_missing'] = 'This plugin specifies that a certain file contains instructions to be carried out when enabling it, %1$s, but the file cannot be found where the plugin said it should be.';
$txt['fatal_install_disable_missing'] = 'This plugin specifies that a certain file contains instructions to be carried out when disabling it, %1$s, but the file cannot be found where the plugin said it should be.';
$txt['fatal_install_remove_missing'] = 'This plugin specifies that a certain file contains instructions to be carried out when removing it, %1$s, but the file cannot be found where the plugin said it should be.';
$txt['fatal_conflicted_plugins'] = 'This plugin provides features to other plugins. You cannot disable it without disabling the following plugin(s): %1$s';
$txt['no_plugins_found'] = 'No plugins found.';

$txt['plugin_written_by'] = 'Written by';
$txt['plugin_author_url'] = 'Author\'s website';
$txt['plugin_website'] = 'Visit the website for %1$s';
$txt['plugin_author_email'] = 'Email the author';
$txt['plugin_readmes'] = 'Information about this plugin';

$txt['invalid_plugin_readme'] = 'No suitable readme could be found for this plugin.';
$txt['enable_plugin'] = 'Enable this plugin';
$txt['disable_plugin'] = 'Disable this plugin';
$txt['remove_plugin'] = 'Remove this plugin';

$txt['remove_plugin_desc'] = 'You have selected that you want to remove the plugin: %1$s.';
$txt['remove_plugin_blurb'] = 'There are two ways that a plugin can be removed.';
$txt['remove_plugin_nodelete'] = 'Saving the data';
$txt['remove_plugin_nodelete_desc'] = 'The plugin and its files will be removed, but any data and settings will be kept. For more information about what will be kept, please contact the plugin\'s author.';
$txt['remove_plugin_delete'] = 'Removing the data';
$txt['remove_plugin_delete_desc'] = 'The plugin, plus its data and settings will be <strong>removed</strong>. There is no undo facility for this! <strong>Only perform this if you are sure you do not want to use this plugin.</strong>';
$txt['remove_plugin_unsure'] = 'If you are not sure whether you want to keep the data or not, select to "not remove the data".';
$txt['remove_plugin_already_enabled'] = 'This plugin is currently enabled. You must disable it before attempting to remove it.';
$txt['remove_plugin_files_still_there'] = 'The files for your plugin could not be deleted. You may have to log into your server with FTP to remove the %1$s folder.';
$txt['remove_plugin_files_pre_still_there'] = 'Your plugin has not yet been removed; the files that make it up cannot be deleted without logging into FTP to change the permissions to make them deletable.';
$txt['remove_plugin_maint'] = 'This plugin states that it requires the forum to be in maintenance mode before it can be removed due to large scale database changes.';

$txt['plugin_filter'] = 'Filter plugins:';
$txt['plugin_filter_all'] = 'All (%1$d)';
$txt['plugin_filter_enabled'] = 'Enabled (%1$d)';
$txt['plugin_filter_disabled'] = 'Disabled (%1$d)';
$txt['plugin_filter_install_errors'] = 'Incompatible (%1$d)';

$txt['could_not_connect_remote'] = 'Wedge was unable to log in to the server to perform changes for you.<br>The server returned the following error: %1$s<br><br>You may wish to try going back and checking the login details provided.';

$txt['plugins_no_gzinflate'] = 'Your server does not support gzinflate, so the upload/download plugin facilities are not available. Your host may be able to help if they can enable zlib support in PHP.';
$txt['plugins_add_desc'] = 'From this area, you can add new plugins to your forum.';
$txt['plugins_add_download'] = 'Download a Plugin from a Repository';
$txt['plugins_add_download_desc'] = 'There are repositories that contain various plugins for your forum, you can use the options below to find plugins on them.';
$txt['plugins_repository'] = 'Repository';
$txt['plugins_active'] = 'Active';
$txt['plugins_browse'] = 'Browse';
$txt['plugins_modify'] = 'Modify';
$txt['plugins_no_repos'] = 'No repositories listed.';
$txt['plugins_add_repo'] = 'Add repository';
$txt['plugins_repo_auth'] = 'When contacting this repository, login details for it will be supplied.';
$txt['plugins_repo_error'] = 'Error';
$txt['plugins_add_upload'] = 'Upload a Plugin from your computer';
$txt['plugins_add_upload_desc'] = 'You can use this facility to upload a plugin in .zip format to your forum.';
$txt['plugins_add_upload_file'] = 'The plugin file to upload:';
$txt['plugins_upload_plugin'] = 'Upload Plugin';

$txt['plugins_browse_invalid_error'] = 'You tried to browse a plugin repository that does not exist.';
$txt['plugins_browse_could_not_connect'] = 'The selected plugin repository could not be located; it may be temporarily unavailable. You may be able to browse it manually via its link: <a href="%1$s">%1$s</a>';

$txt['plugins_edit_repo'] = 'Edit repository';
$txt['plugins_edit_repo_desc'] = 'From this page, you can provide the details for additional repositories for plugins.';
$txt['plugins_edit_invalid'] = 'You tried to edit a plugin repository that does not exist. You can add a new repository below.';
$txt['plugins_edit_invalid_error'] = 'You tried to edit a plugin repository that does not exist.';
$txt['plugins_repo_details'] = 'Repository Details';
$txt['plugins_repo_details_desc'] = 'A repository needs a name and an address, and optionally if you need to, you can also supply a username and password that will be used when contacting the repository for plugins.';

$txt['plugins_repo_name'] = 'Repository name';
$txt['plugins_repo_address'] = 'Repository address';
$txt['plugins_repo_active'] = 'Repository is active';
$txt['plugins_repo_delete'] = 'Delete';
$txt['plugins_repo_delete_confirm'] = 'Are you sure you wish to remove this repository? There is no undo function for this.';

$txt['plugins_repo_auth'] = 'Authorization Details';
$txt['plugins_repo_auth_desc'] = 'If this repository needs a username and password, it should be entered here. If no authorization details should be used, leave both boxes blank.';
$txt['plugins_repo_username'] = 'Repository username';
$txt['plugins_repo_password'] = 'Repository password';
$txt['plugins_repo_password_blank'] = 'Why is it blank?';

$txt['plugins_repo_no_name'] = 'No repository name was provided; one is required.';
$txt['plugins_repo_no_url'] = 'No address was provided for the repository; one is required.';
$txt['plugins_repo_invalid_url'] = 'The address provided for the repository was invalid, please recheck and try again.';
$txt['plugins_auth_pwd_nouser'] = 'You provided a password for a repository but no username - if you wish to provide account details for a repository, both username and password are required.';
$txt['plugins_auth_diffuser'] = 'You have provided a username but no password (or, you\'ve tried to change the username attached to this repository, and not re-supplied the password), both must be given if details are to be used.';

$txt['plugins_invalid_upload'] = 'You appear to have attempted an upload of a plugin, but the file could not be saved. Perhaps it was too large for the server, or some other hosting limit. Remember: the plugin can always be extracted on your own computer and uploaded via FTP or SFTP to your forum\'s Plugins/ folder instead.';
$txt['plugins_unable_read'] = 'The plugin was uploaded but for some reason, Wedge was not able to read the plugin. This may be caused by unusual host configuration, and may mean that you will need to upload plugins to the forum\'s Plugins/ folder via FTP or SFTP rather than through the web interface.';
$txt['plugins_unable_write'] = 'The plugin was uploaded but for some reason, Wedge was not able to unpack the plugin\'s files to your server. This may be caused by unusual host configuration, and may mean that you will need to upload plugins to the forum\'s Plugins/ folder via FTP or SFTP rather than through the web interface.';
$txt['plugins_invalid_zip'] = 'The plugin was uploaded but for some reason, the ZIP file appears to be invalid and cannot be unpacked into a usable plugin. You might want to try re-downloading the plugin from wherever you acquired it, or alternatively you can unpack it manually onto your computer and then upload it to the forum\'s Plugins/ folder through FTP or SFTP.';
$txt['plugins_generic_error'] = 'The plugin was uploaded but unfortunately something went unexpectedly wrong. Please contact Wedge support and use the reference: %1$s:%2$s when describing your problem.';
$txt['plugins_invalid_plugin_no_info'] = 'Your plugin was uploaded, but it does not contain a valid plugin-info.xml file that Wedge needs to understand it. Contact the plugin\'s author for support.';
$txt['plugins_invalid_plugin_overinfo'] = 'Your plugin was uploaded, but it contains multiple files called plugin-info.xml, and Wedge does not know which of those it should refer to. Contact the plugin\'s author for support.';
$txt['plugins_uploaded_error'] = 'There was a problem with the plugin since you uploaded it. Please try uploading it again.';
$txt['plugins_uploaded_tampering'] = 'There was a problem with the plugin since you uploaded it; there are some signs that it may have been tampered with and has been removed for your protection.';

$txt['plugin_duplicate_detected_title'] = 'Duplicate Plugin Detected';
$txt['plugin_duplicate_detected'] = 'The plugin you have uploaded ($1$s) appears to be a duplicate of an existing plugin already active in your site (%2$s). What would you like to do about this?';
$txt['plugin_duplicate_cancel'] = 'I don\'t want to do anything right now';
$txt['plugin_duplicate_cancel_desc'] = 'The existing plugin will be left alone and remain installed, and the file you have just uploaded will be cleaned up.';
$txt['plugin_duplicate_proceed'] = 'I want to replace my old plugin with the new one';
$txt['plugin_duplicate_proceed_desc'] = 'The existing plugin will be disabled and its files (but not its data) removed, and the new one will be unpacked ready for you to re-enable it.';

$txt['plugin_upload_successful_title'] = 'Upload Successful';
$txt['plugin_upload_successful'] = 'Your plugin file was successfully uploaded and so far appears to be valid. Next, Wedge will begin the process of unpacking the plugin. This may be done in several steps to limit server load.';

$txt['plugin_connection_successful_title'] = 'Connection Successful';
$txt['plugin_connection_successful'] = 'The provided details for your server seem to be correct, so we can now proceed to the next stage of the upload.';

$txt['plugin_connection_details_title'] = 'Connection Details';
$txt['plugin_connection_details'] = 'In order to proceed, Wedge requires FTP or SFTP credentials so that your plugin can be properly installed on the server.';
$txt['plugin_connection_cancel_oops'] = 'Um...';
$txt['plugin_connection_cancel'] = 'In the event that your host has not given you FTP or SFTP access details, you will not be able to use the web interface and will need to contact your host about other ways to manage files; in these cases you may need to manually use your hosting control panel.';
$txt['plugin_connection_button'] = 'I haven\'t got these';
$txt['plugin_connection_required'] = 'Assuming you have got such details, here is what Wedge needs to know from you.';

$txt['plugin_connection'] = 'These are my details';
$txt['plugin_ftp_server'] = 'Server:';
$txt['plugin_ftp_port'] = 'Port:';
$txt['plugin_ftp_username'] = 'Username:';
$txt['plugin_ftp_password'] = 'Password:';
$txt['plugin_ftp_type'] = 'Type of connection:';
$txt['plugin_ftp_path'] = 'Path to the Plugins folder:';
$txt['plugin_ftp_save'] = 'Save these details for later';
$txt['plugin_ftp_error'] = 'The details provided were not correct:';
$txt['plugin_ftp_error_bad_server'] = 'The server provided appears to be incorrect.';
$txt['plugin_ftp_error_bad_response'] = 'After contacting the FTP server, there was a strange response from it; perhaps try again later.';
$txt['plugin_ftp_error_bad_username'] = 'The username details provided were incorrect.';
$txt['plugin_ftp_error_bad_password'] = 'The password provided was incorrect.';
$txt['plugin_ftp_error_wrong_folder'] = 'The folder provided was incorrect, and all attempts to find the correct folder all failed.';

$txt['plugin_files_pruned_title'] = 'Old plugin removed';
$txt['plugin_files_pruned'] = 'The old plugin was successfully removed. Wedge will now begin to install the new plugin.';

$txt['plugin_folders_created_title'] = 'Folders created';
$txt['plugin_folders_created'] = 'Before unpacking the files, Wedge had to add all the relevant folders for the files in your plugin. This has now been done, so all that remains is to upload the files.';

$txt['plugin_files_unpacked_title'] = 'Files unpacked';
$txt['plugin_files_unpacked'] = 'All the files have been unpacked. You should now be able to enable your plugin!';
