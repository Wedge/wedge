<?php
/**
 * Wedge
 *
 * Displays the printer-friendly version of a topic page.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

function template_print_before()
{
	global $context, $theme, $options, $txt;

	echo '<!DOCTYPE html>
<html', $context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<meta charset="utf-8">
		<meta name="robots" content="noindex">
		<link rel="canonical" href="', $context['canonical_url'], '">
		<title>', $txt['print_page'], ' - ', $context['topic_subject'], '</title>
		<style>
			body, a
			{
				color: #000;
				background: #fff;
			}
			body, td, .normaltext
			{
				font-family: Verdana, arial, helvetica, serif;
				font-size: small;
			}
			h1#title
			{
				font-size: large;
				font-weight: bold;
			}
			h2#linktree
			{
				margin: 1em 0 2.5em 0;
				font-size: small;
				font-weight: bold;
			}
			dl#posts
			{
				width: 90%;
				margin: 0;
				padding: 0;
				list-style: none;
			}
			dt.postheader
			{
				border: solid #000;
				border-width: 1px 0;
				padding: 4px 0;
			}
			dd.postbody
			{
				margin: 1em 0 2em 2em;
			}
			table
			{
				empty-cells: show;
			}
			blockquote, code
			{
				border: 1px solid #000;
				margin: 3px;
				padding: 1px;
				display: block;
			}
			code
			{
				font: x-small monospace;
			}
			blockquote
			{
				font-size: x-small;
			}
			.smalltext, .bbc_quote header, .bbc_code header
			{
				font-size: x-small;
			}
			.largetext
			{
				font-size: large;
			}
			.center
			{
				text-align: center;
			}
			hr
			{
				height: 1px;
				border: 0;
				color: black;
				background-color: black;
			}
			.footnotes
			{
				border-top: 1px solid #888;
				margin-top: 20px;
				padding-top: 4px;
			}
			.footnotes td
			{
				font-size: smaller;
				padding: 0 2px;
			}
			.footnotes a
			{
				text-decoration: none;
			}
			blockquote .footnotes
			{
				margin: 8px 0 4px;
			}
			.footnum
			{
				vertical-align: top;
				text-align: right;
				width: 30px;
			}
			a.fnotel
			{
				text-decoration: none;
				vertical-align: super;
				font-size: smaller;
				line-height: normal;
			}
		</style>
	</head>
	<body>
		<h1 id="title">', $context['forum_name_html_safe'], '</h1>
		<h2 id="linktree">', $context['category_name'], ' => ', (!empty($context['parent_boards']) ? implode(' => ', $context['parent_boards']) . ' => ' : ''), $context['board_name'], ' => ', $txt['topic_started'], ': ', $context['poster_name'], ' ', $context['post_on_time'], '</h2>
		<dl id="posts">';
}

function template_main()
{
	global $context, $theme, $options, $txt;

	foreach ($context['posts'] as $post)
		echo '
			<dt class="postheader">
				', $txt['title'], ': <strong>', $post['subject'], '</strong><br>
				', $txt['post_by'], ': <strong>', $post['member'], '</strong> ', $post['on_time'], '
			</dt>
			<dd class="postbody">
				', $post['body'], '
			</dd>';
}

function template_print_after()
{
	global $txt;

	echo '
		</dl>
		<div id="footer" class="smalltext">', $txt['copyright'], '</div>
	</body>
</html>';
}
