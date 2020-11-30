<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require 'IO/SWF/Editor.php';
}

$options = getopt("f:v:l:E");

function usage() {
    echo "Usage: php swfdowngrade.php -f <swf_file> -v <swf_version> [-l <limit_tag_swf_version>] [-E]\n";
    echo "ex) php swfdowngrade.php -f test.swf -v 4\n";
}

if (! isset($options['f']) || (! isset($options['v']))) {
    echo "ERROR: require f,v options\n";
    usage();
    exit (1);
}

$filename = $options['f'];

if ($filename === '-') {
    $filename = "php://stdin";
}

$swfVersion = $options['v'];
$limitSwfVersion = isset($options['l'])? $options['l']: null;
if (is_null($limitSwfVersion)) {
    $limitSwfVersion = $swfVersion;
}

$opts = [
    'preserveStyleState' => true,
    'eliminate'          => ! isset($options['E'])
];


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

$swf->downgrade($swfVersion, $limitSwfVersion, $opts);

echo $swf->build($opts);

exit(0);
