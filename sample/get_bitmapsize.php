<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/SWF/Bitmap.php';
}

if ($argc != 2) {
    echo "Usage: php get_bitmapsize.php <bitmap_file>\n";
    echo "ex) php get_bitmapsize.php test.jpg\n";
    exit(1);
}

$bitmap_data = file_get_contents($argv[1]);

$ret = IO_SWF_Bitmap::get_bitmapsize($bitmap_data);

echo "width:{$ret['width']} height:{$ret['height']}\n";
