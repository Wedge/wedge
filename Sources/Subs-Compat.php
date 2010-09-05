<?php
/**********************************************************************************
* Subs-Compat.php                                                                 *
***********************************************************************************
* SMF: Simple Machines Forum                                                      *
* Open-Source Project Inspired by Zef Hemel (zef@zefhemel.com)                    *
* =============================================================================== *
* Software Version:           SMF 2.0 RC3                                         *
* Software by:                Simple Machines (http://www.simplemachines.org)     *
* Copyright 2006-2010 by:     Simple Machines LLC (http://www.simplemachines.org) *
*           2001-2006 by:     Lewis Media (http://www.lewismedia.com)             *
* Support, News, Updates at:  http://www.simplemachines.org                       *
***********************************************************************************
* This program is free software; you may redistribute it and/or modify it under   *
* the terms of the provided license as published by Simple Machines LLC.          *
*                                                                                 *
* This program is distributed in the hope that it is and will be useful, but      *
* WITHOUT ANY WARRANTIES; without even any implied warranty of MERCHANTABILITY    *
* or FITNESS FOR A PARTICULAR PURPOSE.                                            *
*                                                                                 *
* See the "license.txt" file for details of the Simple Machines license.          *
* The latest version can always be found at http://www.simplemachines.org.        *
**********************************************************************************/

if (!defined('SMF'))
	die('Hacking attempt...');

/*
	This file provides a fix for the sha1 function.
	It is also intended to feature replacement functions for older versions of PHP,
	but as of now, none are needed, as they all work under the required PHP 5.
*/

// Define the old SMF sha1 function.
function sha1_smf($str)
{
	// If we have mhash loaded in, use it instead!
	if (function_exists('mhash') && defined('MHASH_SHA1'))
		return bin2hex(mhash(MHASH_SHA1, $str));

	$nblk = (strlen($str) + 8 >> 6) + 1;
	$blks = array_pad(array(), $nblk * 16, 0);

	for ($i = 0; $i < strlen($str); $i++)
		$blks[$i >> 2] |= ord($str{$i}) << (24 - ($i % 4) * 8);

	$blks[$i >> 2] |= 0x80 << (24 - ($i % 4) * 8);

	return sha1_core($blks, strlen($str) * 8);
}

// This is the core SHA-1 calculation routine, used by sha1().
function sha1_core($x, $len)
{
	@$x[$len >> 5] |= 0x80 << (24 - $len % 32);
	$x[(($len + 64 >> 9) << 4) + 15] = $len;

	$w = array();
	$a = 1732584193;
	$b = -271733879;
	$c = -1732584194;
	$d = 271733878;
	$e = -1009589776;

	for ($i = 0, $n = count($x); $i < $n; $i += 16)
	{
		$olda = $a;
		$oldb = $b;
		$oldc = $c;
		$oldd = $d;
		$olde = $e;

		for ($j = 0; $j < 80; $j++)
		{
			if ($j < 16)
				$w[$j] = isset($x[$i + $j]) ? $x[$i + $j] : 0;
			else
				$w[$j] = sha1_rol($w[$j - 3] ^ $w[$j - 8] ^ $w[$j - 14] ^ $w[$j - 16], 1);

			$t = sha1_rol($a, 5) + sha1_ft($j, $b, $c, $d) + $e + $w[$j] + sha1_kt($j);
			$e = $d;
			$d = $c;
			$c = sha1_rol($b, 30);
			$b = $a;
			$a = $t;
		}

		$a += $olda;
		$b += $oldb;
		$c += $oldc;
		$d += $oldd;
		$e += $olde;
	}

	return sprintf('%08x%08x%08x%08x%08x', $a, $b, $c, $d, $e);
}

function sha1_ft($t, $b, $c, $d)
{
	if ($t < 20)
		return ($b & $c) | ((~$b) & $d);
	if ($t < 40)
		return $b ^ $c ^ $d;
	if ($t < 60)
		return ($b & $c) | ($b & $d) | ($c & $d);

	return $b ^ $c ^ $d;
}

function sha1_kt($t)
{
	return $t < 20 ? 1518500249 : ($t < 40 ? 1859775393 : ($t < 60 ? -1894007588 : -899497514));
}

function sha1_rol($num, $cnt)
{
	// Unfortunately, PHP uses unsigned 32-bit longs only.  So we have to kludge it a bit.
	if ($num & 0x80000000)
		$a = ($num >> 1 & 0x7fffffff) >> (31 - $cnt);
	else
		$a = $num >> (32 - $cnt);

	return ($num << $cnt) | $a;
}

?>