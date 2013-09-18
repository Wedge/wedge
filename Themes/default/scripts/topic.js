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
		$('.post code').each(function ()
		{
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

	// Once the page is loaded, we lock user box sizes, to prevent breaking the effect.
	$('.poster>div,.poster').each(function () { $(this).width($(this).width()).height($(this).height()); });

	// If user box has no padding, chances are it doesn't want this effect anyway.
	if (!isNaN(poster_padding_top) && !is_ie6 && !is_ie7)
	{
		$(window).scroll(follow_me);
		follow_me();
	}

	/*
		This is the code for the infinite scrolling feature.
		There are limitations to it, though. Browsing through history is far from perfect,
		and JavaScript may fail, especially if the new page has embedded items in it.
		Please bear with us until we can fix everything...
	*/

	var count_scrolls = 0;
	$(window).on('DOMMouseScroll mousewheel', function ()
	{
		if ($(window).scrollTop() >= $(document).height() - $(window).height())
		{
			// Ensure not to load anything through accidental wheel rolls.
			if (count_scrolls++ > 2)
			{
				var next_page = $('span.next_page > a').first().attr('href');
				if (next_page)
				{
					count_scrolls = -999; // Avoid extra requests while loading the posts...
					show_ajax();
					var $new_page = $('<div/>').insertAfter($('hr.sep').last());
					$new_page.load(

						// Load the next page, and show only the #forumposts area.
						next_page + ' #forumposts',

						// This asks Wedge to ignore the Ajax status, and load the index template for page indexes.
						{ infinite: true },

						function (html)
						{
							// Retrieve the page index for the new area, and replace the parent's with them.
							var page_indexes = $(html).contents().find('.pagesection nav');
							$('.pagesection nav').first().replaceWith(page_indexes.get(0));
							$('.pagesection nav').last().replaceWith(page_indexes.get(1));

							// We're rebuilding scripts from the string response, and inserting them to force jQuery to execute them.
							// Please note that jQuery doesn't need to be reloaded, and script.js causes issues, so we'll avoid it for now.
							$new_page.append($(html).filter('script:not([src*=jquery]):not([src*=script])'));

							// We have to re-run the event delayer, as it has new values to insert...
							// !! Is it worth putting it into its own function in script.js..?
							$('*[data-eve]', $new_page).each(function ()
							{
								var that = $(this);
								$.each(that.attr('data-eve').split(' '), function () {
									that.on(eves[this][0], eves[this][1]);
								});
							});

							// Ensure that all posts are on the same (DOM) level as its predecessors.
							var root = $new_page.find('.msg').first(), up_to = $('.msg').first().parent(), max_count = 0, id;
							while (root.parent()[0] != up_to[0] && max_count++ < 10)
								root.unwrap();

							hide_ajax();

							// Using replaceState, because storing the previous page state is headache material.
							if (window.history && history.replaceState)
								history.replaceState(null, '', next_page);

							count_scrolls = 0;
							root.hide().fadeIn(800);
						}
					);
				}
			}
		}
		else
			count_scrolls = 0;
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
	$('html,body').animate({ scrollTop: 0 }, 1000);
	return false;
}

function go_down()
{
	$('html,body').animate({ scrollTop: $(document).height() - $(window).height() }, 1000);
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
	// *** QuickModify object.
	function QuickModify(opt)
	{
		var
			sCurMessageId = 0,
			sSubjectBuffer = 0,
			oCurMessageDiv,
			oCurSubjectDiv,

			// Function in case the user presses cancel (or other circumstances cause it).
			modifyCancel = function ()
			{
				// Roll back the HTML to its original state.
				if (sSubjectBuffer !== 0)
				{
					oCurSubjectDiv.html(sSubjectBuffer);
					oCurMessageDiv.fadeIn(1000).next().remove();
				}

				// No longer in edit mode, that's right.
				sCurMessageId = 0;

				return false;
			};

		// Function called when a user presses the edit button.
		this.modifyMsg = function (iMessage)
		{
			var iMessageId = $(iMessage).closest('.msg').attr('id').slice(3);

			// Did we press the Quick Modify button by error while trying to submit? Oops.
			if (sCurMessageId == iMessageId)
				return;

			// First cancel if there's another message still being edited.
			if (sCurMessageId)
				modifyCancel();

			sCurMessageId = iMessageId;
			oCurMessageDiv = $('#msg' + sCurMessageId + ' .inner').first().fadeOut(1000);

			// If this is not valid then simply give up.
			if (!oCurMessageDiv.length)
				return modifyCancel();

			// Send out the Ajax request to get more info
			show_ajax();

			$.post(weUrl('action=quotefast;modify'), { quote: iMessageId }, function (XMLDoc)
			{
				// The callback function used for the Ajax request retrieving the message.
				hide_ajax();

				// Confirming that the message ID is the same as requested...
				if (sCurMessageId != $('message', XMLDoc).attr('id'))
					return modifyCancel();

				// Calculate the height of .postheader + .post
				var
					$parent = oCurMessageDiv.parent().parent(),
					target_height = $parent.height();

				// Create the textarea after the message, and show it through a slide animation.
				oCurMessageDiv
					.hide()
					.after(
						opt.sBody.wereplace({
							msg_id: sCurMessageId,
							body: $('message', XMLDoc).text()
						})
					);

				// Replace the subject part.
				oCurSubjectDiv = $('#msg' + sCurMessageId + ' h5').first();
				sSubjectBuffer = oCurSubjectDiv.html();

				oCurSubjectDiv.html(
					opt.sSubject.wereplace({
						subject: $('subject', XMLDoc).text()
					})
				);

				$('#qm_post').height(Math.max(80, $('#qm_post').height() + target_height - $parent.height())).parent().parent().hide().fadeIn(1000);
			});
		};

		// The function called after a user wants to save his precious message.
		this.modifySave = function ()
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
					subject: $('#qm_subject').val().replace(/&#/g, '&#38;#'),
					message: $('#qm_post').val().replace(/&#/g, '&#38;#'),
					msg: $('#qm_msg').val()
				},
				function (XMLDoc)
				{
					// Done saving -- now show the user whether everything's okay!
					hide_ajax();

					if ($('body', XMLDoc).length)
					{
						// Replace current body.
						oCurMessageDiv.html($('body', XMLDoc).text());

						// Destroy the textarea and show the new body...
						modifyCancel();

						// Replace subject text with the new one.
						oCurSubjectDiv.find('a').html($('subject', XMLDoc).text());

						// If this is the first message, also update the topic subject.
						if ($('subject', XMLDoc).attr('is_first'))
							$('#top_subject').html($('subject', XMLDoc).text());

						// Show this message as 'modified on x by y'. If the theme doesn't support this,
						// the request will simply be ignored because jQuery won't find the target.
						$('#msg' + sCurMessageId + ' .modified').html($('modified', XMLDoc).text());

						// Finally, we can safely declare we're up and running...
						sCurMessageId = sSubjectBuffer = 0;
					}
					else if ($('error', XMLDoc).length)
					{
						$('#error_box').html($('error', XMLDoc).text());
						$('#msg' + sCurMessageId + ' input').removeClass('qm_error');
						$($('error', XMLDoc).attr('where')).addClass('qm_error');
					}
				}
			)
			// Unexpected error...?
			.fail(
				function (XHR, textStatus, errorThrown) {
					$('#error_box').html(textStatus + (errorThrown ? ' - ' + errorThrown : ''));
				}
			);

			return false;
		};

		this.modifyCancel = modifyCancel;
	}

	function InTopicModeration(opt)
	{
		var bButtonsShown = false, iNumSelected = 0,

		handleClick = function ()
		{
			var
				display = opt.sStrip + ' ul',
				addButton = function (sClass)
				{
					// Adds a button to the button strip.
					$('<li></li>').addClass(sClass).html('<a href="#"></a>').click(handleSubmit).hide().appendTo('#' + display);
				};

			if (!bButtonsShown)
			{
				// Make sure it can go somewhere.
				if (!$('#' + display).length)
					$('<ul id="' + display + '"></ul>').addClass('buttonlist floatleft').appendTo('#' + opt.sStrip);
				else
					$('#' + display).show();

				// Add the 'remove selected items' button.
				if (opt.bRemove)
					addButton('modrem');

				// Add the 'restore selected items' button.
				if (opt.bRestore)
					addButton('modres');

				// Adding these buttons once should be enough.
				bButtonsShown = true;
			}

			// Keep stats on how many items were selected. ('this' is the checkbox.)
			iNumSelected += this.checked ? 1 : -1;

			// Show the number of messages selected in the button.
			$('.modrem a').html($txt['quickmod_delete_selected'] + ' [' + iNumSelected + ']').parent().filter(iNumSelected > 0 ? ':hidden' : ':visible').fadeToggle(iNumSelected * 300);
			$('.modres a').html($txt['quick_mod_restore'] + ' [' + iNumSelected + ']').parent().filter(iNumSelected > 0 ? ':hidden' : ':visible').fadeToggle(iNumSelected * 300);

			// Try to restore the correct position.
			$('#' + display + ' li').removeClass('last').filter(':visible:last').addClass('last');
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
						oInput = $('<input type="hidden" name="' + we_sessvar + '" />').val(we_sessid).appendTo(oForm);

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
				$('<input type="checkbox" name="msgs[]" value="' + $(this).closest('.msg').attr('id').slice(3) + '"></input>')
				.click(handleClick)
				.appendTo(this);
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
