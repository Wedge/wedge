/**
 * Wedge
 *
 * Helper functions used by the post page.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

@language index, Post;

function previewPost()
{
	var params = {};

	$.each(textFields, function () {
		if (this in postmod)
		{
			// Handle the WYSIWYG editor.
			if (this == postbox && posthandle && posthandle.isWysiwyg)
			{
				params['message_mode'] = 1;
				params[this] = posthandle.getText(false);
			}
			else
				params[this] = postmod[this].value;
		}
	});
	$.each(numericFields, function () {
		if (this in postmod && 'value' in postmod[this])
			params[this] = parseInt(postmod[this].value);
	});
	$.each(checkboxFields, function () {
		if (this in postmod && postmod[this].checked)
			params[this] = postmod[this].value;
	});
	show_ajax();

	$.post(weUrl('action=post2' + (we_board ? ';board=' + we_board : '') + (make_poll ? ';poll' : '') + ';preview;xml'), params, function (XMLDoc)
	{
		if (!XMLDoc)
			$(postmod.preview).click(function () { return true; }).click();

		// Create and show the preview section, with a fine little animation.
		$('#preview_subject').html($('subject', XMLDoc).text());
		$('#preview_section > .postbg').html($('body', XMLDoc).text());
		$('#preview_section').animate({ opacity: 'show', height: 'show' }).addClass('post');

		var
			errorList = [],
			errors = $('errors', XMLDoc),
			$postbox = $('#' + postbox),
			newPostsHTML = '',
			ignored_replies = [],
			ignoring, id, i;

		// Show a list of errors, if any.
		$('error', errors).each(function () {
			errorList.push($(this).text());
		});
		$('#error_serious').toggle(errors.attr('serious') == 1);
		$('#errors').toggle(errorList.length > 0);
		$('#error_list').html(errorList.length > 0 ? errorList.join('<br>') : '');

		// Show a warning if the topic has been locked.
		$('#lock_warning').toggle(errors.attr('topic_locked') == 1);

		// Adjust the color of captions if the given data is erroneous.
		$('caption', errors).each(function () {
			$('#caption_' + $(this).attr('name')).addClass($(this).attr('class'));
		});

		if ($('post_error', errors).length)
			$postbox.css('border', '1px solid red');
		else if ($postbox.css('border-color').indexOf('red') != -1)
			$postbox.css('border', 0);

		// Set the new last message id.
		if ('last_msg' in postmod)
			postmod.last_msg.value = $('last_msg', XMLDoc).text();

		// Remove the new image from old-new replies!
		for (i = 0; i < new_replies.length; i++)
			$('#image_new_' + new_replies[i]).hide();

		new_replies = [];

		$('new_posts post', XMLDoc).each(function () {
			id = $(this).attr('id');
			new_replies.push(id);

			ignoring = false;
			if ($('is_ignored', this).text() != 0)
				ignored_replies.push(ignoring = id);

			newPostsHTML += new_post_tpl.wereplace({
				reply: ++reply_counter % 2 == 0 ? '2' : '',
				poster: $('poster', this).text(),
				date: $('time', this).text(),
				id: id
			});

			if (ignoring)
				newPostsHTML += '<div class="ignored">' + $txt['ignoring_user'] + '</div>';

			newPostsHTML += '<div class="list_posts smalltext clear">' + $('message', this).text() + '</div>';

			if (can_quote)
				newPostsHTML += '<div class="actionbar"><ul class="actions"><li><a href="#postmodify" class="quote_button" onclick="return insertQuoteFast(\''
							 + id + '\');">' + $txt['bbc_quote'] + '</a></li></ul></div>';

			// Closing the two div's opened in new_post_tpl...
			newPostsHTML += '</div></div>';
		});

		$('#new_replies').append(newPostsHTML);

		$.each(ignored_replies, function () {
			new weToggle({
				isCollapsed: true,
				aSwapContainers: [
					'msg' + this + ' .list_posts',
					'msg' + this + ' .actionbar'
				],
				aSwapLinks: ['msg' + this + ' .ignored']
			});
		});

		hide_ajax();
	});

	return false;
}

function insertQuoteFast(msg)
{
	$.get(
		weUrl('action=quotefast;quote=' + msg + ';xml;mode=' + +posthandle.isWysiwyg),
		function (XMLDoc) {
			posthandle.insertText($('quote', XMLDoc).text(), false, true);
		}
	);
	return true;
}

// Poll helper code - ensure the user doesn't create a poll with illegal option combinations.
function pollOptions()
{
	if ($.trim($('#poll_expire').val()) == 0)
	{
		postmod.poll_hide[2].disabled = true;
		if (postmod.poll_hide[2].checked)
			postmod.poll_hide[1].checked = true;
	}
	else
		postmod.poll_hide[2].disabled = false;
}

var pollOptionNum = 0, pollTabIndex;
function addPollOption()
{
	if (!pollOptionNum)
		$.each(postmod.elements, function () {
			if (this.id.slice(0, 8) == 'options-')
			{
				pollOptionNum++;
				pollTabIndex = this.tabIndex;
			}
		});
	pollOptionNum++;

	$('#pollMoreOptions').append(pollOptionTemplate.wereplace({
		pollOptionTxt: pollOptionTxt,
		pollOptionNum: pollOptionNum,
		pollTabIndex: pollTabIndex
	}));
	return false;
}

/*
	Attachment selector, originally based on http://the-stickman.com/web-development/javascript/upload-multiple-files-with-a-single-file-element/
	The original code is MIT licensed, as discussed on http://the-stickman.com/using-code-from-this-site-ie-licence/
	This is quite heavily rewritten, though, to suit our purposes.
*/

function wedgeAttachSelect(opt)
{
	var count = 0, attachId = 0, max = opt.max || -1, message_ext_error_final, current_element,

	// Yay for scope issues.
	checkExtension = function (filename)
	{
		if (!opt.attachment_ext)
			return true; // We're not checking

		var dot = filename.lastIndexOf('.');
		if (!filename || filename.length == 0 || dot == -1)
		{
			message_ext_error_final = opt.message_ext_error.replace(' ({ext})', '');
			return false; // Pfft, didn't specify anything, or no extension
		}

		var extension = filename.slice(dot + 1).toLowerCase();
		if (!in_array(extension, opt.attachment_ext))
		{
			message_ext_error_final = opt.message_ext_error.replace('{ext}', extension);
			return false;
		}

		return true;
	},

	selectorHandler = function (event)
	{
		var element = event.target;

		if ($(element).val() === '')
			return false;

		// We've got one!! Check it, bag it.
		if (checkExtension(element.value))
		{
			// Hide this input.
			$(element).css({ position: 'absolute', left: -1000 });

			// Add a new file selector.
			createFileSelector();

			// Add the display entry and remove button.
			$('<div></div>')
				.html('&nbsp; &nbsp;' + element.value)
				.prepend(
					$('<input type="button" class="delete" style="margin-top: 4px" value="' + $txt['remove'] + '" />').click(function () {
						// Remove element from form
						$(this.parentNode.el).remove();
						$(this.parentNode).slideUp(500, function() {
							$(this).remove();
							count--;
							checkActive();
						});
						return false;
					})
				)
				.appendTo('#' + opt.file_container)
				.hide().slideDown(500)[0].el = element;

			count++;
			checkActive();
		}
		else // Uh oh.
			say(
				message_ext_error_final,
				function () {
					createFileSelector();
					$(element).remove();
				}
			);
	},

	prepareFileSelector = function (element)
	{
		if (element.tagName != 'INPUT' || element.type != 'file')
			return;

		$(element).attr({
			id: 'file_' + attachId++,
			name: 'attachment[]'
		}).change(selectorHandler);
	},

	createFileSelector = function ()
	{
		var new_element = $('<input type="file">').prependTo('#' + opt.file_container);
		prepareFileSelector(current_element = new_element[0]);
	},

	checkActive = function ()
	{
		var session_attach = 0;
		$('input[type=checkbox][name="attach_del[]"]').each(function () {
			if (this.checked)
				session_attach++;
		});
		if (current_element)
			current_element.disabled = max != -1 && max <= session_attach + count;
	};

	// And finally, we begin.
	this.checkActive = checkActive;
	prepareFileSelector($('#' + opt.file_item)[0]);
}
