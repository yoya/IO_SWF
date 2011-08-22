<?php

/*
 * 2011/8/22- (c) yoya@awm.jp
 */

require_once 'IO/Bit.php';
require_once dirname(__FILE__).'/../Type.php';

class IO_SWF_Type_GLYPHENTRY extends IO_SWF_Type {
    static function parse(&$reader, $opts = array()) {
    	$glyphentry = array();
    	$glyphentry['GlyphIndex'] = $reader->getUIBits($opts['GlyphBits']);
        $glyphentry['GlyphAdvance'] = $reader->getUIBits($opts['AdvanceBits']);
    	return $glyphentry;
    }
    static function build(&$writer, $glyphentry, $opts = array()) {
    	$writer->putUIBits($glyphentry['GlyphIndex'], $opts['GlyphBits']);
    	$writer->putUIBits($glyphentry['GlyphAdvance'], $opts['AdvanceBits']);
    }
    static function string($glyphentry, $opts = array()) {
    	return sprintf("GlyphIndex:%s GlyphAdvance:%s", $glyphentry['GlyphIndex'], $glyphentry['GlyphAdvance']);
    }
}
