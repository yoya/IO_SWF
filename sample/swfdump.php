<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require 'IO/SWF.php';
}

$options = getopt("f:hlASd");

if (! isset($options['f']))  {
    echo "Usage: php swfdump.php -f <swf_file> [-h] [-l] [-A] [-S] [-d]\n";
    echo "ex) php swfdump.php -f test.swf -h -l\n";
    exit(1);
}

$filename = $options['f'];
if ($filename === "-") {
    $filename = "php://stdin";
}
$swfdata = file_get_contents($filename);

$swf = new IO_SWF();

$opts = [
    'hexdump'  =>   isset($options['h']),
    'addlabel' =>   isset($options['l']),
    'abcdump'  => ! isset($options['A']),
    'strict'   => ! isset($options['S']),
    'debug'    =>   isset($options['d']),
];

try {
    $swf->parse($swfdata, $opts);
    $swf->dump($opts);
} catch (IO_SWF_Exception $e) {
    print_r($e);
}

exit(0);
