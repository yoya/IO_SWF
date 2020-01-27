<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/SWF/JPEG.php';
}

$options = getopt("f:a:");

if ((isset($options['f']) === false) || (is_readable($options['f']) === false)) {
    echo "Usage: php bitmapalpha2png.php -f <jpeg_file> -a <alpha_file>\n";
    echo "ex) php bitmapalpha2png.php -f test.jpg -a test.alpha > test.png\n";
    exit(1);
}

$jpegfile = $options["f"];
$alphafile = $options["a"];
$jpegdata = file_get_contents($jpegfile);
$alphadata = file_get_contents($alphafile);

$pngdata = IO_SWF_JPEG::bitmapAlpha2PNG($jpegdata, $alphadata);
echo $pngdata;
