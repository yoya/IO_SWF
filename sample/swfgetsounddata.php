<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/SWF/Editor.php';
}

if (($argc != 3)) {
    echo "Usage: php swfgetsound.php <swf_file> <sound_id>\n";
    echo "ex) php swfgetsound.php sound.swf 2\n";
    exit(1);
}

assert(is_readable($argv[1]));
assert(isset($argv[2]));

$swfdata = file_get_contents($argv[1]);
$sound_id = $argv[2];

$swf = new IO_SWF_Editor();
$swf->parse($swfdata);

$sounddata = $swf->getSoundData($sound_id);
if ($sounddata === false) {
    echo "getSOUNDData($sound_id) failed\n";;
    exit(1);
}

echo $sounddata;

exit(0);
