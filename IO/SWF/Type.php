<?php

/*
 * 2011/1/25- (c) yoya@awm.jp
 */

// require_once 'IO/Bit.php';

interface IO_SWF_Type {
    static function parse(&$reader, $opts = array());
    static function build(&$writer, $data, $opts = array());
    static function string($data, $opts = array());
}
