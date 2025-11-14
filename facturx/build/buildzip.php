<?php
/**
 * buildzip.php
 *
 * Copyright (c) 2023 Eric Seigne <eric.seigne@cap-rel.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

// ============================================= configuration

/**
 * list of files & dirs of your module
 *
 * @var array
 */
$list = [
	'admin',
	'backport',
	'class',
	'COPYING',
	'core',
	'img',
	'langs',
	'lib',
	'vendor',
	'*.md',
	'*.json',
	'*.lock',
	'*.php',
	'modulebuilder.txt',
];

/**
 * if you want to exclude some files from your zip
 *
 * @var array
 */
$exclude_list = [
	'/^.git$/',
	'/.*js.map/'
];

// ============================================= end of configuration


/**
 * auto detect module name and version from file name
 *
 * @return  [type]  [return description]
 */
function detectModule()
{
	$tab = glob("core/modules/mod*.class.php");
	if (count($tab) == 1) {
		$file = $tab[0];
		$mod  = "";
		$pattern = "/.*mod(?<mod>.*)\.class\.php/";
		if (preg_match_all($pattern, $file, $matches)) {
			$mod = strtolower(reset($matches['mod']));
		}

		echo "extract data from $file\n";
		if (!file_exists($file) || $mod == "") {
			echo "Erreur de détection du fichier et/ou du code du module ...";
			exit -1;
		}
	} else {
		echo "Erreur il semblerait qu'il y ait plusieurs fichiers mod* dans le répertoire ...";
		exit -1;
	}

	$contents = file_get_contents($file);
	$pattern = "/^.*this->version\s*=\s*'(?<version>.*)'\s*;.*\$/m";

	// search, and store all matching occurences in $matches
	$version = '';
	if (preg_match_all($pattern, $contents, $matches)) {
		$version = reset($matches['version']);
	}

	echo "module name = $mod, version = $version\n";
	return [$mod, $version];
}

/**
 * delete a directory
 *
 * @param   string  $dir  [$dir description]
 *
 * @return  boolean        [return description]
 */
function delTree($dir)
{
	$files = array_diff(scandir($dir), array('.','..'));
	foreach ($files as $file) {
		(is_dir("$dir/$file")) ? delTree("$dir/$file") : unlink("$dir/$file");
	}
	return rmdir($dir);
}

/**
 * check if that filename is concerned by exclude filter
 *
 * @param   string  $filename  [$filename description]
 *
 * @return  boolean             [return description]
 */
function is_excluded($filename)
{
	global $exclude_list;
	$count = 0;
	preg_filter($exclude_list, '1', $filename, -1, $count);
	if ($count > 0) {
		echo " - exclude $filename\n";
		return true;
	}
	return false;
}

/**
 * recursive copy files & dirs
 *
 * @param   string  $src  [$src description]
 * @param   string  $dst  [$dst description]
 *
 */
function rcopy($src, $dst)
{
	if (is_dir($src)) {
		// Make the destination directory if not exist
		@mkdir($dst);
		// open the source directory
		$dir = opendir($src);

		// Loop through the files in source directory
		while ($file = readdir($dir)) {
			if (($file != '.') && ($file != '..')) {
				if (!is_excluded($file)) {
					if (is_dir($src . '/' . $file)) {
						// Recursively calling custom copy function
						// for sub directory
						rcopy($src . '/' . $file, $dst . '/' . $file);
					} else {
						copy($src . '/' . $file, $dst . '/' . $file);
					}
				}
			}
		}
		closedir($dir);
	} elseif (is_file($src)) {
		if (!is_excluded($src)) {
			copy($src, $dst);
		}
	}
}

/**
 * build a zip file with only php code
 */
function zipDir($folder, &$zip, $root = "")
{
	foreach (new \DirectoryIterator($folder) as $f) {
		if ($f->isDot()) {
			continue;
		} //skip . ..
		$src = $folder . '/' . $f;
		$dst = substr($f->getPathname(), strlen($root));
		if ($f->isDir()) {
			$zip->addEmptyDir($dst);
			zipDir($src, $zip, $root);
			continue;
		}
		if ($f->isFile()) {
			$zip->addFile($src, $dst);
		}
	}
}

list($mod, $version) = detectModule();
$outzip = sys_get_temp_dir() . "/module_" . $mod . "-" . $version . ".zip";
$tmpdir = tempnam(sys_get_temp_dir(), $mod . "-module");
unlink($tmpdir);
mkdir($tmpdir);
$dst = $tmpdir . "/" . $mod;
mkdir($dst);

foreach ($list as $l) {
	foreach (glob($l) as $entry) {
		rcopy($entry, $dst . '/' . $entry);
	}
}

if (file_exists($outzip)) {
	unlink($outzip);
}

$z = new ZipArchive();
$z->open($outzip, ZIPARCHIVE::CREATE);
zipDir($tmpdir, $z, $tmpdir . '/');
$z->close();

delTree($tmpdir);

if (file_exists($outzip)) {
	echo "module archive is ready : $outzip ...\n";
} else {
	echo "build zip error\n";
	exit -3;
}
