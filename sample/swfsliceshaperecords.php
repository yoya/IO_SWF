<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require 'IO/SWF/Editor.php';
}

if ($argc < 2) {
    echo "Usage: php swfsliceshapeedges.php <swf_file> [<shape_id> <start> <end>]\n";
    echo "ex) php swfsliceshapeedges.php test.swf\n";
    echo "ex) php swfsliceshapeedges.php test.swf 1 0 32\n";
    exit(1);
}

$swfdata = file_get_contents($argv[1]);

$swf = new IO_SWF_Editor();

$swf->parse($swfdata);


if ($argc < 3) {
    $count_table = $swf->countShapeRecords();
    if ($count_table === false) {
        printf("countShapeEdges return false\n");
        exit(1);
    }
    
    foreach ($count_table as $shape_id => $counts) {
        echo "shape_id: $shape_id:\n";
        foreach ($counts as $idx => $records) {
            echo "\t[$idx]:";
            foreach ($records as $name => $value) {
                if ($value > 0) {
                    echo " $name:$value";
                }
            }
            echo "\n";
        }
    }
} else {
    $count_table = $swf->sliceShapeRecords($argv[2], $argv[3], $argv[4]);
    echo $swf->build();
}

exit(0);
