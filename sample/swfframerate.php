<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require 'IO/SWF/Editor.php';
}

if (($argc != 2) && ($argc != 3)) {
    echo "Usage: php swfframerate.php <swf_file> [<frame_rate>]\n";
    echo "ex) php swfframerate.php test.swf 12\n";
    exit(1);
}

assert(is_readable($argv[1]));

$swfdata = file_get_contents($argv[1]);

$swf = new IO_SWF_Editor();

$swf->parse($swfdata);

if ($argc === 2) {
    echo "FrameRate:".($swf->_headers['FrameRate'] / 0x100).PHP_EOL;
} else {
    $swf->_headers['FrameRate'] = $argv[2] * 0x100;
    echo $swf->build();
}

exit(0);
