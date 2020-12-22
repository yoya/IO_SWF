<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/SWF/Editor.php';
    require_once 'IO/SWF/AE.php';
}

function echoerr($msg) {
    fprintf(STDERR, "%s", $msg);
}

function usage() {
    echoerr("Usage: php swfgetvideodata.php -f <swf_file> -i <video_id> [-o offset] [-n numFrames] [-A]\n");
    echoerr("ex) php swfgetvideodata.php -f video.swf -i 1 > data.vp6\n");
    echoerr("ex) php swfgetvideodata.php -f video.swf -i 1 -n 3 > data-3frames.vp6\n");
    echoerr("ex) php swfgetvideodata.php -f video.swf -i 1 -A > data-noalpha.vp6\n");
}
$options = getopt("f:i:o:i:hA");

if (isset($options['f']) && is_readable($options['f']) &&
    isset($options['i']) && is_numeric($options['i'])) {
    // OK
} else {
    usage();
    exit(1);
}

$filename = $options['f'];
if ($filename === "-") {
    $filename = "php://stdin";
}
$swfdata = file_get_contents($filename);

assert(isset($argv[2]));

$video_id = isset($options['i'])? $options['i']: 0;
$offsetFrame = isset($options['o'])? intval($options['o']): null;
$numFrames = isset($options['n'])? intval($options['n']): null;

$swf = new IO_SWF_Editor();
$swf->parse($swfdata);
$videoStream = $swf->getVideoStream($video_id);
$videoframes = $swf->getVideoFrames($video_id);

function showKeyFrameNumbers($videoframes) {
    echoerr("frames:");
    foreach ($videoframes as $idx => $frame) {
        if (ord($frame["Data"][0]) & 0x80) {
            echoerr(" $idx");  // delta frame
        } else {
            echoerr(" *$idx*");  // key frame
        }
    }
    echoerr("\n");
}

if (! is_null($offsetFrame)) {
    if (ord($videoframes[$offsetFrame]["Data"][0]) & 0x80) { // delta frame
        echoerr("ERROR: offsetFrame($offsetFrame) must specify key frame\n");
        showKeyFrameNumbers($videoframes);
        exit (1);
    }
    if (is_null($numFrames)) {
        $videoframes = array_slice($videoframes, $offsetFrame);
    } else {
        $videoframes = array_slice($videoframes, $offsetFrame, $numFrames);
    }
    $videoStream->_NumFrames = count($videoframes);
}

if ($videoStream === false) {
    echoerr("getVideoStream($video_id) failed\n");
    exit(1);
}
if ($videoframes === false) {
    echoerr("getVideoFrames($video_id) failed\n");
    exit(1);
}

$has_alpha = count($videoframes)? (isset($videoframes[0]["AlphaData"])):
                 false;

if (isset($options['A'])) {
    $has_alpha = false;
}

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

echo $ae->output();

exit(0);
