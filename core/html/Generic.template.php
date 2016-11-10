<?php
/**
 * Shows a generic template called through loadTemplate(function () {});
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * License: http://wedge.org/license/
 */

function template_main()
{
	global $context;

	if (isset($context['closure']) && is_callable($context['closure']))
		call_user_func($context['closure']);
}
