<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/SWF/JPEG.php';
}

function usage() {
    echo "Usage: php jpeg_dump.php <dump|jpegtables|imagedata>".PHP_EOL;
}

if ($argc != 3) {
    usage();
    exit(1);
}

$jpegdata = file_get_contents($argv[2]);

$jpeg = new IO_SWF_JPEG();
$jpeg->input($jpegdata);

switch($argv[1]) {
  case 'dump':
    $jpeg->dumpChunk();
    break;
  case 'jpegtables':
    echo $jpeg->getEncodingTables();
    break;
  case 'imagedata':
    echo $jpeg->getImageData();
    break;
  default:
    usage();
    exit(1);
}

exit(0);
