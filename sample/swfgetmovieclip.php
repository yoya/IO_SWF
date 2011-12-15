<?php

require_once 'IO/SWF/Editor.php';

if (($argc != 3)) {
    echo "Usage: php swfgetmovieclip.php <swf_file> <target_path>\n";
    echo "ex) php swfgetmovieclip.php negimiku2_mcnest.swf miku/negi\n";
    exit(1);
}

assert(is_readable($argv[1]));
assert(isset($argv[2]));

$swfdata = file_get_contents($argv[1]);
$target_path = $argv[2];

$swf = new IO_SWF_Editor();
$swf->parse($swfdata);

$ret = $swf->getMovieClip($target_path);
if ($ret === false) {
    echo "getMovieClip($target_path) failed\n";;
    exit(1);
}

echo $ret;

exit(0);
