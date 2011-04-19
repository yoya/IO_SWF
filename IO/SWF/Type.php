<?php

/*
 * 2011/1/25- (c) yoya@awm.jp
 */

// require_once 'IO/Bit.php';

abstract class IO_SWF_Type {
    abstract static function parse(&$reader, $opts = array());
    abstract static function build(&$writer, $data, $opts = array());
    abstract static function string($data, $opts = array());
}
