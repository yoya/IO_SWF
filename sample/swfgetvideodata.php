<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/SWF/Editor.php';
    require_once 'IO/SWF/AE.php';
}

function usage() {
    echo "Usage: php swfgetvideodata.php <swf_file> [<video_id> <outfile>]\n";
    echo "ex) php swfgetvideodata.php video.swf\n";
    echo "ex) php swfgetvideodata.php video.swf 1 data.vp6\n";
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
$filename = $argv[3];

$swf = new IO_SWF_Editor();
$swf->parse($swfdata);
$videoStream = $swf->getVideoStream($video_id);
$videoframes = $swf->getVideoFrames($video_id);

if ($videoStream === false) {
    echo "getVideoStream($video_id) failed\n";;
    exit(1);
}
if ($videoframes === false) {
    echo "getVideoFrames($video_id) failed\n";;
    exit(1);
}

$has_alpha = count($videoframes)? (isset($videoframes[0]["AlphaData"])):
                 false;

$ae = new IO_SWF_AE($swf->_headers, $videoStream, $has_alpha);

foreach ($videoframes as $idx => $frame) {
    if (! isset($frame["Data"])) {
        throw new Exception("internal error: no Data in VideoFrame");
    }
    if ($has_alpha) {
        if (! isset($frame["AlphaData"])) {
            throw new Exception("internal error: no AlphaData in VideoFrame");
        }
        $ae->addFrame($frame["AlphaData"], true);
    }
    $ae->addFrame($frame["Data"], false);
}

file_put_contents($filename, $ae->output());

exit(0);
