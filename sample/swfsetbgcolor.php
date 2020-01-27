<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require 'IO/SWF/Editor.php';
}

if ($argc != 5) {
    echo "Usage: php swfsetbgcolor.php <swf_file> <red> <green> <blue>\n";
    echo "ex) php swfsetbgcolor.php test.swf 0 0 255\n";
    exit(1);
}

assert(is_readable($argv[1]));
assert(is_numeric($argv[2]));
assert(is_numeric($argv[3]));
assert(is_numeric($argv[4]));

$swfdata = file_get_contents($argv[1]);

$swf = new IO_SWF_Editor();

$swf->parse($swfdata);

$color = pack('CCC', $argv[2], $argv[3], $argv[4]);
$swf->replaceTagContent(9, $color);

echo $swf->build();

exit(0);
