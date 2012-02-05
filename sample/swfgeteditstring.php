<?php

require_once 'IO/SWF/Editor.php';

if ($argc != 3) {
    echo "Usage: php swfgeteditstring.php <swf_file> <id>\n";
    echo "ex) php swfgeteditstring.php test.swf 1\n";
    echo "ex) php swfgeteditstring.php test.swf foo\n";
    exit(1);
}

assert(is_readable($argv[1]));

$swfdata = file_get_contents($argv[1]);
$id = $argv[2];

$swf = new IO_SWF_Editor();
$swf->parse($swfdata);

echo $swf->getEditString($id) . "\n";

exit(0);
