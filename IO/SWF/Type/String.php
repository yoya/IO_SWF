<?php

/*
 * 2011/4/15- (c) yoya@awm.jp
 */

require_once 'IO/Bit.php';
require_once dirname(__FILE__).'/../Type.php';

class IO_SWF_Type_String extends IO_SWF_Type {
    static function parse(&$reader, $opts = array()) {
        $str = '';
        while ($reader->hasNextData(1)) {
            $c = $reader->getData(1);
            if ($c === "\0") {
                break;
            }
            $str .= $c;
        }
    	return $str;
    }
    static function build(&$writer, $str, $opts = array()) {
        $strs = explode("\0", $str);
        $str = $strs[0];
        $writer->putData($str."\0", strlen($str) + 1);
    }
    static function string($str, $opts = array()) {
    	return $str;
    }
}
