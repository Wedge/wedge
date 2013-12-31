#### ATTENTION: You do not need to run or use this file!  The install.php script does everything for you!
#### Install script for MySQL 5.0.3+

#
# Table structure for table `admin_info_files`
#

CREATE TABLE {$db_prefix}admin_info_files (
	id_file tinyint(4) unsigned NOT NULL auto_increment,
	filename varchar(255) NOT NULL default '',
	path varchar(255) NOT NULL default '',
	parameters varchar(255) NOT NULL default '',
	data text NOT NULL,
	filetype varchar(255) NOT NULL default '',
	PRIMARY KEY (id_file),
	KEY filename (filename(30))
) ENGINE=MyISAM;

#
# Dumping data for table `admin_info_files`
#

INSERT INTO {$db_prefix}admin_info_files
	(id_file, filename, path, parameters, data, filetype)
VALUES
	(1, 'current-version.js', '/files/', 'version=%3$s', '', 'text/javascript'),
	(2, 'detailed-version.js', '/files/', 'language=%1$s&version=%3$s', '', 'text/javascript'),
	(3, 'latest-news.js', '/files/', 'language=%1$s&format=%2$s', '', 'text/javascript'),
	(4, 'latest-plugins.js', '/files/', 'language=%1$s&version=%3$s', '', 'text/javascript'),
	(5, 'latest-smileys.js', '/files/', 'language=%1$s&version=%3$s', '', 'text/javascript'),
	(6, 'latest-support.js', '/files/', 'language=%1$s&version=%3$s', '', 'text/javascript'),
	(7, 'latest-themes.js', '/files/', 'language=%1$s&version=%3$s', '', 'text/javascript');
# --------------------------------------------------------

#
# Table structure for table `approval_queue`
#

CREATE TABLE {$db_prefix}approval_queue (
	id_msg int(10) unsigned NOT NULL default 0,
	PRIMARY KEY (id_msg)
) ENGINE=MyISAM;

#
# Table structure for table `attachments`
#

CREATE TABLE {$db_prefix}attachments (
	id_attach int(10) unsigned NOT NULL auto_increment,
	id_thumb int(10) unsigned NOT NULL default 0,
	id_msg int(10) unsigned NOT NULL default 0,
	id_member mediumint(8) unsigned NOT NULL default 0,
	id_folder tinyint(3) unsigned NOT NULL default 1,
	attachment_type tinyint(3) unsigned NOT NULL default 0,
	filename varchar(255) NOT NULL default '',
	file_hash varchar(40) NOT NULL default '',
	fileext varchar(8) NOT NULL default '',
	size int(10) unsigned NOT NULL default 0,
	downloads mediumint(8) unsigned NOT NULL default 0,
	width mediumint(8) unsigned NOT NULL default 0,
	height mediumint(8) unsigned NOT NULL default 0,
	mime_type varchar(20) NOT NULL default '',
	transparency enum('','transparent','opaque') NOT NULL default '',
	PRIMARY KEY (id_attach),
	UNIQUE id_member (id_member, id_attach),
	KEY id_msg (id_msg),
	KEY attachment_type (attachment_type)
) ENGINE=MyISAM;

#
# Table structure for table `bans`
#

CREATE TABLE {$db_prefix}bans (
	id_ban int(10) unsigned NOT NULL auto_increment,
	hardness tinyint(3) unsigned NOT NULL default 0,
	ban_type enum('id_member', 'member_name', 'email', 'ip_address', 'hostname') NOT NULL,
	ban_content varchar(255) NOT NULL default '',
	ban_reason varchar(255) NOT NULL default '',
	extra text NOT NULL,
	added int(10) unsigned NOT NULL default 0,
	member_added mediumint(8) unsigned NOT NULL default 0,
	PRIMARY KEY (id_ban),
	KEY ban_type (ban_type)
) ENGINE=MyISAM;

#
# Table structure for table `bbcode`
#

CREATE TABLE {$db_prefix}bbcode (
	id_bbcode mediumint(8) unsigned NOT NULL auto_increment,
	tag varchar(20) NOT NULL default '',
	len tinyint(3) unsigned NOT NULL default 0,
	bbctype enum('parsed', 'unparsed_equals', 'parsed_equals', 'unparsed_content', 'closed', 'unparsed_commas', 'unparsed_commas_content', 'unparsed_equals_content') NOT NULL,
	before_code text NOT NULL,
	after_code text NOT NULL,
	content text NOT NULL,
	disabled_before varchar(255) NOT NULL default '',
	disabled_after varchar(255) NOT NULL default '',
	disabled_content text NOT NULL,
	block_level tinyint(3) unsigned NOT NULL default 0,
	test varchar(255) NOT NULL default '',
	validate_func text NOT NULL,
	disallow_children varchar(255) NOT NULL default '',
	require_parents varchar(255) NOT NULL default '',
	require_children varchar(255) NOT NULL default '',
	parsed_tags_allowed varchar(255) NOT NULL default '',
	quoted enum('none', 'optional', 'required') NOT NULL,
	params text NOT NULL,
	trim_wspace enum('none', 'inside', 'outside', 'both') NOT NULL,
	id_plugin varchar(255) NOT NULL default '',
	PRIMARY KEY (id_bbcode)
) ENGINE=MyISAM;

#
# Dumping data for table `bbcode`
#

INSERT INTO {$db_prefix}bbcode
	(`id_bbcode`, `tag`, `len`, `bbctype`, `before_code`, `after_code`, `content`, `disabled_before`, `disabled_after`, `disabled_content`, `block_level`, `test`, `validate_func`, `disallow_children`, `require_parents`, `require_children`, `parsed_tags_allowed`, `quoted`, `params`, `trim_wspace`, `id_plugin`)
VALUES
	(1, 'abbr', 4, 'unparsed_equals', '<abbr title="$1">', '</abbr>', '', '', ' ($1)', '', 0, '', '', '', '', '', '', 'optional', '', 'none', ''),
	(2, 'anchor', 6, 'unparsed_equals', '<span id="post_$1">', '</span>', '', '', '', '', 0, '[#]?([A-Za-z][A-Za-z0-9_\\-]*)\\]', '', '', '', '', '', 'none', '', 'none', ''),
	(3, 'b', 1, 'parsed', '<strong>', '</strong>', '', '', '', '', 0, '', '', '', '', '', '', 'none', '', 'none', ''),
	(4, 'bdo', 3, 'unparsed_equals', '<bdo dir="$1">', '</bdo>', '', '', '', '', 1, '(rtl|ltr)\\]', '', '', '', '', '', 'none', '', 'none', ''),
	(5, 'br', 2, 'closed', '', '', '<br>', '', '', '', 0, '', '', '', '', '', '', 'none', '', 'none', ''),
	(6, 'center', 6, 'parsed', '<div class="center">', '</div>', '', '', '', '', 1, '', '', '', '', '', '', 'none', '', 'none', ''),
	(7, 'code', 4, 'unparsed_content', '', '', '<div class="bbc_code"><header>{{code}}: <a href="#" onclick="return weSelectText(this);" class="codeoperation">{{code_select}}</a></header>', '', '', '', 1, '', 'if (!isset($disabled[''code'']))\n{\n	if (we::is(''gecko,opera''))\n		$tag[''content''] .= ''<span class="bbc_pre"><code>$1</code></span></div>'';\n	else\n		$tag[''content''] .= ''<code>$1</code></div>'';\n\n	$php_parts = preg_split(''~(&lt;\\?php|\\?&gt;)~'', $data, -1, PREG_SPLIT_DELIM_CAPTURE);\n\n	for ($php_i = 0, $php_n = count($php_parts); $php_i < $php_n; $php_i++)\n	{\n		// Do PHP code coloring?\n		if ($php_parts[$php_i] != ''&lt;?php'')\n			continue;\n\n		$php_string = '''';\n		while ($php_i + 1 < count($php_parts) && $php_parts[$php_i] != ''?&gt;'')\n		{\n			$php_string .= $php_parts[$php_i];\n			$php_parts[$php_i++] = '''';\n		}\n		$php_parts[$php_i] = highlight_php_code($php_string . $php_parts[$php_i]);\n	}\n\n	// Fix the PHP code stuff...\n	$data = str_replace("<span class=\\"bbc_pre\\">\\t</span>", "\\t", implode('''', $php_parts));\n\n	// Older browsers are annoying, aren''t they?\n	if (!we::is(''gecko''))\n		$data = str_replace("\\t", "<span class=\\"bbc_pre\\">\\t</span>", $data);\n\n	// Fix IE line breaks to actually be copyable.\n	if (we::is(''ie''))\n		$data = str_replace(''<br>'', ''&#13;'', $data);\n}', '', '', '', '', 'none', '', 'none', ''),
	(8, 'code', 4, 'unparsed_equals_content', '', '', '<div class="bbc_code"><header>{{code}}: ($2) <a href="#" onclick="return weSelectText(this);" class="codeoperation">{{code_select}}</a></header>', '', '', '', 1, '', 'if (!isset($disabled[''code'']))\n{\n	if (we::is(''gecko,opera''))\n		$tag[''content''] .= ''<span class="bbc_pre"><code>$1</code></span></div>'';\n	else\n		$tag[''content''] .= ''<code>$1</code></div>'';\n\n	$php_parts = preg_split(''~(&lt;\\?php|\\?&gt;)~'', $data[0], -1, PREG_SPLIT_DELIM_CAPTURE);\n\n	for ($php_i = 0, $php_n = count($php_parts); $php_i < $php_n; $php_i++)\n	{\n		// Do PHP code coloring?\n		if ($php_parts[$php_i] != ''&lt;?php'')\n			continue;\n\n		$php_string = '''';\n		while ($php_i + 1 < count($php_parts) && $php_parts[$php_i] != ''?&gt;'')\n		{\n			$php_string .= $php_parts[$php_i];\n			$php_parts[$php_i++] = '''';\n		}\n		$php_parts[$php_i] = highlight_php_code($php_string . $php_parts[$php_i]);\n	}\n\n	// Fix the PHP code stuff...\n	$data[0] = str_replace("<span class=\\"bbc_pre\\">\\t</span>", "\\t", implode('''', $php_parts));\n\n	// Older browsers are annoying, aren''t they?\n	if (!we::is(''gecko''))\n		$data[0] = str_replace("\\t", "<span class=\\"bbc_pre\\">\\t</span>", $data[0]);\n\n	// Fix IE line breaks to actually be copyable.\n	if (we::is(''ie''))\n		$data[0] = str_replace(''<br>'', ''&#13;'', $data[0]);\n}', '', '', '', '', 'none', '', 'none', ''),
	(9, 'color', 5, 'unparsed_equals', '<span style="color: $1" class="bbc_color">', '</span>', '', '', '', '', 0, '(#[\\da-fA-F]{3}|#[\\da-fA-F]{6}|[A-Za-z]{1,20}|rgb\\(\\d{1,3}, ?\\d{1,3}, ?\\d{1,3}\\))\\]', '', '', '', '', '', 'none', '', 'none', ''),
	(10, 'email', 5, 'unparsed_content', '', '', '<a href="mailto:$1" class="bbc_email">$1</a>', '', '', '', 0, '', '$data = strtr($data, array(''<br>'' => ''''));', '', '', '', '', 'none', '', 'none', ''),
	(11, 'email', 5, 'unparsed_equals', '<a href="mailto:$1" class="bbc_email">', '</a>', '', '', '($1)', '', 0, '', '', 'email,ftp,url,iurl', '', '', '', 'none', '', 'none', ''),
	(12, 'flash', 5, 'unparsed_commas_content', '', '', '<object width="$2" height="$3" data="$1"><param name="movie" value="$1"><param name="play" value="true"><param name="loop" value="true"><param name="quality" value="high"><param name="allowscriptaccess" value="never"><embed src="$1" type="application/x-shockwave-flash" allowscriptaccess="never" width="$2" height="$3"></object>', '', '', '<a href="$1" target="_blank" class="new_win">$1</a>', 0, '\\d+,\\d+\\]', 'if (isset($disabled[''url'']))\n	$tag[''content''] = ''$1'';\nelseif (strpos($data[0], ''http://'') !== 0 && strpos($data[0], ''https://'') !== 0)\n	$data[0] = ''http://'' . $data[0];', '', '', '', '', 'none', '', 'none', ''),
	(13, 'font', 4, 'unparsed_equals', '<span style="font-family: $1" class="bbc_font">', '</span>', '', '', '', '', 0, '[A-Za-z0-9_,\\-\\s]+?\\]', '', '', '', '', '', 'none', '', 'none', ''),
	(14, 'ftp', 3, 'unparsed_content', '', '', '<a href="$1" class="bbc_ftp new_win" target="_blank">$1</a>', '', '', '', 0, '', '$data = strtr($data, array(''<br>'' => ''''));\nif (strpos($data, ''ftp://'') !== 0 && strpos($data, ''ftps://'') !== 0)\n	$data = ''ftp://'' . $data;', '', '', '', '', 'none', '', 'none', ''),
	(15, 'ftp', 3, 'unparsed_equals', '<a href="$1" class="bbc_ftp new_win" target="_blank">', '</a>', '', '', ' ($1)', '', 0, '', 'if (strpos($data, ''ftp://'') !== 0 && strpos($data, ''ftps://'') !== 0)\n	$data = ''ftp://'' . $data;', 'email,ftp,url,iurl', '', '', '', 'none', '', 'none', ''),
	(16, 'html', 4, 'unparsed_content', '', '', '$1', '', '', '$1', 1, '', '', '', '', '', '', 'none', '', 'none', ''),
	(17, 'hr', 2, 'closed', '', '', '<hr>', '', '', '', 1, '', '', '', '', '', '', 'none', '', 'none', ''),
	(18, 'i', 1, 'parsed', '<em>', '</em>', '', '', '', '', 0, '', '', '', '', '', '', 'none', '', 'none', ''),
	(19, 'img', 3, 'unparsed_content', '', '', '<img src="$1" alt="{alt}"{width}{height} class="bbc_img resized{align}">', '', '', '($1)', 0, '', '$data = strtr($data, array(''<br>'' => ''''));\nif (strpos($data, ''http://'') !== 0 && strpos($data, ''https://'') !== 0)\n	$data = ''http://'' . $data;\nadd_js_unique(''\n\t$("img.resized").click(function () { this.style.width = this.style.height = (this.style.width == "auto" ? null : "auto"); });'');', '', '', '', '', 'none', 'a:4:{s:3:"alt";a:1:{s:8:"optional";b:1;}s:5:"align";a:3:{s:8:"optional";b:1;s:5:"value";s:3:" $1";s:5:"match";s:19:"(right|left|center)";}s:5:"width";a:3:{s:8:"optional";b:1;s:5:"value";s:11:" width="$1"";s:5:"match";s:5:"(\\d+)";}s:6:"height";a:3:{s:8:"optional";b:1;s:5:"value";s:12:" height="$1"";s:5:"match";s:5:"(\\d+)";}}', 'none', ''),
	(20, 'img', 3, 'unparsed_content', '', '', '<img src="$1" class="bbc_img">', '', '', '($1)', 0, '', '$data = strtr($data, array(''<br>'' => ''''));\nif (strpos($data, ''http://'') !== 0 && strpos($data, ''https://'') !== 0)\n	$data = ''http://'' . $data;', '', '', '', '', 'none', '', 'none', ''),
	(21, 'iurl', 4, 'unparsed_content', '', '', '<a href="$1" class="bbc_link">$1</a>', '', '', '', 0, '', '$data = strtr($data, array(''<br>'' => ''''));\nif (strpos($data, ''http://'') !== 0 && strpos($data, ''https://'') !== 0)\n	$data = ''http://'' . $data;', '', '', '', '', 'none', '', 'none', ''),
	(22, 'iurl', 4, 'unparsed_equals', '<a href="$1" class="bbc_link">', '</a>', '', '', '($1)', '', 0, '', 'if (substr($data, 0, 1) == ''#'')\n	$data = ''#post_'' . substr($data, 1);\nelseif (strpos($data, ''http://'') !== 0 && strpos($data, ''https://'') !== 0)\n	$data = ''http://'' . $data;', 'email,ftp,url,iurl', '', '', '', 'none', '', 'none', ''),
	(23, 'left', 4, 'parsed', '<div class="left">', '</div>', '', '', '', '', 1, '', '', '', '', '', '', 'none', '', 'none', ''),
	(24, 'li', 2, 'parsed', '<li>', '</li>', '', ' ', '<br>', '', 1, '', '', '', 'list', '', '', 'none', '', 'outside', ''),
	(25, 'list', 4, 'parsed', '<ul class="bbc_list">', '</ul>', '', '', '', '', 1, '', '', '', '', 'li,list', '', 'none', '', 'inside', ''),
	(26, 'list', 4, 'parsed', '<ul class="bbc_list" style="list-style-type: {type}">', '</ul>', '', '', '', '', 1, '', '', '', '', 'li,list', '', 'none', 'a:1:{s:4:"type";a:1:{s:5:"match";s:227:"(none|disc|circle|square|decimal|decimal-leading-zero|lower-roman|upper-roman|lower-alpha|upper-alpha|lower-greek|lower-latin|upper-latin|hebrew|armenian|georgian|cjk-ideographic|hiragana|katakana|hiragana-iroha|katakana-iroha)";}}', 'inside', ''),
	(27, 'ltr', 3, 'parsed', '<div dir="ltr">', '</div>', '', '', '', '', 1, '', '', '', '', '', '', 'none', '', 'none', ''),
	(28, 'me', 2, 'unparsed_equals', '<span class="meaction">* $1&nbsp;', '</span>', '', '/me ', '', '', 1, '', '', '', '', '', '', 'optional', '', 'none', ''),
	(29, 'media', 5, 'closed', '', '', ' ', '', '', '', 0, '', '', '', '', '', '', 'none', '', 'none', ''),
	(30, 'mergedate', 9, 'unparsed_content', '', '', '<div class="mergedate">{{search_date_posted}} $1</div>', '', '', '', 0, '', 'if (is_numeric($data)) $data = timeformat($data);', '', '', '', '', 'none', '', 'none', ''),
	(31, 'more', 4, 'closed', '', '', ' ', '', '', '', 0, '', '', '', '', '', '', 'none', '', 'none', ''),
	(32, 'nobbc', 5, 'unparsed_content', '', '', '$1', '', '', '', 0, '', '', '', '', '', '', 'none', '', 'none', ''),
	(33, 'php', 3, 'unparsed_content', '', '', '<div class="php_code"><code>$1</code></div>', '', '', '$1', 1, '', '$add_begin = substr(trim($data), 0, 5) != ''&lt;'';\n$data = highlight_php_code($add_begin ? ''&lt;?php '' . $data . ''?&gt;'' : $data);\nif ($add_begin)\n	$data = preg_replace(array(''~^(.+?)&lt;\\?.{0,40}?php(?:&nbsp;|\\s)~'', ''~\\?&gt;((?:</(font|span)>)*)$~''), ''$1'', $data, 2);', '', '', '', '', 'none', '', 'none', ''),
	(34, 'pre', 3, 'parsed', '<span class="bbc_pre">', '</span>', '', '', '', '', 0, '', '', '', '', '', '', 'none', '', 'none', ''),
	(35, 'quote', 5, 'parsed', '<div class="bbc_quote"><header>{{quote_noun}}</header><div><blockquote>', '</blockquote></div></div>', '', '', '', '', 1, '', '', '', '', '', '', 'none', '', 'none', ''),
	(36, 'quote', 5, 'parsed', '<div class="bbc_quote"><header>{{quote_from}} {author}</header><div><blockquote>', '</blockquote></div></div>', '', '', '', '', 1, '', '', '', '', '', '', 'none', 'a:1:{s:6:"author";a:2:{s:5:"match";s:11:"(.{1,192}?)";s:6:"quoted";b:1;}}', 'none', ''),
	(37, 'quote', 5, 'parsed_equals', '<div class="bbc_quote"><header>{{quote_from}} $1</header><div><blockquote>', '</blockquote></div></div>', '', '', '', '', 1, '', '', '', '', '', 'url,iurl,ftp', 'optional', '', 'none', ''),
	(38, 'quote', 5, 'parsed', '<div class="bbc_quote"><header>{{quote_from}} {author} <a href="<URL>?{link}">{date}</a></header><div><blockquote>', '</blockquote></div></div>', '', '', '', '', 1, '', '', '', '', '', '', 'none', 'a:3:{s:6:"author";a:1:{s:5:"match";s:15:"([^<>]{1,192}?)";}s:4:"link";a:1:{s:5:"match";s:83:"(topic=[\\dmsg#\\./]{1,40}(?:;start=[\\dmsg#\\./]{1,40})?|action=profile;u=\\d+|msg=\\d+)";}s:4:"date";a:2:{s:5:"match";s:5:"(\\d+)";s:8:"validate";s:13:"on_timeformat";}}', 'none', ''),
	(39, 'quote', 5, 'parsed', '<div class="bbc_quote"><header>{{quote_from}} {author}</header><div><blockquote>', '</blockquote></div></div>', '', '', '', '', 1, '', '', '', '', '', '', 'none', 'a:1:{s:6:"author";a:1:{s:5:"match";s:11:"(.{1,192}?)";}}', 'none', ''),
	(40, 'right', 5, 'parsed', '<div class="right">', '</div>', '', '', '', '', 1, '', '', '', '', '', '', 'none', '', 'none', ''),
	(41, 'rtl', 3, 'parsed', '<div dir="rtl">', '</div>', '', '', '', '', 1, '', '', '', '', '', '', 'none', '', 'none', ''),
	(42, 's', 1, 'parsed', '<del>', '</del>', '', '', '', '', 0, '', '', '', '', '', '', 'none', '', 'none', ''),
	(43, 'size', 4, 'unparsed_equals', '<span style="font-size: $1" class="bbc_size">', '</span>', '', '', '', '', 0, '([1-9][\\d]?p[xt]|small(?:er)?|large[r]?|x[x]?-(?:small|large)|medium|(0\\.[1-9]|[1-9](\\.[\\d][\\d]?)?)?em)\\]', '', '', '', '', '', 'none', '', 'none', ''),
	(44, 'size', 4, 'unparsed_equals', '<span style="font-size: $1" class="bbc_size">', '</span>', '', '', '', '', 0, '[1-7]\\]', '$sizes = array(1 => 8, 2 => 10, 3 => 12, 4 => 14, 5 => 18, 6 => 24, 7 => 36);\n$data = $sizes[$data] . ''pt'';', '', '', '', '', 'none', '', 'none', ''),
	(45, 'spoiler', 7, 'parsed', '<div class="spoiler"><header><input type="button" value="{{spoiler}}" onclick="$(this.parentNode.parentNode.lastChild).toggle(); return false;">{{click_for_spoiler}}</header><blockquote>', '</blockquote></div>', '', '', '', '', 1, '', '', '', '', '', '', 'none', '', 'none', ''),
	(46, 'spoiler', 7, 'parsed_equals', '<div class="spoiler"><header><input type="button" value="$1" onclick="$(this.parentNode.parentNode.lastChild).toggle(); return false;">{{click_for_spoiler}}</header><blockquote>', '</blockquote></div>', '', '', '', '', 1, '', '', '', '', '', ' ', 'optional', '', 'none', ''),
	(47, 'sub', 3, 'parsed', '<sub>', '</sub>', '', '', '', '', 0, '', '', '', '', '', '', 'none', '', 'none', ''),
	(48, 'sup', 3, 'parsed', '<sup>', '</sup>', '', '', '', '', 0, '', '', '', '', '', '', 'none', '', 'none', ''),
	(49, 'table', 5, 'parsed', '<table class="bbc_table">', '</table>', '', '', '', '', 1, '', '', '', '', 'tr', '', 'none', '', 'inside', ''),
	(50, 'td', 2, 'parsed', '<td>', '</td>', '', ' ', ' ', '', 1, '', '', '', 'tr', '', '', 'none', '', 'outside', ''),
	(51, 'time', 4, 'unparsed_content', '', '', '$1', '', '', '', 0, '', 'if (is_numeric($data))\n	$data = timeformat($data);\nelse\n	$tag[''content''] = ''[time]$1[/time]'';', '', '', '', '', 'none', '', 'none', ''),
	(52, 'tr', 2, 'parsed', '<tr>', '</tr>', '', ' ', ' ', '', 1, '', '', '', 'table', 'td', '', 'none', '', 'both', ''),
	(53, 'tt', 2, 'parsed', '<span class="bbc_tt">', '</span>', '', '', '', '', 0, '', '', '', '', '', '', 'none', '', 'none', ''),
	(54, 'u', 1, 'parsed', '<span class="bbc_u">', '</span>', '', '', '', '', 0, '', '', '', '', '', '', 'none', '', 'none', ''),
	(55, 'url', 3, 'unparsed_content', '', '', '<a href="$1" class="bbc_link" target="_blank">$1</a>', '', '', '', 0, '', '$data = strtr($data, array(''<br>'' => ''''));\nif (strpos($data, ''http://'') !== 0 && strpos($data, ''https://'') !== 0)\n	$data = ''http://'' . $data;', '', '', '', '', 'none', '', 'none', ''),
	(56, 'url', 3, 'unparsed_equals', '<a href="$1" class="bbc_link" target="_blank">', '</a>', '', '', '($1)', '', 0, '', 'if (strpos($data, ''http://'') !== 0 && strpos($data, ''https://'') !== 0)\n	$data = ''http://'' . $data;', 'email,ftp,url,iurl', '', '', '', 'none', '', 'none', '');
# --------------------------------------------------------

#
# Table structure for table `board_groups`
#

CREATE TABLE {$db_prefix}board_groups (
	id_board mediumint(8) unsigned NOT NULL default 0,
	id_group smallint(5) NOT NULL default 0,
	view_perm enum('allow', 'disallow', 'deny') NOT NULL default 'allow',
	enter_perm enum('allow', 'disallow', 'deny') NOT NULL default 'allow',
	PRIMARY KEY (id_group, id_board)
) ENGINE=MyISAM;

#
# Dumping data for table `board_groups`
#

INSERT INTO {$db_prefix}board_groups
	(`id_board`, `id_group`, `view_perm`, `enter_perm`)
VALUES
	(1, -1, 'allow', 'allow'),
	(1, 0, 'allow', 'allow'),
	(1, 2, 'allow', 'allow'),
	(2, 2, 'allow', 'allow');
# --------------------------------------------------------

#
# Table structure for table `board_members`
#

CREATE TABLE {$db_prefix}board_members (
	id_member mediumint(8) unsigned NOT NULL default 0,
	id_board mediumint(8) unsigned NOT NULL default 0,
	permission enum('access', 'deny') NOT NULL default 'access',
	PRIMARY KEY (id_member, id_board, permission)
) ENGINE=MyISAM;

#
# Table structure for table `board_permissions`
#

CREATE TABLE {$db_prefix}board_permissions (
	id_group smallint(5) NOT NULL default 0,
	id_profile smallint(5) unsigned NOT NULL default 0,
	permission varchar(30) NOT NULL default '',
	add_deny tinyint(4) NOT NULL default 1,
	PRIMARY KEY (id_group, id_profile, permission)
) ENGINE=MyISAM;

#
# Dumping data for table `board_permissions`
#

INSERT INTO {$db_prefix}board_permissions
	(id_group, id_profile, permission)
VALUES
	(-1, 1, 'poll_view'),

	(0, 1, 'remove_own'),
	(0, 1, 'lock_own'),
	(0, 1, 'mark_any_notify'),
	(0, 1, 'mark_notify'),
	(0, 1, 'modify_own'),
	(0, 1, 'poll_add_own'),
	(0, 1, 'poll_edit_own'),
	(0, 1, 'poll_lock_own'),
	(0, 1, 'poll_post'),
	(0, 1, 'poll_view'),
	(0, 1, 'poll_vote'),
	(0, 1, 'post_attachment'),
	(0, 1, 'post_new'),
	(0, 1, 'post_reply_any'),
	(0, 1, 'post_reply_own'),
	(0, 1, 'save_post_draft'),
	(0, 1, 'auto_save_post_draft'),
	(0, 1, 'delete_own'),
	(0, 1, 'report_any'),
	(0, 1, 'send_topic'),
	(0, 1, 'view_attachments'),

	(2, 1, 'moderate_board'),
	(2, 1, 'post_new'),
	(2, 1, 'post_reply_own'),
	(2, 1, 'post_reply_any'),
	(2, 1, 'save_post_draft'),
	(2, 1, 'auto_save_post_draft'),
	(2, 1, 'poll_post'),
	(2, 1, 'poll_add_any'),
	(2, 1, 'poll_remove_any'),
	(2, 1, 'poll_view'),
	(2, 1, 'poll_vote'),
	(2, 1, 'poll_lock_any'),
	(2, 1, 'poll_edit_any'),
	(2, 1, 'report_any'),
	(2, 1, 'lock_own'),
	(2, 1, 'send_topic'),
	(2, 1, 'mark_any_notify'),
	(2, 1, 'mark_notify'),
	(2, 1, 'delete_own'),
	(2, 1, 'modify_own'),
	(2, 1, 'pin_topic'),
	(2, 1, 'lock_any'),
	(2, 1, 'remove_any'),
	(2, 1, 'move_any'),
	(2, 1, 'merge_any'),
	(2, 1, 'split_any'),
	(2, 1, 'delete_any'),
	(2, 1, 'modify_any'),
	(2, 1, 'approve_posts'),
	(2, 1, 'post_attachment'),
	(2, 1, 'view_attachments'),

	(3, 1, 'moderate_board'),
	(3, 1, 'post_new'),
	(3, 1, 'post_reply_own'),
	(3, 1, 'post_reply_any'),
	(3, 1, 'save_post_draft'),
	(3, 1, 'auto_save_post_draft'),
	(3, 1, 'poll_post'),
	(3, 1, 'poll_add_any'),
	(3, 1, 'poll_remove_any'),
	(3, 1, 'poll_view'),
	(3, 1, 'poll_vote'),
	(3, 1, 'poll_lock_any'),
	(3, 1, 'poll_edit_any'),
	(3, 1, 'report_any'),
	(3, 1, 'lock_own'),
	(3, 1, 'send_topic'),
	(3, 1, 'mark_any_notify'),
	(3, 1, 'mark_notify'),
	(3, 1, 'delete_own'),
	(3, 1, 'modify_own'),
	(3, 1, 'pin_topic'),
	(3, 1, 'lock_any'),
	(3, 1, 'remove_any'),
	(3, 1, 'move_any'),
	(3, 1, 'merge_any'),
	(3, 1, 'split_any'),
	(3, 1, 'delete_any'),
	(3, 1, 'modify_any'),
	(3, 1, 'approve_posts'),
	(3, 1, 'post_attachment'),
	(3, 1, 'view_attachments'),

	(-1, 2, 'poll_view'),

	(0, 2, 'remove_own'),
	(0, 2, 'lock_own'),
	(0, 2, 'mark_any_notify'),
	(0, 2, 'mark_notify'),
	(0, 2, 'modify_own'),
	(0, 2, 'poll_view'),
	(0, 2, 'poll_vote'),
	(0, 2, 'post_attachment'),
	(0, 2, 'post_new'),
	(0, 2, 'post_reply_any'),
	(0, 2, 'post_reply_own'),
	(0, 2, 'save_post_draft'),
	(0, 2, 'auto_save_post_draft'),
	(0, 2, 'delete_own'),
	(0, 2, 'report_any'),
	(0, 2, 'send_topic'),
	(0, 2, 'view_attachments'),

	(2, 2, 'moderate_board'),
	(2, 2, 'post_new'),
	(2, 2, 'post_reply_own'),
	(2, 2, 'post_reply_any'),
	(2, 2, 'save_post_draft'),
	(2, 2, 'auto_save_post_draft'),
	(2, 2, 'poll_post'),
	(2, 2, 'poll_add_any'),
	(2, 2, 'poll_remove_any'),
	(2, 2, 'poll_view'),
	(2, 2, 'poll_vote'),
	(2, 2, 'poll_lock_any'),
	(2, 2, 'poll_edit_any'),
	(2, 2, 'report_any'),
	(2, 2, 'lock_own'),
	(2, 2, 'send_topic'),
	(2, 2, 'mark_any_notify'),
	(2, 2, 'mark_notify'),
	(2, 2, 'delete_own'),
	(2, 2, 'modify_own'),
	(2, 2, 'pin_topic'),
	(2, 2, 'lock_any'),
	(2, 2, 'remove_any'),
	(2, 2, 'move_any'),
	(2, 2, 'merge_any'),
	(2, 2, 'split_any'),
	(2, 2, 'delete_any'),
	(2, 2, 'modify_any'),
	(2, 2, 'approve_posts'),
	(2, 2, 'post_attachment'),
	(2, 2, 'view_attachments'),

	(3, 2, 'moderate_board'),
	(3, 2, 'post_new'),
	(3, 2, 'post_reply_own'),
	(3, 2, 'post_reply_any'),
	(3, 2, 'save_post_draft'),
	(3, 2, 'auto_save_post_draft'),
	(3, 2, 'poll_post'),
	(3, 2, 'poll_add_any'),
	(3, 2, 'poll_remove_any'),
	(3, 2, 'poll_view'),
	(3, 2, 'poll_vote'),
	(3, 2, 'poll_lock_any'),
	(3, 2, 'poll_edit_any'),
	(3, 2, 'report_any'),
	(3, 2, 'lock_own'),
	(3, 2, 'send_topic'),
	(3, 2, 'mark_any_notify'),
	(3, 2, 'mark_notify'),
	(3, 2, 'delete_own'),
	(3, 2, 'modify_own'),
	(3, 2, 'pin_topic'),
	(3, 2, 'lock_any'),
	(3, 2, 'remove_any'),
	(3, 2, 'move_any'),
	(3, 2, 'merge_any'),
	(3, 2, 'split_any'),
	(3, 2, 'delete_any'),
	(3, 2, 'modify_any'),
	(3, 2, 'approve_posts'),
	(3, 2, 'post_attachment'),
	(3, 2, 'view_attachments'),

	(-1, 3, 'poll_view'),

	(0, 3, 'remove_own'),
	(0, 3, 'lock_own'),
	(0, 3, 'mark_any_notify'),
	(0, 3, 'mark_notify'),
	(0, 3, 'modify_own'),
	(0, 3, 'poll_view'),
	(0, 3, 'poll_vote'),
	(0, 3, 'post_attachment'),
	(0, 3, 'post_reply_any'),
	(0, 3, 'post_reply_own'),
	(0, 3, 'save_post_draft'),
	(0, 3, 'auto_save_post_draft'),
	(0, 3, 'delete_own'),
	(0, 3, 'report_any'),
	(0, 3, 'send_topic'),
	(0, 3, 'view_attachments'),

	(2, 3, 'moderate_board'),
	(2, 3, 'post_new'),
	(2, 3, 'post_reply_own'),
	(2, 3, 'post_reply_any'),
	(2, 3, 'save_post_draft'),
	(2, 3, 'auto_save_post_draft'),
	(2, 3, 'poll_post'),
	(2, 3, 'poll_add_any'),
	(2, 3, 'poll_remove_any'),
	(2, 3, 'poll_view'),
	(2, 3, 'poll_vote'),
	(2, 3, 'poll_lock_any'),
	(2, 3, 'poll_edit_any'),
	(2, 3, 'report_any'),
	(2, 3, 'lock_own'),
	(2, 3, 'send_topic'),
	(2, 3, 'mark_any_notify'),
	(2, 3, 'mark_notify'),
	(2, 3, 'delete_own'),
	(2, 3, 'modify_own'),
	(2, 3, 'pin_topic'),
	(2, 3, 'lock_any'),
	(2, 3, 'remove_any'),
	(2, 3, 'move_any'),
	(2, 3, 'merge_any'),
	(2, 3, 'split_any'),
	(2, 3, 'delete_any'),
	(2, 3, 'modify_any'),
	(2, 3, 'approve_posts'),
	(2, 3, 'post_attachment'),
	(2, 3, 'view_attachments'),

	(3, 3, 'moderate_board'),
	(3, 3, 'post_new'),
	(3, 3, 'post_reply_own'),
	(3, 3, 'post_reply_any'),
	(3, 3, 'save_post_draft'),
	(3, 3, 'auto_save_post_draft'),
	(3, 3, 'poll_post'),
	(3, 3, 'poll_add_any'),
	(3, 3, 'poll_remove_any'),
	(3, 3, 'poll_view'),
	(3, 3, 'poll_vote'),
	(3, 3, 'poll_lock_any'),
	(3, 3, 'poll_edit_any'),
	(3, 3, 'report_any'),
	(3, 3, 'lock_own'),
	(3, 3, 'send_topic'),
	(3, 3, 'mark_any_notify'),
	(3, 3, 'mark_notify'),
	(3, 3, 'delete_own'),
	(3, 3, 'modify_own'),
	(3, 3, 'pin_topic'),
	(3, 3, 'lock_any'),
	(3, 3, 'remove_any'),
	(3, 3, 'move_any'),
	(3, 3, 'merge_any'),
	(3, 3, 'split_any'),
	(3, 3, 'delete_any'),
	(3, 3, 'modify_any'),
	(3, 3, 'approve_posts'),
	(3, 3, 'post_attachment'),
	(3, 3, 'view_attachments'),

	(-1, 4, 'poll_view'),

	(0, 4, 'mark_any_notify'),
	(0, 4, 'mark_notify'),
	(0, 4, 'poll_view'),
	(0, 4, 'poll_vote'),
	(0, 4, 'report_any'),
	(0, 4, 'send_topic'),
	(0, 4, 'view_attachments'),

	(2, 4, 'moderate_board'),
	(2, 4, 'post_new'),
	(2, 4, 'post_reply_own'),
	(2, 4, 'post_reply_any'),
	(2, 4, 'save_post_draft'),
	(2, 4, 'auto_save_post_draft'),
	(2, 4, 'poll_post'),
	(2, 4, 'poll_add_any'),
	(2, 4, 'poll_remove_any'),
	(2, 4, 'poll_view'),
	(2, 4, 'poll_vote'),
	(2, 4, 'poll_lock_any'),
	(2, 4, 'poll_edit_any'),
	(2, 4, 'report_any'),
	(2, 4, 'lock_own'),
	(2, 4, 'send_topic'),
	(2, 4, 'mark_any_notify'),
	(2, 4, 'mark_notify'),
	(2, 4, 'delete_own'),
	(2, 4, 'modify_own'),
	(2, 4, 'pin_topic'),
	(2, 4, 'lock_any'),
	(2, 4, 'remove_any'),
	(2, 4, 'move_any'),
	(2, 4, 'merge_any'),
	(2, 4, 'split_any'),
	(2, 4, 'delete_any'),
	(2, 4, 'modify_any'),
	(2, 4, 'approve_posts'),
	(2, 4, 'post_attachment'),
	(2, 4, 'view_attachments'),

	(3, 4, 'moderate_board'),
	(3, 4, 'post_new'),
	(3, 4, 'post_reply_own'),
	(3, 4, 'post_reply_any'),
	(3, 4, 'save_post_draft'),
	(3, 4, 'auto_save_post_draft'),
	(3, 4, 'poll_post'),
	(3, 4, 'poll_add_any'),
	(3, 4, 'poll_remove_any'),
	(3, 4, 'poll_view'),
	(3, 4, 'poll_vote'),
	(3, 4, 'poll_lock_any'),
	(3, 4, 'poll_edit_any'),
	(3, 4, 'report_any'),
	(3, 4, 'lock_own'),
	(3, 4, 'send_topic'),
	(3, 4, 'mark_any_notify'),
	(3, 4, 'mark_notify'),
	(3, 4, 'delete_own'),
	(3, 4, 'modify_own'),
	(3, 4, 'pin_topic'),
	(3, 4, 'lock_any'),
	(3, 4, 'remove_any'),
	(3, 4, 'move_any'),
	(3, 4, 'merge_any'),
	(3, 4, 'split_any'),
	(3, 4, 'delete_any'),
	(3, 4, 'modify_any'),
	(3, 4, 'approve_posts'),
	(3, 4, 'post_attachment'),
	(3, 4, 'view_attachments');
# --------------------------------------------------------

#
# Table structure for table `boards`
#

CREATE TABLE {$db_prefix}boards (
	id_board mediumint(8) unsigned NOT NULL auto_increment,
	id_owner mediumint(8) unsigned NOT NULL default 0,
	id_cat tinyint(4) unsigned NOT NULL default 0,
	child_level tinyint(4) unsigned NOT NULL default 0,
	id_parent smallint(5) unsigned NOT NULL default 0,
	board_order smallint(5) NOT NULL default 0,
	id_last_msg int(10) unsigned NOT NULL default 0,
	id_msg_updated int(10) unsigned NOT NULL default 0,
	member_groups varchar(255) NOT NULL default '-1,0',
	id_profile smallint(5) unsigned NOT NULL default 1,
	name varchar(255) NOT NULL default '',
	url varchar(64) NOT NULL default '',
	urllen tinyint(3) unsigned NOT NULL default 0,
	description text NOT NULL,
	created timestamp NOT NULL default CURRENT_TIMESTAMP,
	num_topics mediumint(8) unsigned NOT NULL default 0,
	num_posts mediumint(8) unsigned NOT NULL default 0,
	num_members mediumint(8) unsigned NOT NULL default 0,
	check_members_date datetime NOT NULL default '0000-00-00 00:00:00',
	count_posts tinyint(4) NOT NULL default 0,
	override_theme tinyint(4) unsigned NOT NULL default 0,
	skin varchar(255) NOT NULL default '',
	skin_mobile varchar(255) NOT NULL default '',
	board_type enum('board', 'blog', 'site') NOT NULL default 'board',
	unapproved_posts smallint(5) NOT NULL default 0,
	unapproved_topics smallint(5) NOT NULL default 0,
	redirect varchar(255) NOT NULL default '',
	redirect_newtab tinyint(1) unsigned NOT NULL default 0,
	most_today smallint(5) unsigned NOT NULL default 0,
	most_ever smallint(5) unsigned NOT NULL default 0,
	most_date bigint(20) unsigned NOT NULL default 0,
	most_updated date NOT NULL default '0000-00-00',
	sort_method enum('subject', 'starter', 'last_poster', 'replies', 'views', 'first_post', 'last_post') NOT NULL default 'last_post',
	sort_override enum('natural_desc', 'natural_asc', 'force_desc', 'force_asc') NOT NULL default 'natural_desc',
	language varchar(255) NOT NULL default '',
	offlimits_msg text NOT NULL,
	PRIMARY KEY (board_order, id_board),
	UNIQUE board_id (id_board),
	UNIQUE categories (id_cat, id_board),
	UNIQUE url (url),
	KEY owner (id_owner),
	KEY id_parent (id_parent),
	KEY id_msg_updated (id_msg_updated),
	KEY member_groups (member_groups(48)),
	KEY urllen (urllen)
) ENGINE=MyISAM;

#
# Dumping data for table `boards`
#

INSERT INTO {$db_prefix}boards
	(id_board, id_cat, board_order, id_last_msg, id_msg_updated, name, description, url, urllen, num_topics, num_posts, member_groups)
VALUES
	(1, 1, 1, 1, 1, '{$default_board_name}', '{$default_board_description}', '{$boarddomain}/{$default_board_url}', CHAR_LENGTH('{$boarddomain}/{$default_board_url}'), 1, 1, '-1,0,2'),
	(2, 1, 2, 0, 0, '{$default_recycling_board_name}', '{$default_recycling_board_description}', '{$default_recycling_board_url}', CHAR_LENGTH('{$default_recycling_board_url}'), 0, 0, '2');
# --------------------------------------------------------

#
# Table structure for table `categories`
#

CREATE TABLE {$db_prefix}categories (
	id_cat tinyint(4) unsigned NOT NULL auto_increment,
	cat_order tinyint(4) NOT NULL default 0,
	name varchar(255) NOT NULL default '',
	can_collapse tinyint(1) unsigned NOT NULL default 1,
	PRIMARY KEY (cat_order, id_cat),
	UNIQUE id_cat (id_cat)
) ENGINE=MyISAM;

#
# Dumping data for table `categories`
#

INSERT INTO {$db_prefix}categories
VALUES (1, 0, '{$default_category_name}', 1);
# --------------------------------------------------------

#
# Table structure for table `collapsed_categories`
#

CREATE TABLE {$db_prefix}collapsed_categories (
	id_cat tinyint(4) unsigned NOT NULL default 0,
	id_member mediumint(8) unsigned NOT NULL default 0,
	PRIMARY KEY (id_cat, id_member)
) ENGINE=MyISAM;

#
# Table structure for table `contact_lists`
#

CREATE TABLE {$db_prefix}contact_lists (
	id_list mediumint(8) unsigned NOT NULL auto_increment,
	id_owner mediumint(8) unsigned NOT NULL default 0,
	name varchar(40) NOT NULL default '{friends}',
	list_type enum('friends', 'family', 'known', 'work', 'follow', 'restrict', 'custom') NOT NULL default 'friends',
	visibility enum('everyone', 'all-contacts', 'just-this-group', 'just-this-member', 'just-me') NOT NULL default 'everyone',
	added int(10) unsigned NOT NULL default 0,
	position tinyint(4) NOT NULL default 0,
	PRIMARY KEY (id_list),
	KEY member (id_owner)
) AUTO_INCREMENT=100 ENGINE=MyISAM;

#
# Table structure for table `contacts`
#

CREATE TABLE {$db_prefix}contacts (
	id_member mediumint(8) unsigned NOT NULL default 0,
	id_owner mediumint(8) unsigned NOT NULL default 0,
	id_list mediumint(8) unsigned NOT NULL default 0,
	list_type enum('friends', 'family', 'known', 'work', 'follow', 'restrict', 'custom') NOT NULL default 'friends',
	is_synchronous tinyint(1) unsigned NOT NULL default 0,
	hidden tinyint(1) unsigned NOT NULL default 0,
	added int(10) unsigned NOT NULL default 0,
	position tinyint(4) NOT NULL default 0,
	PRIMARY KEY (id_member, id_list, list_type),
	KEY member (id_owner)
) ENGINE=MyISAM;

#
# Table structure for table `custom_fields`
#

CREATE TABLE {$db_prefix}custom_fields (
	id_field smallint(5) unsigned NOT NULL auto_increment,
	col_name varchar(12) NOT NULL default '',
	field_name varchar(40) NOT NULL default '',
	field_desc varchar(255) NOT NULL default '',
	field_type varchar(8) NOT NULL default 'text',
	field_length smallint(5) NOT NULL default 255,
	field_options text NOT NULL,
	mask varchar(255) NOT NULL default '',
	show_reg tinyint(3) NOT NULL default 0,
	show_display tinyint(3) NOT NULL default 0,
	show_profile varchar(20) NOT NULL default 'forumprofile',
	show_mlist tinyint(3) NOT NULL default 0,
	can_see varchar(255) NOT NULL default '',
	can_edit varchar(255) NOT NULL default '',
	active tinyint(3) NOT NULL default 1,
	bbc tinyint(3) NOT NULL default 0,
	can_search tinyint(3) NOT NULL default 0,
	default_value varchar(255) NOT NULL default '',
	enclose text NOT NULL,
	placement tinyint(3) NOT NULL default 0,
	position tinyint(3) NOT NULL default 0,
	PRIMARY KEY (id_field),
	UNIQUE col_name (col_name)
) ENGINE=MyISAM;

#
# Table structure for table `drafts`
#

CREATE TABLE {$db_prefix}drafts (
	id_draft int(10) unsigned NOT NULL auto_increment,
	id_member mediumint(8) unsigned NOT NULL default 0,
	subject varchar(255) NOT NULL default '',
	body mediumtext NOT NULL,
	post_time int(10) unsigned,
	is_pm tinyint(3) unsigned NOT NULL default 0,
	id_board mediumint(8) unsigned NOT NULL default 0,
	id_context int(10) unsigned NOT NULL default 0,
	extra text NOT NULL,
	PRIMARY KEY (id_draft),
	KEY member_context (id_member, is_pm, id_context),
	KEY member_board_context (id_member, is_pm, id_board)
) ENGINE=MyISAM;

#
# Table structure for table `group_moderators`
#

CREATE TABLE {$db_prefix}group_moderators (
	id_group smallint(5) NOT NULL default 0,
	id_member mediumint(8) unsigned NOT NULL default 0,
	PRIMARY KEY (id_group, id_member)
) ENGINE=MyISAM;

#
# Table structure for table `infractions`
#

CREATE TABLE {$db_prefix}infractions (
	id_infraction smallint(5) unsigned NOT NULL auto_increment,
	infraction_name varchar(60) NOT NULL default '',
	infraction_msg text NOT NULL,
	points smallint(4) unsigned NOT NULL default 0,
	sanctions varchar(255) NOT NULL default '',
	duration varchar(5) NOT NULL default '',
	issuing_groups varchar(255) NOT NULL default '',
	PRIMARY KEY (id_infraction)
) ENGINE=MyISAM;

#
# Table structure for table `language_changes`
#

CREATE TABLE {$db_prefix}language_changes (
	id_lang varchar(32) NOT NULL,
	lang_file varchar(64) NOT NULL,
	lang_var varchar(8) NOT NULL,
	lang_key varchar(64) NOT NULL,
	lang_string text NOT NULL,
	serial tinyint(3) unsigned NOT NULL,
	PRIMARY KEY (id_lang, lang_file, lang_var, lang_key),
	KEY lang_file (id_lang, lang_file)
) ENGINE=MyISAM;

#
# Table structure for table `likes`
#
CREATE TABLE {$db_prefix}likes (
	id_content int(10) unsigned NOT NULL default 0,
	content_type char(6) NOT NULL default '',
	id_member mediumint(8) unsigned NOT NULL default 0,
	like_time int(10) unsigned NOT NULL default 0,
	PRIMARY KEY (id_content, content_type, id_member),
	KEY (id_content, content_type),
	KEY (id_member)
) ENGINE=MyISAM;

#
# Table structure for table `log_actions`
#

CREATE TABLE {$db_prefix}log_actions (
	id_action int(10) unsigned NOT NULL auto_increment,
	id_log tinyint(3) unsigned NOT NULL default 1,
	log_time int(10) unsigned NOT NULL default 0,
	id_member mediumint(8) unsigned NOT NULL default 0,
	ip int(10) NOT NULL default 0,
	action varchar(30) NOT NULL default '',
	id_board mediumint(8) unsigned NOT NULL default 0,
	id_topic mediumint(8) unsigned NOT NULL default 0,
	id_msg int(10) unsigned NOT NULL default 0,
	extra text NOT NULL,
	PRIMARY KEY (id_action),
	KEY id_log (id_log),
	KEY log_time (log_time),
	KEY id_member (id_member),
	KEY id_board (id_board),
	KEY id_msg (id_msg)
) ENGINE=MyISAM;

#
# Table structure for table `log_activity`
#

CREATE TABLE {$db_prefix}log_activity (
	date date NOT NULL default '0001-01-01',
	hits mediumint(8) unsigned NOT NULL default 0,
	topics smallint(5) unsigned NOT NULL default 0,
	posts smallint(5) unsigned NOT NULL default 0,
	registers smallint(5) unsigned NOT NULL default 0,
	most_on smallint(5) unsigned NOT NULL default 0,
	PRIMARY KEY (date)
) ENGINE=MyISAM;

#
# Table structure for table `log_boards`
#

CREATE TABLE {$db_prefix}log_boards (
	id_member mediumint(8) unsigned NOT NULL default 0,
	id_board mediumint(8) unsigned NOT NULL default 0,
	id_msg int(10) unsigned NOT NULL default 0,
	PRIMARY KEY (id_member, id_board)
) ENGINE=MyISAM;

#
# Table structure for table `log_comments`
#

CREATE TABLE {$db_prefix}log_comments (
	id_comment mediumint(8) unsigned NOT NULL auto_increment,
	id_member mediumint(8) unsigned NOT NULL default 0,
	member_name varchar(80) NOT NULL default '',
	comment_type varchar(8) NOT NULL default 'warning',
	id_recipient mediumint(8) unsigned NOT NULL default 0,
	recipient_name varchar(255) NOT NULL default '',
	log_time int(10) NOT NULL default 0,
	id_notice mediumint(8) unsigned NOT NULL default 0,
	counter tinyint(3) NOT NULL default 0,
	body text NOT NULL,
	PRIMARY KEY (id_comment),
	KEY id_recipient (id_recipient),
	KEY log_time (log_time),
	KEY comment_type (comment_type(8))
) ENGINE=MyISAM;

#
# Table structure for table `log_digest`
#

CREATE TABLE {$db_prefix}log_digest (
	id_topic mediumint(8) unsigned NOT NULL default 0,
	id_msg int(10) unsigned NOT NULL default 0,
	note_type varchar(10) NOT NULL default 'post',
	daily tinyint(3) unsigned NOT NULL default 0,
	exclude mediumint(8) unsigned NOT NULL default 0
) ENGINE=MyISAM;

#
# Table structure for table `log_errors`
#

CREATE TABLE {$db_prefix}log_errors (
	id_error mediumint(8) unsigned NOT NULL auto_increment,
	log_time int(10) unsigned NOT NULL default 0,
	id_member mediumint(8) unsigned NOT NULL default 0,
	ip int(10) NOT NULL default 0,
	url text NOT NULL,
	message text NOT NULL,
	error_type varchar(255) NOT NULL default 'general',
	file varchar(255) NOT NULL default '',
	line mediumint(8) unsigned NOT NULL default 0,
	PRIMARY KEY (id_error),
	KEY log_time (log_time),
	KEY id_member (id_member),
	KEY ip (ip),
	KEY error_type (error_type)
) ENGINE=MyISAM;

#
# Table structure for table `log_floodcontrol`
#

CREATE TABLE {$db_prefix}log_floodcontrol (
	ip int(10) NOT NULL default 0,
	log_time int(10) unsigned NOT NULL default 0,
	log_type varchar(8) NOT NULL default 'post',
	PRIMARY KEY (ip, log_type(8))
) ENGINE=MyISAM;

#
# Table structure for table `log_group_requests`
#

CREATE TABLE {$db_prefix}log_group_requests (
	id_request mediumint(8) unsigned NOT NULL auto_increment,
	id_member mediumint(8) unsigned NOT NULL default 0,
	id_group smallint(5) NOT NULL default 0,
	time_applied int(10) unsigned NOT NULL default 0,
	reason text NOT NULL,
	PRIMARY KEY (id_request),
	UNIQUE id_member (id_member, id_group)
) ENGINE=MyISAM;

#
# Table structure for table `log_ips`
#

CREATE TABLE {$db_prefix}log_ips (
	id_ip int(10) unsigned NOT NULL auto_increment,
	member_ip char(32) NOT NULL default '',
	PRIMARY KEY (id_ip),
	UNIQUE member_ip (member_ip)
) ENGINE=MyISAM;

#
# Table structure for table `log_infractions`
#
CREATE TABLE {$db_prefix}log_infractions (
	id_issue int(10) unsigned NOT NULL auto_increment,
	issued_by mediumint(8) unsigned NOT NULL default 0,
	issued_by_name varchar(255) NOT NULL default '',
	issue_date int(10) unsigned NOT NULL default 0,
	issued_to mediumint(8) unsigned NOT NULL default 0,
	issued_to_name varchar(255) NOT NULL default '',
	reason varchar(255) NOT NULL default '',
	duration varchar(5) NOT NULL default '1w',
	points smallint(5) NOT NULL default 0,
	sanctions varchar(255) NOT NULL default '',
	notice_subject varchar(255) NOT NULL default '',
	notice_body text NOT NULL,
	inf_state tinyint(3) unsigned NOT NULL default 0,
	revoked_by mediumint(8) unsigned NOT NULL default 0,
	revoked_by_name varchar(255) NOT NULL default '',
	revoked_date int(10) unsigned NOT NULL default 0,
	revoked_reason varchar(255) NOT NULL default '',
	imperative int(10) unsigned NOT NULL default 0,
	because_of text NOT NULL,
	PRIMARY KEY (id_issue),
	KEY issued_by (issued_by),
	KEY issued_to (issued_to),
	KEY issue_date (issue_date)
) ENGINE=MyISAM;

#
# Table structure for table `log_intrusion`
#

CREATE TABLE {$db_prefix}log_intrusion (
	id_event int(10) unsigned NOT NULL auto_increment,
	id_member mediumint(8) unsigned NOT NULL default 0,
	error_type char(16) NOT NULL default '                ',
	ip int(10) NOT NULL default 0,
	event_time int(10) unsigned NOT NULL,
	http_method char(4) NOT NULL default '    ',
	request_uri varchar(255) NOT NULL default '',
	protocol varchar(15) NOT NULL default '',
	user_agent varchar(255) NOT NULL default '',
	headers text NOT NULL,
	request_entity text NOT NULL,
	PRIMARY KEY (id_event)
) ENGINE=MyISAM;

#
# Table structure for table `log_mark_read`
#

CREATE TABLE {$db_prefix}log_mark_read (
	id_member mediumint(8) unsigned NOT NULL default 0,
	id_board mediumint(8) unsigned NOT NULL default 0,
	id_msg int(10) unsigned NOT NULL default 0,
	PRIMARY KEY (id_member, id_board)
) ENGINE=MyISAM;

#
# Table structure for table `log_notify`
#

CREATE TABLE {$db_prefix}log_notify (
	id_member mediumint(8) unsigned NOT NULL default 0,
	id_topic mediumint(8) unsigned NOT NULL default 0,
	id_board mediumint(8) unsigned NOT NULL default 0,
	sent tinyint(1) unsigned NOT NULL default 0,
	PRIMARY KEY (id_member, id_topic, id_board),
	KEY id_topic (id_topic, id_member)
) ENGINE=MyISAM;

#
# Table structure for table `log_online`
#

CREATE TABLE {$db_prefix}log_online (
	session varchar(34) NOT NULL default '',
	log_time int(10) NOT NULL default 0,
	id_member mediumint(8) unsigned NOT NULL default 0,
	id_spider smallint(5) unsigned NOT NULL default 0,
	ip int(10) unsigned NOT NULL default 0,
	url text NOT NULL,
	PRIMARY KEY (session),
	KEY log_time (log_time),
	KEY id_member (id_member)
) ENGINE=MyISAM;

#
# Table structure for table `log_polls`
#

CREATE TABLE {$db_prefix}log_polls (
	id_poll mediumint(8) unsigned NOT NULL default 0,
	id_member mediumint(8) unsigned NOT NULL default 0,
	id_choice tinyint(3) unsigned NOT NULL default 0,
	KEY id_poll (id_poll, id_member, id_choice)
) ENGINE=MyISAM;

#
# Table structure for table `log_reported`
#

CREATE TABLE {$db_prefix}log_reported (
	id_report mediumint(8) unsigned NOT NULL auto_increment,
	id_msg int(10) unsigned NOT NULL default 0,
	id_topic mediumint(8) unsigned NOT NULL default 0,
	id_board mediumint(8) unsigned NOT NULL default 0,
	id_member mediumint(8) unsigned NOT NULL default 0,
	membername varchar(255) NOT NULL default '',
	subject varchar(255) NOT NULL default '',
	body text NOT NULL,
	time_started int(10) NOT NULL default 0,
	time_updated int(10) NOT NULL default 0,
	num_reports mediumint(6) NOT NULL default 0,
	closed tinyint(3) NOT NULL default 0,
	ignore_all tinyint(3) NOT NULL default 0,
	PRIMARY KEY (id_report),
	KEY id_member (id_member),
	KEY id_topic (id_topic),
	KEY closed (closed),
	KEY time_started (time_started),
	KEY id_msg (id_msg)
) ENGINE=MyISAM;

#
# Table structure for table `log_reported_comments`
#

CREATE TABLE {$db_prefix}log_reported_comments (
	id_comment mediumint(8) unsigned NOT NULL auto_increment,
	id_report mediumint(8) unsigned NOT NULL default 0,
	id_member mediumint(8) unsigned NOT NULL,
	membername varchar(255) NOT NULL default '',
	email_address varchar(255) NOT NULL default '',
	member_ip int(10) NOT NULL default 0,
	comment varchar(255) NOT NULL default '',
	time_sent int(10) NOT NULL,
	PRIMARY KEY (id_comment),
	KEY id_report (id_report),
	KEY id_member (id_member),
	KEY time_sent (time_sent)
) ENGINE=MyISAM;

#
# Table structure for table `log_scheduled_tasks`
#

CREATE TABLE {$db_prefix}log_scheduled_tasks (
	id_log mediumint(8) unsigned NOT NULL auto_increment,
	id_task smallint(5) NOT NULL default 0,
	time_run int(10) NOT NULL default 0,
	time_taken float NOT NULL default 0,
	PRIMARY KEY (id_log)
) ENGINE=MyISAM;

#
# Table structure for table `log_search_messages`
#

CREATE TABLE {$db_prefix}log_search_messages (
	id_search tinyint(3) unsigned NOT NULL default 0,
	id_msg int(10) unsigned NOT NULL default 0,
	PRIMARY KEY (id_search, id_msg)
) ENGINE=MyISAM;

#
# Table structure for table `log_search_results`
#

CREATE TABLE {$db_prefix}log_search_results (
	id_search tinyint(3) unsigned NOT NULL default 0,
	id_topic mediumint(8) unsigned NOT NULL default 0,
	id_msg int(10) unsigned NOT NULL default 0,
	relevance smallint(5) unsigned NOT NULL default 0,
	num_matches smallint(5) unsigned NOT NULL default 0,
	PRIMARY KEY (id_search, id_topic)
) ENGINE=MyISAM;

#
# Table structure for table `log_search_subjects`
#

CREATE TABLE {$db_prefix}log_search_subjects (
	word varchar(20) NOT NULL default '',
	id_topic mediumint(8) unsigned NOT NULL default 0,
	PRIMARY KEY (word, id_topic),
	KEY id_topic (id_topic)
) ENGINE=MyISAM;

#
# Table structure for table `log_search_topics`
#

CREATE TABLE {$db_prefix}log_search_topics (
	id_search tinyint(3) unsigned NOT NULL default 0,
	id_topic mediumint(8) unsigned NOT NULL default 0,
	PRIMARY KEY (id_search, id_topic)
) ENGINE=MyISAM;

#
# Table structure for table `log_spider_hits`
#

CREATE TABLE {$db_prefix}log_spider_hits (
	id_hit int(10) unsigned NOT NULL auto_increment,
	id_spider smallint(5) unsigned NOT NULL default 0,
	log_time int(10) unsigned NOT NULL default 0,
	url varchar(255) NOT NULL default '',
	processed tinyint(3) NOT NULL default 0,
	PRIMARY KEY (id_hit),
	KEY id_spider(id_spider),
	KEY log_time(log_time),
	KEY processed (processed)
) ENGINE=MyISAM;

#
# Table structure for table `log_spider_stats`
#

CREATE TABLE {$db_prefix}log_spider_stats (
	id_spider smallint(5) unsigned NOT NULL default 0,
	page_hits smallint(5) unsigned NOT NULL default 0,
	last_seen int(10) unsigned NOT NULL default 0,
	stat_date date NOT NULL default '0001-01-01',
	PRIMARY KEY (stat_date, id_spider)
) ENGINE=MyISAM;

#
# Table structure for table `log_subscribed`
#

CREATE TABLE {$db_prefix}log_subscribed (
	id_sublog int(10) unsigned NOT NULL auto_increment,
	id_subscribe mediumint(8) unsigned NOT NULL default 0,
	id_member mediumint(8) unsigned NOT NULL default 0,
	old_id_group smallint(5) NOT NULL default 0,
	start_time int(10) NOT NULL default 0,
	end_time int(10) NOT NULL default 0,
	status tinyint(3) NOT NULL default 0,
	payments_pending tinyint(3) NOT NULL default 0,
	pending_details text NOT NULL,
	reminder_sent tinyint(3) NOT NULL default 0,
	vendor_ref varchar(255) NOT NULL default '',
	PRIMARY KEY (id_sublog),
	UNIQUE KEY id_subscribe (id_subscribe, id_member),
	KEY end_time (end_time),
	KEY reminder_sent (reminder_sent),
	KEY payments_pending (payments_pending),
	KEY status (status),
	KEY id_member (id_member)
) ENGINE=MyISAM;

#
# Table structure for table `log_topics`
#

CREATE TABLE {$db_prefix}log_topics (
	id_member mediumint(8) unsigned NOT NULL default 0,
	id_topic mediumint(8) unsigned NOT NULL default 0,
	id_msg int(10) unsigned NOT NULL default 0,
	PRIMARY KEY (id_member, id_topic),
	KEY id_topic (id_topic)
) ENGINE=MyISAM;

#
# Table structure for table `mail_queue`
#

CREATE TABLE {$db_prefix}mail_queue (
	id_mail int(10) unsigned NOT NULL auto_increment,
	time_sent int(10) NOT NULL default 0,
	recipient varchar(255) NOT NULL default '',
	body mediumtext NOT NULL,
	subject varchar(255) NOT NULL default '',
	headers text NOT NULL,
	send_html tinyint(3) NOT NULL default 0,
	priority tinyint(3) NOT NULL default 1,
	private tinyint(1) unsigned NOT NULL default 0,
	PRIMARY KEY (id_mail),
	KEY time_sent (time_sent),
	KEY mail_priority (priority, id_mail)
) ENGINE=MyISAM;
# --------------------------------------------------------

#
# Table structure for table `media_albums`
#

CREATE TABLE {$db_prefix}media_albums (
	id_album int(10) unsigned NOT NULL auto_increment,
	album_of mediumint(8) unsigned NOT NULL default 0,
	featured tinyint(1) unsigned NOT NULL default 0,
	name varchar(255) NOT NULL default '',
	description text NOT NULL,
	master int(10) unsigned NOT NULL default 0,
	icon int(10) unsigned NOT NULL default 0,
	bigicon int(10) unsigned NOT NULL default 0,
	passwd varchar(64) NOT NULL default '',
	directory text NOT NULL,
	parent int(10) unsigned NOT NULL default 0,
	access varchar(255) NOT NULL default '',
	access_write varchar(255) NOT NULL default '',
	approved tinyint(1) unsigned NOT NULL default 0,
	a_order int(11) NOT NULL default 0,
	child_level int(11) NOT NULL default 0,
	id_last_media int(11) unsigned NOT NULL default 0,
	num_items int(11) unsigned NOT NULL default 0,
	options text NOT NULL,
	id_perm_profile int(11) NOT NULL default 0,
	id_quota_profile int(11) NOT NULL default 0,
	hidden tinyint(1) unsigned NOT NULL default 0,
	allowed_members varchar(255) NOT NULL default '',
	allowed_write varchar(255) NOT NULL default '',
	denied_members varchar(255) NOT NULL default '',
	denied_write varchar(255) NOT NULL default '',
	id_topic mediumint(8) unsigned NOT NULL default 0,
	PRIMARY KEY (id_album),
	KEY album_of (album_of),
	KEY master (master),
	KEY id_of (id_album, album_of, featured)
) ENGINE=MyISAM;

#
# Table structure for table `media_comments`
#

CREATE TABLE {$db_prefix}media_comments (
	id_comment int(10) unsigned NOT NULL auto_increment,
	id_member mediumint(8) unsigned NOT NULL default 0,
	id_media int(10) unsigned NOT NULL default 0,
	id_album int(10) unsigned NOT NULL default 0,
	message text NOT NULL,
	posted_on int(10) unsigned NOT NULL default 0,
	last_edited int(10) unsigned NOT NULL default 0,
	last_edited_by int(10) unsigned NOT NULL default 0,
	last_edited_name varchar(25) NOT NULL default '',
	approved tinyint(1) unsigned NOT NULL default 0,
	PRIMARY KEY (id_comment)
) ENGINE=MyISAM;

#
# Table structure for table `media_fields`
#

CREATE TABLE {$db_prefix}media_fields (
	id_field int(10) unsigned NOT NULL auto_increment,
	name varchar(100) NOT NULL default '',
	type varchar(20) NOT NULL default 'text',
	options text NOT NULL,
	required tinyint(1) unsigned NOT NULL default 0,
	searchable tinyint(1) unsigned NOT NULL default 0,
	description text NOT NULL,
	bbc tinyint(1) unsigned NOT NULL default 0,
	albums text NOT NULL,
	PRIMARY KEY (id_field)
) ENGINE=MyISAM;

#
# Table structure for table `media_field_data`
#

CREATE TABLE {$db_prefix}media_field_data (
	id_field int(10) unsigned NOT NULL default 0,
	id_media int(10) unsigned NOT NULL default 0,
	value text NOT NULL,
	PRIMARY KEY (id_field, id_media)
) ENGINE=MyISAM;

#
# Table structure for table `media_files`
#

CREATE TABLE {$db_prefix}media_files (
	id_file int(10) unsigned NOT NULL auto_increment,
	filesize int(10) unsigned NOT NULL default 0,
	filename text NOT NULL,
	width smallint(5) NOT NULL default 1,
	height smallint(5) NOT NULL default 1,
	directory text NOT NULL,
	id_album int(10) unsigned NOT NULL default 0,
	transparency enum('', 'transparent', 'opaque') NOT NULL default '',
	meta text NOT NULL,
	PRIMARY KEY (id_file)
) ENGINE=MyISAM;
# --------------------------------------------------------

#
# Dumping data for table `media_files`
#

INSERT INTO {$db_prefix}media_files
	(id_file, filename, filesize, directory, width, height, id_album, meta)
VALUES
	('1', 'music.png', '4118', 'icons', '48', '48', '0', ''),
	('2', 'film.png', '2911', 'icons', '48', '48', '0', ''),
	('3', 'camera.png', '2438', 'icons', '48', '48', '0', ''),
	('4', 'folder.png', '2799', 'icons', '48', '48', '0', '');

#
# Table structure for table `media_items`
#

CREATE TABLE {$db_prefix}media_items (
	id_media int(10) unsigned NOT NULL auto_increment,
	id_member mediumint(8) unsigned NOT NULL default 0,
	member_name varchar(25) NOT NULL default '',
	last_edited int(10) unsigned NOT NULL default 0,
	last_edited_by int(10) unsigned NOT NULL default 0,
	last_edited_name text NOT NULL,
	id_file int(10) unsigned NOT NULL default 0,
	id_thumb int(10) unsigned NOT NULL default 0,
	id_preview int(10) unsigned NOT NULL default 0,
	type varchar(10) NOT NULL default 'image',
	album_id int(10) unsigned NOT NULL default 0,
	rating mediumint(8) unsigned NOT NULL default 0,
	voters mediumint(8) unsigned NOT NULL default 0,
	weighted float NOT NULL default 0,
	title varchar(255) NOT NULL default '(No title)',
	description text NOT NULL,
	approved tinyint(1) unsigned NOT NULL default 0,
	time_added int(10) unsigned NOT NULL default 0,
	views int(10) unsigned NOT NULL default 0,
	downloads int(10) unsigned NOT NULL default 0,
	last_viewed tinyint(1) unsigned NOT NULL default 0,
	keywords text NOT NULL,
	embed_url text NOT NULL,
	id_last_comment int(10) unsigned NOT NULL default 0,
	log_last_access_time int(10) unsigned NOT NULL default 0,
	num_comments mediumint(8) unsigned NOT NULL default 0,
	PRIMARY KEY (id_media),
	KEY id_thumb (id_thumb),
	KEY time_added (time_added),
	KEY album_id (album_id)
) ENGINE=MyISAM;

#
# Table structure for table `media_log_media`
#

CREATE TABLE {$db_prefix}media_log_media (
	id_media int(10) unsigned NOT NULL default 0,
	id_member mediumint(8) unsigned NOT NULL default 0,
	time int(10) unsigned NOT NULL default 0,
	PRIMARY KEY (id_media, id_member)
) ENGINE=MyISAM;

#
# Table structure for table `media_log_ratings`
#

CREATE TABLE {$db_prefix}media_log_ratings (
	id_media int(10) unsigned NOT NULL default 0,
	id_member mediumint(8) unsigned NOT NULL default 0,
	rating mediumint(8) NOT NULL default 0,
	time int(10) unsigned NOT NULL default 0,
	PRIMARY KEY (id_media, id_member)
) ENGINE=MyISAM;

#
# Table structure for table `media_perms`
#

CREATE TABLE {$db_prefix}media_perms (
	id_group smallint(5) NOT NULL default 0,
	id_profile int(10) unsigned NOT NULL default 0,
	permission varchar(255) NOT NULL default '',
	PRIMARY KEY (id_group, id_profile, permission)
) ENGINE=MyISAM;

#
# Table structure for table `media_playlists`
#

CREATE TABLE {$db_prefix}media_playlists (
	id_playlist int(10) unsigned NOT NULL auto_increment,
	id_member mediumint(8) unsigned NOT NULL default 0,
	name varchar(80) NOT NULL default '',
	description text NOT NULL,
	views int(10) unsigned NOT NULL default 0,
	PRIMARY KEY (id_playlist),
	KEY name (name),
	KEY views (views)
) ENGINE=MyISAM;

#
# Table structure for table `media_playlist_data`
#

CREATE TABLE {$db_prefix}media_playlist_data (
	id_playlist int(10) unsigned NOT NULL default 0,
	id_media int(10) unsigned NOT NULL default 0,
	play_order int(5) unsigned NOT NULL auto_increment,
	description text NOT NULL,
	PRIMARY KEY (id_playlist, play_order, id_media)
) ENGINE=MyISAM;

#
# Table structure for table `media_quotas`
#

CREATE TABLE {$db_prefix}media_quotas (
	id_profile int(10) unsigned NOT NULL default 0,
	id_group smallint(5) NOT NULL default 0,
	type varchar(10) NOT NULL default '',
	quota int(10) unsigned NOT NULL default 0,
	PRIMARY KEY (id_profile, id_group, type)
) ENGINE=MyISAM;

#
# Table structure for table `media_settings`
#

CREATE TABLE {$db_prefix}media_settings (
	name varchar(30) NOT NULL default '',
	value text NOT NULL,
	PRIMARY KEY (name)
) ENGINE=MyISAM;
# --------------------------------------------------------

#
# Dumping data for table `media_settings`
#

INSERT INTO {$db_prefix}media_settings
	(name, value)
VALUES
	('installed_on', UNIX_TIMESTAMP()),
	('data_dir_path', '{$boarddir}/media'),
	('data_dir_url', '{$boardurl}/media'),
	('max_dir_files', '1500'),
	('num_items_per_page', '15'),
	('max_dir_size', '51400'),
	('max_file_size', '1024'),
	('max_width', '2048'),
	('max_height', '1536'),
	('allow_over_max', '1'),
	('upload_security_check', '0'),
	('jpeg_compression', '80'),
	('num_unapproved_items', '0'),
	('num_unapproved_albums', '0'),
	('num_unapproved_comments', '0'),
	('num_unapproved_item_edits', '0'),
	('num_unapproved_album_edits', '0'),
	('num_reported_items', '0'),
	('num_reported_comments', '0'),
	('recent_item_limit', '5'),
	('random_item_limit', '5'),
	('recent_comments_limit', '10'),
	('recent_albums_limit', '10'),
	('total_items', '0'),
	('total_comments', '0'),
	('total_albums', '0'),
	('total_contests', '0'),
	('show_sub_albums_on_index', '1'),
	('enable_re-rating', '0'),
	('use_metadata_date', '1'),
	('max_thumb_width', '120'),
	('max_thumb_height', '120'),
	('max_preview_width', '500'),
	('max_preview_height', '500'),
	('max_bigicon_width', '200'),
	('max_bigicon_height', '200'),
	('max_thumbs_per_page', '100'),
	('max_title_length', '30'),
	('show_extra_info', '1'),
	('clear_thumbnames', '1'),
	('image_handler', '1'),
	('enable_cache', '0'),
	('use_zoom', '1'),
	('show_linking_code', '1'),
	('album_edit_unapprove', '1'),
	('item_edit_unapprove', '1'),
	('album_columns', '1'),
	('disable_rss', '0'),
	('disable_playlists', '0'),
	('disable_comments', '0'),
	('disable_ratings', '0'),
	('my_docs', 'txt,rtf,diff,patch,pdf,xls,doc,ppt,docx,xlsx,pptx,odt,ods,odp,xml,html,htm,mht,torrent,srt,ssa,php,css,js,zip,rar,ace,arj,7z,gz,tar,tgz,bz,bzip2,sit');

#
# Table structure for table `media_variables`
#

CREATE TABLE {$db_prefix}media_variables (
	id int(10) unsigned NOT NULL auto_increment,
	type varchar(15) NOT NULL default '',
	val1 text NOT NULL,
	val2 text NOT NULL,
	val3 text NOT NULL,
	val4 text NOT NULL,
	val5 text NOT NULL,
	val6 text NOT NULL,
	val7 text NOT NULL,
	val8 text NOT NULL,
	val9 text NOT NULL,
	PRIMARY KEY (id)
) ENGINE=MyISAM;
# --------------------------------------------------------

#
# Table structure for table `membergroups`
#

CREATE TABLE {$db_prefix}membergroups (
	id_group smallint(5) NOT NULL auto_increment,
	group_name varchar(80) NOT NULL default '',
	description text NOT NULL,
	online_color varchar(20) NOT NULL default '',
	format varchar(255) NOT NULL default '',
	min_posts mediumint(9) NOT NULL default '-1',
	max_messages smallint(5) unsigned NOT NULL default 0,
	show_when tinyint(3) unsigned NOT NULL default 0,
	display_order smallint(5) unsigned NOT NULL default 0,
	stars varchar(255) NOT NULL default '',
	group_type tinyint(3) NOT NULL default 0,
	hidden tinyint(3) NOT NULL default 0,
	id_parent smallint(5) NOT NULL default '-2',
	PRIMARY KEY (id_group),
	KEY min_posts (min_posts)
) ENGINE=MyISAM;

#
# Dumping data for table `membergroups`
#

INSERT INTO {$db_prefix}membergroups
	(id_group, group_name, description, online_color, min_posts, show_when, display_order, stars, group_type)
VALUES
	(1, '{$default_administrator_group}', '', '#d2653a', -1, 1, 1, '5#rankadmin.gif', 1),
	(2, '{$default_global_moderator_group}', '', '#c18933', -1, 2, 2, '5#rankgmod.gif', 0),
	(3, '{$default_moderator_group}', '', '', -1, 3, 3, '5#rankmod.gif', 0),
	(4, '{$default_newbie_group}', '', '', 0, 2, 4, '1#rank.gif', 0),
	(5, '{$default_junior_group}', '', '', 50, 2, 5, '2#rank.gif', 0),
	(6, '{$default_full_group}', '', '', 100, 2, 6, '3#rank.gif', 0),
	(7, '{$default_senior_group}', '', '', 250, 2, 7, '4#rank.gif', 0),
	(8, '{$default_hero_group}', '', '#5e8e75', 500, 2, 8, '5#rank.gif', 0);
# --------------------------------------------------------

#
# Table structure for table `members`
#

CREATE TABLE {$db_prefix}members (
	id_member mediumint(8) unsigned NOT NULL auto_increment,
	member_name varchar(80) NOT NULL default '',
	real_name varchar(255) NOT NULL default '',
	posts mediumint(8) unsigned NOT NULL default 0,
	id_group smallint(5) NOT NULL default 0,
	id_post_group smallint(5) unsigned NOT NULL default 0,
	additional_groups varchar(255) NOT NULL default '',
	lngfile varchar(255) NOT NULL default '',
	instant_messages smallint(5) NOT NULL default 0,
	unread_messages smallint(5) NOT NULL default 0,
	new_pm tinyint(3) unsigned NOT NULL default 0,
	buddy_list text NOT NULL,
	pm_ignore_list varchar(255) NOT NULL default '',
	pm_receive_from tinyint(4) unsigned NOT NULL default 1,
	pm_email_notify tinyint(4) NOT NULL default 0,
	pm_prefs mediumint(8) NOT NULL default 0,
	mod_prefs varchar(20) NOT NULL default '',
	ignore_boards text NOT NULL,
	message_labels text NOT NULL,
	passwd varchar(64) NOT NULL default '',
	passwd_flood varchar(12) NOT NULL default '',
	password_salt varchar(255) NOT NULL default '',
	show_online tinyint(4) NOT NULL default 1,
	hide_email tinyint(4) NOT NULL default 0,
	email_address varchar(255) NOT NULL default '',
	personal_text varchar(500) NOT NULL default '',
	gender tinyint(4) unsigned NOT NULL default 0,
	birthdate date NOT NULL default '0001-01-01',
	website_title varchar(255) NOT NULL default '',
	website_url varchar(255) NOT NULL default '',
	location varchar(255) NOT NULL default '',
	signature text NOT NULL,
	time_format varchar(80) NOT NULL default '',
	time_offset float NOT NULL default 0,
	timezone varchar(50) NOT NULL default '',
	smiley_set varchar(48) NOT NULL default '',
	avatar varchar(255) NOT NULL default '',
	usertitle varchar(255) NOT NULL default '',
	notify_announcements tinyint(4) NOT NULL default 1,
	notify_regularity tinyint(4) NOT NULL default 1,
	notify_send_body tinyint(4) NOT NULL default 0,
	notify_types tinyint(4) NOT NULL default 2,
	member_ip varchar(32) NOT NULL default '',
	member_ip2 varchar(32) NOT NULL default '',
	skin varchar(255) NOT NULL default '',
	skin_mobile varchar(255) NOT NULL default '',
	is_activated tinyint(3) unsigned NOT NULL default 1,
	active_state_change int(10) unsigned NOT NULL default 0,
	validation_code varchar(10) NOT NULL default '',
	secret_question varchar(255) NOT NULL default '',
	secret_answer varchar(64) NOT NULL default '',
	last_login int(10) unsigned NOT NULL default 0,
	date_registered int(10) unsigned NOT NULL default 0,
	id_msg_last_visit int(10) unsigned NOT NULL default 0,
	total_time_logged_in int(10) unsigned NOT NULL default 0,
	warning smallint(5) NOT NULL default 0,
	media_items mediumint(8) unsigned NOT NULL default 0,
	media_comments mediumint(8) unsigned NOT NULL default 0,
	media_unseen mediumint(8) NOT NULL default '-1',
	unread_notifications smallint(5) unsigned NOT NULL default 0,
	notify_email_period tinyint(3) NOT NULL default 7,
	notify_email_last_sent int(10) NOT NULL default 0,
	data text NOT NULL,
	PRIMARY KEY (id_member),
	KEY member_name (member_name),
	KEY real_name (real_name),
	KEY date_registered (date_registered),
	KEY id_group (id_group),
	KEY birthdate (birthdate),
	KEY posts (posts),
	KEY last_login (last_login),
	KEY lngfile (lngfile(30)),
	KEY id_post_group (id_post_group),
	KEY warning (warning),
	KEY total_time_logged_in (total_time_logged_in),
	KEY skin (skin(20))
) ENGINE=MyISAM;

#
# Table structure for table `message_icons`
#

CREATE TABLE {$db_prefix}message_icons (
	id_icon smallint(5) unsigned NOT NULL auto_increment,
	title varchar(80) NOT NULL default '',
	filename varchar(80) NOT NULL default '',
	id_board mediumint(8) unsigned NOT NULL default 0,
	icon_order smallint(5) unsigned NOT NULL default 0,
	PRIMARY KEY (id_icon),
	KEY id_board (id_board)
) ENGINE=MyISAM;

#
# Dumping data for table `message_icons`
#

# // !!! i18n
INSERT INTO {$db_prefix}message_icons
	(filename, title, icon_order)
VALUES
	('xx', 'Standard', '0'),
	('thumbup', 'Thumb Up', '1'),
	('thumbdown', 'Thumb Down', '2'),
	('exclamation', 'Exclamation point', '3'),
	('question', 'Question mark', '4'),
	('lamp', 'Lamp', '5'),
	('smiley', 'Smiley', '6'),
	('angry', 'Angry', '7'),
	('cheesy', 'Cheesy', '8'),
	('grin', 'Grin', '9'),
	('sad', 'Sad', '10'),
	('wink', 'Wink', '11');
# --------------------------------------------------------

#
# Table structure for table `messages`
#

CREATE TABLE {$db_prefix}messages (
	id_msg int(10) unsigned NOT NULL auto_increment,
	id_topic mediumint(8) unsigned NOT NULL default 0,
	id_board mediumint(8) unsigned NOT NULL default 0,
	poster_time int(10) unsigned NOT NULL default 0,
	id_member mediumint(8) unsigned NOT NULL default 0,
	id_msg_modified int(10) unsigned NOT NULL default 0,
	id_parent int(10) unsigned NOT NULL default 0,
	subject varchar(255) NOT NULL default '',
	poster_name varchar(255) NOT NULL default '',
	poster_email varchar(255) NOT NULL default '',
	poster_ip int(10) NOT NULL default 0,
	smileys_enabled tinyint(4) NOT NULL default 1,
	modified_time int(10) unsigned NOT NULL default 0,
	modified_name varchar(255) NOT NULL default '',
	modified_member mediumint(8) unsigned NOT NULL default 0,
	body mediumtext NOT NULL,
	icon varchar(16) NOT NULL default 'xx',
	approved tinyint(3) NOT NULL default 1,
	data text NOT NULL,
	PRIMARY KEY (id_msg),
	UNIQUE topic (id_topic, id_msg),
	UNIQUE id_board (id_board, id_msg),
	UNIQUE id_member (id_member, id_msg),
	KEY approved (approved),
	KEY ip_index (poster_ip, id_topic),
	KEY participation (id_member, id_topic),
	KEY show_posts (id_member, id_board),
	KEY id_topic (id_topic),
	KEY id_member_msg (id_member, approved, id_msg),
	KEY current_topic (id_topic, id_msg, id_member, approved),
	KEY related_ip (id_member, poster_ip, id_msg)
) ENGINE=MyISAM;

#
# Dumping data for table `messages`
#

INSERT INTO {$db_prefix}messages
	(id_msg, id_msg_modified, id_topic, id_board, poster_time, subject, poster_name, poster_email, poster_ip, modified_name, body, icon)
VALUES (1, 1, 1, 1, UNIX_TIMESTAMP(), '{$default_topic_subject}', 'Wedge', 'dontreply@wedge.org', '0', '', '{$default_topic_message}', 'xx');
# --------------------------------------------------------

#
# Table structure for table `moderators`
#

CREATE TABLE {$db_prefix}moderators (
	id_board mediumint(8) unsigned NOT NULL default 0,
	id_member mediumint(8) unsigned NOT NULL default 0,
	PRIMARY KEY (id_board, id_member)
) ENGINE=MyISAM;

#
# Table structure for table `mod_filter_msg`
#

CREATE TABLE {$db_prefix}mod_filter_msg (
	id_rule smallint(5) unsigned NOT NULL,
	rule_type varchar(10) NOT NULL default '',
	lang varchar(255) NOT NULL default '',
	msg text NOT NULL,
	PRIMARY KEY (id_rule, rule_type, lang)
) ENGINE=MyISAM;

#
# Table structure for the table `notifications`
#
CREATE TABLE {$db_prefix}notifications (
	id_notification int(10) NOT NULL AUTO_INCREMENT,
	id_member int(10) NOT NULL default 0,
	id_member_from int(10) NOT NULL default 0,
	notifier varchar(50) NOT NULL default '',
	id_object int(10) NOT NULL default 0,
	time int(10) NOT NULL default 0,
	unread tinyint(1) unsigned NOT NULL default 0,
	data text NOT NULL,
	PRIMARY KEY (id_notification)
) ENGINE=MyISAM;

#
# Table structure for table 'notif_subs'
#
CREATE TABLE {$db_prefix}notif_subs (
	id_member int(10) NOT NULL,
	id_object int(10) NOT NULL,
	type varchar(20) NOT NULL,
	starttime int(10) NOT NULL default 0,
	PRIMARY KEY (id_member, id_object, type)
) ENGINE=MyISAM;

#
# Table structure for table `permission_profiles`
#

CREATE TABLE {$db_prefix}permission_profiles (
	id_profile smallint(5) NOT NULL auto_increment,
	profile_name varchar(255) NOT NULL default '',
	PRIMARY KEY (id_profile)
) ENGINE=MyISAM;

#
# Dumping data for table `permission_profiles`
#

INSERT INTO {$db_prefix}permission_profiles
	(id_profile, profile_name)
VALUES (1, 'default'), (2, 'no_polls'), (3, 'reply_only'), (4, 'read_only');
# --------------------------------------------------------

#
# Table structure for table `permissions`
#

CREATE TABLE {$db_prefix}permissions (
	id_group smallint(5) NOT NULL default 0,
	permission varchar(30) NOT NULL default '',
	add_deny tinyint(4) NOT NULL default 1,
	PRIMARY KEY (id_group, permission)
) ENGINE=MyISAM;

#
# Dumping data for table `permissions`
#

INSERT INTO {$db_prefix}permissions
	(id_group, permission)
VALUES
	(-1, 'search_posts'),
	(-1, 'view_stats'),
	(-1, 'profile_view_any'),
	(0, 'view_mlist'),
	(0, 'search_posts'),
	(0, 'profile_view_own'),
	(0, 'profile_view_any'),
	(0, 'pm_read'),
	(0, 'pm_send'),
	(0, 'save_pm_draft'),
	(0, 'auto_save_pm_draft'),
	(0, 'post_thought'),
	(0, 'view_stats'),
	(0, 'who_view'),
	(0, 'profile_identity_own'),
	(0, 'profile_extra_own'),
	(0, 'profile_signature_own'),
	(0, 'profile_website_own'),
	(0, 'profile_remove_own'),
	(0, 'profile_server_avatar'),
	(0, 'profile_upload_avatar'),
	(0, 'profile_remote_avatar'),
	(2, 'view_mlist'),
	(2, 'search_posts'),
	(2, 'profile_view_own'),
	(2, 'profile_view_any'),
	(2, 'pm_read'),
	(2, 'pm_send'),
	(2, 'save_pm_draft'),
	(2, 'auto_save_pm_draft'),
	(2, 'post_thought'),
	(2, 'view_stats'),
	(2, 'who_view'),
	(2, 'profile_identity_own'),
	(2, 'profile_extra_own'),
	(2, 'profile_signature_own'),
	(2, 'profile_website_own'),
	(2, 'profile_remove_own'),
	(2, 'profile_server_avatar'),
	(2, 'profile_upload_avatar'),
	(2, 'profile_remote_avatar'),
	(2, 'profile_title_own'),
	(2, 'access_mod_center');
# --------------------------------------------------------

#
# Table structure for table `personal_messages`
#

CREATE TABLE {$db_prefix}personal_messages (
	id_pm int(10) unsigned NOT NULL auto_increment,
	id_pm_head int(10) unsigned NOT NULL default 0,
	id_member_from mediumint(8) unsigned NOT NULL default 0,
	deleted_by_sender tinyint(3) unsigned NOT NULL default 0,
	from_name varchar(255) NOT NULL default '',
	msgtime int(10) unsigned NOT NULL default 0,
	subject varchar(255) NOT NULL default '',
	body text NOT NULL,
	PRIMARY KEY (id_pm),
	KEY id_member (id_member_from, deleted_by_sender),
	KEY msgtime (msgtime),
	KEY id_pm_head (id_pm_head)
) ENGINE=MyISAM;

#
# Table structure for table `plugin_servers`
#

CREATE TABLE {$db_prefix}plugin_servers (
	id_server smallint(5) unsigned NOT NULL auto_increment,
	name varchar(255) NOT NULL default '',
	url varchar(255) NOT NULL default '',
	username varchar(255) NOT NULL default '',
	password varchar(64) NOT NULL default '',
	status tinyint(3) NOT NULL default 0,
	PRIMARY KEY (id_server)
) ENGINE=MyISAM;

#
# Dumping data for table `plugin_servers`
#

INSERT INTO {$db_prefix}plugin_servers
	(name, url, status)
VALUES ('Wedge Third-party Mod Site', 'http://plugins.wedge.org', 1);
# --------------------------------------------------------

#
# Table structure for table `pm_recipients`
#

CREATE TABLE {$db_prefix}pm_recipients (
	id_pm int(10) unsigned NOT NULL default 0,
	id_member mediumint(8) unsigned NOT NULL default 0,
	labels varchar(60) NOT NULL default '-1',
	bcc tinyint(3) unsigned NOT NULL default 0,
	is_read tinyint(3) unsigned NOT NULL default 0,
	is_new tinyint(3) unsigned NOT NULL default 0,
	deleted tinyint(3) unsigned NOT NULL default 0,
	PRIMARY KEY (id_pm, id_member),
	UNIQUE id_member (id_member, deleted, id_pm)
) ENGINE=MyISAM;

#
# Table structure for table `pm_rules`
#

CREATE TABLE {$db_prefix}pm_rules (
	id_rule int(10) unsigned NOT NULL auto_increment,
	id_member mediumint(8) unsigned NOT NULL default 0,
	rule_name varchar(60) NOT NULL,
	criteria text NOT NULL,
	actions text NOT NULL,
	delete_pm tinyint(3) unsigned NOT NULL default 0,
	is_or tinyint(3) unsigned NOT NULL default 0,
	PRIMARY KEY (id_rule),
	KEY id_member (id_member),
	KEY delete_pm (delete_pm)
) ENGINE=MyISAM;

#
# Table structure for table `poll_choices`
#

CREATE TABLE {$db_prefix}poll_choices (
	id_poll mediumint(8) unsigned NOT NULL default 0,
	id_choice tinyint(3) unsigned NOT NULL default 0,
	label varchar(255) NOT NULL default '',
	votes smallint(5) unsigned NOT NULL default 0,
	PRIMARY KEY (id_poll, id_choice)
) ENGINE=MyISAM;

#
# Table structure for table `polls`
#

CREATE TABLE {$db_prefix}polls (
	id_poll mediumint(8) unsigned NOT NULL auto_increment,
	question varchar(255) NOT NULL default '',
	voting_locked tinyint(1) unsigned NOT NULL default 0,
	max_votes tinyint(3) unsigned NOT NULL default 1,
	expire_time int(10) unsigned NOT NULL default 0,
	hide_results tinyint(3) unsigned NOT NULL default 0,
	voters_visible tinyint(3) unsigned NOT NULL default 0,
	change_vote tinyint(3) unsigned NOT NULL default 0,
	guest_vote tinyint(3) unsigned NOT NULL default 0,
	num_guest_voters int(10) unsigned NOT NULL default 0,
	reset_poll int(10) unsigned NOT NULL default 0,
	id_member mediumint(8) unsigned NOT NULL default 0,
	poster_name varchar(255) NOT NULL default '',
	PRIMARY KEY (id_poll)
) ENGINE=MyISAM;

#
# Table structure for table `pretty_topic_urls`
#

CREATE TABLE {$db_prefix}pretty_topic_urls (
	id_topic mediumint(8) unsigned NOT NULL default 0,
	pretty_url varchar(80) NOT NULL,
	PRIMARY KEY (id_topic),
	KEY pretty_url (pretty_url)
) ENGINE=MyISAM;

#
# Table structure for table `pretty_urls_cache`
#

CREATE TABLE {$db_prefix}pretty_urls_cache (
	url_id varchar(255) NOT NULL,
	replacement varchar(255) NOT NULL,
	log_time timestamp NOT NULL default CURRENT_TIMESTAMP,
	PRIMARY KEY (url_id)
) ENGINE=MyISAM;

#
# Table structure for table `privacy_boards`
#

CREATE TABLE {$db_prefix}privacy_boards (
	id_board smallint(5) unsigned NOT NULL,
	privacy int(10) NOT NULL default 0,
	PRIMARY KEY (id_board, privacy)
) ENGINE=MyISAM;

#
# Table structure for table `privacy_thoughts`
#

CREATE TABLE {$db_prefix}privacy_thoughts (
	id_thought int(10) unsigned NOT NULL,
	privacy int(10) NOT NULL default 0,
	PRIMARY KEY (id_thought, privacy)
) ENGINE=MyISAM;

#
# Table structure for table `privacy_topics`
#

CREATE TABLE {$db_prefix}privacy_topics (
	id_topic mediumint(8) unsigned NOT NULL,
	privacy int(10) NOT NULL default 0,
	PRIMARY KEY (id_topic, privacy)
) ENGINE=MyISAM;

#
# Table structure for table `scheduled_imperative`
#

CREATE TABLE {$db_prefix}scheduled_imperative (
	id_instr int(10) NOT NULL auto_increment,
	instr_time int(10) NOT NULL default 0,
	instr_details text NOT NULL,
	PRIMARY KEY (id_instr),
	KEY instr_time (instr_time)
) ENGINE=MyISAM;


#
# Table structure for table `scheduled_tasks`
#

CREATE TABLE {$db_prefix}scheduled_tasks (
	id_task smallint(5) NOT NULL auto_increment,
	next_time int(10) NOT NULL default 0,
	time_offset int(10) NOT NULL default 0,
	time_regularity smallint(5) NOT NULL default 0,
	time_unit varchar(1) NOT NULL default 'h',
	disabled tinyint(3) NOT NULL default 0,
	task varchar(32) NOT NULL default '',
	sourcefile varchar(255) NOT NULL default '',
	PRIMARY KEY (id_task),
	KEY next_time (next_time),
	KEY disabled (disabled),
	UNIQUE task (task)
) ENGINE=MyISAM;

#
# Dumping data for table `scheduled_tasks`
#

INSERT INTO {$db_prefix}scheduled_tasks
	(id_task, next_time, time_offset, time_regularity, time_unit, disabled, task, sourcefile)
VALUES
	(1, 0, 0, 2, 'h', 0, 'approval_notification', ''),
	(2, 0, 0, 7, 'd', 0, 'auto_optimize', ''),
	(3, 0, 60, 1, 'd', 0, 'daily_maintenance', ''),
	(5, 0, 0, 1, 'd', 0, 'daily_digest', ''),
	(6, 0, 0, 1, 'w', 0, 'weekly_digest', ''),
	(7, 0, {$sched_task_offset}, 1, 'd', 0, 'fetchRemoteFiles', ''),
	(8, 0, 0, 1, 'w', 0, 'weekly_maintenance', ''),
	(9, 0, 120, 1, 'd', 1, 'paid_subscriptions', ''),
	(10, 0, 0, 1, 'd', 0, 'weNotif::scheduled_prune', 'Notifications'),
	(11, 0, 0, 6, 'h', 0, 'weNotif::scheduled_periodical', 'Notifications');
# --------------------------------------------------------

#
# Table structure for table `sessions`
#

CREATE TABLE {$db_prefix}sessions (
	session_id char(32) NOT NULL,
	last_update int(10) unsigned NOT NULL,
	data text NOT NULL,
	PRIMARY KEY (session_id)
) ENGINE=MyISAM;

#
# Table structure for table `settings`
#

CREATE TABLE {$db_prefix}settings (
	variable varchar(255) NOT NULL default '',
	value text NOT NULL,
	PRIMARY KEY (variable(30))
) ENGINE=MyISAM;

#
# Dumping data for table `settings`
#

INSERT INTO {$db_prefix}settings
	(variable, value)
VALUES
	('language', '{$language}'),
	('theme_url', '{$boardurl}/Themes/default'),
	('images_url', '{$boardurl}/Themes/default/images'),
	('theme_dir', '{$boarddir}/Themes/default'),
	('news', 'e{$default_news}'),
	('todayMod', '2'),
	('enablePreviousNext', '1'),
	('password_strength', '1'),
	('pollMode', '1'),
	('enableCompressedOutput', '{$enableCompressedOutput}'),
	('enableCompressedData', '{$enableCompressedData}'),
	('attachmentSizeLimit', '128'),
	('attachmentPostLimit', '192'),
	('attachmentNumPerPostLimit', '4'),
	('attachmentDirSizeLimit', '10240'),
	('attachmentUploadDir', '{$boarddir}/attachments'),
	('attachmentExtensions', 'doc,docx,gif,jpg,png,pdf,txt,rtf,diff,patch,zip,rar,7z'),
	('attachmentCheckExtensions', '0'),
	('attachmentShowImages', '1'),
	('attachmentEnable', '1'),
	('attachmentEncryptFilenames', '1'),
	('attachmentThumbnails', '1'),
	('attachmentThumbWidth', '150'),
	('attachmentThumbHeight', '150'),
	('censorIgnoreCase', '1'),
	('mostOnline', '1'),
	('mostOnlineToday', '1'),
	('mostDate', UNIX_TIMESTAMP()),
	('allow_disableAnnounce', '1'),
	('trackStats', '1'),
	('userLanguage', '1'),
	('titlesEnable', '1'),
	('topicSummaryPosts', '15'),
	('enableErrorLogging', '1'),
	('enableErrorPasswordLogging', '1'),
	('max_image_width', '0'),
	('max_image_height', '0'),
	('smtp_host', ''),
	('smtp_port', '25'),
	('smtp_username', ''),
	('smtp_password', ''),
	('mail_type', '0'),
	('timeLoadPageEnable', '0'),
	('totalMembers', '0'),
	('totalTopics', '1'),
	('totalMessages', '1'),
	('censor_vulgar', ''),
	('censor_proper', ''),
	('allow_no_censored', '0'),
	('enablePostHTML', '0'),
	('theme_allow', '1'),
	('theme_skin_guests', 'Wilde'),
	('theme_skin_guests_mobile', 'Wilderless'),
	('enableEmbeddedFlash', '0'),
	('xmlnews_enable', '1'),
	('xmlnews_maxlen', '255'),
	('xmlnews_sidebar', '1'),
	('registration_method', '0'),
	('send_validation_onChange', '0'),
	('send_welcomeEmail', '1'),
	('allow_editDisplayName', '1'),
	('allow_hideOnline', '1'),
	('spamWaitTime', '5'),
	('pm_spam_settings', '10,5,20'),
	('autoLinkUrls', '1'),
	('banLastUpdated', '0'),
	('smileys_dir', '{$boarddir}/Smileys'),
	('smileys_url', '{$boardurl}/Smileys'),
	('avatar_directory', '{$boarddir}/avatars'),
	('avatar_url', '{$boardurl}/avatars'),
	('avatar_max_height_external', '65'),
	('avatar_max_width_external', '65'),
	('avatar_action_too_large', 'option_html_resize'),
	('avatar_max_height_upload', '65'),
	('avatar_max_width_upload', '65'),
	('avatar_resize_upload', '1'),
	('avatar_download_png', '1'),
	('failed_login_threshold', '3'),
	('enable_quick_login', '1'),
	('oldTopicDays', '120'),
	('edit_wait_time', '90'),
	('edit_disable_time', '0'),
	('autoFixDatabase', '1'),
	('allow_guestAccess', '1'),
	('enableBBC', '1'),
	('editorSizes', '6pt\n8pt\n10pt\n12pt\n14pt\n18pt\n24pt'),
	('editorFonts', 'Courier New\nArial\nImpact\nVerdana\nTimes New Roman\nGeorgia\nTrebuchet MS\nSegoe UI'),
	('max_messageLength', '20000'),
	('signature_settings', '1,300,0,0,0,0,0,0:'),
	('autoOptMaxOnline', '0'),
	('defaultMaxMessages', '15'),
	('defaultMaxTopics', '20'),
	('defaultMaxMembers', '30'),
	('enableParticipation', '1'),
	('recycle_enable', '1'),
	('recycle_board', '2'),
	('maxMsgID', '1'),
	('enableAllMessages', '0'),
	('knownThemes', '1,2,3'),
	('who_enabled', '1'),
	('display_who_viewing', '2'),
	('time_offset', '0'),
	('cookieTime', '60'),
	('lastActive', '15'),
	('smiley_sets_known', 'default,aaron'),
	('smiley_sets_names', '{$default_smileyset_name}\n{$default_aaron_smileyset_name}'),
	('smiley_sets_default', 'default'),
	('requireAgreement', '1'),
	('unapprovedMembers', '0'),
	('databaseSession_enable', '{$databaseSession_enable}'),
	('databaseSession_loose', '1'),
	('databaseSession_lifetime', '2880'),
	('search_cache_size', '50'),
	('search_results_per_page', '30'),
	('search_weight_frequency', '30'),
	('search_weight_age', '25'),
	('search_weight_length', '20'),
	('search_weight_subject', '15'),
	('search_weight_first_message', '10'),
	('search_max_results', '1200'),
	('search_floodcontrol_time', '5'),
	('permission_enable_deny', '0'),
	('permission_enable_postgroups', '0'),
	('group_text_show', 'cond'),
	('mail_next_send', '0'),
	('mail_recent', '0000000000|0'),
	('settings_updated', '0'),
	('next_task_time', '1'),
	('warning_settings', '20,0'),
	('warning_watch', '10'),
	('warning_moderate', '35'),
	('warning_mute', '60'),
	('embed_enabled', '1'),
	('media_enabled', '1'),
	('log_enabled_moderate', '1'),
	('log_enabled_admin', '1'),
	('last_mod_report_action', '0'),
	('pruningOptions', '30,180,180,30,0'),
	('cache_enable', '1'),
	('reg_verification', '1'),
	('use_captcha_images', '1'),
	('use_animated_captcha', '1'),
	('enable_buddylist', '1'),
	('attachment_image_reencode', '1'),
	('attachment_image_paranoid', '0'),
	('attachment_thumb_png', '1'),
	('avatar_reencode', '1'),
	('avatar_paranoid', '0'),
	('minify', 'packer'),
	('jquery_origin', 'google'),
	('masterSavePostDrafts', '1'),
	('masterAutoSavePostDrafts', '1'),
	('masterSavePmDrafts', '1'),
	('masterAutoSavePmDrafts', '1'),
	('masterAutoSaveDraftsDelay', '30'),
	('pruneSaveDrafts', '7'),
	('gravatarEnabled', '1'),
	('gravatarOverride', '0'),
	('gravatarAllowExtraEmail', '1'),
	('gravatarMaxRating', 'PG'),
	('display_flags', 'specified'),
	('reverse_proxy_header', 'X-Forwarded-For'),
	('pretty_enable_filters', '0'),
	('pretty_prefix_profile', 'profile/'),
	('pretty_prefix_action', 'do/'),
	('embed_lookups', '1'),
	('embed_max_per_post', '12'),
	('embed_max_per_page', '12'),
	('embed_yq', '0'),
	('embed_titles', '0'),
	('embed_inlinetitles', '1'),
	('embed_noscript', '0'),
	('embed_expins', '1'),
	('embed_quotes', '0'),
	('embed_incontext', '0'),
	('embed_fix_html', '1'),
	('embed_includeurl', '1'),
	('embed_debug', '0'),
	('embed_adult', '0'),
	('embed_nonlocal', '0'),
	('embed_mp3', '0'),
	('embed_flv', '0'),
	('embed_avi', '0'),
	('embed_divx', '0'),
	('embed_mov', '0'),
	('embed_wmp', '0'),
	('embed_real', '0'),
	('embed_swf', '0'),
	('disableTemplateEval', '1'),
	('default_index', 'Welcome'),
	('pm_enabled', '1'),
	('enable_news', '1'),
	('show_newsfader', '0'),
	('newsfader_time', '5000'),
	('show_stats_index', '1'),
	('show_latest_member', '1'),
	('show_blurb', '1'),
	('show_gender', '1'),
	('additional_options_collapsable', '1'),
	('likes_enable', '1'),
	('notifications_prune_days', '7'),
	('infraction_settings', 'a:3:{s:17:"revoke_own_issued";b:1;s:17:"revoke_any_issued";a:1:{i:0;i:1;}s:14:"no_warn_groups";a:1:{i:0;i:1;}}'),
	('infraction_levels', 'a:8:{s:9:"no_avatar";a:2:{s:6:"points";i:20;s:7:"enabled";b:0;}s:6:"no_sig";a:2:{s:6:"points";i:20;s:7:"enabled";b:0;}s:10:"disemvowel";a:2:{s:6:"points";i:40;s:7:"enabled";b:1;}s:8:"moderate";a:2:{s:6:"points";i:45;s:7:"enabled";b:1;}s:8:"post_ban";a:2:{s:6:"points";i:70;s:7:"enabled";b:1;}s:6:"pm_ban";a:2:{s:6:"points";i:80;s:7:"enabled";b:1;}s:8:"soft_ban";a:2:{s:6:"points";i:100;s:7:"enabled";b:1;}s:8:"hard_ban";a:2:{s:6:"points";i:150;s:7:"enabled";b:1;}}');
# --------------------------------------------------------

#
# Table structure for table `smileys`
#

CREATE TABLE {$db_prefix}smileys (
	id_smiley smallint(5) unsigned NOT NULL auto_increment,
	code varchar(30) NOT NULL default '',
	filename varchar(48) NOT NULL default '',
	description varchar(80) NOT NULL default '',
	smiley_row tinyint(4) unsigned NOT NULL default 0,
	smiley_order smallint(5) unsigned NOT NULL default 0,
	hidden tinyint(4) unsigned NOT NULL default 0,
	PRIMARY KEY (id_smiley)
) ENGINE=MyISAM;

#
# Dumping data for table `smileys`
#

INSERT INTO {$db_prefix}smileys
	(code, filename, description, smiley_order, hidden)
VALUES
	(':)', 'smiley.gif', '{$default_smiley_smiley}', 0, 0),
	(';)', 'wink.gif', '{$default_wink_smiley}', 1, 0),
	(':D', 'cheesy.gif', '{$default_cheesy_smiley}', 2, 0),
	(';D', 'grin.gif', '{$default_grin_smiley}', 3, 0),
	('>:(', 'angry.gif', '{$default_angry_smiley}', 4, 0),
	(':(', 'sad.gif', '{$default_sad_smiley}', 5, 0),
	(':o', 'shocked.gif', '{$default_shocked_smiley}', 6, 0),
	('8)', 'cool.gif', '{$default_cool_smiley}', 7, 0),
	('???', 'huh.gif', '{$default_huh_smiley}', 8, 0),
	('::)', 'rolleyes.gif', '{$default_roll_eyes_smiley}', 9, 0),
	(':P', 'tongue.gif', '{$default_tongue_smiley}', 10, 0),
	(':-[', 'embarrassed.gif', '{$default_embarrassed_smiley}', 11, 0),
	(':-X', 'lipsrsealed.gif', '{$default_lips_sealed_smiley}', 12, 0),
	(':-\\', 'undecided.gif', '{$default_undecided_smiley}', 13, 0),
	(':-*', 'kiss.gif', '{$default_kiss_smiley}', 14, 0),
	(':\'(', 'cry.gif', '{$default_cry_smiley}', 15, 0),
	('>:D', 'evil.gif', '{$default_evil_smiley}', 16, 2),
	('^-^', 'azn.gif', '{$default_azn_smiley}', 17, 2),
	('O0', 'afro.gif', '{$default_afro_smiley}', 18, 2),
	(':))', 'laugh.gif', '{$default_laugh_smiley}', 19, 2),
	('C:-)', 'police.gif', '{$default_police_smiley}', 20, 2),
	('O:-)', 'angel.gif', '{$default_angel_smiley}', 21, 2),
	(':edit:', 'edit.gif', '{$default_edit_smiley}', 22, 2);
# --------------------------------------------------------

#
# Table structure for table `spiders`
#

CREATE TABLE {$db_prefix}spiders (
	id_spider smallint(5) unsigned NOT NULL auto_increment,
	spider_name varchar(255) NOT NULL default '',
	user_agent varchar(255) NOT NULL default '',
	ip_info varchar(255) NOT NULL default '',
	PRIMARY KEY id_spider(id_spider)
) ENGINE=MyISAM;

#
# Dumping data for table `spiders`
#

INSERT INTO {$db_prefix}spiders
	(id_spider, spider_name, user_agent, ip_info)
VALUES
	(1, 'Google', 'googlebot', ''),
	(2, 'Yahoo!', 'slurp', ''),
	(3, 'Bing', 'msnbot', ''),
	(4, 'Google (Mobile)', 'Googlebot-Mobile', ''),
	(5, 'Google (Image)', 'Googlebot-Image', ''),
	(6, 'Google (AdSense)', 'Mediapartners-Google', ''),
	(7, 'Google (Adwords)', 'AdsBot-Google', ''),
	(8, 'Yahoo! (Mobile)', 'YahooSeeker/M1A1-R2D2', ''),
	(9, 'Yahoo! (Image)', 'Yahoo-MMCrawler', ''),
	(10, 'Bing (Mobile)', 'MSNBOT_Mobile', ''),
	(11, 'Bing (Media)', 'msnbot-media', ''),
	(12, 'InternetArchive', 'ia_archiver-web.archive.org', ''),
	(13, 'Ask', 'Teoma', ''),
	(14, 'Baidu', 'Baiduspider', ''),
	(15, 'Gigablast', 'Gigabot', ''),
	(16, 'Alexa', 'ia_archiver', ''),
	(17, 'Omgili', 'omgilibot', ''),
	(18, 'EntireWeb', 'Speedy Spider', ''),
	(19, 'Yandex', 'Yandex', ''),
	(20, 'UptimeRobot', 'UptimeRobot', '');
# --------------------------------------------------------

#
# Table structure for table `subscriptions`
#

CREATE TABLE {$db_prefix}subscriptions (
	id_subscribe mediumint(8) unsigned NOT NULL auto_increment,
	name varchar(60) NOT NULL default '',
	description varchar(255) NOT NULL default '',
	cost text NOT NULL,
	length varchar(6) NOT NULL default '',
	id_group smallint(5) NOT NULL default 0,
	add_groups varchar(40) NOT NULL default '',
	active tinyint(3) NOT NULL default 1,
	repeatable tinyint(3) NOT NULL default 0,
	allow_partial tinyint(3) NOT NULL default 0,
	reminder tinyint(3) NOT NULL default 0,
	email_complete text NOT NULL,
	PRIMARY KEY (id_subscribe),
	KEY active (active)
) ENGINE=MyISAM;

#
# Table structure for table `subscriptions_groups`
#

CREATE TABLE {$db_prefix}subscriptions_groups (
	id_subscribe mediumint(8) unsigned NOT NULL default 0,
	id_group smallint(5) NOT NULL default 0,
	PRIMARY KEY (id_subscribe, id_group)
) ENGINE=MyISAM;

#
# Table structure for table `themes`
#

CREATE TABLE {$db_prefix}themes (
	id_member mediumint(8) NOT NULL default 0,
	variable varchar(255) NOT NULL default '',
	value text NOT NULL,
	PRIMARY KEY (id_member, variable(30)),
	KEY id_member (id_member)
) ENGINE=MyISAM;

#
# Dumping data for table `themes`
# Default options for guests and new members.
# Guests ignore PM-related options, of course.
#

INSERT INTO {$db_prefix}themes
	(id_member, variable, value)
VALUES
	(-1, 'display_quick_reply', '2'),
	(-1, 'posts_apply_ignore_list', '1'),
	(-1, 'return_to_post', '1'),
	(-1, 'view_newest_pm_first', '1'),
	(-1, 'pm_remove_inbox_label', '1');
# --------------------------------------------------------

#
# Table structure for table `thoughts`
#

CREATE TABLE {$db_prefix}thoughts (
	id_thought int(10) unsigned NOT NULL auto_increment,
	id_parent int(10) unsigned NOT NULL default 0,
	id_master int(10) unsigned NOT NULL default 0,
	id_member mediumint(8) unsigned NOT NULL default 0,
	updated int(10) unsigned NOT NULL default 0,
	thought varchar(2048) NOT NULL default '',
	privacy int(10) NOT NULL default 0,
	PRIMARY KEY (id_thought, privacy),
	KEY mup (id_member, updated)
) ENGINE=MyISAM;

#
# Table structure for table `topics`
#

CREATE TABLE {$db_prefix}topics (
	id_topic mediumint(8) unsigned NOT NULL auto_increment,
	is_pinned tinyint(4) NOT NULL default 0,
	id_board mediumint(8) unsigned NOT NULL default 0,
	id_first_msg int(10) unsigned NOT NULL default 0,
	id_last_msg int(10) unsigned NOT NULL default 0,
	id_member_started mediumint(8) unsigned NOT NULL default 0,
	id_member_updated mediumint(8) unsigned NOT NULL default 0,
	id_poll mediumint(8) unsigned NOT NULL default 0,
	id_media int(10) unsigned NOT NULL default 0,
	id_previous_board smallint(5) NOT NULL default 0,
	id_previous_topic mediumint(8) NOT NULL default 0,
	num_replies int(10) unsigned NOT NULL default 0,
	num_views int(10) unsigned NOT NULL default 0,
	locked tinyint(4) NOT NULL default 0,
	unapproved_posts smallint(5) NOT NULL default 0,
	approved tinyint(3) NOT NULL default 1,
	privacy int(10) NOT NULL default 0,
	PRIMARY KEY (id_topic, privacy),
	UNIQUE last_message (id_last_msg, id_board),
	UNIQUE first_message (id_first_msg, id_board),
	UNIQUE poll (id_poll, id_topic),
	KEY is_pinned (is_pinned),
	KEY approved (approved),
	KEY id_board (id_board),
	KEY member_started (id_member_started, id_board),
	KEY last_message_pinned (id_board, is_pinned, id_last_msg),
	KEY board_news (id_board, id_first_msg)
) ENGINE=MyISAM;

#
# Dumping data for table `topics`
#

INSERT INTO {$db_prefix}topics
	(id_topic, id_board, id_first_msg, id_last_msg, id_member_started, id_member_updated)
VALUES (1, 1, 1, 1, 0, 0);
# --------------------------------------------------------
