<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require 'IO/SWF/ActionEditor.php';
}

if ($argc != 2) {
    echo "Usage: php swfactioneditor.php <swf_file>\n";
    echo "ex) php swfactioneditor.php test.swf\n";
    exit(1);
}

assert(is_readable($argv[1]));

$swfdata = file_get_contents($argv[1]);
$swf = new IO_SWF_ActionEditor();
$swf->parse($swfdata);

$opts = array();
$swf->parseAllTagContent($opts);

// Insert simple trace at main time line, frame 1, line 1.
$swf->insertSimpleTrace(0, 1, 1, "(^_^)/ Hello, world.");

// Insert trace which shows value of i at sprite1, frame 2, line 4.
$swf->insertVarDumpTrace(1, 2, 4, "i");

$swf->rebuild();
echo $swf->build();

exit(0);
