<?php
/**
 * Find files in a directory by regular expression.
 * Returns name, type, size, modification time, and hash of file.
 *
 * The relative file path uses the leading slash to aid with regex matching
 * and is then removed in the final result.
 *
 * Requires these variables to be set:
 *
 * @param $path             string    Absolute path to directory, without trailing slash (/).
 * @param $plength          int       Character count of $path.
 * @param $regexIgnore      string    Regex pattern of file names to ignore.
 * @param $regexNoHash      string    Regex pattern of file names not to hash.
 * @param $hashName         int       Character count of $path.
 *
 * @sets  $rtval    array    Array of name/value pairs (associative array) with info about each file.
 */

ini_set("memory_limit", "512M");

$rtval = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)) as $f) {
	$fn = $f->getPathname();        //	full path name
	$rn = substr($fn, $plength);    //	Leaves relative path name with leading slash.
	$t  = $f->getType()[0];            //	file type (d, f, or l)
	if (preg_match($regexIgnore, $rn) === 0) {
		$fname = ($t === "d") ? substr($rn, 1, -2) : substr($rn, 1);
		$rtval[$fname] = [
			"ftype"   => $t,
			"sizeb"   => ($t === "f") ? $f->getSize() : null,
			"modts"   => $f->getMTime(),
			"hashval" => ($t === "f" && preg_match($regexNoHash, $rn) === 0) ?
				hash_file($hashAlgo, $fn) : "",
		];
	}
}