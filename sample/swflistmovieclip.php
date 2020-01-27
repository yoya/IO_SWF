<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require 'IO/SWF/Editor.php';
}

if ($argc != 2) {
    echo "Usage: php swflistmovieclip.php <swf_file>\n";
    exit(1);
}

$swfdata = file_get_contents($argv[1]);

$swf = new IO_SWF_Editor();

$swf->parse($swfdata);

$mc_list = $swf->listMovieClip();
if ($mc_list === false) {
    echo "mc_list === false\n";
    exit(1);
}

foreach ($mc_list as $spriteId => $mc) {
    echo "SpriteId:$spriteId FrameCount:{$mc['FrameCount']} TagCount:{$mc['TagCount']}";
    if (isset($mc['name'])) {
            echo ' name:'.$mc['name'];
    }
    echo PHP_EOL;
    if (isset($mc['path_list'])) {
        foreach ($mc['path_list'] as $path) {
            echo "\t".$path['path'];
            if (count($path['parent_cids']) > 0) {
                echo ' ('.implode(',', $path['parent_cids']).')';
            }
            echo PHP_EOL;
        }
    }
}

exit(0);
