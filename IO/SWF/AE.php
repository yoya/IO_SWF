<?php

/*
 * 2020/10/03- (c) yoya@awm.jp
 */

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/Bit.php';
}

// https://wiki.multimedia.cx/index.php/Electronic_Arts_Formats
// https://wiki.multimedia.cx/index.php/Electronic_Arts_VP6

class IO_SWF_AE {
    var $bit = null;
    var $largetstFrameChunkSizeOffset = null;
    var $largetstFrameChunkSize = 0;
    function __construct($swfHeaders, $videoStream, $has_alpha) {
        $bit = new IO_Bit();
        // EA video header
        if ($has_alpha) {
            $bit->putData("AVP6");
            $bit->putUI32LE(8);  // AVP6 length 8 is constant.
        }
        $bit->putData("MVhd");
        $bit->putUI32LE(0x20);  // MVhd length 0x20 is constant.
        // vp6 extension
        $bit->putData("vp60");  // VP60
        $bit->putUI16LE($videoStream->_Width);
        $bit->putUI16LE($videoStream->_Height);
        $bit->putUI32LE($videoStream->_NumFrames);
        list($this->largetstFrameChunkSizeOffset, $dummy) = $bit->getOffset();
        $bit->putUI32LE(0);  // Largetst Frame Chunk size, overwritten later.
        // FrameRate;: denom(rate), numerator(scale);
        $bit->putUI32LE($swfHeaders["FrameRate"]);
        $bit->putUI32LE(0x100);
        //
        if ($has_alpha) {
            $bit->putData("AVhd");
            $bit->putUI32LE(0x20);  // AVhd length 0x20 is constant.
            // vp6 extension
            $bit->putData("vp60");  // VP60
            $bit->putUI16LE($videoStream->_Width);
            $bit->putUI16LE($videoStream->_Height);
            $bit->putUI32LE($videoStream->_NumFrames);
            list($this->largetstFrameChunkSizeOffset, $dummy) = $bit->getOffset();
            $bit->putUI32LE(0);  // Largetst Frame Chunk size
            $bit->putUI32LE($swfHeaders["FrameRate"] / 0x100);  // frame ratew (denom, rate);
            $bit->putUI32LE(1);  // frame ratew (numerator, scale);
        }
        $this->bit = $bit;
    }
    function addFrame($frameData, $alpha) {
        $bit = $this->bit;
        $frameSize = 4 + 4 + strlen($frameData);
        $keyFrame = ! (ord($frameData[0]) & 0x80);
        if ($alpha) {
            if ($keyFrame) {
                $bit->putData("AV0K");  // key frame
            } else {
                $bit->putData("AV0F");  // delta frame
            }
        } else {
            if ($keyFrame) {
                $bit->putData("MV0K");  // key frame
            } else {
                $bit->putData("MV0F");  // delta frame
            }
        }
        $bit->putUI32LE($frameSize);
        $bit->putData($frameData);
        //
        if ($this->largetstFrameChunkSize < $frameSize) {
            $this->largetstFrameChunkSize = $frameSize;
        }
    }
    function output() {
        $bit = $this->bit;
        if (is_null($this->largetstFrameChunkSizeOffset)) {
            throw new Exception("internal error: largetstFrameChunkSizeOffset is null");
        }
        $bit->setUI32LE($this->largetstFrameChunkSize,
                        $this->largetstFrameChunkSizeOffset);
        return $bit->output();
    }
}

