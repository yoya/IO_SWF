<?php

  //require 'IO/SWF.php';
require 'IO/SWF/Editor.php';

if ($argc != 3) {
    echo "Usage: php swfreplacejpeg.php <swf_file> <jpeg_file>\n";
    echo "ex) php swfreplacejpeg.php test.swf test.jpg\n";
    exit(1);
}

assert(is_readable($argv[1]));
assert(is_readable($argv[2]));


$swfdata = file_get_contents($argv[1]);

$swf = new IO_SWF_Editor();
$swf->parse($swfdata);
$swf->setCharacterId($swfdata);

$erroreous_header = pack('CCCC', 0xFF, 0xD9, 0xFF, 0xD8);
$soi_eoi =  pack('CCCC', 0xFF, 0xD8, 0xFF, 0xD9);
$jpegdata = file_get_contents($argv[2]);

$swf->replaceTagContentByCharacterId(21, 1, $erroreous_header.$jpegdata);
// $swf->replaceTagContentByCharacterId(21, 1, $jpegdata.$soi_eoi);

echo $swf->build();

exit(0);
