<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/SWF/Editor.php';
}

if (($argc != 4)) {
    echo "Usage: php swfreplacemovieclip.php <swf_file> <target_path> <mc_swf_file>\n";
    echo "ex) php swfreplacemovieclip.php negimiku2_mcnest.swf miku/negi saitama3.swf\n";
    exit(1);
}

assert(is_readable($argv[1]));
assert(isset($argv[2]));
assert(is_readable($argv[3]));

$swfdata = file_get_contents($argv[1]);
$target_path = $argv[2];
$mc_swfdata = file_get_contents($argv[3]);

$swf = new IO_SWF_Editor();
$swf->parse($swfdata);

$ret = $swf->replaceMovieClip($target_path, $mc_swfdata);
if ($ret === false) {
    echo "replaceMovieClip($target_path, mc_swfdata) failed\n";;
    exit(1);
}

echo $swf->build();

exit(0);
