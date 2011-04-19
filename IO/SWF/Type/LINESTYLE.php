<?php

/*
 * 2011/4/15- (c) yoya@awm.jp
 */

require_once 'IO/Bit.php';
require_once dirname(__FILE__).'/../Type.php';

class IO_SWF_Type_LINESTYLE extends IO_SWF_Type {
    static function parse(&$reader, $opts = array()) {
        $lineStyle = array();
        $lineStyle['Width'] = $reader->getUI16LE();
        if ($tagCode < 32 ) { // 32:DefineShape3
            $lineStyle['Color'] = IO_SWF_Type_RGB::parse($reader);
        } else {
            $lineStyle['Color'] = IO_SWF_Type_RGBA::parse($reader);
        }
        return  $lineStyle;
    }
    static function build(&$writer, $lineStyle, $opts = array()) {
        $tagCode = $opts['tagCode'];
        $writer->putUI16LE($lineStyle['Width']);
        if ($tagCode < 32 ) { // 32:DefineShape3
            IO_SWF_Type_RGB::build($writer, $lineStyle['Color']);
        } else {
            IO_SWF_Type_RGBA::build($writer, $lineStyle['Color']);
        }
        return true;
    }
    static function string($lineStyle, $opts = array()) {
        $tagCode = $opts['tagCode'];
        $text = '';
        $width = $lineStyle['Width'];
        $color = $lineStyle['Color'];
        if ($tagCode < 32 ) { // 32:DefineShape3
            $color_str = IO_SWF_Type_RGB::string($color);
        } else {
            $color_str = IO_SWF_Type_RGBA::string($color);
        }
        $text .= "\tWitdh: $width Color: $color_str\n";
        return $text;
    }
}
