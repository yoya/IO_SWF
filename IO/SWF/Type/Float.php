<?php

/*
 * 2011/6/27- (c) yoya@awm.jp
 */

require_once 'IO/Bit.php';
require_once dirname(__FILE__).'/../Type.php';

class IO_SWF_Type_Float extends IO_SWF_Type {
    static function parse(&$reader, $opts = array()) {
        $data = $reader->getData(4);
	$unpacked_data = unpack('f', $data);
    	return (float)$unpacked_data[1];
    }
    static function build(&$writer, $value, $opts = array()) {
        $data = pack('f', (float)$value);
    	$writer->putData($data, 4);
    }
    static function string($value, $opts = array()) {
    	return sprintf("(Float)%f", $value);
    }
}
