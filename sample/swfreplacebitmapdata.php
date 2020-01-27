<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/SWF/Editor.php';
}

if (($argc != 4) && ($argc != 5)) {
    echo "Usage: php swfreplacebitmapdata.php <swf_file> <bitmap_id> <bitmap_file> [<alpha_file>]\n";
    echo "ex) php swfreplacebitmapdata.php test.swf 1 test.jpg test.alpha\n";
    echo "ex) php swfreplacebitmapdata.php test.swf 1 test.png\n";
    echo "ex) php swfreplacebitmapdata.php test.swf 1 test.git\n";
    exit(1);
}

assert(is_readable($argv[1]));
assert(is_numeric($argv[2]));
assert(is_readable($argv[3]));

$swfdata = file_get_contents($argv[1]);
$bitmap_id = (int) $argv[2];
$bitmapdata = file_get_contents($argv[3]);
if (isset($argv[4])) { // with jpeg alphadata
    assert(is_readable($argv[4]));
    $jpeg_alphadata = file_get_contents($argv[4]);
} else {
    $jpeg_alphadata = null;
}

$swf = new IO_SWF_Editor();
$swf->parse($swfdata);

// $swf->setShapeAdjustMode(IO_SWF_Editor::SHAPE_BITMAP_RECT_RESIZE);
$ret = $swf->replaceBitmapData($bitmap_id, $bitmapdata, $jpeg_alphadata);

echo $swf->build();

exit(0);
