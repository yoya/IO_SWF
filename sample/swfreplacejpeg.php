<?php

require_once 'IO/SWF/Editor.php';
require_once 'IO/SWF/JPEG.php';

if ($argc != 4) {
    echo "Usage: php swfreplacejpeg.php <swf_file> <image_id> <jpeg_file>\n";
    echo "ex) php swfreplacejpeg.php test.swf 1 test.jpg\n";
    exit(1);
}

assert(is_readable($argv[1]));
assert(is_numeric($argv[2]));
assert(is_readable($argv[3]));

$swfdata = file_get_contents($argv[1]);
$image_id = (int) $argv[2];

$swf = new IO_SWF_Editor();
$swf->parse($swfdata);
$swf->setCharacterId($swfdata);

$erroneous_header = pack('CCCC', 0xFF, 0xD9, 0xFF, 0xD8);
$jpegdata = file_get_contents($argv[3]);

// 21: DefineBitsJPEG2
$tag_code = 21;

if (true) {
    $swf_jpeg = new IO_SWF_JPEG();
    $swf_jpeg->input($jpegdata);
    $jpeg_table = $swf_jpeg->getEncodingTables();
    $jpeg_image =  $swf_jpeg->getImageData();
    // 21: DefineBitsJPEG2
    $ret = $swf->replaceTagContentByCharacterId($tag_code, $image_id, $jpeg_table.$jpeg_image);
} else {
    $ret = $swf->replaceTagContentByCharacterId($tag_code, $image_id, $erroneous_header.$jpegdata);
}

if ($ret == 0) {
    echo "Error: not found tag_code=$tag_code and image_id=$image_id tag".PHP_EOL;
    exit (1);
}

echo $swf->build();

exit(0);
