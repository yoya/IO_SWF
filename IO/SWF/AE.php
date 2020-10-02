<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/Bit.php';
}

// https://wiki.multimedia.cx/index.php/Electronic_Arts_Formats
// https://wiki.multimedia.cx/index.php/Electronic_Arts_VP6

class IO_SWF_AE {
    var $bit = null;
    function __construct($swfHeaders, $videoStream) {
        // var_dump($swfHeaders);
        // var_dump($videoStream);
        $bit = new IO_Bit();
        //
        $bit->putData("MVhd");
        $bit->putUI32LE(0x20); // MVhd length 20 is constant.
        //
        $bit->putData("vp60"); // VP60
        $bit->putUI16LE($videoStream->_Width);
        $bit->putUI16LE($videoStream->_Height);
        $bit->putUI32LE($videoStream->_NumFrames);
        $bit->putUI32LE(0);  // Largetst Frame Chunk size
        $bit->putUI32LE($swfHeaders["FrameRate"] / 0x100);  // frame ratew (denom, rate);
        $bit->putUI32LE(1);  // frame ratew (numerator, scale);
        //
        $this->bit = $bit;
    }
    function addFrame($keyFrame, $frameData, $alpha) {
        $bit = $this->bit;
        if ($keyFrame) {
            $bit->putData("MV0K");  // key frame
        } else {
            $bit->putData("MV0F");  // delta frame
        }
        $bit->putUI32LE(4 + 4 + strlen($frameData));
        $bit->putData($frameData);
    }
    function output() {
        return $this->bit->output();
    }
}

