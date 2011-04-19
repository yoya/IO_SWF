<?php

/*
 * 2011/4/15- (c) yoya@awm.jp
 */

require_once 'IO/Bit.php';
require_once dirname(__FILE__).'/../Type.php';

class IO_SWF_Type_LINESTYLEARRAY extends IO_SWF_Type {
    static function parse(&$reader, $opts = array()) {
        $lineStyles = array();
        // LineStyle
        $lineStyleCount = $reader->getUI8();
        if (($tagCode > 2) && ($lineStyleCount == 0xff)) {
            // DefineShape2 以降は 0xffff サイズまで扱える
            $lineStyleCount = $reader->getUI16LE();
        }
        for ($i = 0 ; $i < $lineStyleCount ; $i++) {
            $lineStyle = array();
            $lineStyle['Width'] = $reader->getUI16LE();
            if ($tagCode < 32 ) { // 32:DefineShape3
                $lineStyle['Color'] = IO_SWF_Type_RGB::parse($reader);
            } else {
                $lineStyle['Color'] = IO_SWF_Type_RGBA::parse($reader);
            }
            $lineStyles[] = $lineStyle;
        }
        return $lineStyles;
    }
    static function build(&$writer, $lineStyles, $opts = array()) {
        $tagCode = $opts['tagCode'];
        $lineStyleCount = count($lineStyles);
        if ($lineStyleCount < 0xff) {
            $writer->putUI8($lineStyleCount);
        } else {
            $writer->putUI8(0xff);
            if ($tagCode > 2) {
                $writer->putUI16LE($lineStyleCount);
            } else {
                $lineStyleCount = 0xff; // DefineShape(1)
            }
        }
        foreach ($lineStyles as $lineStyle) {
            $writer->putUI16LE($lineStyle['Width']);
            if ($tagCode < 32 ) { // 32:DefineShape3
                IO_SWF_Type_RGB::build($writer, $lineStyle['Color']);
            } else {
                IO_SWF_Type_RGBA::build($writer, $lineStyle['Color']);
            }
        }
        return true;
    }
    static function string($lineStyles, $opts = array()) {
        $tagCode = $opts['tagCode'];
        $text = '';
        foreach ($lineStyles as $lineStyle) {
            $width = $lineStyle['Width'];
            $color = $lineStyle['Color'];
            if ($tagCode < 32 ) { // 32:DefineShape3
                $color_str = IO_SWF_Type_RGB::string($color);
            } else {
                $color_str = IO_SWF_Type_RGBA::string($color);
            }
            $text .= "\tWitdh: $width Color: $color_str\n";
        }
        return $text;
    }
}
