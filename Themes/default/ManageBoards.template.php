<?php
/**
 * Wedge
 *
 * User interface for creating boards and categories, and editing boards in general.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

// Template for listing all the current categories and boards.
function template_main()
{
	global $context, $theme, $options, $txt, $settings;

	// Table header.
	echo '
	<div id="manage_boards">
		<we:cat>
			', $txt['boardsEdit'], '
		</we:cat>';

	if (!empty($context['move_board']))
		echo '
		<div class="information">
			<div class="floatright"><form action="<URL>?action=admin;area=manageboards" method="post"><input type="submit" class="cancel" value="', $txt['mboards_cancel_moving'], '"></form></div>
			<p>', $context['move_title'], '</p>
		</div>';

	// No categories so show a label.
	if (empty($context['categories']))
		echo '
		<div class="windowbg wrc center">
			', $txt['mboards_no_cats'], '
		</div>';

	// Loop through every category, listing the boards in each as we go.
	foreach ($context['categories'] as $category)
	{
		// Link to modify the category.
		echo '
		<we:title>
			<a href="<URL>?action=admin;area=manageboards;sa=cat;cat=' . $category['id'] . '">', $category['name'], '</a> <a href="<URL>?action=admin;area=manageboards;sa=cat;cat=' . $category['id'] . '">', $txt['catModify'], '</a>
		</we:title>';

		// Boards table header.
		echo '
		<form action="<URL>?action=admin;area=manageboards;sa=newboard;cat=', $category['id'], '" method="post" accept-charset="UTF-8">
			<div class="windowbg wrc">
				<ul id="category_', $category['id'], '">';

		if (!empty($category['move_link']))
			echo '
					<li><a href="', $category['move_link']['href'], '" title="', $category['move_link']['label'], '"><img src="', $theme['images_url'], '/smiley_select_spot.gif" alt="', $category['move_link']['label'], '"></a></li>';

		$alternate = false;

		// List through every board in the category, printing its name and link to modify the board.
		foreach ($category['boards'] as $board)
		{
			$alternate = !$alternate;

			echo '
					<li', !empty($settings['recycle_board']) && !empty($settings['recycle_enable']) && $settings['recycle_board'] == $board['id'] ? ' id="recycle_board"' : '', ' class="windowbg', $alternate ? '' : '2', '" style="padding-', $context['right_to_left'] ? 'right' : 'left', ': ', 5 + 30 * $board['child_level'], 'px', $board['move'] ? '; color: red' : '', '"><span class="floatleft"><img src="', $theme['default_theme_url'] . '/languages/Flag.', empty($board['language']) ? $settings['language'] : $board['language'], '.png"> <a href="<URL>?board=', $board['id'], '">', $board['name'], '</a>', !empty($settings['recycle_board']) && !empty($settings['recycle_enable']) && $settings['recycle_board'] == $board['id'] ? '<a href="<URL>?action=admin;area=manageboards;sa=settings"> <img src="' . $theme['images_url'] . '/post/recycled.gif" alt="' . $txt['recycle_board'] . '"></a></span>' : '</span>', '
						<span class="floatright">', $context['can_manage_permissions'] ? '<span class="modify_boards"><a href="<URL>?action=admin;area=permissions;sa=index;pid=' . $board['permission_profile'] . ';' . $context['session_query'] . '">' . $txt['mboards_permissions'] . '</a></span>' : '', '
						<span class="modify_boards"><a href="<URL>?action=admin;area=manageboards;move=', $board['id'], '">', $txt['mboards_move'], '</a></span>
						<span class="modify_boards"><a href="<URL>?action=admin;area=manageboards;sa=board;boardid=', $board['id'], '">', $txt['mboards_modify'], '</a></span></span><br class="clear_right">
					</li>';

			if (!empty($board['move_links']))
			{
				$alternate = !$alternate;

				echo '
					<li class="windowbg', $alternate ? '' : '2', '" style="padding-', $context['right_to_left'] ? 'right' : 'left', ': ', 5 + 30 * $board['move_links'][0]['child_level'], 'px">';

				foreach ($board['move_links'] as $link)
					echo '
						<a href="', $link['href'], '" class="move_links" title="', $link['label'], '"><img src="', $theme['images_url'], '/board_select_spot', $link['child_level'] > 0 ? '_child' : '', '.gif" alt="', $link['label'], '" style="padding: 0; margin: 0"></a>';

				echo '
					</li>';
			}
		}

		// Button to add a new board.
		echo '
				</ul>
				<div class="right">
					<input type="submit" value="', $txt['mboards_new_board'], '" class="new">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				</div>
			</div>
		</form>';
	}
	echo '
	</div>
	<br class="clear">';
}

// Template for editing/adding a category on the forum.
function template_modify_category()
{
	global $context, $theme, $options, $txt;

	// Print table header.
	echo '
	<div id="manage_boards">
		<form action="<URL>?action=admin;area=manageboards;sa=cat2" method="post" accept-charset="UTF-8">
			<input type="hidden" name="cat" value="', $context['category']['id'], '">
				<we:cat>
					', isset($context['category']['is_new']) ? $txt['mboards_new_cat_name'] : $txt['catEdit'], '
				</we:cat>
				<div class="windowbg wrc">
					<dl class="settings">';

	// If this isn't the only category, let the user choose where this category should be positioned down the board index.
	if (count($context['category_order']) > 1)
	{
		echo '
					<dt><strong>', $txt['order'], ':</strong></dt>
					<dd>
						<select name="cat_order">';
		// Print every existing category into a select box.
		foreach ($context['category_order'] as $order)
			echo '
							<option', $order['selected'] ? ' selected' : '', ' value="', $order['id'], '">', $order['name'], '</option>';
		echo '
						</select>
					</dd>';
	}
	// Allow the user to edit the category name and/or choose whether you can collapse the category.
	echo '
					<dt>
						<strong>', $txt['full_name'], ':</strong>
						<dfn>', $txt['name_on_display'], '</dfn>
					</dt>
					<dd>
						<input name="cat_name" value="', $context['category']['editable_name'], '" size="30" tabindex="', $context['tabindex']++, '">
					</dd>
					<dt>
						<strong>' . $txt['collapse_enable'] . '</strong>
						<dfn>' . $txt['collapse_desc'] . '</dfn>
					</dt>
					<dd>
						<input type="checkbox" name="collapse"', $context['category']['can_collapse'] ? ' checked' : '', ' tabindex="', $context['tabindex']++, '">
					</dd>';

	// Table footer.
	echo '
				</dl>
				<hr>
				<div class="right">';

	if (isset($context['category']['is_new']))
		echo '
					<input type="submit" name="add" value="', $txt['mboards_add_cat_button'], '" onclick="return $.trim(this.form.cat_name.value) !== \'\';" tabindex="', $context['tabindex']++, '" class="new">';
	else
		echo '
					<input type="submit" name="edit" value="', $txt['modify'], '" onclick="return $.trim(this.form.cat_name.value) !== \'\';" tabindex="', $context['tabindex']++, '" class="save">
					<input type="submit" name="delete" value="', $txt['mboards_delete_cat'], '" onclick="return ask(', JavaScriptEscape($txt['catConfirm']), ', e);" class="delete">';
	echo '
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">';

	// If this category is empty we don't bother with the next confirmation screen.
	if ($context['category']['is_empty'])
		echo '
					<input type="hidden" name="empty" value="1">';

	echo '
				</div>
			</div>
		</form>
	</div>
	<br class="clear">';
}

// A template to confirm if a user wishes to delete a category - and whether they want to save the boards.
function template_confirm_category_delete()
{
	global $context, $theme, $options, $txt;

	// Print table header.
	echo '
	<div id="manage_boards">
		<form action="<URL>?action=admin;area=manageboards;sa=cat2" method="post" accept-charset="UTF-8">
			<input type="hidden" name="cat" value="', $context['category']['id'], '">
			<we:cat>
				', $txt['mboards_delete_cat'], '
			</we:cat>
			<div class="windowbg wrc">
				<p>', $txt['mboards_delete_cat_contains'], ':</p>
				<ul>';

	foreach ($context['category']['children'] as $child)
		echo '
					<li>', $child, '</li>';

	echo '
				</ul>
			</div>
			<we:title>
				', $txt['mboards_delete_what_do'], '
			</we:title>
			<div class="windowbg wrc">
				<p>
					<label><input type="radio" id="delete_action0" name="delete_action" value="0" checked>', $txt['mboards_delete_option1'], '</label><br>
					<label><input type="radio" id="delete_action1" name="delete_action" value="1"', count($context['category_order']) == 1 ? ' disabled' : '', '>', $txt['mboards_delete_option2'], '</label>:
					<select name="cat_to"', count($context['category_order']) == 1 ? ' disabled' : '', '>';

	foreach ($context['category_order'] as $cat)
		if ($cat['id'] != 0)
			echo '
						<option value="', $cat['id'], '">', $cat['true_name'], '</option>';

	echo '
					</select>
				</p>
				<hr>
				<div class="right">
					<input type="submit" name="delete" value="', $txt['mboards_delete_confirm'], '" class="delete">
					<input type="submit" name="cancel" value="', $txt['mboards_delete_cancel'], '" class="cancel">
					<input type="hidden" name="confirmation" value="1">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				</div>
			</div>
		</form>
	</div>
	<br class="clear">';
}

// Below is the template for adding/editing an board on the forum.
function template_modify_board()
{
	global $context, $theme, $options, $txt, $settings;

	// The main table header.
	echo '
	<div id="manage_boards">
		<form action="<URL>?action=admin;area=manageboards;sa=board2" method="post" accept-charset="UTF-8">
			<input type="hidden" name="boardid" value="', $context['board']['id'], '">
			<we:cat>
				', isset($context['board']['is_new']) ? $txt['mboards_new_board_name'] : $txt['boardsEdit'], '
			</we:cat>
			<div class="windowbg wrc">
				<dl class="settings">';

	// Option for choosing the category the board lives in.
	echo '
					<dt>
						<strong>', $txt['mboards_category'], ':</strong>
					</dt>
					<dd>
						<select name="new_cat" onchange="if (this.form.order) {this.form.order.disabled = this.options[this.selectedIndex].value != 0; this.form.board_order.disabled = this.options[this.selectedIndex].value != 0 || this.form.order.options[this.form.order.selectedIndex].value == \'\';}">';

	foreach ($context['categories'] as $category)
		echo '
							<option', $category['selected'] ? ' selected' : '', ' value="', $category['id'], '">', $category['name'], '</option>';

	echo '
						</select>
					</dd>';

	// If this isn't the only board in this category let the user choose where the board is to live.
	if ((isset($context['board']['is_new']) && count($context['board_order']) > 0) || count($context['board_order']) > 1)
	{
		echo '
					<dt>
						<strong>', $txt['order'], ':</strong>
					</dt>
					<dd>';

		// The first select box gives the user the option to position it before, after or as a child of another board.
		echo '
						<select id="order" name="placement" onchange="$(\'#board_order\').prop(\'disabled\', $(this).val() == \'\'); $(\'#board_order\').sb();">
							', !isset($context['board']['is_new']) ? '<option value="">(' . $txt['mboards_unchanged'] . ')</option>' : '', '
							<option value="after">' . $txt['mboards_order_after'] . '...</option>
							<option value="child">' . $txt['mboards_order_child_of'] . '...</option>
							<option value="before">' . $txt['mboards_order_before'] . '...</option>
						</select>';

		// The second select box lists all the boards in the category.
		echo '
						<select id="board_order" name="board_order"', isset($context['board']['is_new']) ? '' : ' disabled', '>
							', !isset($context['board']['is_new']) ? '<option value="">(' . $txt['mboards_unchanged'] . ')</option>' : '';

		foreach ($context['board_order'] as $order)
			echo '
							<option', $order['selected'] ? ' selected' : '', ' value="', $order['id'], '">', $order['name'], '</option>';

		echo '
						</select>
					</dd>';
	}

	echo '
				</dl>
				<hr>
				<dl class="settings">
					<dt>
						<strong>', $txt['full_name'], ':</strong>
						<dfn>', $txt['name_on_display'], '</dfn>
					</dt>
					<dd>
						<input name="board_name" value="', $context['board']['name'], '" size="30">
					</dd>';

	if (!empty($settings['pretty_filters']['boards']))
	{
		$m = array(1 => '', 2 => $_SERVER['HTTP_HOST'], 3 => '');
		if (isset($context['board']['url']))
			preg_match('~(?:([a-z0-9-]+)\.)?([^.]+\.\w{2,4})(?:/([a-z0-9/-]+))?~', $context['board']['url'], $m);

		// Options for board name and description.
		echo '
					<dt>
						<strong>', $txt['pretty']['url'], ':</strong>
						<dfn>', $txt['pretty']['url_desc'], '</dfn>
					</dt>
					<dd class="nowrap">
						<select dir="rtl" name="pretty_url_dom">';

		foreach ($context['board']['subdomains'] as $subdo)
			echo !empty($subdo) ? '
							<option value="' . $subdo . '"' . (isset($m[1], $m[2]) && ($m[1] . '.' . $m[2] == $subdo || $m[2] == $subdo) ? ' selected' : '') . '>' . $subdo . '</option>' : '';

		echo '
						</select>/<input maxlength="32" name="pretty_url" value="' . (!empty($m[3]) ? $m[3] : '') . '" size="25">
					</dd>';
	}

	echo '
					<dt>
						<strong>', $txt['mboards_description'], ':</strong>
						<dfn>', $txt['mboards_description_desc'], '</dfn>
					</dt>
					<dd>
						<textarea name="desc" rows="6" style="', we::is('ie8') ? 'width: 635px; max-width: 99%; min-width: 99%' : 'width: 99%', '">', $context['board']['description'], '</textarea>
					</dd>
					<dt>
						<strong>', $txt['permission_profile'], ':</strong>
						<dfn>', $context['can_manage_permissions'] ? sprintf($txt['permission_profile_desc'], '<URL>?action=admin;area=permissions;sa=profiles;' . $context['session_query']) : strip_tags($txt['permission_profile_desc']), '</dfn>
					</dt>
					<dd>
						<select name="profile">';

	if (isset($context['board']['is_new']))
		echo '
							<option value="-1">[', $txt['permission_profile_inherit'], ']</option>';

	foreach ($context['profiles'] as $id => $profile)
		echo '
							<option value="', $id, '"', $id == $context['board']['profile'] ? ' selected' : '', '>', $profile['name'], '</option>';

	echo '
						</select>
					</dd>
					<dt>
						<strong>', $txt['mboards_groups'], ':</strong>
						<dfn>', $txt['mboards_groups_desc'], '</dfn>
					</dt>
					<dd>
						<label><input type="checkbox" name="view_enter_same" id="view_enter_same"', !empty($context['view_enter_same']) ? ' checked' : '', ' onclick="$(\'#enter_perm_col, #offlimits_cont\').toggle(!this.checked)"> ', $txt['mboards_groups_view_enter_same'], '</label><br>
						<label><input type="checkbox" name="need_deny_perm" id="need_deny_perm"', !empty($context['need_deny_perm']) ? ' checked' : '', ' onclick="$(\'.deny_perm\').toggle(this.checked)"> ', $txt['mboards_groups_need_deny_perm'], '</label> <a href="<URL>?action=help;in=need_deny_perm" onclick="return reqWin(this);" class="help" title="', $txt['help'], '"></a><br>
						<br>
						<div id="view_perm_col" class="two-columns">
							<fieldset>
								<legend>', $txt['mboards_view_board'], '</legend>
								<table>
									<tr>
										<th></th>
										<th>', $txt['yes'], '</th>
										<th>', $txt['no'], '</th>
										<th class="deny_perm"', empty($context['need_deny_perm']) ? ' style="display: none"' : '', '>', $txt['mboards_never'], '</th>
									</tr>
									<tr class="everyone">
										<td class="smalltext">
											<span class="everyone" title="', $txt['mboards_groups_everyone_desc'], '">', $txt['mboards_groups_everyone'], '</span>
										</td>
										<td>
											<input type="radio" name="vieweveryone" value="allow" onchange="updateView(\'view\', this)">
										</td>
										<td>
											<input type="radio" name="vieweveryone" value="disallow" onchange="updateView(\'view\', this)">
										</td>
										<td class="deny_perm center"', empty($context['need_deny_perm']) ? ' style="display: none"' : '', '>
											<input type="radio" name="vieweveryone" value="deny" onchange="updateView(\'view\', this)">
										</td>
									</tr>';

	foreach ($context['groups'] as $group)
	{
						echo '
									<tr>
										<td class="smalltext">
											<span', $group['is_post_group'] ? ' class="post_group" title="' . $txt['mboards_groups_post_group'] . '"' : '', $group['id'] == 0 ? ' class="regular_members" title="' . $txt['mboards_groups_regular_members'] . '"' : '', '>
												', $group['name'], '
											</span>
										</td>
										<td>
											<input type="radio" name="viewgroup[', $group['id'], ']" value="allow"', $group['view_perm'] == 'allow' ? ' checked' : '', '>
										</td>
										<td>
											<input type="radio" name="viewgroup[', $group['id'], ']" value="disallow"', (empty($context['need_deny_perm']) && $group['view_perm'] == 'deny') || $group['view_perm'] == 'disallow' ? ' checked' : '', '>
										</td>
										<td class="deny_perm center"', empty($context['need_deny_perm']) ? ' style="display: none"' : '', '>
											<input type="radio" name="viewgroup[', $group['id'], ']" value="deny"', !empty($context['need_deny_perm']) && $group['view_perm'] == 'deny' ? ' checked' : '', '>
										</td>
									</tr>';
	}

	echo '
								</table>
							</fieldset>
						</div>
						<div id="enter_perm_col" class="two-columns"', !empty($context['view_enter_same']) ? ' style="display: none"' : '', '>
							<fieldset>
								<legend>', $txt['mboards_enter_board'], '</legend>
								<table>
									<tr>
										<th></th>
										<th>', $txt['yes'], '</th>
										<th>', $txt['no'], '</th>
										<th class="deny_perm"', empty($context['need_deny_perm']) ? ' style="display: none"' : '', '>', $txt['mboards_never'], '</th>
									</tr>
									<tr class="everyone">
										<td class="smalltext">
											<span class="everyone" title="', $txt['mboards_groups_everyone_desc'], '">', $txt['mboards_groups_everyone'], '</span>
										</td>
										<td>
											<input type="radio" name="entereveryone" value="allow" onchange="updateView(\'enter\', this)">
										</td>
										<td>
											<input type="radio" name="entereveryone" value="disallow" onchange="updateView(\'enter\', this)">
										</td>
										<td class="deny_perm center"', empty($context['need_deny_perm']) ? ' style="display: none"' : '', '>
											<input type="radio" name="entereveryone" value="deny" onchange="updateView(\'enter\', this)">
										</td>
									</tr>';

	foreach ($context['groups'] as $group)
	{
						echo '
									<tr>
										<td class="smalltext">
											<span', $group['is_post_group'] ? ' class="post_group" title="' . $txt['mboards_groups_post_group'] . '"' : '', $group['id'] == 0 ? ' class="regular_members" title="' . $txt['mboards_groups_regular_members'] . '"' : '', '>
												', $group['name'], '
											</span>
										</td>
										<td>
											<input type="radio" name="entergroup[', $group['id'], ']" value="allow"', $group['enter_perm'] == 'allow' ? ' checked' : '', '>
										</td>
										<td>
											<input type="radio" name="entergroup[', $group['id'], ']" value="disallow"', (empty($context['need_deny_perm']) && $group['enter_perm'] == 'deny') || $group['enter_perm'] == 'disallow' ? ' checked' : '', '>
										</td>
										<td class="deny_perm center"', empty($context['need_deny_perm']) ? ' style="display: none"' : '', '>
											<input type="radio" name="entergroup[', $group['id'], ']" value="deny"', !empty($context['need_deny_perm']) && $group['enter_perm'] == 'deny' ? ' checked' : '', '>
										</td>
									</tr>';
	}

	echo '
								</table>
							</fieldset>
						</div>
						<br class="clear"><br>
						<div id="offlimits_cont"', !empty($context['view_enter_same']) ? ' style="display: none"' : '', '>
							<strong>', $txt['mboards_offlimits_msg'], '</strong>
							<textarea name="offlimits_msg" rows="6" style="', we::is('ie8') ? 'width: 635px; max-width: 99%; min-width: 99%' : 'width: 99%', '">', $context['board']['offlimits_msg'], '</textarea>
							<br>
						</div>
					</dd>';

	// Options to choose moderators, specify as announcement board and choose whether to count posts here.
	echo '
					<dt>
						<strong>', $txt['mboards_moderators'], ':</strong>
						<dfn>', $txt['mboards_moderators_desc'], '</dfn>
					</dt>
					<dd>
						<input name="moderators" id="moderators" value="', $context['board']['moderator_list'], '" size="30">
					</dd>
				</dl>
				<hr>';

	if (empty($context['board']['is_recycle']) && empty($context['board']['topics']))
		echo '
				<dl class="settings">
					<dt>
						<strong', $context['board']['topics'] ? ' style="color: gray"' : '', '>', $txt['mboards_redirect'], ':</strong>
						<dfn>', $txt['mboards_redirect_desc'], '</dfn>
					</dt>
					<dd>
						<input type="checkbox" id="redirect_enable" name="redirect_enable"', $context['board']['topics'] ? ' disabled' : '', $context['board']['redirect'] != '' ? ' checked' : '', ' onclick="refreshOptions();">
					</dd>
				</dl>';

	if (!empty($context['board']['is_recycle']))
		echo '
				<div class="information">', $txt['mboards_redirect_disabled_recycle'], '</div>';

	if (empty($context['board']['is_recycle']) && !empty($context['board']['topics']))
		echo '
				<div class="information">
					<strong>', $txt['mboards_redirect'], '</strong>
					<br>
					', $txt['mboards_redirect_disabled'], '
				</div>';

	if (!$context['board']['topics'] && empty($context['board']['is_recycle']))
	{
		echo '
				<div id="redirect_address_div">
					<dl class="settings">
						<dt>
							<strong>', $txt['mboards_redirect_url'], ':</strong>
							<dfn>', $txt['mboards_redirect_url_desc'], '</dfn>
						</dt>
						<dd>
							<input name="redirect_address" value="', $context['board']['redirect'], '" size="40">
						</dd>
						<dt>
							<strong>', $txt['mboards_redirect_newtab'], ':</strong>
						</dt>
						<dd>
							<input type="checkbox" name="redirect_newtab"', $context['board']['redirect_newtab'] ? ' checked' : '', '>
						</dd>
					</dl>
				</div>';

		if ($context['board']['redirect'])
			echo '
				<div id="reset_redirect_div">
					<dl class="settings">
						<dt>
							<strong>', $txt['mboards_redirect_reset'], ':</strong>
							<dfn>', $txt['mboards_redirect_reset_desc'], '</dfn>
						</dt>
						<dd>
							<input type="checkbox" name="reset_redirect">
							<em>(', sprintf($txt['mboards_current_redirects'], $context['board']['posts']), ')</em>
						</dd>
					</dl>
				</div>';
	}

	echo '
				<div id="count_posts_div">
					<dl class="settings">
						<dt>
							<strong>', $txt['mboards_count_posts'], ':</strong>
							<dfn>', $txt['mboards_count_posts_desc'], '</dfn>
						</dt>
						<dd>
							<input type="checkbox" name="count" ', $context['board']['count_posts'] ? ' checked' : '', '>
						</dd>
					</dl>
				</div>';

	// Here the user can choose to force this board to use a theme other than the default theme for the forum.
	echo '
				<div id="board_theme_div">
					<dl class="settings">
						<dt>
							<strong>', $txt['mboards_theme'], ':</strong>
							<dfn>', $txt['mboards_theme_desc'], '</dfn>
						</dt>
						<dd>
							<select name="boardtheme" id="boardtheme" onchange="refreshOptions();">
								<option value="0"', $context['board']['theme'] == 0 ? ' selected' : '', '>', $txt['mboards_theme_default'], '</option>';

	foreach ($context['themes'] as $th)
	{
		echo '<option value="', $th['id'], '"', $context['board']['theme'] == $th['id'] && (empty($context['board']['skin']) || $context['board']['skin'] == 'skins') ? ' selected' : '', '>', $th['name'], '</option>';
		if (!empty($th['skins']))
			echo wedge_show_skins($th, $th['skins'], $context['board']['theme'], $context['board']['skin']);
	}

	echo '
							</select>
						</dd>
					</dl>
				</div>
				<div id="override_theme_div">
					<dl class="settings">
						<dt>
							<strong>', $txt['mboards_override_theme'], ':</strong>
							<dfn>', $txt['mboards_override_theme_desc'], '</dfn>
						</dt>
						<dd>
							<input type="checkbox" name="override_theme"', $context['board']['override_theme'] ? ' checked' : '', '>
						</dd>
					</dl>
				</div>';

	// Picking a language for this board. No point specifying it if there's only one language.
	if (count($context['languages']) > 1)
	{
		echo '
				<dl class="settings">
					<dt>
						<strong>', $txt['mboards_language'], ':</strong>
						<dfn>', $txt['mboards_language_desc'], '</dfn>
					</dt>
					<dd>
						<select name="language" id="language">';

		$langset = array_merge(
			array(
				'' => array(
					'name' => $context['languages'][$settings['language']]['name'] . ' ' . $txt['mboards_theme_default'],
				)
			),
			$context['languages']
		);
		foreach ($langset as $lang_id => $lang)
		{
			if ($lang_id == $settings['language'])
				continue;

			echo '
							<option value="', $lang_id, '"', $context['board']['language'] == $lang_id ? ' selected' : '', '>', $lang['name'], '</option>';
		}

		echo '
						</select>
					</dd>
				</dl>';
	}

	if (!empty($context['board']['is_recycle']))
		echo '
				<div class="information">', $txt['mboards_recycle_disabled_delete'], '</div>';

	echo '
				<input type="hidden" name="rid" value="', $context['redirect_location'], '">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<hr>
				<div class="right">';

	// If this board has no children don't bother with the next confirmation screen.
	if ($context['board']['no_children'])
		echo '
					<input type="hidden" name="no_children" value="1">';

	if (isset($context['board']['is_new']))
		echo '
					<input type="hidden" name="cur_cat" value="', $context['board']['category'], '">
					<input type="submit" name="add" value="', $txt['mboards_new_board'], '" onclick="return $.trim(this.form.board_name.value) !== \'\';" class="new">';
	else
		echo '
					<input type="submit" name="edit" value="', $txt['modify'], '" onclick="return $.trim(this.form.board_name.value) !== \'\';" class="save">';

	if (!isset($context['board']['is_new']) && empty($context['board']['is_recycle']))
		echo '
					<span', $context['board']['is_recycle'] ? ' style="visibility: hidden">' : '>', '<input type="submit" name="delete" value="', $txt['mboards_delete_board'], '" onclick="return ask(', JavaScriptEscape($txt['boardConfirm']), ', e);"', ' class="delete"></span>';

	echo '
				</div>
			</div>
		</form>
	</div>
	<br class="clear">';

	add_js_file('scripts/suggest.js');
	add_js('
	new weAutoSuggest({
		bItemList: true,
		sControlId: \'moderators\',
		sPostName: \'moderator_list\',
		aListItems: {');

	foreach ($context['board']['moderators'] as $id_member => $member_name)
		add_js('
			', (int) $id_member, ': ', JavaScriptEscape($member_name), $id_member == $context['board']['last_moderator_id'] ? '' : ',');

	add_js('
		}
	});
	function updateView(selection, obj)
	{
		if ((selection == "view" || selection=="enter") && (obj.value == "allow" || obj.value == "disallow" || obj.value == "deny"))
		{
			$(\'#\' + selection + \'_perm_col input\').filter(\'[value="\' + obj.value + \'"]\').prop(\'checked\', true);
			$(\'#\' + selection + \'_perm_col input[name="\' + selection + \'everyone"]\').prop(\'checked\', false);
		}
	};');

	// JavaScript for deciding what to show.
	add_js_inline('
	function refreshOptions()
	{
		var redirect = document.getElementById("redirect_enable");
		var redirectEnabled = redirect ? redirect.checked : false;
		var nonDefaultTheme = document.getElementById("boardtheme").value == 0 ? false : true;

		// What to show?
		document.getElementById("override_theme_div").style.display = redirectEnabled || !nonDefaultTheme ? "none" : "";
		document.getElementById("board_theme_div").style.display = redirectEnabled ? "none" : "";
		document.getElementById("count_posts_div").style.display = redirectEnabled ? "none" : "";');

	if (!$context['board']['topics'] && empty($context['board']['is_recycle']))
	{
		add_js_inline('
		document.getElementById("redirect_address_div").style.display = redirectEnabled ? "" : "none";');

		if ($context['board']['redirect'])
			add_js_inline('
		document.getElementById("reset_redirect_div").style.display = redirectEnabled ? "" : "none";');
	}

	add_js_inline('
	}
	refreshOptions();');
}

// A template used when a user is deleting a board with child boards in it - to see what they want to do with them.
function template_confirm_board_delete()
{
	global $context, $theme, $options, $txt;

	// Print table header.
	echo '
	<div id="manage_boards">
		<form action="<URL>?action=admin;area=manageboards;sa=board2" method="post" accept-charset="UTF-8">
			<input type="hidden" name="boardid" value="', $context['board']['id'], '">

			<we:cat>
				', $txt['mboards_delete_board'], '
			</we:cat>
			<div class="windowbg wrc">
				<p>', $txt['mboards_delete_board_contains'], '</p>
				<ul>';

	foreach ($context['children'] as $child)
		echo '
					<li>', $child['node']['name'], '</li>';

	echo '
				</ul>
			</div>

			<we:title>
				', $txt['mboards_delete_what_do'], '
			</we:title>
			<div class="windowbg wrc">
				<p>
					<label><input type="radio" id="delete_action0" name="delete_action" value="0" checked>', $txt['mboards_delete_board_option1'], '</label><br>
					<label><input type="radio" id="delete_action1" name="delete_action" value="1"', empty($context['can_move_children']) ? ' disabled' : '', '>', $txt['mboards_delete_board_option2'], '</label>:
					<select name="board_to"', empty($context['can_move_children']) ? ' disabled' : '', '>';

	foreach ($context['board_order'] as $board)
		if ($board['id'] != $context['board']['id'] && empty($board['is_child']))
			echo '
						<option value="', $board['id'], '">', $board['name'], '</option>';

	echo '
					</select>
				</p>
				<input type="submit" name="delete" value="', $txt['mboards_delete_confirm'], '" class="delete">
				<input type="submit" name="cancel" value="', $txt['mboards_delete_cancel'], '" class="cancel">
				<input type="hidden" name="confirmation" value="1">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			</div>
		</form>
	</div>
	<br class="clear">';
}
