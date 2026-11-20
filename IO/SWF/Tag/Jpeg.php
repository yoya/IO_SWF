<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/Bit.php';
}
require_once dirname(__FILE__).'/Base.php';

class IO_SWF_Tag_Jpeg extends IO_SWF_Tag_Base {
    var $_CharacterID;
    var $_AlphaDataOffset = null;
    var $_JPEGData;
    var $_ZlibBitmapAlphaData = null;
    var $_DeblockParam = null; // DefineBitsJPEG4 (FIXED8)
    // DefineBitsJPEG3 (35) / DefineBitsJPEG4 (90) は AlphaDataOffset と
    // アルファデータを持つ。JPEG4 はさらに DeblockParam UI16 を持つ。
    static function hasAlpha($tagCode) {
        return ($tagCode == 35) || ($tagCode == 90);
    }
    function parseContent($tagCode, $content, $opts = array()) {
        $reader = new IO_Bit();
    	$reader->input($content);
        if ($tagCode != 8) { // ! JPEGTable
            $this->_CharacterID = $reader->getUI16LE();
        }
        if (self::hasAlpha($tagCode)) { // DefineBitsJPEG3, DefineBitsJPEG4
            $alphaDataOffset = $reader->getUI32LE();
            $this->_AlphaDataOffset = $alphaDataOffset;
            if ($tagCode == 90) { // DefineBitsJPEG4
                $this->_DeblockParam = $reader->getUI16LE();
            }
            $this->_JPEGData = $reader->getData($alphaDataOffset);
            $this->_ZlibBitmapAlphaData = $reader->getDataUntil(false);
        } else {
            $this->_JPEGData = $reader->getDataUntil(false);
        }
    }

    function dumpContent($tagCode, $opts = array()) {
        if ($tagCode != 8) { // ! JPEGTables
            echo "\tCharacterID:{$this->_CharacterID}\n";
        }
        if (self::hasAlpha($tagCode)) { // DefineBitsJPEG3, DefineBitsJPEG4
            echo "\tAlphaDataOffset:{$this->_AlphaDataOffset}";
            if ($tagCode == 90) {
                printf("  DeblockParam:%d (%.4f)", $this->_DeblockParam, $this->_DeblockParam / 256);
            }
            echo "\n";
        }
    }

    function buildContent($tagCode, $opts = array()) {
        $writer = new IO_Bit();
        if ($tagCode != 8) { // ! JPEGTablesa
            $writer->putUI16LE($this->_CharacterID);
        }
        if (self::hasAlpha($tagCode)) { // DefineBitsJPEG3, DefineBitsJPEG4
            $this->_AlphaDataOffset = strlen($this->_JPEGData);
            $writer->putUI32LE($this->_AlphaDataOffset);
            if ($tagCode == 90) { // DefineBitsJPEG4
                $writer->putUI16LE(is_null($this->_DeblockParam) ? 0 : $this->_DeblockParam);
            }
            $writer->putData($this->_JPEGData);
            $writer->putData($this->_ZlibBitmapAlphaData);
        } else {
            $writer->putData($this->_JPEGData);
        }
    	return $writer->output();
    }
}
