<?php

/*
 * 2011/4/15- (c) yoya@awm.jp
 */

require_once 'IO/Bit.php';
require_once dirname(__FILE__).'/../Type.php';
require_once dirname(__FILE__).'/LINESTYLE.php';


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
            $lineStyles[] = IO_SWF_Type_LINESTYLE::parse($reader, $opts);
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
            IO_SWF_Type_LINESTYLE::build($writer, $lineStyle);
        }
        return true;
    }
    static function string($lineStyles, $opts = array()) {
        $tagCode = $opts['tagCode'];
        $text = '';
        foreach ($lineStyles as $lineStyle) {
            $text .= IO_SWF_Type_LINESTYLE::string($lineStyle, $opts);
        }
        return $text;
    }
}
