<?php

require_once 'IO/Bit.php';
require_once dirname(__FILE__).'/Base.php';
require_once dirname(__FILE__).'/../Type/LANGCODE.php';
require_once dirname(__FILE__).'/../Type/SHAPE.php';
require_once dirname(__FILE__).'/../Type/KERNINGRECORD.php';

class IO_SWF_Tag_Font extends IO_SWF_Tag_Base {
    var $FontID;
    var $FontFlagsWideOffsets;
    var $FontFlagsWideCodes;
    var $LanguageCode;
    var $FontName;
    var $OffsetTable = array();
    var $GlyphShapeTable = array();
    var $CodeTable = array();
    // Layout Information
    var $FontAscent;
    var $FontDescent;
    var $FontLeading;
    var $FontAdvanceTable;
    var $FontBoundsTable;
    var $FontKerningTable;
    function parseContent($tagCode, $content, $opts = array()) {
        $reader = new IO_Bit();
    	$reader->input($content);
        $this->FontID = $reader->getUI16LE();
        $fontFlagsHasLayout = $reader->getUIBit();
        $fontFlagsShiftJIS = $reader->getUIBit();
        $fontFlagsSmallText = $reader->getUIBit();
        $fontFlagsANSI = $reader->getUIBit();
        $this->FontFlagsWideOffsets = $reader->getUIBit();
        $this->FontFlagsWideCodes = $reader->getUIBit();
        $fontFlagsItalic = $reader->getUIBit();
        $fontFlagsBold = $reader->getUIBit();
        $this->LanguageCode = IO_SWF_Type_LANGCODE::parse($reader);
        $fontNameLen = $reader->getUI8();
        $this->FontName = $reader->getData($fontNameLen);
        $numGlyphs = $reader->getUI16LE();
        list($startOfOffsetTable, $dummy) = $reader->getOffset();
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
        for ($i = 0 ; $i < $numGlyphs ; $i++) {
            $this->GlyphShapeTable []= IO_SWF_Type_SHAPE::parse($reader, $opts);
        }
        list($startOfOffsetCodeTable, $dummy) = $reader->getOffset();
        if ($startOfOffsetCodeTable != $startOfOffsetTable + $codeTableOffset) {
            // trigger_error("startOfOffsetCodeTable:$startOfOffsetCodeTable != startOfOffsetTable:$startOfOffsetTable + codeTableOffset:$codeTableOffset", E_USER_WARNING);
        }
        $reader->setOffset($startOfOffsetTable + $codeTableOffset, 0);
        if ($this->FontFlagsWideCodes) {
            for ($i = 0 ; $i < $numGlyphs ; $i++) {
                $this->CodeTable []= $reader->getUI16LE();
            }
        } else {
            for ($i = 0 ; $i < $numGlyphs ; $i++) {
                $this->CodeTable []= $reader->getUI8();
            }
        }
        if ($fontFlagsHasLayout) {
            $this->FontAscent = $reader->getSI16LE();
            $this->FontDescent = $reader->getSI16LE();
            $this->FontLeading = $reader->getSI16LE();
            $this->FontAdvanceTable[] = array();
            for ($i = 0 ; $i < $numGlyphs ; $i++) {
                $this->FontAdvanceTable[] = $reader->getSI16LE();
            }
            $this->FontBoundsTable[] = array();
            for ($i = 0 ; $i < $numGlyphs ; $i++) {
                $this->FontBoundsTable[] = IO_SWF_TYPE_RECT::parse($reader);
            }
            $kerningCount =  $reader->getUI16LE();
            if ($kerningCount > 0) {
                $this->FontKerningTable = array();
                for ($i = 0 ; $i < $kerningCount ; $i++) {
                    $opts = array('FontFlagsWideCodes' => $this->FontFlagsWideCodes);
                    $this->FontKerningTable[] = IO_SWF_TYPE_KERNINGRECORD::parse($reader, $opts);
                }
            }
        }
    
    }

    function dumpContent($tagCode, $opts = array()) {
        echo "\tFontID: {$this->FontID} FontFlagsWideOffsets: {$this->FontFlagsWideOffsets} FontFlagsWideCodes: {$this->FontFlagsWideCodes}".PHP_EOL;;
        echo "\tLanguageCode: ".IO_SWF_Type_LANGCODE::string($this->LanguageCode)."FontName: {$this->FontName}".PHP_EOL;
        echo "\tOffsetTable:";
        foreach ($this->OffsetTable as $idx => $offset) {
            echo " [$idx]$offset";
        }
        echo PHP_EOL;
        echo "\tGlyphShapeTable:".PHP_EOL;
        $opts['indent']  = 2;
        foreach ($this->GlyphShapeTable as $idx => $glyph) {
            echo IO_SWF_Type_SHAPE::string($glyph, $opts);
        }
        echo "\tCodeTable:";
        foreach ($this->CodeTable as $idx => $c) {
            echo " [$idx]$c";
        }
        echo PHP_EOL;
        if ($this->FontAscent) {
            echo "\tFontAscent: {$this->FontAscent} FontDescent: {$this->FontDescent} FontLeading: {$this->FontLeading}".PHP_EOL;
            foreach ($this->FontAdvanceTable as $idx => $advance) {
                echo " [$idx]$advance";
            }
            echo PHP_EOL;
            echo "\tFontBoundsTable:";
            echo IO_SWF_TYPE_RECT::string($this->FontBoundsTable);
        } else {
            echo "\t(FontFlagsHasLayout is false)".PHP_EOL;
        }
        echo "\tFontAdvanceTable:";
        if ($this->FontKerningTable) {
            echo "FontKerningTable:".PHP_EOL;
            foreach ($this->FontKerningTable as $fontKerning) {
                echo "\t\t".IO_SWF_Type_KERNINGRECORD::string($fontkerning).PHP_EOL;
            }
        } else {
            echo "\t(FontKerningTable is null)".PHP_EOL;
        }
    }

    function buildContent($tagCode, $opts = array()) {
        $writer = new IO_Bit();
        $writer->getUI16LE($this->FontID);

    	return $writer->output();
    }
}
