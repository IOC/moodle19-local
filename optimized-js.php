<?php

require_once(dirname(__FILE__) . '/../config.php');
require_once($CFG->dirroot . '/local/lib/jsmin.php');

$lifetime = 24 * 3600;
$cache = "{$CFG->dataroot}/cache/optimized.js";
$files = array('lib/javascript-static.js',
               'lib/javascript-mod.php',
               'lib/overlib/overlib.js',
               'lib/overlib/overlib_cssstyle.js',
               'lib/cookies.js',
               'lib/ufo.js',
               'lib/dropdown.js');

header('Last-Modified: ' . gmdate("D, d M Y H:i:s", time()) . ' GMT');
header('Expires: ' . gmdate("D, d M Y H:i:s", time() + $lifetime) . ' GMT');
header('Cache-Control: max-age='. $lifetime);
header('Pragma: ');
header("Content-type: application/javascript");

$t = microtime(true);

$lastmodified = filemtime(__FILE__);
foreach ($files as $file) {
    $lastmodified = max($lastmodified, filemtime("{$CFG->dirroot}/$file"));
}

if (!file_exists($cache) or filemtime($cache) < $lastmodified) {
    make_upload_directory("temp", false);
    make_upload_directory("cache", false);

    ob_start();
    foreach ($files as $file) {
        include("{$CFG->dirroot}/$file");
        echo "\n";
    }
    $js = ob_get_clean();
    $js = JSMin::minify($js);
    
    $temp_cache = tempnam("{$CFG->dataroot}/temp", 'optimized-js');
    file_put_contents($temp_cache, $js);
    rename($temp_cache, $cache);
 } else {
    $js = file_get_contents($cache);
 }

echo $js;
