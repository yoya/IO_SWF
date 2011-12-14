<?php

// require 'IO/SWF.php';
require dirname(__FILE__).'/../IO/SWF/Decompiler.php';

$options = getopt("f:h");

if (is_readable($options['f']) === false) {
    echo "Usage: php swfdecompile.php -f <swf_file> [-h]\n";
    echo "ex) php swfdecompile.php -f test.swf -h \n";
    exit(1);
}

$swfdata = file_get_contents($options['f']);

$swf = new IO_SWF_Decompiler();

$swf->parse($swfdata);

$opts = array();
if (isset($options['h'])) {
    $opts['hexdump'] = true;
}
$opts['addlabel'] = true;

$swf->dump($opts);

exit(0);
