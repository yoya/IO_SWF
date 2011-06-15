<?php

require 'IO/SWF/Editor.php';
// require dirname(__FILE__).'/../IO/SWF/Editor.php';

if ($argc != 2) {
    echo "Usage: php swfcountshapeedges.php <swf_file>\n";
    echo "ex) php swfcountshapeedges.php test.swf\n";
    exit(1);
}

$swfdata = file_get_contents($argv[1]);

$swf = new IO_SWF_Editor();

$swf->parse($swfdata);

$count_table = $swf->countShapeEdges();
if ($count_table === false) {
    printf("countShapeEdges return false\n");
    exit(1);
}

foreach ($count_table as $shape_id => $count) {
   echo "shape_id: $shape_id => edges_count: $count\n";
}

exit(0);
