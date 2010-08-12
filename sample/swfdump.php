<?php

require 'IO/SWF/Dumper.php';

if ($argc != 2) {
    echo "Usage: php swfdump.php <swf_file>\n";
    echo "ex) php swfdump.php test.swf\n";
    exit(1);
}

assert(is_readable($argv[1]));

$swfdata = file_get_contents($argv[1]);

$swf = new IO_SWF_Dumper();

$swf->parse($swfdata);

$swf->dump();

exit(0);
