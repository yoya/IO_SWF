<?php

require 'IO/SWF.php';
// require dirname(__FILE__).'/../IO/SWF.php';

$options = getopt("f:cd");

function usage() {
    echo "Usage: php swfcompress.php -[cd] -f <swf_file>\n";
    echo "ex) php swfcompress.php -c -f test.swf # compress (*WS=>CWS)\n";
    echo "ex) php swfcompress.php -d -f test.swf # decompress (*WS=>FWS)\n";
}

if (is_readable($options['f']) === false) {
    usage();
    exit(1);
}

if (isset($options['c'])) {
     $compress = true;
} elseif (isset($options['d'])) {
     $compress = false;
} else {
    usage();
    exit(1);
}


$swfdata = file_get_contents($options['f']);

$swf = new IO_SWF();

$swf->parse($swfdata);

if ($compress) {
    $swf->_headers['Signature'] = 'CWS';
} else {
    $swf->_headers['Signature'] = 'FWS';
}

echo $swf->build();

exit(0);
