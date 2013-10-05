/*!
 * Helper functions for topic pages
 *
 * @package Wedge
 * @copyright 2010 René-Gilles Deberdt, wedge.org
 * @license http://wedge.org/license/
 * @author see contributors.txt
 */

@language index;

$(function ()
{
	// This is one of the weirdest bugs in Chrome... If a horizontal (but no vertical)
	// scrollbar is set on an element inside a flex container, the scrollbar will be
	// 'ignored' by the layout engine. Forcing the element's flex to none fixes this.
	if (is_chrome)
		$('.post code').each(function () {
			if (this.scrollWidth > this.offsetWidth && this.scrollHeight <= this.offsetHeight)
				$(this).css('flex', 'none');
		});

	// Only execute this on MessageIndex pages.
	if (!$(document).find('#messageindex').length)
		return;

	// Fix icons in MessageIndex
	$.each('.locked .pinned .poll .my'.split(' '), function (key, val) {
		$('.subject' + val).each(function () {
			$('<span/>').addClass('floatright icon_' + val.slice(1)).prependTo(this);
		});
	});
});

$(window).load(function ()
{
	// Only execute this on Display pages.
	if (!$(document).find('#forumposts').length)
		return;

	/*
		This area will deal with ensuring that user boxes (avatars etc.) stay on-screen
		while you're scrolling the page, making it easier to determine who wrote what.
	*/

	// We need padding values, to ensure the user box doesn't go beyond acceptable boundaries.
	var
		$first_post = $('.poster').first(),
		poster_padding_top = parseInt($first_post.css('paddingTop')),
		poster_padding_bot = parseInt($first_post.css('paddingBottom')),
		sep_height = $('hr.sep').first().outerHeight(),
		follow_me = function ()
		{
			var
				top = $(window).scrollTop(),
				$poster,
				$col,
				offset,
				poster_top,
				col_height,
				poster_height;

			// On each scroll, we retrieve the top position
			// of all posts, to see which one is in scope.
			$('.poster').each(function ()
			{
				$poster = $(this);
				offset = $poster.offset();
				poster_top = offset.top;
				poster_height = $poster.height();
				if (top < poster_top + poster_height + poster_padding_top + poster_padding_bot + sep_height)
					return false;
			});

			$col = $poster.find('>div');
			col_height = $col.height();

			// If we're above the first post, or the post is shorter than the user box, we can just forget about the effect.
			if (poster_height == col_height || top < $first_post.offset().top)
				$col = false;
			// If we're close to the next post, stick the previous user box to the bottom; this increases performance.
			else if (top >= poster_top + poster_height - col_height)
				$col.css({
					position: '',
					top: '',
					left: '',
					paddingTop: poster_height - col_height
				});
			// Otherwise, go ahead and 'fix' our current post's user box position.
			else
				$col.css({
					position: 'fixed',
					top: poster_padding_top,
					left: offset.left,
					paddingTop: 0
				});

			$('.poster>div').not($col).css({
				position: '',
				top: '',
				left: '',
				paddingTop: 0
			});
		};

	if (!is_touch)
	{
		// Once the page is loaded, we lock user box sizes, to prevent breaking the effect.
		$('.poster>div,.poster').each(function () { $(this).width($(this).width()).css('min-height', $(this).height()); });

		// If user box has no padding, chances are it doesn't want this effect anyway.
		if (!isNaN(poster_padding_top) && !is_ie6 && !is_ie7)
		{
			$(window).bind('scroll resize', follow_me);
			follow_me();
		}
	}

	@if member
		// Run the post icon init code.
		IconList();
	@endif

	/*
		This is the code for the infinite scrolling feature.
		There are limitations to it, though; mostly, browsing through history is far from perfect.
	*/

	var requested = false, done_once = false, no_more_pages = false, ready_to_show = false, next_link, $new_page;
	$(window).on('DOMMouseScroll mousewheel scroll', function ()
	{
		// Are we scrolling at most three pages from the bottom..? If yes, prefect the next page.
		if (!no_more_pages && !requested && $(window).scrollTop() >= Math.max(600, $(document).height() - $(window).height() * 3))
		{
			requested = true;
			next_link = $('span.next_page > a').first().attr('href');
			// And now, load the next page and hide it!
			if (next_link)
			{
				done_once = true;
				$new_page = $('<div style="overflow:hidden"/>').height(0).insertAfter($('hr.sep').last()).load(
					next_link,
					{ infinite: true },	// This asks Wedge to ignore the Ajax status, so it can get page indexes from the index template.
					function () { ready_to_show = true; }
				);
			}
			else
				no_more_pages = true;
		}

		if (no_more_pages && done_once && !next_link)
		{
			requested = false;
			$.post(weUrl('action=markasread;sa=topic;topic=' + we_topic + ';t=' + (1 + +$('.msg').last().attr('id').slice(3)) + ';' + we_sessvar + '=' + we_sessid));
		}

		// Did we reach the end of the page..?
		if (ready_to_show && $(window).scrollTop() >= $(document).height() - $(window).height())
		{
			requested = false;
			ready_to_show = false;

			// Retrieve the page index for the new area, and replace the parent's with them.
			$('.pagesection nav').each(function () {
				$(this).find('.updown').siblings().remove().end().before($('#pinf').clone().contents());
			});
			$('#pinf').remove();

			// We have to re-run the event delayer, as it has new values to insert...
			// !! Is it worth putting it into its own function in script.js..?
			$('[data-eve]', $new_page).each(function ()
			{
				var that = $(this);
				$.each(that.attr('data-eve').split(' '), function () {
					that.on(eves[this][0], eves[this][1]);
				});
			});

			// Using replaceState, because storing the previous page state is headache material.
			if (window.history && history.replaceState)
				history.replaceState(null, '', next_link);

			// Move all posts at the same (DOM) level as their predecessors, and fade them in.
			$new_page.children().hide().fadeIn(800).first().unwrap();

			// Prepare all new posts for follow_me.
			if (!is_touch)
				$('.poster>div,.poster').each(function () { $(this).width($(this).width()).css('min-height', $(this).height()); });
		}
	});
});

var hide_prefixes = [];

// Expand an attached thumbnail
function expandThumb(thumbID)
{
	var img = $('#thumb_' + thumbID)[0], link = $('#link_' + thumbID)[0], tmp = img.src;

	img.src = link.href;
	img.style.width = '';
	img.style.height = '';
	link.href = tmp;

	return false;
}

@if member
	function likePost(obj)
	{
		var iMessageId = $(obj).closest('.msg').attr('id').slice(3);

		show_ajax();
		$.post(obj.href, function (response)
		{
			hide_ajax();
			$('#msg' + iMessageId + ' .post_like').replaceWith(response);
		});

		return false;
	}
@endif

function go_up()
{
	$('html,body').animate({ scrollTop: 0 }, 600);
	return false;
}

function go_down()
{
	$('html,body').animate({ scrollTop: $(document).height() - $(window).height() }, 600);
	return false;
}

@if member
	function modify_topic(topic_id, first_msg_id)
	{
		var cur_topic_id, cur_msg_id, cur_subject_div, buff_subject, in_edit_mode = false,

		// For templating, shown when an inline edit is made.
		show_edit = function (subject)
		{
			// Just template the subject.
			cur_subject_div.html('<input type="text" id="qm_subject" size="60" style="width: 95%" maxlength="80">');
			$('#qm_subject')
				.data('id', cur_topic_id)
				.data('msg', cur_msg_id)
				.keypress(key_press)
				.val(subject);
		},

		key_press = function (e)
		{
			if (e.which == 13)
			{
				save();
				e.preventDefault();
			}
		},

		restore_subject = function ()
		{
			cur_subject_div.html(buff_subject);

			set_hidden_topic_areas(true);
			in_edit_mode = false;
			$('body').off('.mt');
			return false;
		},

		save = function ()
		{
			if (!in_edit_mode)
				return true;

			show_ajax();
			$.post(
				weUrl('action=jsmodify;' + we_sessvar + '=' + we_sessid),
				{
					topic: $('#qm_subject').data('id'),
					subject: $('#qm_subject').val().replace(/&#/g, '&#38;#'),
					msg: $('#qm_subject').data('msg')
				},
				function (XMLDoc)
				{
					hide_ajax();

					// Any problems? Ignore the rest...
					if (!XMLDoc);

					else if ($('error', XMLDoc).length)
						$('#qm_subject').after($('error', XMLDoc).text());

					else if ($('subject', XMLDoc).length)
					{
						restore_subject();

						// Re-template the subject!
						cur_subject_div.find('a').html($('subject', XMLDoc).text());
					}

					return false;
				}
			);

			return false;
		},

		// Simply restore any hidden bits during topic editing.
		set_hidden_topic_areas = function (state)
		{
			$.each(hide_prefixes, function () { $('#' + this + cur_msg_id).toggle(state); });
		};

		if (in_edit_mode)
		{
			if (cur_topic_id == topic_id)
				return;
			else
				restore_subject();
		}

		in_edit_mode = true;
		cur_topic_id = topic_id;

		// Clicking outside the edit area will save the topic.
		$('body').on('click.mt', function (e) {
			if (in_edit_mode && !$(e.target).closest('#topic_' + cur_topic_id).length)
				save();
		});

		show_ajax();
		$.post(weUrl('action=quotefast;modify'), { quote: first_msg_id }, function (XMLDoc) {
			hide_ajax();
			cur_msg_id = $('message', XMLDoc).attr('id');

			cur_subject_div = $('#msg_' + cur_msg_id);
			buff_subject = cur_subject_div.html();

			// Here we hide any other things they want hiding on edit.
			set_hidden_topic_areas(false);

			show_edit($('subject', XMLDoc).text().php_unhtmlspecialchars().replace(/&#039;/g, "'"));
		});
	}
@endif


// *** QuickReply object.
function QuickReply(opt)
{
	// When a user presses quote, put it in the quick reply box (if expanded).
	this.quote = function (iMessage)
	{
		var iMessageId = $(iMessage).closest('.msg').attr('id').slice(3);

		if (!bCollapsed)
		{
			show_ajax();
			$.post(
				weUrl('action=quotefast'),
				{
					quote: iMessageId,
					mode: +oEditorHandle_message.isWysiwyg
				},
				function (XMLDoc)
				{
					hide_ajax();
					oEditorHandle_message.insertText($('quote', XMLDoc).text(), false, true);
				}
			);

			// Move the view to the quick reply box.
			location.hash = '#' + opt.sJumpAnchor;
		}
		return bCollapsed;
	};

	// The function handling the swapping of the quick reply.
	this.swap = function ()
	{
		var cont = $('#' + opt.sContainerId);
		$('#' + opt.sImageId).toggleClass('fold', bCollapsed);
		bCollapsed ? cont.slideDown(150) : cont.slideUp(200);
		bCollapsed = !bCollapsed;

		return false;
	};

	// Switch from basic to more powerful editor
	this.switchMode = function ()
	{
		if (opt.sBbcDiv != '')
			$('#' + opt.sBbcDiv).slideDown(500);
		if (opt.sSmileyDiv != '')
			$('#' + opt.sSmileyDiv).slideDown(500);
		if (opt.sBbcDiv != '' || opt.sSmileyDiv != '')
			$('#' + opt.sSwitchMode).slideUp(500);
		// !!! Are we positive that QuickReply always refers to oEditorHandle_message?
		if (opt.bUsingWysiwyg)
			oEditorHandle_message.toggleView(true);
	};

	var bCollapsed = opt.bDefaultCollapsed;
	$('#' + opt.sSwitchMode).show();
}


@if member
	// *** QuickEdit object.
	function QuickEdit(tabindex)
	{
		var sCurMessageId = 0, $body, $post, $editor, $quicked,

		// Function in case the user presses cancel, or other circumstances cause it.
		qe_cancel = function ()
		{
			// Roll back the HTML to its original state.
			if (sCurMessageId)
			{
				$post.height('');
				$post.children().not($quicked).finish().css('visibility', 'visible').fadeTo(800, 1);
				$quicked.finish().fadeOut(800, function () { $(this).remove(); });
				$('#quickModForm').unbind('submit');
			}

			// No longer in edit mode, that's right.
			sCurMessageId = 0;

			return false;
		},

		// Function called when a user presses the edit button.
		qe_edit = function ()
		{
			var iMessageId = $(this).closest('.msg').attr('id').slice(3);

			// Did we press the Quick Edit button by error while trying to submit? Oops.
			if (sCurMessageId == iMessageId)
				return;

			// First cancel if there's another message still being edited.
			if (sCurMessageId)
			{
				qe_cancel();
				$quicked.remove(); // Can't afford to wait.
			}

			sCurMessageId = iMessageId;
			$body = $('#msg' + sCurMessageId + ' .inner').first();

			// If this is not valid then simply give up.
			if (!$body.length)
				return qe_cancel();

			// Send out the Ajax request to get more info
			show_ajax();

			$.post(weUrl('action=quotefast;modify'), { quote: iMessageId }, function (XMLDoc)
			{
				// The callback function used for the Ajax request retrieving the message.
				hide_ajax();

				// Confirming that the message ID is the same as requested...
				if (sCurMessageId != $('message', XMLDoc).attr('id'))
					return qe_cancel();

				// Add a layer above the post header & body, where we'll put our textarea.
				$post = $body.closest('article');

				// Now, create the quick editor.
				// !! Is 65.500 chars a valid limit..? Dunno. Should check.
				$quicked = $(
					'<div>\
						<input id="qe_subject" maxlength="80">\
						<div id="error_box" class="error"/>\
						<textarea maxlength="65500"/>\
						<div class="right">\
							<input type="submit" name="post" class="save" accesskey="s">&nbsp;\
							<input type="submit" name="cancel" class="cancel">\
						</div>\
					</div>'
				).addClass('quicked').appendTo($post);

				$editor = $quicked.find('textarea');
				$('#quickModForm').submit(qe_save);
				$quicked.find('.save')
					.attr('tabindex', tabindex + 2)
					.val(we_submit)
					.click(qe_save);
				$quicked.find('.cancel')
					.attr('tabindex', tabindex + 3)
					.val(we_cancel)
					.click(qe_cancel);
				$('#qe_subject')
					.attr('tabindex', tabindex)
					.val($('subject', XMLDoc).text());

				// Make the textarea as short as possible, to avoid an overflow.
				$editor
					.attr('tabindex', tabindex + 1)
					.val($('message', XMLDoc).text())
					.height(0)
					.addClass('editor')
					// !! Running this several times per keypress..? Ugly, but works even on repeated keystrokes.
					.bind('change keydown keypress keyup', function ()
					{
						var offset = $editor.height();

						$editor.height(0).css('height', Math.max(min_height, Math.min(max_height, $editor[0].scrollHeight + 12)));
						offset = $editor.height() - offset;
						var new_height = parseInt($editor.css('height'));

						if (offset)
						{
							$post.height('+=' + offset);
							window.scrollTo(0, $(window).scrollTop() + offset);
							if (offset < 0 && new_height >= max_height - offset) // Are we back from the limit?
								$editor.css('overflow-y', 'hidden');
						}
						else if (new_height >= max_height) // Are we going over the limit..?
							$editor.css('overflow-y', 'auto');
					});

				// Resize the textarea to use all available space.
				$editor.height($editor.height() + $post.height() - $quicked.height());

				var min_height = parseInt($editor.css('height')), max_height = Math.max(min_height, 800);

				if ($post.height() < $quicked.height())
					$post.height($quicked.height());

				if (parseInt($editor.css('height')) >= max_height) // Are we ALREADY over the limit..?
					$editor.css('overflow-y', 'auto');

				// Hide the regular post, and show the text area instead.
				// Visibility hack is needed for IE6/IE7, because of the action menu's z-index.
				$post.children().not($quicked).fadeTo(800, 0, function () { $(this).css('visibility', 'hidden'); });
				$quicked.fadeTo(0, 0).fadeTo(800, 1);
			});
		},

		// The function called after a user wants to save his precious message.
		qe_save = function ()
		{
			// We cannot save if we weren't in edit mode.
			if (!sCurMessageId)
				return false;

			// Send in the Ajax request and let's hope for the best.
			show_ajax();
			$.post(
				weUrl('action=jsmodify;' + we_sessvar + '=' + we_sessid),
				{
					topic: we_topic,
					subject: $('#qe_subject').val().replace(/&#/g, '&#38;#'),
					message: $editor.val().replace(/&#/g, '&#38;#'),
					msg: sCurMessageId
				},
				function (XMLDoc)
				{
					// Done saving -- now show the user whether everything's okay!
					hide_ajax();

					if ($('body', XMLDoc).length)
					{
						// Replace current body.
						$body.html($('body', XMLDoc).text());

						// Destroy the textarea and show the new body...
						qe_cancel();

						// Replace subject text with the new one.
						$post.find('h5 a').first().html($('subject', XMLDoc).text());

						// If this is the first message, also update the topic subject.
						if ($('subject', XMLDoc).attr('is_first'))
							$('#top_subject').html($('subject', XMLDoc).text());

						// Show this message as 'modified on x by y'. If the theme doesn't support this,
						// the request will simply be ignored because jQuery won't find the target.
						$post.find('.modified').html($('modified', XMLDoc).text());
					}
					else if ($('error', XMLDoc).length)
					{
						$('#error_box').html($('error', XMLDoc).text()).fadeIn();
						$post.find('input,textarea').removeClass('qe_error');
						$($('error', XMLDoc).attr('where')).addClass('qe_error');
					}
				}
			)
			// Unexpected error...?
			.fail(
				function (XHR, textStatus, errorThrown) {
					$('#error_box').html(textStatus + (errorThrown ? ' - ' + errorThrown : '')).fadeIn();
				}
			);

			return false;
		};

		// Add the quick edit button to all posts that can be modified, and don't already have it (e.g. during an infinite scroll fetch.)
		$('<div class="quick_edit">&nbsp;</div>').click(qe_edit).mousedown(false).attr('title', $txt['modify_msg']).prependTo('.can-mod .actionbar:not(:has(.quick_edit))');
	}

	function InTopicModeration(opt)
	{
		var buttons_added = false, iNumSelected = 0,

		handleClick = function ()
		{
			var $display = $('#' + opt.sStrip + ' ul');

			if (!buttons_added)
			{
				opt.bRemove && $('<li/>').addClass('modrem').html('<a href="#"/>').click(handleSubmit).hide().appendTo($display);  // Add 'remove selected items'.
				opt.bRestore && $('<li/>').addClass('modres').html('<a href="#"/>').click(handleSubmit).hide().appendTo($display); // Add 'restore selected items'.
				buttons_added = true;
			}

			// Keep stats on how many items were selected. ('this' is the checkbox.)
			iNumSelected += this.checked ? 1 : -1;

			// Show the number of messages selected in the button.
			$('.modrem a').html($txt['quickmod_delete_selected'] + ' [' + iNumSelected + ']').parent().filter(iNumSelected > 0 ? ':hidden' : ':visible').fadeToggle(iNumSelected * 300);
			$('.modres a').html($txt['quick_mod_restore'] + ' [' + iNumSelected + ']').parent().filter(iNumSelected > 0 ? ':hidden' : ':visible').fadeToggle(iNumSelected * 300);

			// Try to restore the correct position.
			$display.show().children().removeClass('last').filter(':visible:last').addClass('last');
		},

		handleSubmit = function (e)
		{
			return ask(we_confirm, e, function (proceed)
			{
				if (proceed)
				{
					// Make sure this form isn't submitted in another way than this function.
					var
						oForm = $('#' + opt.sFormId)[0],
						oInput = $('<input type="hidden">').attr('name', we_sessvar).val(we_sessid).appendTo(oForm);

					if ($(this).parent().hasClass('modrem')) // 'this' is the remove button itself.
						oForm.action = oForm.action.replace(/[?;]restore_selected=1/, '');
					else // restore button?
						oForm.action = oForm.action + (oForm.action.search(/[?;]/) == -1 ? '?' : ';') + 'restore_selected=1';

					oForm.submit();
				}

				return proceed;
			});
		};

		// Add checkboxes to all the messages.
		$('.' + opt.sClass).each(function () {
			if (!$(this).find('input[type="checkbox"]').length)
			{
				// !! The zero hack is for IE 11. For some reason, it sends an error without this,
				// if the dev tools are opened AND the debugger isn't running. (Sep 2013)
				$('<input type="checkbox">').attr('name', 'msgs[]').val(($(this).closest('.msg').attr('id') || '   0').slice(3))
				.click(handleClick)
				.appendTo(this);
			}
		});
	}


	// *** IconList object.
	function IconList()
	{
		var oContainerDiv,

		// Show the list of icons after the user clicked the original icon.
		openPopup = function (oDiv, iMessageId)
		{
			var iCurMessageId = iMessageId, oCurDiv = oDiv;

			if (!oContainerDiv)
			{
				// Create a container div.
				oContainerDiv = $('<div id="iconlist"/>').hide().css('width', oCurDiv.offsetWidth).appendTo('body');

				// Start to fetch its contents.
				show_ajax();
				$.post(weUrl('action=ajax;sa=messageicons'), { board: we_board }, function (XMLDoc)
				{
					hide_ajax();
					$('icon', XMLDoc).each(function (key, iconxml)
					{
						oContainerDiv.append(
							$('<div class="item"/>')
								.mousedown(function ()
								{
									// Event handler for clicking on one of the icons.
									var thisicon = this;
									show_ajax();

									$.post(
										weUrl('action=jsmodify;' + we_sessvar + '=' + we_sessid),
										{
											topic: we_topic,
											msg: iCurMessageId,
											icon: $(iconxml).attr('value')
										},
										function (oXMLDoc)
										{
											hide_ajax();
											if (!$('error', oXMLDoc).length)
												$('img', oCurDiv).attr('src', $('img', thisicon).attr('src'));
										}
									);
								})
								.append($(iconxml).text())
						);
					});
				});
			}

			// Show the container, and position it.
			oContainerDiv.fadeIn().css({
				top: $(oCurDiv).offset().top + oDiv.offsetHeight,
				left: $(oCurDiv).offset().left - 1
			});

			// If user clicks outside, this will close the list.
			$('body').one('mousedown', function () { oContainerDiv.fadeOut(); });
		};

		// Replace all message icons by icons with hoverable and clickable div's.
		$('.can-mod').each(function () {
			var id = this.id.slice(3);
			$(this)
				.find('.messageicon:first')
				.addClass('iconbox')
				.click(function () { openPopup(this, id); });
		});
	}
@endif
