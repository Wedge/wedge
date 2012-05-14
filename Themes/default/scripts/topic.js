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
		cur_subject_div.html('<input type="text" id="qm_subject" value="' + subject + '" size="60" style="width: 95%" maxlength="80"><input type="hidden" id="qm_topic" value="' + cur_topic_id + '"><input type="hidden" id="qm_msg" value="' + cur_msg_id.substr(4) + '">');
		$('#qm_subject').keypress(key_press);
	},

	// And the reverse for hiding it.
	hide_edit = function (subject)
	{
		// Re-template the subject!
		cur_subject_div.html('<a href="' + weUrl() + 'topic=' + cur_topic_id + '.0">' + subject + '</a>');
	},

	key_press = function (e)
	{
		if (e.which == 13)
		{
			save();
			e.preventDefault();
		}
	},

	cancel = function ()
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
			weUrl() + 'action=jsmodify;topic=' + $('#qm_topic').val() + ';' + we_sessvar + '=' + we_sessid + ';xml',
			'subject=' + $('#qm_subject').val().replace(/&#/g, '&#38;#').php_urlencode() + '&topic=' + $('#qm_topic').val() + '&msg=' + $('#qm_msg').val(),
			function (XMLDoc) {
				if (!XMLDoc)
				{
					cancel();
					return true;
				}

				var
					subject = $('we message subject', XMLDoc),
					error = $('we message error', XMLDoc);

				hide_ajax();

				if (!subject.length || error.length)
					return false;

				hide_edit(subject.text());
				set_hidden_topic_areas(true);
				in_edit_mode = false;
				$('body').unbind('.mt');

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
			cancel();
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
		function (XMLDoc) {
			cur_msg_id = $('message', XMLDoc).attr('id').substr(4);

			cur_subject_div = $('#msg_' + cur_msg_id);
			buff_subject = cur_subject_div.html();

			// Here we hide any other things they want hiding on edit.
			set_hidden_topic_areas(false);

			show_edit($('subject', XMLDoc).text());
			hide_ajax();
		}
	);
}



// *** QuickReply object.
function QuickReply(opt)
{
	// When a user presses quote, put it in the quick reply box (if expanded).
	this.quote = function (iMessage)
	{
		// iMessageId is taken from the owner ID -- quote_button_xxx
		var iMessageId = iMessage && iMessage.id ? iMessage.id.substr(13) : '';

		if (bCollapsed)
		{
			window.location.href = weUrl() + 'action=post;quote=' + iMessageId + ';topic=' + opt.iTopicId + '.' + opt.iStart;
			return false;
		}
		else
		{
			show_ajax();
			getXMLDocument(weUrl() + 'action=quotefast;quote=' + iMessageId + ';xml;mode=' + +oEditorHandle_message.bRichTextEnabled, function (oXMLDoc) {
				oEditorHandle_message.insertText($('quote', oXMLDoc).text(), false, true);
				hide_ajax();
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
		sCurMessageId = '',
		sMessageBuffer, sSubjectBuffer,
		oCurMessageDiv, oCurSubjectDiv,

		// The callback function used for the Ajax request retrieving the message.
		onMessageReceived = function (XMLDoc)
		{
			// Hide the 'loading...' sign.
			hide_ajax();

			// Grab the message ID.
			var sId = $('message', XMLDoc).attr('id');

			if (sId == sCurMessageId)
				return;
			else if (sCurMessageId)
				this.modifyCancel();
			sCurMessageId = sId;

			// If this is not valid then simply give up.
			oCurMessageDiv = $('#' + sCurMessageId);

			if (!oCurMessageDiv.length)
				return this.modifyCancel();

			sMessageBuffer = oCurMessageDiv.html();

			// Actually create the content, with a bodge for disappearing dollar signs.
			oCurMessageDiv.html(
				opt.sTemplateBodyEdit
					.wereplace({
						msg_id: sCurMessageId.substr(4),
						body: $('message', XMLDoc).text()
					})
			);

			// Replace the subject part.
			oCurSubjectDiv = $('#subject_' + sCurMessageId.substr(4));
			sSubjectBuffer = oCurSubjectDiv.html();

			oCurSubjectDiv.html(
				opt.sTemplateSubjectEdit
					.wereplace({
						subject: $('subject', XMLDoc).text()
					})
			);
		},

		// Callback function of the Ajax request sending the modified message.
		onModifyDone = function (XMLDoc)
		{
			// We've finished the loading part.
			hide_ajax();

			var
				message = $('we message', XMLDoc),
				body = $('body', message),
				error = $('error', message);

			// If we didn't get a valid document, just cancel.
			if (!XMLDoc || !message.length)
			{
				// If you could instead tell us what's wrong...?
				if (XMLDoc)
					$('#error_box').html(XMLDoc.childNodes && XMLDoc.childNodes.length > 0 && XMLDoc.firstChild.nodeName == 'parsererror' ? XMLDoc.firstChild.textContent : XMLDoc);
				else
					this.modifyCancel();
				return;
			}

			if (body.length)
			{
				// Show new body.
				sMessageBuffer = opt.sTemplateBodyNormal.wereplace({ body: body.text() });
				oCurMessageDiv.html(sMessageBuffer);

				// Show new subject.
				var oSubject = $('subject', message), sSubjectText = oSubject.text();
				sSubjectBuffer = opt.sTemplateSubjectNormal.wereplace({
					msg_id: sCurMessageId.substr(4),
					subject: sSubjectText
				});
				oCurSubjectDiv.html(sSubjectBuffer);

				// If this is the first message, also update the topic subject.
				if (oSubject.attr('is_first') == '1')
					$('#top_subject').html(sSubjectText);

				// Show this message as 'modified on x by y'. If the theme doesn't support this,
				// the request will simply be ignored because jQuery won't find the target.
				$('#modified_' + sCurMessageId.substr(4)).html($('modified', message).text());

				// Finally, we can safely declare we're up and running...
				sCurMessageId = '';
			}
			else if (error.length)
			{
				$('#error_box').html(error.text());
				$('#qm_post').css('border', error.attr('in_body') == '1' ? opt.sErrorBorderStyle : '');
				$('#qm_subject').css('border', error.attr('in_subject') == '1' ? opt.sErrorBorderStyle : '');
			}
		};

	// Function called when a user presses the edit button.
	this.modifyMsg = function (iMessage)
	{
		if (!can_ajax)
			return;

		// iMessageId is taken from the owner ID -- modify_button_xxx
		var iMessageId = iMessage && iMessage.id ? iMessage.id.substr(14) : '';

		// Did we press the Quick Modify button by error while trying to submit? Oops.
		if (sCurMessageId && sCurMessageId.substr(4) == iMessageId)
			return;

		// First cancel if there's another message still being edited.
		if (sCurMessageId)
			this.modifyCancel();

		// Send out the Ajax request to get more info
		show_ajax();
		getXMLDocument(weUrl() + 'action=quotefast;quote=' + iMessageId + ';modify;xml', onMessageReceived);
	};

	// Function in case the user presses cancel (or other circumstances cause it).
	this.modifyCancel = function ()
	{
		// Roll back the HTML to its original state.
		if (oCurMessageDiv && oCurMessageDiv.length)
		{
			oCurMessageDiv.html(sMessageBuffer);
			oCurSubjectDiv.html(sSubjectBuffer);
		}

		// No longer in edit mode, that's right.
		sCurMessageId = '';

		return false;
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
			weUrl() + 'action=jsmodify;topic=' + opt.iTopicId + ';' + we_sessvar + '=' + we_sessid + ';xml',
			'subject=' + $('#qm_subject').val().replace(/&#/g, '&#38;#').php_urlencode() + '&message=' + $('#qm_post').val().replace(/&#/g, '&#38;#').php_urlencode()
			+ '&topic=' + $('#qm_topic').val() + '&msg=' + $('#qm_msg').val(),
			onModifyDone
		);

		return false;
	};
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
			getXMLDocument(weUrl() + 'action=ajax;sa=messageicons;board=' + opt.iBoardId + ';xml', function (oXMLDoc)
			{
				$('we icon', oXMLDoc).each(function ()
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
									weUrl() + 'action=jsmodify;topic=' + opt.iTopicId + ';msg=' + iCurMessageId + ';'
									+ we_sessvar + '=' + we_sessid + ';icon=' + $(iconxml).attr('value') + ';xml',
									function (oXMLDoc)
									{
										var oMessage = $('we message', oXMLDoc);

										if (!($('error', oMessage).length))
											$('img', oCurDiv).attr('src', $('img', thisicon).attr('src'));

										hide_ajax();
									}
								);
							})
							.append($(iconxml).text())
					);
				});

				if (is_ie)
					oContainerDiv.css('width', oContainerDiv.clientWidth);

				hide_ajax();
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
	$('.messageicon').each(function () {
		var id = this.id.substr(opt.sPrefix.length);
		$(this)
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



// *** The UserMenu
function MiniMenu(oList, bAcme, oStrings)
{
	$(bAcme ? '.acme' : '.umme').hover(
		function ()
		{
			var
				is_right_side = $(this).css('textAlign') === 'right',
				details = this.id.substr(2).split('_'),
				iMsg = details[0], id = details[1 - bAcme],
				$men = $(this).children('.mimenu');

			if (!oList[id])
				return;

			if (!$men.length)
			{
				var sHTML = '', href = this.href, pms, sLink;
				$.each(oList[id], function ()
				{
					pms = oStrings[this.substr(0, 2)];
					sLink = pms[2] ? pms[2].wereplace({
						id: id,
						special: this.substr(3)
					}) : href;
					if (!bAcme && sLink.charAt(0) == '?')
						sLink = href + sLink;

					sHTML += '<li><a href="' + sLink + '"'
						+ (pms[3] ? ' class="' + pms[3] + '"' : '')
						+ (pms[4] ? ' ' + pms[4] : '') // Custom data, such as events?
						+ (pms[1] ? ' title="' + pms[1] + '"' : '')
						+ '>' + pms[0] + '</a></li>';
				});

				$men = $('<div class="mimenu' + (is_right_side ? ' right' : '') + '"></div>')
					.html('<ul class="actions mimenuitem windowbg"><li class="menu-top"></li>' + sHTML + '</ul>')
					.appendTo(this);

				$(this) // If we're animating from the right, we need to force right to 0.
					.data('start', { width: 60, height: 60, opacity: 0, paddingLeft: 0, paddingTop: 0, right: is_right_side ? 0 : 'auto' })
					.data('end', { width: $men.width(), height: $men.height(), paddingLeft: $men.css('paddingLeft'), paddingTop: $men.css('paddingTop'), opacity: 1 });
			}

			$men.stop(true).css($(this).data('start')).show().animate($(this).data('end'), 300, function () { $(this).css('overflow', 'visible'); });
		},
		function ()
		{
			$(this).children('.mimenu').stop(true).animate($(this).data('start'), 200, function () { $(this).css({ width: 0, height: 0, display: 'none' }); });
		}
	);
}
