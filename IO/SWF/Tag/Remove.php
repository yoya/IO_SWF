<?php

require_once 'IO/Bit.php';
require_once dirname(__FILE__).'/Base.php';

class IO_SWF_Tag_Remove extends IO_SWF_Tag_Base {
    var $_characterId = null;
    var $_depth;

    function parseContent($tagCode, $content, $opts = array()) {
        $reader = new IO_Bit();
    	$reader->input($content);
        switch ($tagCode) {
        case 5:   // RemoveObject
            $this->_characterId = $reader->getUI16LE();
            $this->_depth = $reader->getUI16LE();
            break;
        case 28:  // RemoveObject2
            $this->_depth = $reader->getUI16LE();
            break;
        }
        return true;
    }

    function dumpContent($tagCode, $opts = array()) {
        if (is_null($this->_characterId) === false) {
            echo "\tCharacterId: ".$this->_characterId."\n";
        }
        if (is_null($this->_depth) === false) {
            echo "\tDepth: ".$this->_depth."\n";
        }
    }

    function buildContent($tagCode, $opts = array()) {
        $writer = new IO_Bit();
        switch ($tagCode) {
        case 5:   // RemoveObject
            $this->_characterId = $writer->putUI16LE();
            $this->_depth = $writer->putUI16LE();
            break;
        case 28:  // RemoveObject2
            $this->_depth = $writer->putUI16LE();
            break;
        }
    	return $writer->output();
    }
}
