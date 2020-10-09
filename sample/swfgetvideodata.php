<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/SWF/Editor.php';
    require_once 'IO/SWF/AE.php';
}

function usage() {
    echo "Usage: php swfgetvideodata.php <swf_file> [<video_id> <outfile> [offset [limitFrames]]]\n";
    // echo "ex) php swfgetvideodata.php video.swf\n";
    echo "ex) php swfgetvideodata.php video.swf 1 data.vp6\n";
    echo "ex) php swfgetvideodata.php video.swf 1 data.vp6 0 3\n";
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
$offsetFrame = isset($argv[4])? intval($argv[4]): null;
$limitFrames = isset($argv[5])? intval($argv[5]): null;

$swf = new IO_SWF_Editor();
$swf->parse($swfdata);
$videoStream = $swf->getVideoStream($video_id);
$videoframes = $swf->getVideoFrames($video_id);

function showKeyFrameNumbers($videoframes) {
    echo "frames:";
    foreach ($videoframes as $idx => $frame) {
        if (ord($frame["Data"][0]) & 0x80) {
            echo " $idx";  // delta frame
        } else {
            echo " *$idx*";  // key frame
        }
    }
    echo "\n";
}

if (! is_null($offsetFrame)) {
    if (ord($videoframes[$offsetFrame]["Data"][0]) & 0x80) { // delta frame
        echo "ERROR: offsetFrame($offsetFrame) must specify key frame\n";
        showKeyFrameNumbers($videoframes);
        exit (1);
    }
    if (is_null($limitFrames)) {
        $videoframes = array_slice($videoframes, $offsetFrame);
    } else {
        $videoframes = array_slice($videoframes, $offsetFrame, $limitFrames);
    }
    $videoStream->_NumFrames = count($videoframes);
}

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
    if ($has_alpha) {
        if (! isset($frame["AlphaData"])) {
            throw new Exception("internal error: no AlphaData in VideoFrame");
        }
        $ae->addFrame($frame["AlphaData"], true);
    }
    if (! isset($frame["Data"])) {
        throw new Exception("internal error: no Data in VideoFrame");
    }
    $ae->addFrame($frame["Data"], false);
}

file_put_contents($filename, $ae->output());

exit(0);
