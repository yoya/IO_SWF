<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/Bit.php';
}
require_once dirname(__FILE__).'/Base.php';

class IO_SWF_Tag_VideoStream extends IO_SWF_Tag_Base {
    var $_CharacterID;
    var $_NumFrames;
    var $_Width, $_Height;
    var $_VideoFlags;
    var $_CodecID;

    var $_codecIDs = [
        2 => "H.263", 3 => "Screen Video",
        4 => "On2 VP6 SWF", 5=> "On2 VP6 SWF with Alpha",
        6 =>"Screen Video v2"
    ];

    function getCodecName($id) {
        if (isset($this->_codecIDs[$id])) {
                return $this->_codecIDs[$id];
        }
        return "Unknown";
    }
    
    function parseContent($tagCode, $content, $opts = array()) {
        $reader = new IO_Bit();
    	$reader->input($content);
        $this->_CharacterID = $reader->getUI16LE();
        $this->_NumFrames = $reader->getUI16LE();
        $this->_Width  = $reader->getUI16LE();
        $this->_Height = $reader->getUI16LE();
        $this->_VideoFlags = $reader->getUI8();
        $this->_CodecID = $reader->getUI8();
    }

    function dumpContent($tagCode, $opts = array()) {
        echo "    CharacterID: {$this->_CharacterID}";
        echo "  NumFrames: {$this->_NumFrames}";
        echo "  Width: {$this->_Width}  Height: {$this->_Height}\n";
        echo "    VideoFlags: {$this->_VideoFlags}";
        $codecID = $this->_CodecID;
        $codecName = $this->getCodecName($codecID);
        echo "  CodecID: $codecID ($codecName)\n";
    }

    function buildContent($tagCode, $opts = array()) {
        $writer = new IO_Bit();
        $writer->putUI16LE($this->_CharacterID);
        $writer->putUI16LE($this->_NumFrames);
        $writer->putUI16LE($this->_Width);
        $writer->putUI16LE($this->_Height);
        $writer->putUI8($this->_VideoFlags);
        $writer->putUI8($this->_CodecID);
    	return $writer->output();
    }
}
