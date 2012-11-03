<?php

require_once 'IO/SWF/Editor.php';

if (($argc != 3)) {
    echo "Usage: php swfgetpng.php <swf_file> <bitmap_id>\n";
    echo "ex) php swfgetpng.php colorformat.swf 2\n";
    exit(1);
}

assert(is_readable($argv[1]));
assert(isset($argv[2]));

$swfdata = file_get_contents($argv[1]);
$bitmap_id = $argv[2];

$swf = new IO_SWF_Editor();
$swf->parse($swfdata);

$pngdata = $swf->getPNGData($bitmap_id);
if ($pngdata === false) {
    echo "getPNGData($bitmap_id) failed\n";;
    exit(1);
}

echo $pngdata;

exit(0);
