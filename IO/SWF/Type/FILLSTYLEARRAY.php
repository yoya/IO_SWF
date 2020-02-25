<?php

/*
 * 2011/4/15- (c) yoya@awm.jp
 */

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/Bit.php';
}
require_once dirname(__FILE__).'/../Type.php';
require_once dirname(__FILE__).'/FILLSTYLE.php';

class IO_SWF_Type_FILLSTYLEARRAY implements IO_SWF_Type {
    static function parse(&$reader, $opts = array()) {
        $tagCode = $opts['tagCode'];
        $fillStyles = array();

        // FillStyle
        $fillStyleCount = $reader->getUI8();
        if (($tagCode > 2) && ($fillStyleCount == 0xff)) {
           // DefineShape2 以降は 0xffff サイズまで扱える
           $fillStyleCount = $reader->getUI16LE();
        }
        for ($i = 0 ; $i < $fillStyleCount ; $i++) {
            $fillStyles[] = IO_SWF_Type_FILLSTYLE::parse($reader, $opts);
        }
        return $fillStyles;
    }
    static function build(&$writer, $fillStyles, $opts = array()) {
        $tagCode = $opts['tagCode'];
        $fillStyleCount = count($fillStyles);
        if ($fillStyleCount < 0xff) {
            $writer->putUI8($fillStyleCount);
        } else {
            $writer->putUI8(0xff);
            if ($tagCode > 2) {
                $writer->putUI16LE($fillStyleCount);
            } else {
                $fillStyleCount = 0xff; // DefineShape(1)
            }
        }
        foreach ($fillStyles as $fillStyle) {
            IO_SWF_Type_FILLSTYLE::build($writer, $fillStyle, $opts);
        }
        return true;
    }
    static function string($fillStyles, $opts = array()) {
        $text = '';
        if (count($fillStyles) > 0) {
            foreach ($fillStyles as $idx => $fillStyle) {
                $text .= "    [" . ($idx + 1) . "] ";
                $text .= IO_SWF_Type_FILLSTYLE::string($fillStyle, $opts);
            }
        } else {
            $text .= "    (none)\n";
        }
        return $text;
    }
}
