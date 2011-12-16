<?php

require_once 'IO/Bit.php';
require_once dirname(__FILE__).'/Base.php';
require_once dirname(__FILE__).'/../Type/LANGCODE.php';
require_once dirname(__FILE__).'/../Type/SHAPE.php';
require_once dirname(__FILE__).'/../Type/KERNINGRECORD.php';

class IO_SWF_Tag_Font extends IO_SWF_Tag_Base {
    var $FontID;
    var $FontFlagsShiftJIS;
    var $FontFlagsSmallText;
    var $FontflagsANSI;
    var $FontFlagsWideOffsets;
    var $FontFlagsWideCodes;
    var $FontFlagsItalic;
    var $FontFlagsBold;
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
        $this->FontFlagsShiftJIS = $reader->getUIBit();
        $this->FontFlagsSmallText = $reader->getUIBit();
        $this->FontFlagsANSI = $reader->getUIBit();
        $this->FontFlagsWideOffsets = $reader->getUIBit();
        $this->FontFlagsWideCodes = $reader->getUIBit();
        $this->FontFlagsItalic = $reader->getUIBit();
        $this->FontFlagsBold = $reader->getUIBit();
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
            $reader->setOffset($startOfOffsetTable + $this->OffsetTable[$i], 0);
            $this->GlyphShapeTable []= IO_SWF_Type_SHAPE::parse($reader, $opts);
            $reader->byteAlign();
        }
        list($startOfOffsetCodeTable, $dummy) = $reader->getOffset();
        if ($startOfOffsetCodeTable != $startOfOffsetTable + $codeTableOffset) {
            trigger_error("startOfOffsetCodeTable:$startOfOffsetCodeTable != startOfOffsetTable:$startOfOffsetTable + codeTableOffset:$codeTableOffset", E_USER_WARNING);
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
            $this->FontBoundsTable = array();
            for ($i = 0 ; $i < $numGlyphs ; $i++) {
                $this->FontBoundsTable[] = IO_SWF_TYPE_RECT::parse($reader);
            }
            $kerningCount =  $reader->getUI16LE();
            if ($kerningCount > 0) {
                $this->FontKerningTable = array();
                $opts['FontFlagsWideCodes'] = $this->FontFlagsWideCodes;
                for ($i = 0 ; $i < $kerningCount ; $i++) {
                    $this->FontKerningTable[] = IO_SWF_TYPE_KERNINGRECORD::parse($reader, $opts);
                }
            }
        }    
    }

    function dumpContent($tagCode, $opts = array()) {
        echo "    FontID: {$this->FontID}".PHP_EOL;
        $fontFlagsHasLayout = is_null($this->FontAscent)?0:1;
        echo "FontFlagsHasLayout: $fontFlagsHasLayout FontFlagsShiftJIS: {$this->FontFlagsShiftJIS} FontFlagsSmallText: {$this->FontFlagsSmallText} FontFlagsANSI: {$this->FontFlagsANSI}".PHP_EOL;
        echo "FontFlagsWideOffsets: {$this->FontFlagsWideOffsets} FontFlagsWideCodes: {$this->FontFlagsWideCodes}".PHP_EOL;
        
        echo "    LanguageCode: ".IO_SWF_Type_LANGCODE::string($this->LanguageCode)."FontName: {$this->FontName}".PHP_EOL;
        echo "    OffsetTable:";
        foreach ($this->OffsetTable as $idx => $offset) {
            echo " [$idx]$offset";
        }
        echo PHP_EOL;
        echo "    GlyphShapeTable:".PHP_EOL;
        $opts['indent'] = 1;
        foreach ($this->GlyphShapeTable as $idx => $glyph)
        {
            echo "      [$idx]".PHP_EOL;
            echo IO_SWF_Type_SHAPE::string($glyph, $opts);
        }
        echo "    CodeTable:";
        foreach ($this->CodeTable as $idx => $c) {
            echo " [$idx]$c";
        }
        echo PHP_EOL;
        if ($this->FontAscent) {
            echo "    FontAscent: {$this->FontAscent} FontDescent: {$this->FontDescent} FontLeading: {$this->FontLeading}".PHP_EOL;
            foreach ($this->FontAdvanceTable as $idx => $advance) {
                echo " [$idx]$advance";
            }
            echo PHP_EOL;
            echo "    FontBoundsTable:";
            foreach ($this->FontBoundsTable as $idx => $fontBounds) {
                echo "\t[$idx]".IO_SWF_TYPE_RECT::string($fontBounds).PHP_EOL;
            }
        } else {
            echo "    (FontFlagsHasLayout is false)".PHP_EOL;
        }
        if ($this->FontKerningTable) {
            echo "    FontKerningTable:".PHP_EOL;
            foreach ($this->FontKerningTable as $fontKerning) {
                echo "\t".IO_SWF_Type_KERNINGRECORD::string($fontkerning).PHP_EOL;
            }
        } else {
            echo "\t(FontKerningTable is null)".PHP_EOL;
        }
    }

    function buildContent($tagCode, $opts = array()) {
        $writer = new IO_Bit();
        $writer->putUI16LE($this->FontID);
        //
        $fontFlagsHasLayout = is_null($this->FontAscent)?0:1;
        //
        $writer->putUIBit($fontFlagsHasLayout);
        $writer->putUIBit($this->FontFlagsShiftJIS);
        $writer->putUIBit($this->FontFlagsSmallText);
        $writer->putUIBit($this->FontFlagsANSI);
        $writer->putUIBit($this->FontFlagsWideOffsets);
        $writer->putUIBit($this->FontFlagsWideCodes);
        $writer->putUIBit($this->FontFlagsItalic);
        $writer->putUIBit($this->FontFlagsBold);
        IO_SWF_Type_LANGCODE::build($writer, $this->LanguageCode);
        $fontNameLen = strlen($this->FontName);
        $writer->putUI8($fontNameLen);
        $writer->putData($this->FontName);
        $numGlyphs = count($this->OffsetTable);
        $writer->putUI16LE($numGlyphs);
        list($startOfOffsetTable, $dummy) = $writer->getOffset();
        $startOfOffsetTable2 = array();
        if ($this->FontFlagsWideOffsets) {
            foreach ($this->OffsetTable as $idx => $offset) {
                list($startOfOffsetTables[$idx], $dummy) = $writer->getOffset();
                $writer->putUI32LE(0); // dummy
            }
        } else {
            foreach ($this->OffsetTable as $idx => $offset) {
                list($startOfOffsetTables[$idx], $dummy) = $writer->getOffset();
                $writer->putUI16LE(0); // dummy
            }
        }
        list($startOfcodeTableOffset, $dummy) = $writer->getOffset();
        if ($this->FontFlagsWideOffsets) {
            $writer->putUI32LE(0); // dummy
        } else {
            $writer->putUI16LE(0); // dummy
        }
        $opts['fillStyleCount'] = 1;
        $opts['lineStyleCount'] = 0;
        foreach ($this->GlyphShapeTable as $idx => $glyphShape) {
            list($startOfGlyphShape, $dummy) = $writer->getOffset();
            if ($this->FontFlagsWideOffsets) {
                $writer->setUI32LE($startOfGlyphShape - $startOfOffsetTable, $startOfOffsetTables[$idx]);
            } else {
                $writer->setUI16LE($startOfGlyphShape - $startOfOffsetTable, $startOfOffsetTables[$idx]);
            }
            IO_SWF_Type_SHAPE::build($writer, $glyphShape, $opts);
            $writer->byteAlign();
        }
        //
        list($startOfCodeTable, $dummy) = $writer->getOffset();
        $codeTableOffset  = $startOfCodeTable - $startOfOffsetTable;
        if ($this->FontFlagsWideOffsets) {
            $writer->setUI32LE($codeTableOffset, $startOfcodeTableOffset);
        } else {
            $writer->setUI16LE($codeTableOffset, $startOfcodeTableOffset);
        }
        if ($this->FontFlagsWideCodes) {
            foreach ($this->CodeTable as $c) {
                $writer->putUI16LE($c);
            }
        } else {
            foreach ($this->CodeTable as $c) {
                $writer->putUI8($c);
            }
        }
        if ($fontFlagsHasLayout) {
            $writer->putSI16LE($this->FontAscent );
            $writer->putSI16LE($this->FontDescent);
            $writer->putSI16LE($this->FontLeading);
            foreach ($this->FontAdvanceTable as $fontAdvance) {
                $writer->putSI16LE($fontAdvance);
            }
            foreach ($this->FontBoundsTable as $fontBounds) {
                IO_SWF_TYPE_RECT::build($writer, $fontBounds);
            }
            if (is_null($this->FontKerningTable)) {
                $writer->putUI16LE(0);
            } else {
                $kerningCount = count($this->FontKerningTable);
                $writer->putUI16LE($kerningCount);
                $opts['FontFlagsWideCodes'] = $this->FontFlagsWideCodes;
                foreach ($this->FontKerningTable as $fontKerning) {
                    IO_SWF_TYPE_KERNINGRECORD::build($writer, $fontKerning, $opts);
                }
            }
        }    
    	return $writer->output();
    }
}
