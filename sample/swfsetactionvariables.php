<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require 'IO/SWF/Editor.php';
}

if ($argc != 4) {
    fprintf(STDERR, "Usage: php swfsetactiovaribles.phpp <swf_file> <from_str> <to_str>\n");
    fprintf(STDERR, "ex) php swfactionvariables.php test.swf foo baa\n");
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

$swf->setActionVariables($from_str, $to_str);

echo $swf->build();

exit(0);
