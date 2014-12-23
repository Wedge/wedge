<?php
/**
 * Displays the search interface and results.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

function template_main()
{
	global $context, $txt;

	if (!empty($context['search_errors']))
		echo '
	<div class="errorbox">', implode('<br>', $context['search_errors']['messages']), '</div>';

	echo '
	<form action="<URL>?action=search2" method="post" accept-charset="UTF-8" name="searchform" id="searchform">
		<we:cat>
			<img src="' . ASSETS . '/buttons/search.gif">
			', $txt['search'], '
		</we:cat>
		<div class="windowbg wrc">
			<fieldset id="simple_search">
				<div id="search_term_input">
					<strong>', $txt['search_for'], ':</strong>
					<input type="search" name="search"', !empty($context['search_params']['search']) ? ' value="' . $context['search_params']['search'] . '"' : '', ' maxlength="', $context['search_string_limit'], '" size="40" class="search">
					<select name="searchtype">
						<option value="1"', empty($context['search_params']['searchtype']) ? ' selected' : '', '>', $txt['all_words'], '</option>
						<option value="2"', !empty($context['search_params']['searchtype']) ? ' selected' : '', '>', $txt['any_words'], '</option>
					</select>
				</div>';

	echo '
				<p>', $txt['search_example'], '</p>';

	if ($context['require_verification'])
		echo '
				<div class="verification">
					<strong>', $txt['verification'], ':</strong>
					<br>', template_control_verification($context['visual_verification_id'], 'all'), '<br>
					', $txt['search_visual_verification_desc'], '
				</div>';

	echo '
			</fieldset>
		</div>';

	// Now for all the fun options.
	echo '
		<div class="windowbg2 wrc">
			<h6>
				', $txt['set_parameters'], '
			</h6>
			<fieldset id="advanced_search">
				<dl id="search_options">
					<dt>', $txt['by_user'], ':</dt>
					<dd><input id="userspec" name="userspec" value="', empty($context['search_params']['userspec']) ? '*' : $context['search_params']['userspec'], '" size="40"></dd>
					<dt>', $txt['search_order'], ':</dt>
					<dd>
						<select id="sort" name="sort">
							<option value="relevance|desc">', $txt['search_orderby_relevant_first'], '</option>
							<option value="num_replies|desc">', $txt['search_orderby_large_first'], '</option>
							<option value="num_replies|asc">', $txt['search_orderby_small_first'], '</option>
							<option value="id_msg|desc">', $txt['search_orderby_recent_first'], '</option>
							<option value="id_msg|asc">', $txt['search_orderby_old_first'], '</option>
						</select>
					</dd>
					<dt class="options">', $txt['search_options'], ':</dt>
					<dd class="options">
						<label><input type="checkbox" name="show_complete" id="show_complete" value="1"', !empty($context['search_params']['show_complete']) ? ' checked' : '', '> ', $txt['search_show_complete_messages'], '</label><br>
						<label><input type="checkbox" name="subject_only" id="subject_only" value="1"', !empty($context['search_params']['subject_only']) ? ' checked' : '', '> ', $txt['search_subject_only'], '</label>
					</dd>
					<dt class="between">', $txt['search_post_age'], ':</dt>
					<dd>', $txt['search_between'], ' <input type="number" pattern="\d*" name="minage" value="', empty($context['search_params']['minage']) ? '0' : $context['search_params']['minage'], '" size="5" maxlength="4" min="0" max="9999">&nbsp;', $txt['search_and'], '&nbsp;<input type="number" pattern="[0-9]*" name="maxage" value="', empty($context['search_params']['maxage']) ? '9999' : $context['search_params']['maxage'], '" size="5" maxlength="4" min="0" max="9999"> ', $txt['days_word'], '</dd>
				</dl>';

	// If $context['search_params']['topic'] is set, that means we're searching just one topic.
	if (!empty($context['search_params']['topic']))
		echo '
				<p>', $txt['search_specific_topic'], ' &quot;', $context['search_topic']['link'], '&quot;.</p>
				<input type="hidden" name="topic" value="', $context['search_topic']['id'], '">';

	echo '
			</fieldset>';

	if (empty($context['search_params']['topic']))
	{
		echo '
			<br>
			<fieldset class="flow_hidden">
				<label><input type="radio" name="all_boards" value="1" onclick="$(\'#searchBoardsExpand\').hide(300); $(\'#search_where\').val(\'everywhere\');"', $context['boards_check_all'] ? ' checked' : '', '> ', $txt['all_boards'], '</label>
				<br><label><input type="radio" name="all_boards" value="0" onclick="$(\'#searchBoardsExpand\').show(300); $(\'#search_where\').val(\'board\');"', $context['boards_check_all'] ? '' : ' checked', '> ', $txt['choose_board'], '</label>
				<div id="searchBoardsExpand"', $context['boards_check_all'] ? ' class="hide"' : '', '>
					<ul class="ignoreboards floatleft">';

		$i = 0;

		// Vanity card #342. I offered this code to SMF back in June 2010, and the other devs promptly rejected it. Their loss!
		// Categories MUST be taken into account by $i, in case they each have very different numbers of boards. -- Nao

		$limit = max(12, ceil(($context['num_boards'] + count($context['categories'])) / 2));
		foreach ($context['categories'] as $category)
		{
			if ($i++ == $limit)
				echo '
					</ul>
					<ul class="ignoreboards floatright">';

			echo '
						<li class="category">
							<a href="#" onclick="selectBoards([', implode(', ', $category['child_ids']), ']); return false;">', $category['name'], '</a>
							<ul>';

			foreach ($category['boards'] as $bdata)
			{
				if ($i++ == $limit)
					echo '
							</ul>
						</li>
					</ul>
					<ul class="ignoreboards floatright">
						<li class="category">
							<ul>';

				echo '
								<li class="board" style="margin-', $context['right_to_left'] ? 'right' : 'left', ': ', $bdata['child_level'], 'em">
									<label><input type="checkbox" id="brd', $bdata['id'], '" name="brd[', $bdata['id'], ']" value="', $bdata['id'], '"', $bdata['selected'] ? ' checked' : '', '> ', $bdata['name'], '</label>
								</li>';
			}

			echo '
							</ul>
						</li>';
		}

		echo '
					</ul>
					<br class="clear"><br>
					<label class="padding">
						<input type="checkbox" name="all" id="check_all" value=""', $context['boards_check_all'] ? ' checked' : '', ' onclick="invertAll(this, this.form, \'brd\');" class="marginleft">
						<em>', $txt['check_all'], '</em>
					</label>
				</div>
				<hr class="clear">
				<div class="padding clearfix">
					<input type="hidden" id="search_where" name="search_type" value="">
					<input type="submit" value="', $txt['search'], '" class="submit floatright">
				</div>
			</fieldset>';
	}

	echo '
		</div>
	</form>';

	add_js('
	function selectBoards(ids)
	{
		var toggle = true;

		for (i = 0; i < ids.length; i++)
			toggle &= document.forms.searchform["brd" + ids[i]].checked;

		for (i = 0; i < ids.length; i++)
			document.forms.searchform["brd" + ids[i]].checked = !toggle;
	}');
}

function template_search_ajax()
{
	global $context, $txt;

	echo '
		<ul class="actions"><li>
		<div id="search_popup">';

	if (!empty($context['current_topic']) || !empty($context['current_board']))
	{
		echo '
			<h6>
				', $txt['search_scope'], '
			</h6>
			<select name="search_type">';

		if (!empty($context['current_topic']))
			echo '
				<option value="topic" selected>', $txt['search_this_topic'], '</option>';

		if (!empty($context['current_board']))
			echo '
				<option value="board"', empty($context['current_topic']) ? ' selected' : '', '>', $txt['search_this_board'], '</option>
				<option value="tree">', $txt['search_this_tree'], '</option>';

		echo '
				<option value="everywhere">', $txt['search_everywhere'], '</option>
			</select>';
	}

	echo '
			<h6>
				', $txt['set_parameters'], '
			</h6>
			<div id="advanced_search">
				<dl>
					<dt>', $txt['by_user'], ':</dt>
					<dd><input class="w100" id="userspec" name="userspec" value="', empty($context['search_params']['userspec']) ? '*' : $context['search_params']['userspec'], '"></dd>
					<dt>', $txt['search_order'], ':</dt>
					<dd>
						<select id="sort" name="sort">
							<option value="relevance|desc">', $txt['search_orderby_relevant_first'], '</option>
							<option value="num_replies|desc">', $txt['search_orderby_large_first'], '</option>
							<option value="num_replies|asc">', $txt['search_orderby_small_first'], '</option>
							<option value="id_msg|desc">', $txt['search_orderby_recent_first'], '</option>
							<option value="id_msg|asc">', $txt['search_orderby_old_first'], '</option>
						</select>
					</dd>
					<dt class="options">', $txt['search_options'], ':</dt>
					<dd class="options">
						<label><input type="checkbox" name="show_complete" id="show_complete" value="1"', !empty($context['search_params']['show_complete']) ? ' checked' : '', '> ', $txt['search_show_complete_messages'], '</label><br>
						<label><input type="checkbox" name="subject_only" id="subject_only" value="1"', !empty($context['search_params']['subject_only']) ? ' checked' : '', '> ', $txt['search_subject_only'], '</label>
					</dd>
					<dt class="between">', $txt['search_post_age'], ':</dt>
					<dd>', $txt['search_between'], ' <input type="number" pattern="\d*" name="minage" value="', empty($context['search_params']['minage']) ? '0' : $context['search_params']['minage'], '" size="5" maxlength="4" min="0" max="9999"> ', $txt['search_and'], ' <input type="number" pattern="[0-9]*" name="maxage" value="', empty($context['search_params']['maxage']) ? '9999' : $context['search_params']['maxage'], '" size="5" maxlength="4" min="0" max="9999"> ', $txt['days_word'], '</dd>
				</dl>';

	// If $context['search_params']['topic'] is set, that means we're searching just one topic.
	if (!empty($context['search_params']['topic']))
		echo '
				<p>', $txt['search_specific_topic'], ' &quot;', $context['search_topic']['link'], '&quot;.</p>
				<input type="hidden" name="topic" value="', $context['search_topic']['id'], '">';

	echo '
			</div>
			<hr>
			<input type="submit" class="submit floatright" value="', $txt['search'], '">
			<a href="<URL>?action=search" style="padding: 0; margin: 10px 0 -5px">', $txt['more_actions'], '</a>
		</div>
		</li></ul>';
}

function template_results()
{
	global $context, $txt, $message;

	if (isset($context['did_you_mean']) || empty($context['topics']))
	{
		echo '
	<div id="search_results">
		<we:cat>
			', $txt['search_adjust_query'], '
		</we:cat>
		<div class="windowbg wrc">';

		// Did they make any typos or mistakes, perhaps?
		if (isset($context['did_you_mean']))
			echo '
			<p>', $txt['search_did_you_mean'], ' <a href="<URL>?action=search2;params=', $context['did_you_mean_params'], '">', $context['did_you_mean'], '</a>.</p>';

		echo '
			<form action="<URL>?action=search2" method="post" accept-charset="UTF-8">
				<strong>', $txt['search_for'], ':</strong>
				<input type="search" name="search"', !empty($context['search_params']['search']) ? ' value="' . $context['search_params']['search'] . '"' : '', ' maxlength="', $context['search_string_limit'], '" size="40" class="search">
				<input type="submit" value="', $txt['search_adjust_submit'], '">
				<input type="hidden" name="searchtype" value="', !empty($context['search_params']['searchtype']) ? $context['search_params']['searchtype'] : 0, '">
				<input type="hidden" name="userspec" value="', !empty($context['search_params']['userspec']) ? $context['search_params']['userspec'] : '', '">
				<input type="hidden" name="show_complete" value="', !empty($context['search_params']['show_complete']) ? 1 : 0, '">
				<input type="hidden" name="subject_only" value="', !empty($context['search_params']['subject_only']) ? 1 : 0, '">
				<input type="hidden" name="minage" value="', !empty($context['search_params']['minage']) ? $context['search_params']['minage'] : '0', '">
				<input type="hidden" name="maxage" value="', !empty($context['search_params']['maxage']) ? $context['search_params']['maxage'] : '9999', '">
				<input type="hidden" name="sort" value="', !empty($context['search_params']['sort']) ? $context['search_params']['sort'] : 'relevance', '">';

		if (!empty($context['search_params']['brd']))
			foreach ($context['search_params']['brd'] as $board_id)
				echo '
				<input type="hidden" name="brd[', $board_id, ']" value="', $board_id, '">';

		echo '
			</form>
		</div>
	</div>
	<br>';
	}

	if ($context['compact'])
	{
		echo '
	<form action="<URL>?action=quickmod" method="post" accept-charset="UTF-8" name="topicForm">';

		echo '
		<we:cat>
			<img src="', ASSETS, '/buttons/search.gif">', $txt['mlist_search_results'], ':&nbsp;', $context['search_params']['search'], '
		</we:cat>
		<div class="pagesection">
			<nav>', $txt['pages'], ': ', $context['page_index'], '</nav>
		</div>';

		while ($topic = $context['get_topics']())
		{
			$color_class = '';
			if ($topic['is_pinned'])
				$color_class .= ' pinned';
			if ($topic['is_locked'])
				$color_class .= ' locked';

			echo '
		<div class="search_results_posts">
			<div class="windowbg', $message['alternate'] == 0 ? '' : '2', ' wrc core_posts', $color_class, '">
				<div class="flow_auto">';

			foreach ($topic['matches'] as $message)
			{
				echo '
					<div class="topic_details floatleft" style="width: 94%">
						<div class="counter">', $message['counter'], '</div>
						<h5>', $topic['board']['link'], ' / <a href="<URL>?topic=', $topic['id'], '.msg', $message['id'], '#msg', $message['id'], '">', $message['subject_highlighted'], '</a></h5>
						<span class="smalltext">«&nbsp;', $message['on_time'], ' ', $txt['by'], ' <strong>', $message['member']['link'], '</strong>&nbsp;»</span>
					</div>';

				echo '
					<div class="floatright">
						<input type="checkbox" name="topics[]" value="', $topic['id'], '">
					</div>';

				if ($message['body_highlighted'] != '')
					echo '
					<br class="clear">
					<div class="list_posts double_height">', $message['body_highlighted'], '</div>';
			}

			echo '
				</div>
			</div>
		</div>';
		}

		if (!empty($context['topics']))
			echo '
		<div class="pagesection">
			<nav>', $txt['pages'], ': ', $context['page_index'], '</nav>
		</div>';

		if (!empty($context['topics']) && !empty($context['quick_moderation']))
		{
			echo '
		<div class="roundframe">
			<div class="floatright">
				<select class="fixed" name="qaction"', $context['can_move'] ? ' onchange="$(\'#sbmoveItTo\').toggleClass(\'hide\', $(this).val() != \'move\');"' : '', '>
					<option value="">--- ', $txt['moderate'], ' ---</option>';

			foreach ($context['quick_moderation'] as $qmod_id => $qmod_txt)
				echo '
					<option value="', $qmod_id, '">', $qmod_txt, '</option>';

			echo '
				</select>';

				if ($context['can_move'])
				{
						echo '
				<select id="moveItTo" name="move_to" class="hide">';

						foreach ($context['move_to_boards'] as $category)
						{
							echo '
					<optgroup label="', $category['name'], '">';

							foreach ($category['boards'] as $bdata)
									echo '
					<option value="', $bdata['id'], '"', $bdata['selected'] ? ' selected' : '', '>', $bdata['child_level'] > 0 ? str_repeat('==', $bdata['child_level'] - 1) . '=&gt;' : '', ' ', $bdata['name'], '</option>';

							echo '
					</optgroup>';
						}
						echo '
				</select>';
				}

				echo '
				<input type="hidden" name="redirect_url" value="<URL>?action=search2;params=' . $context['params'], '">
				<input type="submit" style="font-size: 0.8em" value="', $txt['quick_mod_go'], '" onclick="return this.form.qaction.value != \'\' && ask(we_confirm, e);">
			</div>
			<br class="clear">
		</div>';
		}

		echo '
		<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '">
	</form>';
	}
	else
	{
		echo '
		<we:cat>
			<img src="' . ASSETS . '/buttons/search.gif">&nbsp;', $txt['mlist_search_results'], ':&nbsp;', $context['search_params']['search'], '
		</we:cat>
		<div class="pagesection">
			<nav>', $txt['pages'], ': ', $context['page_index'], '</nav>
		</div>';

		if (empty($context['topics']))
			echo '
		<div class="information">(', $txt['search_no_results'], ')</div>';

		while ($topic = $context['get_topics']())
		{
			foreach ($topic['matches'] as $message)
			{
				echo '
		<div class="search_results_posts">
			<div class="', $message['alternate'] == 0 ? 'windowbg' : 'windowbg2', ' wrc core_posts">
				<div class="counter">', $message['counter'], '</div>
				<div class="topic_details">
					<h5>', $topic['board']['link'], ' / <a href="<URL>?topic=', $topic['id'], '.', $message['start'], '#msg', $message['id'], '">', $message['subject_highlighted'], '</a></h5>
					<span class="smalltext">«&nbsp;', $message['on_time'], ' ', $txt['by'], ' <strong>', $message['member']['link'], '</strong>&nbsp;»</span>
				</div>
				<div class="list_posts">', $message['body_highlighted'], '</div>';

				if ($topic['can_reply'] || $topic['can_quote'])
				{
					echo '
				<div class="actionbar">
					<ul class="actions">';

					// If they *can* reply?
					if ($topic['can_reply'])
						echo '
						<li class="reply_button"><a href="<URL>?action=post;topic=' . $topic['id'] . '.' . $message['start'], '">', $txt['reply'], '</a></li>';

					// If they *can* quote?
					if ($topic['can_quote'])
						echo '
						<li class="quote_button"><a href="<URL>?action=post;topic=' . $topic['id'] . '.' . $message['start'] . ';quote=' . $message['id'] . '">', $txt['quote'], '</a></li>';

					echo '
					</ul>
				</div>';
				}

				echo '
			</div>
		</div>';
			}
		}

		echo '
		<div class="pagesection">
			<nav>', $txt['pages'], ': ', $context['page_index'], '</nav>
		</div>';
	}
}
