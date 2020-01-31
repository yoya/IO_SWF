<?php

/*
  IO_SWF_ABC class
  (c) 2020/01/29 yoya@awm.jp
  ref) https://www.adobe.com/content/dam/acom/en/devnet/pdf/avm2overview.pdf
 */

require_once dirname(__FILE__).'/ABC/Bit.php';
require_once dirname(__FILE__).'/Exception.php';

class IO_SWF_ABC {
    const CONSTANT_QName       = 0x07;
    const CONSTANT_QNameA      = 0x0D;
    const CONSTANT_RTQName     = 0x0F;
    const CONSTANT_RTQNameA    = 0x10;
    const CONSTANT_RTQNameL    = 0x11;
    const CONSTANT_RTQNameLA   = 0x12;
    const CONSTANT_Multiname   = 0x09;
    const CONSTANT_MultinameA  = 0x0E;
    const CONSTANT_MultinameL  = 0x1B;
    const CONSTANT_MultinameLA = 0x1C;
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
        $multiname_count = $bit->get_u30();
        $multinameArray = [];
        for ($i = 0; $i < $multiname_count; $i++) {
            $multinameArray []= $this->parse_multiname_info($bit);
        }
        $info['multiname'] = $multinameArray;
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
    function parse_multiname_info($bit) {
        $kind = $bit->get_u8();
        $info = ["kind" => $kind];
        switch ($kind) {
        case self::CONSTANT_QName:        // 0x07
        case self::CONSTANT_QNameA:       // 0x0D
            // multiname_kind_QName format
            $info["ns"]   = $bit->get_u30();
            $info["name"] = $bit->get_u30();
            break;
        case self::CONSTANT_RTQName:      // 0x0F
        case self::CONSTANT_RTQNameA:     // 0x10
            // multiname_kiind_RTQName format
            $info["name"] = $bit->get_u30();
            break;
        case self::CONSTANT_RTQNameL:     // 0x11
        case self::CONSTANT_RTQNameLA:    // 0x12
            // multiname_kind_RTQNameL format
            // This kind has no associated data.
            break;
        case self::CONSTANT_Multiname:    // 0x09
        case self::CONSTANT_MultinameA:   // 0x0E
            // multiname_kind_Multiname format
            $info["name"]   = $bit->get_u30();
            $info["ns_set"] = $bit->get_u30();
            break;
        case self::CONSTANT_MultinameL:   // 0x1B
        case self::CONSTANT_MultinameLA:  // 0x1C
            // multiname_kind_MultinameL format
            $info["ns_set"] = $bit->get_u30();
            break;
        }
        return $info;
    }
    function dump($opts = array()) {
        
    }
    function build() {
        
    }
}
