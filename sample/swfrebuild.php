<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require 'IO/SWF/Editor.php';
}

if ($argc != 2) {
    fprintf(STDERR, "Usage: php swfrebuild.php <swf_file>\n");
    fprintf(STDERR, "ex) php swfrebuild.php test.swf\n");
    exit(1);
}

assert(is_readable($argv[1]));

$swfdata = file_get_contents($argv[1]);

$swf = new IO_SWF_Editor();

$swf->parse($swfdata);

$swf->rebuild();

echo $swf->build();

exit(0);
