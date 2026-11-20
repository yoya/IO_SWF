<?php

/*
 * CSMTextSettings (74)
 *   TextID UI16
 *   UseFlashType UB[2], GridFit UB[3], Reserved UB[3]
 *   Thickness F32, Sharpness F32
 *   Reserved UI8
 */

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/Bit.php';
}
require_once dirname(__FILE__).'/Base.php';

class IO_SWF_Tag_CSMTextSettings extends IO_SWF_Tag_Base {
    static $GridFitNoteList = array('None', 'Pixel', 'SubPixel');
    var $_textId;
    var $_useFlashType;
    var $_gridFit;
    var $_reserved;
    // F32 は往復でビットが変わらないよう 4 バイトの生値で保持し、dump 時だけ数値化する
    var $_thickness;
    var $_sharpness;
    var $_reserved2;
    var $_rest = null;

    function parseContent($tagCode, $content, $opts = array()) {
        $reader = new IO_Bit();
        $reader->input($content);
        $this->_textId = $reader->getUI16LE();
        $this->_useFlashType = $reader->getUIBits(2);
        $this->_gridFit = $reader->getUIBits(3);
        $this->_reserved = $reader->getUIBits(3);
        $this->_thickness = $reader->getData(4);
        $this->_sharpness = $reader->getData(4);
        $this->_reserved2 = $reader->getUI8();
        if ($reader->hasNextData(1)) {
            $this->_rest = $reader->getDataUntil(false);
        }
    }

    static function f32($data) {
        return unpack('g', $data)[1]; // little-endian float
    }

    function dumpContent($tagCode, $opts = array()) {
        $gridFit = isset(self::$GridFitNoteList[$this->_gridFit]) ? self::$GridFitNoteList[$this->_gridFit] : 'unknown';
        echo "    TextID: {$this->_textId}  UseFlashType: {$this->_useFlashType}  GridFit: {$this->_gridFit}($gridFit)\n";
        printf("    Thickness: %g  Sharpness: %g\n", self::f32($this->_thickness), self::f32($this->_sharpness));
        if (! is_null($this->_rest)) {
            echo "    (trailing data: ".strlen($this->_rest)." bytes)\n";
        }
    }

    function buildContent($tagCode, $opts = array()) {
        $writer = new IO_Bit();
        $writer->putUI16LE($this->_textId);
        $writer->putUIBits($this->_useFlashType, 2);
        $writer->putUIBits($this->_gridFit, 3);
        $writer->putUIBits($this->_reserved, 3);
        $writer->putData($this->_thickness, 4);
        $writer->putData($this->_sharpness, 4);
        $writer->putUI8($this->_reserved2);
        if (! is_null($this->_rest)) {
            $writer->putData($this->_rest);
        }
        return $writer->output();
    }
}
