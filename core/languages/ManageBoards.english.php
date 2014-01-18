<?php
// Version: 2.0; ManageBoards

$txt['boards_and_cats'] = 'Manage Boards and Categories';
$txt['order'] = 'Order';
$txt['full_name'] = 'Full name';
$txt['name_on_display'] = 'This is the name that will be displayed.';
$txt['boards_and_cats_desc'] = 'Edit your categories and boards here. List multiple moderators as <em>&quot;username&quot;, &quot;username&quot;</em>. (These must be usernames, not display names!)<br>To create a new board, click the Add Board button. To create a sub-board, select "Sub-board of..." from the Order drop down menu when creating the board, and select its parent board.';
$txt['parent_members_only'] = 'Regular Members';
$txt['parent_guests_only'] = 'Guests';
$txt['catConfirm'] = 'Do you really want to delete this category?';
$txt['boardConfirm'] = 'Do you really want to delete this board?';

$txt['catEdit'] = 'Edit Category';
$txt['collapse_enable'] = 'Collapsible';
$txt['collapse_desc'] = 'Allow users to collapse this category';
$txt['catModify'] = '(modify)';

$txt['mboards_order_after'] = 'After ';
$txt['mboards_order_inside'] = 'Inside ';
$txt['mboards_order_first'] = 'In first place';

$txt['mboards_new_board'] = 'Add Board';
$txt['mboards_new_cat_name'] = 'New Category';
$txt['mboards_add_cat_button'] = 'Add Category';
$txt['mboards_new_board_name'] = 'New Board';

$txt['mboards_name'] = 'Name';
$txt['mboards_modify'] = 'modify';
$txt['mboards_permissions'] = 'Permissions';
// Don't use entities in the below string.
$txt['mboards_permissions_confirm'] = 'Are you sure you want to switch this board to use local permissions?';

$txt['mboards_delete_cat'] = 'Delete Category';
$txt['mboards_delete_board'] = 'Delete Board';

$txt['mboards_delete_cat_contains'] = 'Deleting this category will also delete the below boards, including all topics, posts and attachments within each board';
$txt['mboards_delete_option1'] = 'Delete category and all boards contained within.';
$txt['mboards_delete_option2'] = 'Delete category and move all boards contained within to';
$txt['mboards_delete_board_contains'] = 'Deleting this board will also move the sub-boards below, including all topics, posts and attachments within each board';
$txt['mboards_delete_board_option1'] = 'Delete board and move sub-boards contained within to category level.';
$txt['mboards_delete_board_option2'] = 'Delete board and move all sub-boards contained within to';
$txt['mboards_delete_what_do'] = 'Please select what you would like to do with these boards';
$txt['mboards_delete_confirm'] = 'Confirm';
$txt['mboards_delete_cancel'] = 'Cancel';

$txt['mboards_category'] = 'Category';
$txt['mboards_description'] = 'Description';
$txt['mboards_description_desc'] = 'A short description of your board.';
$txt['mboards_groups'] = 'Allowed Groups';
$txt['mboards_groups_desc'] = 'Groups allowed to access this board.<br><em>Note: if the member is in any group or post group checked, they will have access to this board.</em>';
$txt['mboards_groups_regular_members'] = 'This group contains all members that have no primary group set.';
$txt['mboards_groups_post_group'] = 'This group is a post count based group.';

$txt['mboards_never'] = 'Never';
$txt['mboards_view_board'] = 'Who can see this board';
$txt['mboards_enter_board'] = 'Who can enter this board';
$txt['mboards_groups_view_enter_same'] = 'All groups who can see a board can enter it as well';
$txt['mboards_groups_need_deny_perm'] = 'I want to deny a group from this board';
$txt['mboards_groups_everyone'] = 'Everyone';
$txt['mboards_groups_everyone_desc'] = 'Use this option to quickly select all the groups below.';
$txt['mboards_offlimits_msg'] = 'Message to those who can see this board but not enter it:';

$txt['mboards_moderators'] = 'Moderators';
$txt['mboards_moderators_desc'] = 'Additional members to have moderation privileges on this board. Note that administrators don\'t have to be listed here.';
$txt['mboards_count_posts'] = 'Count Posts';
$txt['mboards_count_posts_desc'] = 'Makes new replies and topics raise members\' post counts.';
$txt['mboards_unchanged'] = 'Unchanged';
$txt['mboards_skin'] = 'Board Skin';
$txt['mboards_skin_desc'] = 'This allows you to change the look of your forum inside only this board.';
$txt['mboards_skin_mobile'] = 'Board Mobile Skin';
$txt['mboards_skin_mobile_desc'] = 'Same, but when accessed from a mobile device.';
$txt['mboards_skin_default'] = '(overall forum default)';
$txt['mboards_override_skin'] = 'Override Member\'s Skin';
$txt['mboards_override_skin_desc'] = 'Use this board\'s skin even if the member didn\'t choose to use the defaults.';
$txt['mboards_language'] = 'Board Language';
$txt['mboards_language_desc'] = 'This allows you to set the default language of the forum (such as the text on buttons) inside only this board. Note that this is only a default, if a user picks a language preference, their preference will override this setting.';

$txt['mboards_type'] = 'Board Type';
$txt['mboards_type_desc'] = 'This changes the way the board\'s homepage is presented. Board shows a tabular list of topics, sorted by latest reply. Blog shows a list of posts sorted by date, regardless of comment dates.';

$txt['mboards_redirect'] = 'Redirect to a web address';
$txt['mboards_redirect_desc'] = 'Enable this option to redirect anyone who clicks on this board to another web address.';
$txt['mboards_redirect_url'] = 'Address to redirect users to';
$txt['mboards_redirect_url_desc'] = 'For example: &quot;http://wedge.org&quot;.';
$txt['mboards_redirect_newtab'] = 'Open the new address in a new tab';
$txt['mboards_redirect_reset'] = 'Reset redirect count';
$txt['mboards_redirect_reset_desc'] = 'Selecting this will reset the redirection count for this board to zero.';
$txt['mboards_current_redirects'] = 'Currently: %1$s';
$txt['mboards_redirect_disabled'] = 'Note: Board must be empty of topics to enable this option.';
$txt['mboards_redirect_disabled_recycle'] = 'Note: You cannot set the recycle bin board to be a redirection board.';

$txt['mboards_order_before'] = 'Before';
$txt['mboards_order_child_of'] = 'Sub-board of';
$txt['mboards_order_in_category'] = 'In category';
$txt['mboards_current_position'] = 'Current Position';
$txt['no_valid_parent'] = 'Board %1$s does not have a valid parent. Use the \'find and repair errors\' function to fix this.';

$txt['mboards_recycle_disabled_delete'] = 'Note: You must select an alternative recycle bin board or disable recycling before you can delete this board.';

$txt['mboards_settings_desc'] = 'Edit general board and category settings.';
$txt['groups_manage_boards'] = 'Membergroups allowed to manage boards and categories';
$txt['recycle_enable'] = 'Enable recycling of deleted topics';
$txt['recycle_board'] = 'Board for recycled topics';
$txt['recycle_board_unselected_notice'] = 'You have enabled the recycling of topics without specifying a board to place them in. This feature will not be enabled until you specify a board to place recycled topics into.';
$txt['countChildPosts'] = 'Count sub-board\'s posts in parent\'s totals';
$txt['ignorable_boards'] = 'Which boards can users ignore?';
$txt['display_flags'] = 'Display flags on the board index?';
$txt['flags_none'] = 'No flags';
$txt['flags_specified'] = 'Flags for boards that have a language set';
$txt['flags_all'] = 'Flags for all boards';
$txt['modcenter_category'] = 'Position of the Moderation category';
$txt['modcenter_category_first'] = 'As the first category';
$txt['modcenter_category_after'] = 'After the "%1$s" category';

$txt['mboards_select_destination'] = 'Select destination for board \'<strong>%1$s</strong>\'';
$txt['mboards_cancel_moving'] = 'Cancel moving';
$txt['mboards_move'] = 'move';

$txt['mboards_no_cats'] = 'There are currently no categories or boards configured.';
