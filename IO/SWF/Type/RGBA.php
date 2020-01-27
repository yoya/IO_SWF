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

class IO_SWF_Type_RGBA implements IO_SWF_Type {
    static function parse(&$reader, $opts = array()) {
    	$rgba = array();
    	$rgba['Red'] = $reader->getUI8();
    	$rgba['Green'] = $reader->getUI8();
    	$rgba['Blue'] = $reader->getUI8();
    	$rgba['Alpha'] = $reader->getUI8();
    	return $rgba;
    }
    static function build(&$writer, $rgba, $opts = array()) {
    	$writer->putUI8($rgba['Red']);
	    $writer->putUI8($rgba['Green']);
    	$writer->putUI8($rgba['Blue']);
    	$writer->putUI8($rgba['Alpha']);
    }
    static function string($color, $opts = array()) {
    	return sprintf("#%02x%02x%02x(%02x)", $color['Red'], $color['Green'], $color['Blue'], $color['Alpha']);
    }
}
