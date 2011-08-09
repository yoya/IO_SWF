<?php

require_once 'IO/Bit.php';
require_once dirname(__FILE__).'/Base.php';

class IO_SWF_Tag_Jpeg extends IO_SWF_Tag_Base {
    var $_CharacterID;
    var $_AlphaDataOffset = null;
    var $_JPEGData;
    var $_ZlibBitmapAlphaData = null;
    function parseContent($tagCode, $content, $opts = array()) {
        $reader = new IO_Bit();
    	$reader->input($content);
        if ($tagCode != 8) { // ! JPEGTablesa
            $this->_CharacterID = $reader->getUI16LE();
        }
        if ($tagCode == 35) { // DefgineBitsJPEG3
            $alphaDataOffset = $reader->getUI32LE();
            $this->_AlphaDataOffset = $alphaDataOffset;
        }
        if ($tagCode != 35) { // ! DefgineBitsJPEG3
            $this->_JPEGData = $reader->getDataUntil(false);
        } else {
            $this->_JPEGData = $reader->getData($alphaDataOffset);
            $this->_ZlibBitmapAlphaData = $reader->getDataUntil(false);
        }
    }

    function dumpContent($tagCode, $opts = array()) {
        if ($tagCode != 8) { // ! JPEGTables
            echo "\tCharacterID:{$this->_CharacterID}\n";
        }
        if ($tagCode == 35) { // DefineBitsJPEG3
            echo "\tAlphaDataOffset:{$this->_AlphaDataOffset}\n";
        }
    }

    function buildContent($tagCode, $opts = array()) {
        $writer = new IO_Bit();
        if ($tagCode != 8) { // ! JPEGTablesa
            $writer->putUI16LE($this->_CharacterID);
        }
        if ($tagCode == 35) { // DefgineBitsJPEG3
            $this->_AlphaDataOffset = strlen($this->_JPEGData);
            $writer->putUI32LE($this->_AlphaDataOffset);
        }
        if ($tagCode != 35) { // ! DefgineBitsJPEG3
            $writer->putData($this->_JPEGData);
        } else {
            $writer->putData($this->_JPEGData);
            $writer->putData($this->_ZlibBitmapAlphaData);
        }
    	return $writer->output();
    }
}
