<?php

/*
  IO_SWF_ABC class
  (c) 2020/01/29 yoya@awm.jp
  ref) https://www.adobe.com/content/dam/acom/en/devnet/pdf/avm2overview.pdf
 */

require_once dirname(__FILE__).'/ABC/Bit.php';
require_once dirname(__FILE__).'/Exception.php';

class IO_SWF_ABC {
    var $_minor_version = null;
    var $_major_version = null;
    var $_constant_pool;
    function parse($abcdata) {
        $this->_abcdata = $abcdata;
        $bit = new IO_SWF_ABC_Bit();
        $bit->input($abcdata);
        $this->_minor_version = $bit->get_u16();
        $this->_major_version = $bit->get_u16();
        $this->_constant_pool = $this->parse_cpool_info($bit);
    }
    function parse_cpool_info($bit) {
        $info = [];
        $int_count = $bit->get_u30();
        $integerArray = [];
        for ($i = 0; $i < $int_count; $i++) {
            $integerArray []=  $bit->get_s32();
        }
        $info['integer'] = $integerArray;
        $uint_count = $bit->get_u30();
        $uintegerArray = [];
        for ($i = 0; $i < $uint_count; $i++) {
            $uintegerArray []= $bit->get_u32();
        }
        $info['uinteger'] = $uintegerArray;
        $double_count = $bit->get_u30();
        $doubleArray = [];
        for ($i = 0; $i < $double_count; $i++) {
            $doubleArray []= $bit->get_d64();
        }
        $info['double'] = $doubleArray;
        $string_count = $bit->get_u30();
        $stringArray = [];
        for ($i = 0; $i < $string_count; $i++) {
            $stringArray []= $this->parse_string_info($bit);
        }
        $info['string'] = $stringArray;
        $namespace_count = $bit->get_u30();
        $namespaceArray = [];
        for ($i = 0; $i < $namespace_count; $i++) {
            $namespaceArray []= $this->parse_namespace_info($bit);
        }
        $info['namespace'] = $namespaceArray;
        $ns_set_count = $bit->get_u30();
        $ns_setArray = [];
        for ($i = 0; $i < $ns_set_count; $i++) {
            $ns_setArray []= $this->parse_ns_set_info($bit);
        }
        $info['ns_set'] = $ns_setArray;
        
        return $info;
    }
    function parse_string_info($bit) {
        $size = $bit->get_u30();
        return $bit->getData($size);
    }
    function parse_namespace_info($bit) {
        return ["kind" => $bit->get_u8(),
                "name" => $bit->get_u30()];
    }
    function parse_ns_set_info($bit) {
        $count = $bit->get_u30();
        $nsArray = [];
        for ($i = 0; $i < $count; $i++) {
            $nsArray []= $bit->get_u30();
        }
        return $nsArray;
    }
    function dump($opts = array()) {
        
    }
    function build() {
        
    }
}
