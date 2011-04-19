<?php

/*
 * 2011/4/15- (c) yoya@awm.jp
 */

require_once 'IO/Bit.php';
require_once dirname(__FILE__).'/../Type.php';

class IO_SWF_Type_RGB extends IO_SWF_Type {
    static function parse(&$reader, $opts = array()) {
    	$rgb = array();
    	$rgb['Red'] = $reader->getUI8();
    	$rgb['Green'] = $reader->getUI8();
    	$rgb['Blue'] = $reader->getUI8();
    	return $rgb;
    }
    static function build(&$writer, $rgb, $opts = array()) {
    	$writer->putUI8($rgb['Red']);
    	$writer->putUI8($rgb['Green']);
    	$writer->putUI8($rgb['Blue']);
    }
    static function string($color, $opts = array()) {
    	return sprintf("#%02x%02x%02x", $color['Red'], $color['Green'], $color['Blue']);
    }
}
