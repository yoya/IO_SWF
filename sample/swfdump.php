<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require 'IO/SWF.php';
}

$options = getopt("f:hla");

if (! isset($options['f']))  {
    echo "Usage: php swfdump.php -f <swf_file> [-h] [-l] [-a]\n";
    echo "ex) php swfdump.php -f test.swf -h -l -a\n";
    exit(1);
}

$filename = $options['f'];
if ($filename === "-") {
    $filename = "php://stdin";
}
$swfdata = file_get_contents($filename);

$swf = new IO_SWF();

$swf->parse($swfdata);

$opts = array();

if (isset($options['h'])) {
    $opts['hexdump'] = true;
}
if (isset($options['l'])) {
    $opts['addlabel'] = true;
}
$opts['abcdump'] = isset($options['a']);

$swf->dump($opts);

exit(0);
