<?php

/*
 * 2011/8/22- (c) yoya@awm.jp
 */

require_once 'IO/Bit.php';
require_once dirname(__FILE__).'/../Type.php';
require_once dirname(__FILE__).'/RGB.php';
require_once dirname(__FILE__).'/RGBA.php';
require_once dirname(__FILE__).'/GLYPHENTRY.php';


class IO_SWF_Type_TEXTRECORD extends IO_SWF_Type {
    static function parse(&$reader, $opts = array()) {
    	$textrecord = array();
        $reader->byteAlign();
        $textrecord['TextRecordType'] = $reader->getUIBit();
        $textrecord['StyleFlagsReserved'] = $reader->getUIBits(3);
        $styleFlagsHasFont = $reader->getUIBit();
        $styleFlagsHasColor = $reader->getUIBit();
        $styleFlagsHasYOffeet = $reader->getUIBit();
        $styleFlagsHasXOffeet = $reader->getUIBit();
        $textrecord['StyleFlagsHasFont'] = $styleFlagsHasFont;
        $textrecord['StyleFlagsHasColor'] = $styleFlagsHasColor;
        $textrecord['StyleFlagsHasYOffeet'] = $styleFlagsHasYOffeet;
        $textrecord['StyleFlagsHasXOffeet'] = $styleFlagsHasXOffeet;
        //
        if ($styleFlagsHasFont) {
            $textrecord['FontID'] = $reader->getUI16LE();
        }
        if ($styleFlagsHasColor) {
            if ($opts['tagCode'] == 11) {// DefintText
                $textrecord['TextColor'] = IO_SWF_Type_RGB::parse($reader);
            } else { // DefineText2
                $textrecord['TextColor'] = IO_SWF_Type_RGBA::parse($reader);
            }
        }
        if ($styleFlagsHasXOffeet) {
            $textrecord['XOffset'] = $reader->getUI16LE();
        }
        if ($styleFlagsHasYOffeet) {
            $textrecord['YOffset'] = $reader->getUI16LE();
        }
        if ($styleFlagsHasFont) {
            $textrecord['TextHeight'] = $reader->getUI16LE();
        }
        $glyphCount = $reader->getUI8();
        $textrecord['GlyphCount'] = $glyphCount;
        $glyphEntries = array();
        for ($i = 0 ; $i < $glyphCount; $i++) {
            $glyphEntries []= IO_SWF_Type_GLYPHENTRY::parse($reader, $opts);
        }
        $textrecord['GlyphEntries'] = $glyphEntries;
    	return $textrecord;
    }
    static function build(&$writer, $textrecord, $opts = array()) {
        $writer->byteAlign();
        $writer->putUIBit($textrecord['TextRecordType']);
        $writer->putUIBits($textrecord['StyleFlagsReserved'], 3);

        $styleFlagsHasFont = $textrecord['StyleFlagsHasFont'];
        $styleFlagsHasColor = $textrecord['StyleFlagsHasColor'];
        $styleFlagsHasYOffeet = $textrecord['StyleFlagsHasYOffeet'];
        $styleFlagsHasXOffeet = $textrecord['StyleFlagsHasXOffeet'];
        $writer->putUIBit($styleFlagsHasFont);
        $writer->putUIBit($styleFlagsHasColor);
        $writer->putUIBit($styleFlagsHasYOffeet);
        $writer->putUIBit($styleFlagsHasXOffeet);
        //
        if ($styleFlagsHasFont) {
            $writer->putUI16LE($textrecord['FontID']);
        }
        if ($styleFlagsHasColor) {
            if ($opts['tagCode'] == 11) {// DefintText
                IO_SWF_Type_RGB::build($writer, $textrecord['TextColor']);
            } else { // DefineText2
                IO_SWF_Type_RGBA::build($writer, $textrecord['TextColor']);
            }
        }
        if ($styleFlagsHasXOffeet) {
            $writer->putUI16LE($textrecord['XOffset']);
        }
        if ($styleFlagsHasYOffeet) {
            $writer->putUI16LE($textrecord['YOffset']);
        }
        if ($styleFlagsHasFont) {
            $writer->putUI16LE($textrecord['TextHeight']);
        }
        $glyphCount = count($textrecord['GlyphEntries']);
        $writer->putUI8($glyphCount);
        foreach ($textrecord['GlyphEntries'] as $glyphEntrie) {
            IO_SWF_Type_GLYPHENTRY::build($writer, $glyphEntrie, $opts);
        }
    }
    static function string($textrecord, $opts = array()) {
        $text = '';
        if ($textrecord['StyleFlagsHasFont']) {
            $text .= sprintf("FontID:%d TextHeight:%d ",
                             $textrecord['FontID'],
                             $textrecord['TextHeight']);
        }
        if ($textrecord['StyleFlagsHasColor']) {
            if ($opts['tagCode'] == 11) {// DefintText
                $color_str = IO_SWF_Type_RGB::string($textrecord['TextColor']);
            } else { // DefineText2
                $color_str = IO_SWF_Type_RGBA::string($textrecord['TextColor']);
            }
            $text .= "TextColor:$color_str ";
        }
        if ($textrecord['StyleFlagsHasXOffeet']) {
            $text .= sprintf("XOffset: %s", $textrecord['XOffset']);
        }
        if ($textrecord['StyleFlagsHasYOffeet']) {
            $text .= sprintf("YOffset: %s", $textrecord['YOffset']);
        }
        if ($text != '') {
            $text .= "\n\t";
        }

        $text .= "GryphEntries:";
        foreach ($textrecord['GlyphEntries'] as $glyphEntrie) {
            $text .= "\n\t\t".IO_SWF_Type_GLYPHENTRY::string($glyphEntrie, $opts);
        }
        $text .= "\n";
        return $text;
    }
}
