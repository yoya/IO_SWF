<?php

require 'IO/SWF/Editor.php';

function usage() {
    echo "Usage: php swfdegrade.php <swf_file> <version>\n";
    echo "ex) php swfdegrade.php test.swf 4\n";
}

if ($argc < 3) {
    echo "ERROR: require 2 arguments\n";
    usage();
    exit (1);
}

$filename = $argv[1];
$swfVersion = $argv[2];

if (is_readable($filename) === false) {
    echo "ERROR: can't open file:$filename\n";
    usage();
    exit (1);
}
if (is_numeric($swfVersion) === false) {
    echo "ERROR: swfVersion:$swfVersion is not numeric.\n";
    usage();
    exit (1);
}

$swfdata = file_get_contents($filename);

$swf = new IO_SWF_Editor();

$swf->parse($swfdata);

$swf->degrade($swfVersion);

echo $swf->build();

exit(0);
