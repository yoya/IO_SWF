<?php

// require 'IO/SWF/Editor.php';
require dirname(__FILE__).'/../IO/SWF/Editor.php';

if ($argc != 3) {
    fprintf(STDERR, "Usage: php swfdeformeshape.php <swf_file> <threshold>\n");
    fprintf(STDERR, "ex) php swfdeformeshape.php test.swf 10 \n");
    exit(1);
}

assert(is_readable($argv[1]));
assert(is_numeric($argv[2]));

$swfdata = file_get_contents($argv[1]);
$threshold = $argv[2];

$swf = new IO_SWF_Editor();

$swf->parse($swfdata);

$swf->deformeShape($threshold);

echo $swf->build();

exit(0);
