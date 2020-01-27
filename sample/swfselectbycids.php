<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/SWF/Editor.php';
}

if (($argc < 3)) {
    echo "Usage: php swfselectbycids.php <swf_file> <cid> [<cid2> [...]]\n";
    echo "ex) php swfselectbycids.php negimiku2_mcnest.swf 1\n";
    exit(1);
}

assert(is_readable($argv[1]));
assert(isset($argv[2]));

$swfdata = file_get_contents($argv[1]);
$cids = array_slice($argv, 2);

$swf = new IO_SWF_Editor();
$swf->parse($swfdata);

$ret = $swf->selectByCIDs($cids);
if ($ret === false) {
    echo "selectByCIDs(Array(".join(',', $cids).")) failed\n";
    exit(1);
}

echo $ret;

exit(0);
