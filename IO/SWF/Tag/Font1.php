<?php

/*
 * DefineFont (10)
 *   FontID UI16
 *   OffsetTable UI16[nGlyphs]   (各グリフの OffsetTable 先頭からのバイト位置。
 *                                nGlyphs = OffsetTable[0] / 2)
 *   GlyphShapeTable SHAPE[nGlyphs]
 * DefineFont2/3 は Tag_Font が扱う。
 */

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/Bit.php';
}
require_once dirname(__FILE__).'/Base.php';
require_once dirname(__FILE__).'/../Type/SHAPE.php';

class IO_SWF_Tag_Font1 extends IO_SWF_Tag_Base {
    var $CharacterID;              // FontID
    var $GlyphShapeTable = array();

    function parseContent($tagCode, $content, $opts = array()) {
        $opts['tagCode'] = $tagCode;
        $reader = new IO_Bit();
        $reader->input($content);
        $this->CharacterID = $reader->getUI16LE();
        $this->GlyphShapeTable = array();
        if (! $reader->hasNextData(2)) {
            return; // グリフなし
        }
        list($startOfOffsetTable, $dummy) = $reader->getOffset();
        $firstOffset = $reader->getUI16LE();
        $numGlyphs = (int) ($firstOffset / 2);
        $offsetTable = array($firstOffset);
        for ($i = 1; $i < $numGlyphs; $i++) {
            $offsetTable []= $reader->getUI16LE();
        }
        for ($i = 0; $i < $numGlyphs; $i++) {
            $reader->setOffset($startOfOffsetTable + $offsetTable[$i], 0);
            $this->GlyphShapeTable []= IO_SWF_Type_SHAPE::parse($reader, $opts);
            $reader->byteAlign();
        }
    }

    function dumpContent($tagCode, $opts = array()) {
        $opts['tagCode'] = $tagCode;
        echo "    FontID: {$this->CharacterID}\n";
        echo "    GlyphShapeTable: (".count($this->GlyphShapeTable)." glyphs)\n";
        foreach ($this->GlyphShapeTable as $idx => $glyph) {
            echo "      [$idx]\n";
            echo IO_SWF_Type_SHAPE::string($glyph, $opts);
        }
    }

    function buildContent($tagCode, $opts = array()) {
        $opts['tagCode'] = $tagCode;
        $writer = new IO_Bit();
        $writer->putUI16LE($this->CharacterID);
        $numGlyphs = count($this->GlyphShapeTable);
        if ($numGlyphs === 0) {
            return $writer->output();
        }
        list($startOfOffsetTable, $dummy) = $writer->getOffset();
        $offsetPositions = array();
        for ($i = 0; $i < $numGlyphs; $i++) {
            list($offsetPositions[$i], $dummy) = $writer->getOffset();
            $writer->putUI16LE(0); // dummy (後で埋める)
        }
        // グリフはフィルスタイル 1 つ、ラインスタイルなしで描かれる
        $opts['fillStyleCount'] = 1;
        $opts['lineStyleCount'] = 0;
        foreach ($this->GlyphShapeTable as $idx => $glyphShape) {
            list($startOfGlyphShape, $dummy) = $writer->getOffset();
            $writer->setUI16LE($startOfGlyphShape - $startOfOffsetTable, $offsetPositions[$idx]);
            IO_SWF_Type_SHAPE::build($writer, $glyphShape, $opts);
            $writer->byteAlign();
        }
        return $writer->output();
    }
}
