<?php

require 'IO/SWF.php';

if ($argc != 2) {
    echo "Usage: php swfcopy.php <swf_file>\n";
    echo "ex) php swfdopy.php test.swf\n";
    exit(1);
}

assert(is_readable($argv[1]));

$swfdata = file_get_contents($argv[1]);

$swf = new IO_SWF();

$swf->parse($swfdata);

echo $swf->build();

exit(0);
