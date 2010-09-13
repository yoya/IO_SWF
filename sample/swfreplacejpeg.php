<?php

require_once 'IO/SWF/Editor.php';
require_once 'IO/SWF/JPEG.php';

if (($argc != 4) && ($argc != 5)) {
    echo "Usage: php swfreplacejpeg.php <swf_file> <image_id> <jpeg_file> [<alpha_file>]\n";
    echo "ex) php swfreplacejpeg.php test.swf 1 test.jpg test.alpha\n";
    exit(1);
}

assert(is_readable($argv[1]));
assert(is_numeric($argv[2]));
assert(is_readable($argv[3]));

$swfdata = file_get_contents($argv[1]);
$image_id = (int) $argv[2];

if (isset($argv[4])) { // with alphadata
    assert(is_readable($argv[4]));
    $alphadata = file_get_contents($argv[4]);
} else {
    $alphadata = null;
}

$swf = new IO_SWF_Editor();
$swf->parse($swfdata);
$swf->setCharacterId($swfdata);

$erroneous_header = pack('CCCC', 0xFF, 0xD9, 0xFF, 0xD8);
$jpegdata = file_get_contents($argv[3]);

$tag_code = array(6, 21, 35); // DefineBits, DefineBitsJPEG2,3

$swf_jpeg = new IO_SWF_JPEG();
$swf_jpeg->input($jpegdata);
$jpeg_table = $swf_jpeg->getEncodingTables();
$jpeg_image =  $swf_jpeg->getImageData();

if (is_null($alphadata)) {
    // 21: DefineBitsJPEG2
    $content = pack('v', $image_id).$jpeg_table.$jpeg_image;
    $tag = array('Code' => 21,
                 'Content' => $content);
    $ret = $swf->replaceTagByCharacterId($tag_code, $image_id, $tag);   
} else {
    // 35: DefineBitsJPEG3
    $jpeg_data = $jpeg_table.$jpeg_image;
    $compressed_alphadata = gzcompress($alphadata);
    $content = pack('v', $image_id).pack('V', strlen($jpeg_data)).$jpeg_data.$compressed_alphadata;
    $tag = array('Code' => 35,
                 'Content' => $content);
    $ret = $swf->replaceTagByCharacterId($tag_code, $image_id, $tag);   
}

if ($ret == 0) {
    echo "Error: not found tag_code=".implode(',',$tag_code)." and image_id=$image_id tag".PHP_EOL;
    exit (1);
}

echo $swf->build();

exit(0);
