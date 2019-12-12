<?php

require 'IO/SWF/Editor.php';

function usage() {
    echo "Usage: php swfdegrade.php <swf_file> <swf_version> <limit_swf_version>\n";
    echo "ex) php swfdegrade.php test.swf 4 4\n";
}

if ($argc < 4) {
    echo "ERROR: require 3 arguments\n";
    usage();
    exit (1);
}

$filename = $argv[1];
$swfVersion = $argv[2];
$limitSwfVersion = $argv[3];

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
if (is_numeric($limitSwfVersion) === false) {
    echo "ERROR: limitSwfVersion:$limitSwfVersion is not numeric.\n";
    usage();
    exit (1);
}


$swfdata = file_get_contents($filename);

$swf = new IO_SWF_Editor();

$swf->parse($swfdata);

$swf->degrade($swfVersion, $limitSwfVersion);

echo $swf->build();

exit(0);
