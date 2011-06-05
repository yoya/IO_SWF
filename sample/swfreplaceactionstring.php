<?php

require 'IO/SWF/Editor.php';
// require dirname(__FILE__).'/../IO/SWF/Editor.php';

if ($argc != 4) {
    fprintf(STDERR, "Usage: php swfreplaceactionstring.phpp <swf_file> <from_str> <to_str>\n");
    fprintf(STDERR, "ex) php swfreplaceactionstring.php test.swf foo baa\n");
    exit(1);
}

assert(is_readable($argv[1]));
assert(isset($argv[2]));
assert(isset($argv[3]));

$swfdata = file_get_contents($argv[1]);
$from_str = $argv[2];
$to_str = $argv[3];

$swf = new IO_SWF_Editor();

$swf->parse($swfdata);

$swf->replaceActionString($from_str, $to_str);

echo $swf->build();

exit(0);
