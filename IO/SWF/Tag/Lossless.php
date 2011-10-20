<?php

require_once 'IO/Bit.php';
require_once dirname(__FILE__).'/Base.php';

class IO_SWF_Tag_Lossless extends IO_SWF_Tag_Base {
    var $_CharacterID;
    var $_BitmapFormat;
    var $_BitmapWidth;
    var $_BitmapHeight;
    var $_BitmapColorTableSize = null;
    var $_ZlibBitmapData;
   function parseContent($tagCode, $content, $opts = array()) {
        $reader = new IO_Bit();
    	$reader->input($content);
        $this->_CharacterID = $reader->getUI16LE();
        $bitmapFormat = $reader->getUI8();
        $this->_BitmapFormat = $bitmapFormat;
        $this->_BitmapWidth = $reader->getUI16LE();
        $this->_BitmapHeight = $reader->getUI16LE();
        if ($bitmapFormat == 3) {
            $this->_BitmapColorTableSize = $reader->getUI8() + 1;
        }
        $this->_ZlibBitmapData = $reader->getDataUntil(false);
    }

    function dumpContent($tagCode, $opts = array()) {
        $bitmapFormat = $this->_BitmapFormat;
        echo "\tCharacterID:{$this->_CharacterID} BitmapFormat={$bitmapFormat}\n";
        echo "\tBitmapWidth:{$this->_BitmapWidth} BitmapHeight:{$this->_BitmapHeight}\n";
        if ($bitmapFormat == 3) {
            echo "\tBitmapColorTableSize:{$this->_BitmapColorTableSize}\n";
        }
    }

    function buildContent($tagCode, $opts = array()) {
        $writer = new IO_Bit();
        $writer->putUI16LE($this->_CharacterID);
        $bitmapFormat = $this->_BitmapFormat;
        $writer->putUI8($bitmapFormat);
        $writer->putUI16LE($this->_BitmapWidth);
        $writer->putUI16LE($this->_BitmapHeight);
        if ($bitmapFormat == 3) {
            $writer->putUI8($this->_BitmapColorTableSize - 1);
        }
        $writer->putData($this->_ZlibBitmapData);
    	return $writer->output();
    }
}
