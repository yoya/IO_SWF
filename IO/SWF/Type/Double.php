<?php

/*
 * 2011/6/27- (c) yoya@awm.jp
 */

require_once 'IO/Bit.php';
require_once dirname(__FILE__).'/../Type.php';

class IO_SWF_Type_Double extends IO_SWF_Type {
    static function parse(&$reader, $opts = array()) {
        $data = $reader->getData(8);
	$unpacked_data = unpack('d', $data);
    	return (double)$unpacked_data[1];
    }
    static function build(&$writer, $value, $opts = array()) {
        $data = pack('d', (double)$value);
    	$writer->putData($data, 8);
    }
    static function string($value, $opts = array()) {
    	return sprintf("(Double)%d", $value);
    }
}
