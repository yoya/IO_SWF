<?php

/*
 * 2011/4/15- (c) yoya@awm.jp
 */

require_once 'IO/Bit.php';
require_once dirname(__FILE__).'/../Type.php';
require_once dirname(__FILE__).'/../Type/RGB.php';
require_once dirname(__FILE__).'/../Type/RGBA.php';

class IO_SWF_Type_LINESTYLE extends IO_SWF_Type {
    static function parse(&$reader, $opts = array()) {
        $tagCode = $opts['tagCode'];
        $isMorph = ($tagCode == 46) || ($tagCode == 84);
        $lineStyle = array();
        if ($isMorph === false) {
            $lineStyle['Width'] = $reader->getUI16LE();
            if ($tagCode < 32 ) { // 32:DefineShape3
                $lineStyle['Color'] = IO_SWF_Type_RGB::parse($reader);
            } else {
                $lineStyle['Color'] = IO_SWF_Type_RGBA::parse($reader);
            }
        } else {
            $lineStyle['StartWidth'] = $reader->getUI16LE();
            $lineStyle['EndWidth']   = $reader->getUI16LE();
            $lineStyle['StartColor'] = IO_SWF_Type_RGBA::parse($reader);
            $lineStyle['EndColor']   = IO_SWF_Type_RGBA::parse($reader);
        }
        return  $lineStyle;
    }
    static function build(&$writer, $lineStyle, $opts = array()) {
        $tagCode = $opts['tagCode'];
        $isMorph = ($tagCode == 46) || ($tagCode == 84);
        if ($isMorph === false) {
            $writer->putUI16LE($lineStyle['Width']);
            if ($tagCode < 32 ) { // 32:DefineShape3
                IO_SWF_Type_RGB::build($writer, $lineStyle['Color']);
            } else {
                IO_SWF_Type_RGBA::build($writer, $lineStyle['Color']);
            }
        } else {
            $writer->putUI16LE($lineStyle['StartWidth']);
            $writer->putUI16LE($lineStyle['EndWidth']);
            IO_SWF_Type_RGBA::build($writer, $lineStyle['StartColor']);
            IO_SWF_Type_RGBA::build($writer, $lineStyle['EndColor']);
        }
        return true;
    }
    static function string($lineStyle, $opts = array()) {
        $tagCode = $opts['tagCode'];
        $isMorph = ($tagCode == 46) || ($tagCode == 84);
        $text = '';

        if ($isMorph === false) {
            $width = $lineStyle['Width'];
            if ($tagCode < 32 ) { // 32:DefineShape3
                $color_str = IO_SWF_Type_RGB::string($lineStyle['Color']);
            } else {
                $color_str = IO_SWF_Type_RGBA::string($lineStyle['Color']);
            }
            $text .= "\tWitdh: $width Color: $color_str\n";
        } else {
            $startWidth = $lineStyle['StartWidth'];
            $endWidth = $lineStyle['EndWidth'];
            $startColorStr = IO_SWF_Type_RGBA::string($lineStyle['StartColor']);
            $endColorStr = IO_SWF_Type_RGBA::string($lineStyle['EndColor']);
            $text .= "\tWitdh: $startWidth => $endWidth Color: $startColorStr => $endColorStr\n";
        }
        return $text;
    }
}
