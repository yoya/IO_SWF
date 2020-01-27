<?php

require 'IO/SWF/Editor.php';

$options = getopt("f:v:l:");

function usage() {
    echo "Usage: php swfdegrade.php -f <swf_file> -v <swf_version> -l <limit_swf_version>\n";
    echo "ex) php swfdegrade.php -f test.swf -v 4 -l 4\n";
}

if ((! is_readable($options['f'])) ||
    (! is_numeric($options['v'])) ||
    (! is_numeric($options['l']))) {
    echo "ERROR: require f v l options\n";
    usage();
    exit (1);
}

$filename = $options['f'];
$swfVersion = $options['v'];
$limitSwfVersion = $options['l'];

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
