<?php

/*
 * 2011/6/27- (c) yoya@awm.jp
 */

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/Bit.php';
}
require_once dirname(__FILE__).'/../Type.php';

class IO_SWF_Type_Double implements IO_SWF_Type {
    static function parse(&$reader, $opts = array()) {
        $data = $reader->getData(8);
	$unpacked_data = unpack('d', $data);
    	return (float)$unpacked_data[1];
    }
    static function build(&$writer, $value, $opts = array()) {
        $data = pack('d', (float)$value);
    	$writer->putData($data, 8);
    }
    static function string($value, $opts = array()) {
    	return sprintf("(Double)%d", $value);
    }
}
