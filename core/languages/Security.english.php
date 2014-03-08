<?php

$txt['http_error'] = 'HTTP Error';

// Titles for the errors
$txt['behav_400'] = 'An invalid request was received!';
$txt['behav_403'] = 'You do not have permission to access this server.';
$txt['behav_417'] = 'Expectation Failed.';

$txt['behavior_admin'] = 'Security Error';
$txt['behavior_header'] = 'We apologize for the inconvenience, but we are unable to complete your request; as part of protection against spreading bad things like spam and malware, the defenses of this site found potentially suspicious behavior that matched that of known spammers and/or malware distributors. Fortunately the problem is normally quite easy to fix.<br><br>The information for this was supplied by <a href="http://bad-behavior.ioerror.us/">Bad Behavior</a>.';

$txt['behavior_false_ua'] = 'Every page request your browser sends, it should be sending a User-Agent - a string to identify itself to the server. The user-agent supplied in this request was false; if you reset it to what the browser is supposed to send, you should be able to access this site.';
$txt['behavior_misconfigured_proxy'] = 'This problem is often associated with proxy servers and systems that are not set up properly. Normally you will need to uninstall the software, since invariably just disabling it is not enough. If this is not an option, please contact the server administrator.';
$txt['behavior_misconfigured_privacy'] = 'This problem can be happen when there is browser privacy software or personal firewall software that was not set-up properly, or has bugs in it. If you are using something like this, you need to either turn it off or change its settings before trying again. (Example: If you use Norton Internet Security, it has a Stealth Mode Browsing feature that interferes with proper Internet operation.)';
$txt['behavior_malware'] = 'This problem can be caused by viruses or spyware on your computer, or by malicious software that pretends to be anti-virus or anti-spyware software. Ensure that you have REAL anti-virus and anti-spyware software on your computer, that they are kept up-to-date, and that you have run a full system scan using each tool. Once your system is cleaned of viruses and spyware, please try your request again.<br><br>As a first measure, <a href="http://www.microsoft.com/security_essentials/">Microsoft Security Essentials</a> can be a useful free addition if you do not have such tools installed or available, but it is no by means complete.';
$txt['behavior_opera_bug'] = 'Some older versions of the Opera browser have been known to show this behavior; please update to the current version.';
$txt['behavior_chrome_bug'] = 'Some versions of Chrome have a design flaw that can trigger this. One workaround appears to be to clear the browsing history, then reload the page.';

$txt['behavior_footer'] = 'If the above steps do not help, you can contact the webmaster via email: {email_address} and refer to incident number {incident}.';
// Begin the rules as defined in Security.php
// Each rule has a _desc (the text to use, complete with <br> as appropriate) and _log (the text to display in the admin log)

$txt['behav_blacklist_desc'] = 'You do not have permission to access this server.';
$txt['behav_blacklist_log'] = 'Blacklisted user-agent provided';

$txt['behav_not_cloudflare_desc'] = 'You do not have permission to access this server.';
$txt['behav_not_cloudflare_log'] = 'User-agent identified itself as CloudFlare, cannot substantiate claim.';

$txt['behav_expect_header_desc'] = 'The request contained an expectation, unfortunately this could not be managed - please retry your request. This is mostly known to be associated with software that has been banned from accessing this site due to observed malicious activity. You are advised to uninstall it and contact the author; in the meantime a conventional browser should be suitable, such as Firefox, Chrome, Opera, Internet Explorer or Safari.';
$txt['behav_expect_header_log'] = 'Request contained \'Expect\' header; asked for resend.';

$txt['behav_no_ua_in_post_desc'] = 'Your browser sent a request for the page that was not valid.';
$txt['behav_no_ua_in_post_log'] = 'User-agent did not identify itself, required when posting.';

$txt['behav_content_range_desc'] = 'Your browser sent a request for the page that was not valid.';
$txt['behav_content_range_log'] = 'Request contained \'Range\' or \'Content-Range\', should not contain these.';

$txt['behav_empty_refer_desc'] = 'Your browser sent a request for the page that was not valid.';
$txt['behav_empty_refer_log'] = 'Request specified \'Referer\' but Referer was blank.';

$txt['behav_invalid_refer_desc'] = 'Your browser sent a request for the page that was not valid.';
$txt['behav_invalid_refer_log'] = 'Request specified \'Referer\' but Referer appeared corrupt.';

$txt['behav_alive_close_desc'] = 'Your browser sent a request for the page that was not valid.';
$txt['behav_alive_close_log'] = 'Request specified \'Connection\' but contained invalid values.';

$txt['behav_wrong_keep_alive_desc'] = 'Your browser sent a request for the page that was not valid.';
$txt['behav_wrong_keep_alive_log'] = 'Request specified \'Keep-Alive\' but form corrupted.';

$txt['behav_rogue_chars_desc'] = 'You do not have permission to access this server. Malicious information was found in the request.';
$txt['behav_rogue_chars_log'] = 'Malicious code found in request.';

$txt['behav_invalid_via_desc'] = 'You appear to be using a proxy server that is not permitted here.';
$txt['behav_invalid_via_log'] = 'Request contained invalid \'via\' header.';

$txt['behav_banned_via_proxy_desc'] = 'You appear to be using a proxy server that is not permitted here.';
$txt['behav_banned_via_proxy_log'] = 'Request from banned proxy server.';

$txt['behav_banned_xaa_proxy_desc'] = 'You appear to be using a proxy server that is not permitted here.';
$txt['behav_banned_xaa_proxy_log'] = 'Invalid headers \'X-Aaaaaaaaaa\' or \'X-Aaaaaaaaaaaa\' found.';

$txt['behav_bot_rfc2965_desc'] = 'The cookies received by the server were not valid. Please update your software, or contact the software author/publisher regarding it not conforming to current Internet standards (specifically, RFC 2965)';
$txt['behav_bot_rfc2965_log'] = 'Bot not compliant with RFC 2965.';

$txt['behav_proxy_connection_desc'] = 'Your browser sent a request for the page that was not valid.';
$txt['behav_proxy_connection_log'] = 'Request contained prohibited header \'Proxy-Connection\'.';

$txt['behav_pragma_desc'] = 'Your browser sent a request for the page that was not valid.';
$txt['behav_pragma_log'] = 'Header \'Pragma\' without \'Cache-Control\' prohibited for HTTP/1.1 requests.';

$txt['behav_te_error_desc'] = 'Your browser sent a request for the page that was not valid.';
$txt['behav_te_error_log'] = 'Header \'TE\' present but TE not specified in \'Connection\' header.';

$txt['behav_invalid_range_desc'] = 'The program you are using to access this server is not permitted. Please use a different program, ideally a standard web browser such as Firefox, Internet Explorer, Opera, Safari or Chrome.';
$txt['behav_invalid_range_log'] = 'Request contained prohibited header \'Range\'.';

$txt['behav_no_accept_desc'] = 'Your browser sent a request for the page that was not valid.';
$txt['behav_no_accept_log'] = 'Request did not contain \'Accept\' header.';

$txt['behav_invalid_win_desc'] = 'Your request was denied; the details of your browser are invalid.';
$txt['behav_invalid_win_log'] = 'User-agent claimed to be IE but with forged header.';

$txt['behav_te_not_msie_desc'] = 'Your request was invalid; if you are using the Opera browser, then it should be identifying itself as Opera rather than pretending to be something else.';
$txt['behav_te_not_msie_log'] = 'User-agent claims to be IE, but Connection: TE present, not used by MSIE.';

$txt['behav_not_msnbot_desc'] = 'Your request was invalid; it claimed that you are associated with a major search engine but this claim could not be verified.';
$txt['behav_not_msnbot_log'] = 'User-agent identified itself as msnbot, cannot substantiate claim.';

$txt['behav_not_yahoobot_desc'] = 'Your request was invalid; it claimed that you are associated with a major search engine but this claim could not be verified.';
$txt['behav_not_yahoobot_log'] = 'User-agent identified itself as Yahoo, cannot substantiate claim.';

$txt['behav_not_googlebot_desc'] = 'Your request was invalid; it claimed that you are associated with a major search engine but this claim could not be verified.';
$txt['behav_not_googlebot_log'] = 'User-agent identified itself as Google, cannot substantiate claim.';

$txt['behav_not_baidu_desc'] = 'Your request was invalid; it claimed that you are associated with a major search engine but this claim could not be verified.';
$txt['behav_not_baidu_log'] = 'User-agent identified itself as Baidu, cannot substantiate claim.';

$txt['behav_offsite_form_desc'] = 'Data was submitted to this site from outside the site; this is not permitted.';
$txt['behav_offsite_form_log'] = 'Form appeared to be sent from outside the site.';
