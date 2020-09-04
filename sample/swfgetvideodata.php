<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/SWF/Editor.php';
}

function usage() {
    echo "Usage: php swfgetvideodata.php <swf_file> [<video_id> <outfile> [outalphafile]]\n";
    echo "ex) php swfgetvideodata.php video.swf\n";
    echo "ex) php swfgetvideodata.php video.swf 1 data-%04.vp6\n";
    echo "ex) php swfgetvideodata.php video.swf 1 data-%04.vp6 alpha-%04d.vp6\n";
}

if (($argc < 2)) {
    usage();
    exit(1);
}

assert(is_readable($argv[1]));
$swfdata = file_get_contents($argv[1]);

if ($argc === 2) {  // list
    echo "list video, not implemented yet.\n";
    exit(0);
}
if (($argc < 4)) {
    usage();
    exit(1);
}


assert(isset($argv[2]));

$video_id = $argv[2];
$datafilename = $argv[3];
$alphafilename = ($argc < 5)?null: $argv[4];

$swf = new IO_SWF_Editor();
$swf->parse($swfdata);
$videoframes = $swf->getVideoFrames($video_id);

if ($videoframes === false) {
    echo "getVideoFrames($video_id) failed\n";;
    exit(1);
}

foreach ($videoframes as $idx => $frame) {
    if (isset($frame["Data"])) {
        $fname = sprintf($datafilename, $idx);
        echo "$fname\n";
        file_put_contents($fname, $frame["Data"]);
    } else {
        fprintf(STDERR, "ERROR: No VideFrame Data\n");
        exit (1);
    }
    if (! is_null($alphafilename)) {
        if (isset($frame["AlphaData"])) {
            $faname = sprintf($alphafilename, $idx);
            echo "$faname\n";
            file_put_contents($faname, $frame["AlphaData"]);
        } else {
            fprintf(STDERR, "ERROR: No VideoFrame AlphaData\n");
            exit (1);
        }
    }
}

exit(0);
