<?php

require_once 'IO/Bit.php';
require_once dirname(__FILE__).'/Base.php';
require_once dirname(__FILE__).'/../Type/SHAPE.php';

class IO_SWF_Tag_Font extends IO_SWF_Tag_Base {
    var $FontID;
    var $FontFlagsWideOffsets;
    var $LanguageCode;
    var $FontNameLen;
    var $FontName;
    var $OffsetTable = array();
    function parseContent($tagCode, $content, $opts = array()) {
        $reader = new IO_Bit();
    	$reader->input($content);
        $this->FontID = $reader->getUI16LE();
        $fontFlagsHasLayout = $reader->getUIBit();
        $fontFlagsShiftJIS = $reader->getUIBit();
        $fontFlagsSmallText = $reader->getUIBit();
        $fontFlagsANSI = $reader->getUIBit();
        $this->FontFlagsWideOffsets = $reader->getUIBit();
        $fontFlagsWideCodes = $reader->getUIBit();
        $fontFlagsItalic = $reader->getUIBit();
        $fontFlagsBold = $reader->getUIBit();
        $this->LanguageCode = IO_SWF_Type_LANGCODE::parse($reader);
        $fontNameLen = $reader->getUI8();
        $this->FontName = $reader->getData($fontNameLen);
        $numGlyphs = $reader->getUI16LE();
        list($startOfOffsetTable, $dummy) = 
        if ($this->FontFlagsWideOffsets) {
            for ($i = 0 ; $i < $numGlyphs ; $i++) {
                $this->OffsetTable []= $reader->getUI32LE();
            }
        } else {
            for ($i = 0 ; $i < $numGlyphs ; $i++) {
                $this->OffsetTable []= $reader->getUI16LE();
            }
        }
        if ($this->FontFlagsWideOffsets) {
            $codeTableOffset  = $reader->getUI32LE();
        } else {
            $codeTableOffset  = $reader->getUI16LE();
        }
    }

    function dumpContent($tagCode, $opts = array()) {
        $color_str = IO_SWF_Type_RGB::string($this->_color);
        echo "\tColor: $color_str\n";
    }

    function buildContent($tagCode, $opts = array()) {
        $writer = new IO_Bit();
        $writer->getUI16LE($this->FontID);

    	return $writer->output();
    }
}
