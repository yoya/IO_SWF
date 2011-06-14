<?php

require 'IO/SWF/Info.php';
// require dirname(__FILE__).'/../IO/SWF/Info.php';

$options = getopt("f:h");

if (is_readable($options['f']) === false) {
    echo "Usage: php swfdump.php -f <swf_file> [-h]\n";
    echo "ex) php swfdump.php -f test.swf -h \n";
    exit(1);
}

$swfdata = file_get_contents($options['f']);

$swf = new IO_SWF_Info();

$swf->parse($swfdata);

$opts = array();
if (isset($options['h'])) {
    $opts['hexdump'] = true;
}

$swf->dump($opts);

exit(0);
