<?php

/*
 * EncodedU32: 1-5 バイトの可変長符号なし整数 (下位 7 bit ずつ、MSB が継続フラグ)
 * DefineSceneAndFrameLabelData などで使う。
 */

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/Bit.php';
}
require_once dirname(__FILE__).'/../Type.php';
require_once dirname(__FILE__).'/../Exception.php';

class IO_SWF_Type_EncodedU32 implements IO_SWF_Type {
    static function parse(&$reader, $opts = array()) {
        $value = 0;
        for ($i = 0; $i < 5; $i++) {
            $b = $reader->getUI8();
            $value |= ($b & 0x7f) << (7 * $i);
            if (($b & 0x80) === 0) {
                break;
            }
        }
        return $value & 0xFFFFFFFF;
    }
    static function build(&$writer, $value, $opts = array()) {
        if ($value < 0 || $value > 0xFFFFFFFF) {
            throw new IO_SWF_Exception("EncodedU32 out of range: $value");
        }
        do {
            $b = $value & 0x7f;
            $value >>= 7;
            if ($value > 0) {
                $b |= 0x80;
            }
            $writer->putUI8($b);
        } while ($value > 0);
    }
    static function string($value, $opts = array()) {
        return (string) $value;
    }
}
