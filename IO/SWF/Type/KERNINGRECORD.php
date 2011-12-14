<?php

/*
 * 2011/4/15- (c) yoya@awm.jp
 */

require_once 'IO/Bit.php';
require_once dirname(__FILE__).'/../Type.php';

class IO_SWF_Type_KERNINGRECORD extends IO_SWF_Type {
    static function parse(&$reader, $opts = array()) {
    	$kerningrecord = array();
        if ($opts['FontFlagsWideCodes']) {
            $kerningrecord['FontKerningCode1'] = $reader->getUI16LE();
            $kerningrecord['FontKerningCode2'] = $reader->getUI16LE();
        } else {
            $kerningrecord['FontKerningCode1'] = $reader->getUI8();
            $kerningrecord['FontKerningCode2'] = $reader->getUI8();
        }
    	$kerningrecord['FontKerningAdjustment'] = $reader->getSI16LE();
    	return $kerningrecord;
    }
    static function build(&$writer, $kerningrecord, $opts = array()) {
        if ($opts['FontFlagsWideCodes']) {
            $writer->getUI16LE($kerningrecord['FontKerningCode1']);
            $writer->getUI16LE($kerningrecord['FontKerningCode2']);
        } else {
            $writer->getUI8($kerningrecord['FontKerningCode1']);
            $writer->getUI8($kerningrecord['FontKerningCode2']);
        }
        $writer->getSI16LE($kerningrecord['FontKerningAdjustment']);
    }
    static function string($kerningrecord, $opts = array()) {
        $text = "FontKerningCode1: {$kerningrecord['FontKerningCode1']}";
        $text .= " FontKerningCode2: {$kerningrecord['FontKerningCode2']}";
        $text .= " FontKerningAdjustment: {$kerningrecord['FontKerningAdjustment']}";
        return $text;
    }
}
