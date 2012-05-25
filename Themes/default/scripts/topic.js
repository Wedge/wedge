/*!
 * Wedge
 *
 * Helper functions for topic pages
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

var hide_prefixes = [];

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
		$('body').unbind('.mt');
		return false;
	},

	save = function ()
	{
		if (!in_edit_mode)
			return true;

		show_ajax();
		sendXMLDocument(
			weUrl() + 'action=jsmodify;topic=' + $('#qm_subject').data('id') + ';' + we_sessvar + '=' + we_sessid + ';xml',
			'subject=' + $('#qm_subject').val().replace(/&#/g, '&#38;#').php_urlencode() + '&msg=' + $('#qm_subject').data('msg'),
			function (XMLDoc)
			{
				hide_ajax();

				// Any problems?
				if (!XMLDoc || !$('subject', XMLDoc).length)
					return false;

				restore_subject();

				// Re-template the subject!
				cur_subject_div.find('a').html($('subject', XMLDoc).text());

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

	if (!can_ajax)
		return;

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
	$('body').bind('click.mt', function (e) {
		if (in_edit_mode && !$(e.target).closest('#topic_' + cur_topic_id).length)
			save();
	});

	show_ajax();
	getXMLDocument(
		weUrl() + 'action=quotefast;quote=' + first_msg_id + ';modify;xml',
		function (XMLDoc)
		{
			hide_ajax();
			cur_msg_id = $('message', XMLDoc).attr('id');

			cur_subject_div = $('#msg_' + cur_msg_id);
			buff_subject = cur_subject_div.html();

			// Here we hide any other things they want hiding on edit.
			set_hidden_topic_areas(false);

			show_edit($('subject', XMLDoc).text());
		}
	);
}



// *** QuickReply object.
function QuickReply(opt)
{
	// When a user presses quote, put it in the quick reply box (if expanded).
	this.quote = function (iMessage)
	{
		var iMessageId = $(iMessage).closest('.root').attr('id').substr(3);

		if (bCollapsed)
		{
			window.location.href = weUrl() + 'action=post;quote=' + iMessageId + ';topic=' + we_topic + '.' + opt.iStart;
			return false;
		}
		else
		{
			show_ajax();
			getXMLDocument(weUrl() + 'action=quotefast;quote=' + iMessageId + ';xml;mode=' + +oEditorHandle_message.bRichTextEnabled, function (XMLDoc)
			{
				hide_ajax();
				oEditorHandle_message.insertText($('quote', XMLDoc).text(), false, true);
			});

			// Move the view to the quick reply box.
			window.location.hash = (is_ie ? '' : '#') + opt.sJumpAnchor;

			return false;
		}
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
		if (opt.bUsingWysiwyg)
			oEditorHandle_message.toggleView(true);
	};

	var bCollapsed = opt.bDefaultCollapsed;
	$('#' + opt.sSwitchMode).show();
}



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
				oCurMessageDiv.show().next().remove();
			}

			// No longer in edit mode, that's right.
			sCurMessageId = 0;

			return false;
		};

	// Function called when a user presses the edit button.
	this.modifyMsg = function (iMessage)
	{
		if (!can_ajax)
			return;

		var iMessageId = $(iMessage).closest('.root').attr('id').substr(3);

		// Did we press the Quick Modify button by error while trying to submit? Oops.
		if (sCurMessageId == iMessageId)
			return;

		// First cancel if there's another message still being edited.
		if (sCurMessageId)
			modifyCancel();

		sCurMessageId = iMessageId;
		oCurMessageDiv = $('#msg' + sCurMessageId + ' .inner');

		// If this is not valid then simply give up.
		if (!oCurMessageDiv.length)
			return modifyCancel();

		// Send out the Ajax request to get more info
		show_ajax();

		getXMLDocument(weUrl() + 'action=quotefast;quote=' + iMessageId + ';modify;xml', function (XMLDoc)
		{
			// The callback function used for the Ajax request retrieving the message.
			hide_ajax();

			// Confirming that the message ID is the same as requested...
			if (sCurMessageId != $('message', XMLDoc).attr('id'))
				return modifyCancel();

			// Create the textarea after the message, and show it through a slide animation.
			oCurMessageDiv
				.slideUp(500)
				.after(
					opt.sBody.wereplace({
						msg_id: sCurMessageId,
						body: $('message', XMLDoc).text()
					})
				)
				.next()
				.hide()
				.slideDown(500);

			// Replace the subject part.
			oCurSubjectDiv = $('#msg' + sCurMessageId + ' h5');
			sSubjectBuffer = oCurSubjectDiv.html();

			oCurSubjectDiv
				.html(
					opt.sSubject.wereplace({
						subject: $('subject', XMLDoc).text()
					})
				)
				.hide()
				.slideDown(sSubjectBuffer == '' ? 500 : 0);
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
		sendXMLDocument(
			weUrl() + 'action=jsmodify;topic=' + we_topic + ';' + we_sessvar + '=' + we_sessid + ';xml',
			'subject=' + $('#qm_subject').val().replace(/&#/g, '&#38;#').php_urlencode() +
			'&message=' + $('#qm_post').val().replace(/&#/g, '&#38;#').php_urlencode() +
			'&msg=' + $('#qm_msg').val(),
			function (XMLDoc)
			{
				// Done saving -- now show the user whether everything's okay!
				hide_ajax();

				// If we didn't get a valid document, just cancel.
				if (!XMLDoc || !$('we', XMLDoc).length)
				{
					// If you could instead tell us what's wrong...?
					if (XMLDoc)
						$('#error_box').html(XMLDoc.childNodes && XMLDoc.childNodes.length > 0 && XMLDoc.firstChild.nodeName == 'parsererror' ? XMLDoc.firstChild.textContent : XMLDoc);
					else
						modifyCancel();
					return;
				}

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
					sCurMessageId = 0;
					sSubjectBuffer = 0;
				}
				else if ($('error', XMLDoc).length)
				{
					$('#error_box').html($('error', XMLDoc).text());
					$('#msg' + sCurMessageId + ' input').removeClass('qm_error');
					$($('error', XMLDoc).attr('where')).addClass('qm_error');
				}
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
			display = opt.sStrip + '_strip',
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
			if (opt.sRemoveLabel)
				addButton('modrem');

			// Add the 'restore selected items' button.
			if (opt.sRestoreLabel)
				addButton('modres');

			// Adding these buttons once should be enough.
			bButtonsShown = true;
		}

		// Keep stats on how many items were selected. ('this' is the checkbox.)
		iNumSelected += this.checked ? 1 : -1;

		// Show the number of messages selected in the button.
		$('.modrem a').html(opt.sRemoveLabel + ' [' + iNumSelected + ']').parent().filter(iNumSelected > 0 ? ':hidden' : ':visible').fadeToggle(iNumSelected * 300);
		$('.modres a').html(opt.sRestoreLabel + ' [' + iNumSelected + ']').parent().filter(iNumSelected > 0 ? ':hidden' : ':visible').fadeToggle(iNumSelected * 300);

		// Try to restore the correct position.
		$('#' + display + ' li').removeClass('last').filter(':visible:last').addClass('last');
	},

	handleSubmit = function ()
	{
		// Make sure this form isn't submitted in another way than this function.
		var
			oForm = $('#' + opt.sFormId)[0],
			oInput = $('<input type="hidden" name="' + we_sessvar + '" />').val(we_sessid).appendTo(oForm);

		if ($(this).hasClass('modrem')) // 'this' is the remove button itself.
		{
			if (!confirm(opt.sRemoveConfirm))
				return false;
			oForm.action = oForm.action.replace(/;restore_selected=1/, '');
		}
		else // restore button?
		{
			if (!confirm(opt.sRestoreConfirm))
				return false;
			oForm.action = oForm.action + ';restore_selected=1';
		}

		oForm.submit();
		return true;
	};

	// Add checkboxes to all the messages.
	$('.' + opt.sClass).each(function () {
		$('<input type="checkbox" name="msgs[]" value="' + this.id.substr(17) + '"></input>')
		.click(handleClick)
		.appendTo(this);
	});
}



// *** IconList object.
function IconList(opt)
{
	var oContainerDiv, oCurDiv, iCurMessageId,

	// Show the list of icons after the user clicked the original icon.
	openPopup = function (oDiv, iMessageId)
	{
		iCurMessageId = iMessageId;
		oCurDiv = oDiv;

		if (!oContainerDiv)
		{
			// Create a container div.
			oContainerDiv = $('<div id="iconlist"></div>').hide().css('width', oCurDiv.offsetWidth).appendTo('body');

			// Start to fetch its contents.
			show_ajax();
			getXMLDocument(weUrl() + 'action=ajax;sa=messageicons;board=' + opt.iBoardId + ';xml', function (XMLDoc)
			{
				hide_ajax();
				$('icon', XMLDoc).each(function ()
				{
					var iconxml = this;
					oContainerDiv.append(
						$('<div class="item"></div>')
							.hover(function () { $(this).toggleClass('hover'); })
							.mousedown(function ()
							{
								// Event handler for clicking on one of the icons.
								var thisicon = this;
								show_ajax();

								getXMLDocument(
									weUrl() + 'action=jsmodify;topic=' + we_topic + ';msg=' + iCurMessageId + ';'
									+ we_sessvar + '=' + we_sessid + ';icon=' + $(iconxml).attr('value') + ';xml',
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

				if (is_ie)
					oContainerDiv.css('width', oContainerDiv.clientWidth);
			});
		}

		// Show the container, and position it.
		oContainerDiv.fadeIn().css({
			top: $(oCurDiv).offset().top + oDiv.offsetHeight,
			left: $(oCurDiv).offset().left - 1
		});


		// If user clicks outside, this will close the list.
		$('body').bind('mousedown.ic', function () {
			oContainerDiv.fadeOut();
			$('body').unbind('mousedown.ic');
		});
	};

	if (!can_ajax)
		return;

	// Replace all message icons by icons with hoverable and clickable div's.
	$('.can-mod').each(function () {
		var id = this.id.substr(3);
		$(this)
			.find('.messageicon')
			.addClass('iconbox')
			.hover(function () { $(this).toggleClass('hover'); })
			.click(function () { openPopup(this, id); });
	});
}



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



// *** Mini-menu (mime) plugin. Yay.
$.fn.mime = function (oList, oStrings, bUseDataId)
{
	this
		.wrap('<span class="mime"></span>')
		.parent()
		.hover(function ()
		{
			var $men = $(this)
				.toggleClass('hover')
				.find('.mimenu')
					.stop(true);

			// Do we need to initialize the actual menu?
			if (!$men.length)
			{
				var
					$mime = $(this).children().first(),
					is_right_side = $mime.css('textAlign') === 'right',
					sHTML = '', href = $mime[0].href,
					// Extract the context id from the parent message
					id = bUseDataId ? $mime.data('id') : $mime.closest('.root').attr('id').substr(3);

				$.each(oList[id], function ()
				{
					var pms = oStrings[this.substr(0, 2)],
						sLink = pms[2] ? pms[2].wereplace({
							id: id,
							special: this.substr(3)
						}) : href;

					sHTML += '<li><a href="' + (sLink.charAt(0) == '?' ? href : '') + sLink + '"'
						+ (pms[3] ? ' class="' + pms[3] + '"' : '')
						+ (pms[4] ? ' ' + pms[4] : '') // Custom data, such as events?
						+ (pms[1] ? ' title="' + pms[1] + '"' : '')
						+ '>' + pms[0] + '</a></li>';
				});

				$men = $('<div class="mimenu' + (is_right_side ? ' right' : '') + '"></div>')
					.html('<ul class="actions">' + sHTML + '</ul>')
					.insertAfter($mime);

				$(this)
					// This is the starter position (or end position if we're closing the menu.)
					.data('start', {
						opacity: 0,
						// If we start from a 60x60 square, the animation looks nicer.
						width: 60,
						height: 60,
						paddingTop: 0,
						top: $mime.height(),
						// We need to force left to 0, otherwise IE doesn't like it.
						left: !is_right_side ? 0 : 'auto',
						// And if we're animating from the right, we'll simply force right to 0.
						right: is_right_side ? 0 : 'auto'
					})
					.data('end', {
						opacity: 1,
						width: $men.width(),
						height: $men.height(),
						paddingTop: $men.css('paddingTop')
					});
			}

			$men
				.css($(this).data('start'))
				.show()
				.animate($(this).data('end'), 300, function () { $(this).css('overflow', 'visible'); });
		},
		function ()
		{
			$(this)
				.toggleClass('hover')
				.find('.mimenu')
					.stop(true)
					.animate($(this).data('start'), 200, function () { $(this).hide(); });
		}
	);
}
