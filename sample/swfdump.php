<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require 'IO/SWF.php';
}

$options = getopt("f:hlA");

if (! isset($options['f']))  {
    echo "Usage: php swfdump.php -f <swf_file> [-h] [-l] [-A]\n";
    echo "ex) php swfdump.php -f test.swf -h -l\n";
    exit(1);
}

$filename = $options['f'];
if ($filename === "-") {
    $filename = "php://stdin";
}
$swfdata = file_get_contents($filename);

$swf = new IO_SWF();

$swf->parse($swfdata);

$opts = [ 'hexdump'  =>   isset($options['h']),
          'addlabel' =>   isset($options['l']),
          'abcdump'  => ! isset($options['A']) ];

$swf->dump($opts);

exit(0);
