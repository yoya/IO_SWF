<?php

/*
  IO_SWF_ABC class
  (c) 2020/01/29 yoya@awm.jp
  ref) https://www.adobe.com/content/dam/acom/en/devnet/pdf/avm2overview.pdf
 */

require_once dirname(__FILE__).'/ABC/Bit.php';
require_once dirname(__FILE__).'/Exception.php';

class IO_SWF_ABC {
    const CONSTANT_unused_0x00        = 0x01;
    const CONSTANT_Utf8               = 0x01;
    const CONSTANT_Float              = 0x02;
    const CONSTANT_Int                = 0x03;
    const CONSTANT_UInt               = 0x04;
    const CONSTANT_PrivateNs          = 0x05;
    const CONSTANT_Double             = 0x06;
    const CONSTANT_QName              = 0x07;
    const CONSTANT_Namespace          = 0x08;
    const CONSTANT_Multiname          = 0x09;
    const CONSTANT_False              = 0x0A;
    const CONSTANT_True               = 0x0B;
    const CONSTANT_Null               = 0x0C;
    const CONSTANT_QNameA             = 0x0D;
    const CONSTANT_MultinameA         = 0x0E;
    const CONSTANT_RTQName            = 0x0F;
    const CONSTANT_RTQNameA           = 0x10;
    const CONSTANT_RTQNameL           = 0x11;
    const CONSTANT_RTQNameLA          = 0x12;
    const CONSTANT_NamespaceSet       = 0x15;
    const CONSTANT_PackageNamespace   = 0x16;
    const CONSTANT_PackageInternalNs  = 0x17;
    const CONSTANT_ProtectedNamespace = 0x18;
    const CONSTANT_ExplicitNamespace  = 0x19;
    const CONSTANT_StaticProtectedNs  = 0x1A;
    const CONSTANT_MultinameL         = 0x1B;
    const CONSTANT_MultinameLA        = 0x1C;
    const CONSTANT_TypeName           = 0x1D;
    const CONSTANT_Float4             = 0x1E;

    function getCONSTANT_name($n)  {
        static $CONSTANT_nameTable = [
            0x01 => "Utf8",
            0x02 => "Float",
            0x03 => "Int",
            0x04 => "UInt",
            0x05 => "PrivateNs",     // non-shared namespace
            0x06 => "Double",
            0x07 => "QName",         // o.ns::name, ct ns, ct name
            0x08 => "Namespace",
            0x09 => "Multiname",     // o.name, ct nsset, ct name
            0x0A => "False",
            0x0B => "True",
            0x0C => "Null",
            0x0D => "QNameA",        // o.@ns::name, ct ns, ct attr-name
            0x0E => "MultinameA",    // o.@name, ct attr-name
            0x0F => "RTQName",       // o.ns::name, rt ns, ct name
            0x10 => "RTQNameA",      // o.@ns::name, rt ns, ct attr-name
            0x11 => "RTQNameL",      // o.ns::[name], rt ns, rt name
            0x12 => "RTQNameLA",     // o.@ns::[name], rt ns, rt attr-name
            0x15 => "NamespaceSet",
            0x16 => "PackageNamespace",
            0x17 => "PackageInternalNs",
            0x18 => "ProtectedNamespace",
            0x19 => "ExplicitNamespace",
            0x1A => "StaticProtectedNs",
            0x1B => "MultinameL",
            0x1C => "MultinameLA",
            0x1D => "TypeName",
            0x1E => "Float4",
        ];
        if (isset($CONSTANT_nameTable[$n])) {
            return $CONSTANT_nameTable[$n];
        }
        return "Unknown";
    }
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
        $method_count = $bit->get_u30();
        $method = [];
        for ($i = 0; $i < $method_count; $i++) {
            $method []= $this->parse_method_info($bit);
        }
        $this->method = $method;
        /*
        u30 metadata_count
        metadata_info metadata[metadata_count]
        u30 class_count
        instance_info instance[class_count]
        class_info class[class_count]
        u30 script_count
        script_info script[script_count]
        u30 method_body_count
        method_body_info method_body[method_body_count]
        */
    }
    function parse_cpool_info($bit) {
        $info = [];
        $int_count = $bit->get_u30();
        $integerArray = [];
        for ($i = 0; $i < $int_count; $i++) {
            if ($i === 0) {
                $integerArray []= 0;
            } else {
                $integerArray []=  $bit->get_s32();
            }
        }
        $info['integer'] = $integerArray;
        $uint_count = $bit->get_u30();
        $uintegerArray = [];
        for ($i = 0; $i < $uint_count; $i++) {
            if ($i === 0) {
                $uintegerArray []= 0;
            } else {
                $uintegerArray []= $bit->get_u32();
            }
        }
        $info['uinteger'] = $uintegerArray;
        $double_count = $bit->get_u32();
        $doubleArray = [];
        for ($i = 0; $i < $double_count; $i++) {
            if ($i === 0) {
                $doubleArray []= NAN;
            } else {
                $doubleArray []= $bit->get_d64();
            }
        }
        $info['double'] = $doubleArray;
        $string_count = $bit->get_u30();
        $stringArray = [];
        for ($i = 0; $i < $string_count; $i++) {
            if ($i === 0) {
                $stringArray []= "*";  // any name "*" in ActionScript
            } else {
                $stringArray []= $this->parse_string_info($bit);
            }
        }
        $info['string'] = $stringArray;
        $namespace_count = $bit->get_u30();
        $namespaceArray = [];
        for ($i = 0; $i < $namespace_count; $i++) {
            if ($i === 0) {
                $namespaceArray []= []; // any namespacey
            } else {
                $namespaceArray []= $this->parse_namespace_info($bit);
            }
        }
        $info['namespace'] = $namespaceArray;
        $ns_set_count = $bit->get_u30();
        $ns_setArray = [];
        for ($i = 0; $i < $ns_set_count; $i++) {
            if ($i === 0) {
                $ns_setArray []= [];
            } else {
                $ns_setArray []= $this->parse_ns_set_info($bit);
            }
        }
        $info['ns_set'] = $ns_setArray;
        $multiname_count = $bit->get_u30();
        $multinameArray = [];
        for ($i = 0; $i < $multiname_count; $i++) {
            if ($i === 0) {
                $multinameArray []= [];
            } else {
                $multinameArray []= $this->parse_multiname_info($bit);
            }
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
    function parse_method_info($bit) {
        $info = [];
        $param_count = $bit->get_u30();
        $info["param_count"] = $param_count;
        $info["return_type"] = $bit->get_u30();
        $param_type = [];
        for ($i = 0; $i < $param_count; $i++) {
            $param_type []= $bit->get_u30();
        }
        $info["param_type"] = $param_type;
        $info["name"] = $bit->get_u30();
        $info["flags"] = $bit->get_u8();
        $info["options"] = $this->parse_option_info($bit);
        $info["param_names"] = $this->parse_param_info($bit, $param_count);
        return $info;
    }
    function parse_option_info($bit) {
        $info = [];
        $option_count = $bit->get_u30();
        $info["option_count"] = $option_count;
        $option = [];
        for ($i = 0; $i < $option_count; $i++) {
            $option []= $this->parse_option_detail($bit);
        }
        $info["option"] = $option;
        return $info;
    }
    function parse_option_detail($bit) {
        $info = [];
        $info["val"]  = $bit->get_u30();
        $info["kind"] = $bit->get_u8();
        return $info;
    }
    function parse_param_info($bit, $param_count) {
        $info = [];
        for ($i = 0; $i < $param_count; $i++) {
            $info []= $bit->get_u30();
        }
        return $info;
    }
    function dump($opts = array()) {
        echo "    minor_version: ".$this->_minor_version;
        echo "  major_version: ".$this->_major_version;
        echo "\n";
        $this->dump_cpool_info($this->_constant_pool);
        $method_count = count($this->method);
        echo "    method_count:$method_count\n";
        foreach ($this->method as $idx => $info) {
            echo "    [$idx]";
            $this->dump_method_info($info);
        }
    }
    function dump_cpool_info($info) {
        foreach (['integer', 'uinteger', 'double', 'string'] as $key) {
            $count = count($info[$key]);
            echo "    $key(count:$count)\n";
            foreach ($info[$key] as $i => $v) {
                echo "    [$i]:$v\n";
            }
        }
        $namespace_count = count($info['namespace']);
        echo "    namespace(count:$namespace_count)\n";
        foreach ($info['namespace'] as $i => $v) {
            if (count($v) === 0) {
                echo "    [$i]: (any namespace)\n";
            } else {
                $kind = $v['kind'];
                $kindName = self::getCONSTANT_name($kind);
                $name = $v['name'];
                $nameName = $info["string"][$name];
                echo "    [$i]: kind: $kind ($kindName)\n";
                echo "        name:$name ({$nameName})\n";
            }
        }
        $ns_set_count = count($info['ns_set']);
        echo "    ns_set(count:$ns_set_count)\n";
        foreach ($info['ns_set'] as $i => $v) {
            echo "    [$i]: ";
            foreach ($v as $v2) {
                echo " $v2";
            }
            echo "\n";
        }
        $multiname_count = count($info['multiname']);
        echo "    multiname(count:$multiname_count)\n";
        foreach ($info['multiname'] as $i => $v) {
            if (count($v) === 0) {
                echo "    [$i]: (empty)";
            } else {
                $kind = $v['kind'];
                $kindName = self::getCONSTANT_name($kind);
                echo "    [$i]: kind: $kind ($kindName)\n";
            }
            switch($kind) {
            case self::CONSTANT_QName:        // 0x07
            case self::CONSTANT_QNameA:       // 0x0D
                // multiname_kind_QName format
                $ns   = $v["ns"];
                $ns_kind = $info['namespace'][$ns]["kind"];
                $ns_name = $info['namespace'][$ns]["name"];
                $ns_kindName = self::getCONSTANT_name($ns_kind);
                $ns_nameName = $info["string"][$ns_name];
                $name = $v["name"];
                $nameName = $info["string"][$name];
                echo "        ns: $ns ($ns_kind:$ns_kindName $ns_name:$ns_nameName)\n";
                echo "        name: $name ($nameName)";
                break;
            case self::CONSTANT_RTQName:      // 0x0F
            case self::CONSTANT_RTQNameA:     // 0x10
                // multiname_kind_RTQName format
                $name = $v["name"];
                $nameName = $info["string"][$name];
                echo "         name: $name ($nameName)";
                break;
            case self::CONSTANT_RTQNameL:     // 0x11
            case self::CONSTANT_RTQNameLA:    // 0x12
                // multiname_kind_RTQNameL format
                // This kind has no associated data.
                break;
            case self::CONSTANT_Multiname:    // 0x09
            case self::CONSTANT_MultinameA:   // 0x0E
                // multiname_kind_Multiname format
                $name   = $v["name"];
                $nameName = $info["string"][$name];
                $ns_set = $v["ns_set"];
                echo "         name: $name ($nameName)\n";;
                echo "         ns_set: $ns_set";
                break;
            case self::CONSTANT_MultinameL:   // 0x1B
            case self::CONSTANT_MultinameLA:  // 0x1C
                // multiname_kind_MultinameL format
                $ns_set = $v["ns_set"];
                echo "         ns_set: $ns_set";
                break;
            }
            echo "\n";
        }
    }
    function dump_method_info($info) {
        echo "  param_count: ".$info["param_count"];
        echo "  return_type: ".$info["return_type"];
        echo "  param_type:";
        foreach ($info["param_type"] as $param_type) {
            echo " ".$param_type;
        }
        echo "\n";
        echo "         name: ".$info["name"];
        echo "  flags: ".$info["flags"]."\n";
        $this->dump_option_info($info["options"]);
        $this->dump_param_info($info["param_names"]);
    }
    function dump_option_info($info) {
        $option_count = $info["option_count"];
        echo "         option_info:(count:$option_count)";
        foreach ($info["option"] as $option) {
            $this->dump_option_detail($option);
        }
    }
    function dump_option_detail($info) {
        echo "        val: ".$info["val"]. "  kind: ".$info["kind"];
        echo "\n";
    }
    function dump_param_info($info) {
        $count_param = count($info);
        echo "    param_info(count=$count_param):";
        foreach ($info as $param) {
            echo " ".$param;
        }
        echo "\n";
    }
    function build() {
        
    }
}
