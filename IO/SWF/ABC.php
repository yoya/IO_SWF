<?php

/*
  IO_SWF_ABC class
  (c) 2020/01/29 yoya@awm.jp
  ref) https://www.adobe.com/content/dam/acom/en/devnet/pdf/avm2overview.pdf
 */

require_once dirname(__FILE__).'/ABC/Bit.php';
require_once dirname(__FILE__).'/ABC/Code.php';
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
    function getString_name($n)  {
        return $this->_constant_pool["string"][$n];
    }
    function getMultiname($n)  {
        if ($n === 0) {
            return [];
        }
        $multiname_name = "";
        if (! isset($this->_constant_pool["multiname"][$n])) {
            printf(STDERR, "no multiname\n");
            return [];
        }
        $info = $this->_constant_pool["multiname"][$n];
        return $info;
    }
    function getMultiname_name($n)  {
        if ($n === 0) {
            return "*";
        }
        $multiname_name = "";
        if (! isset($this->_constant_pool["multiname"][$n])) {
            return "no multiname";
        }
        $info = $this->_constant_pool["multiname"][$n];
        $kind = $info["kind"];
        $kind_name = $this->getCONSTANT_name($kind);
        $multiname_name .= "kind:$kind($kind_name)";
        if (isset($info["ns"])) {
            $ns = $info["ns"];
            $nsName = $this->getString_name($ns);
            $multiname_name .= ",ns=$ns($nsName)";
        }
        if (isset($info["name"])) {
            $name = $info["name"];
            $nameName = $this->getString_name($name);
            $multiname_name .= ",name=$name($nameName)";
        }
        return $multiname_name;
    }
    var $_abcdata;
    var $_minor_version = null;
    var $_major_version = null;
    var $_constant_pool;
    var $method, $metadata, $instance;
    var $klass, $script, $method_body;
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
        $metadata_count = $bit->get_u30();
        $metadata = [];
        for ($i = 0; $i < $metadata_count; $i++) {
            $metadata []= $this->parse_metadata_info($bit);
        }
        $this->metadata = $metadata;
        $class_count = $bit->get_u30();
        $instance = [];
        for ($i = 0; $i < $class_count; $i++) {
            $instance []= $this->parse_instance_info($bit);
        }
        $this->instance = $instance;
        $klass = [];
        for ($i = 0; $i < $class_count; $i++) {
            $klass []= $this->parse_class_info($bit);
        }
        $this->klass = $klass;
        $script_count = $bit->get_u30();
        $script = [];
        for ($i = 0; $i < $script_count; $i++) {
            $script []= $this->parse_script_info($bit);
        }
        $this->script = $script;
        $method_body_count = $bit->get_u30();
        $method_body = [];
        for ($i = 0; $i < $method_body_count; $i++) {
            $method_body []= $this->parse_method_body_info($bit);
        }
        $this->method_body = $method_body;
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
        $flags = $bit->get_u8();
        $info["flags"] = $flags;
        if ($flags & 0x08) {  // HAS_OPTIONAL
            $info["options"] = $this->parse_option_info($bit);
        }
        if ($flags & 0x80) {  // HAS_PARAM_NAMES
            $info["param_names"] = $this->parse_param_info($bit, $param_count);
        }
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
    function parse_metadata_info($bit) {
        $info = [];
        $info["name"]       = $bit->get_u30();
        $item_count         = $bit->get_u30();
        $info["item_count"] = $item_count;
        $items = [];
        for ($i = 0; $i < $item_count; $i++) {
            $items []= $this->parse_item_info($bit);
        }
        $info["items"] = $items;
        return $info;
    }
    function parse_item_info($bit) {
        $info = [];
        $info["key"]   = $bit->get_u30();
        $info["value"] = $bit->get_u30();
        return $info;
    }
    function parse_instance_info($bit) {
        $info = [];
        $info["name"]        = $bit->get_u30();
        $info["super_name"]  = $bit->get_u30();
        $flags               = $bit->get_u8();
        $info["flags"]       = $flags;
        if ($flags & 0x08) {
            $info["protectedNs"] = $bit->get_u30();
        }
        $intrf_count         = $bit->get_u30();
        $info["intrf_count"] = $intrf_count;
        $interface = [];
        for ($i = 0; $i < $intrf_count ; $i++) {
            $interface []= $bit->get_u30();
        }
        $info["interface"]   = $interface;
        $info["iinit"]       = $bit->get_u30();
        $trait_count         = $bit->get_u30();
        $trait = [];
        for ($i = 0; $i < $trait_count; $i++) {
            $trait []= $this->parse_traits_info($bit);
        }
        $info["trait"]       = $trait;
        return $info;
    }
    function parse_traits_info($bit) {
        $info = [];
        $info["name"] = $bit->get_u30();
        $kind         = $bit->get_u8();
        $info["kind"] = $kind;
        switch ($kind & 0x0F) {
        case 0:  // Trait_Slot
        case 6:  // Trait_Const
            $info["slot_id"]   = $bit->get_u30();
            $info["type_name"] = $bit->get_u30();
            $info["vindex"]    = $bit->get_u30();
            if ($info["vindex"] > 0) {
                $info["vkind"] = $bit->get_u8();
            } else {
                $info["vkind"] = null;
            }
            break;
        case 1:  // Trait_Method
        case 2:  // Trait_Getter
        case 3:  // Trait_Setter
            $info["disp_id"] = $bit->get_u30();
            $info["method"]  = $bit->get_u30();
            break;
        case 4:  // Trait_Class
            $info["slot_id"] = $bit->get_u30();
            $info["classi"]  = $bit->get_u30();
            break;
        case 5:  // Trait_Function
            $info["slot_id"]  = $bit->get_u30();
            $info["function"] = $bit->get_u30();
            break;
        }
        if ($kind & 0x40) {  // ATTR_Metadata
            $metadata_count = $bit->get_u30();
            $metadata = [];
            for ($i = 0; $i < $metadata_count; $i++) {
                $metadata [] = $bit->get_u30();
            }
            $info["metadata"] = $metadata;
        } else {
            $info["metadata"] = null;
        }
        return $info;
    }                                               
    function parse_class_info($bit) {
        $info = [];
        $info["cinit"]       = $bit->get_u30();
        $trait_count         = $bit->get_u30();
        $info["trait_count"] = $trait_count;
        $traits = [];
        for ($i = 0; $i < $trait_count; $i++) {
            $traits []= $this->parse_traits_info($bit);
        }
        $info["traits"] = $traits;
        return $info;
    }
    function parse_script_info($bit) {
        $info = [];
        $info["init"] = $bit->get_u30();
        $trait_count  = $bit->get_u30();
        $trait = [];
        for ($i = 0; $i < $trait_count; $i++) {
            $trait []= $this->parse_traits_info($bit);
        }
        $info["trait"] = $trait;
        return $info;
    }
    function parse_method_body_info($bit) {
        $info = [];
        $info["method"]           = $bit->get_u30();
        $info["max_stack"]        = $bit->get_u30();
        $info["local_count"]      = $bit->get_u30();
        $info["init_scope_depth"] = $bit->get_u30();
        $info["max_scope_depth"]  = $bit->get_u30();
        $code_length              = $bit->get_u30();
        $code_data                = $bit->getData($code_length);
        $code = new IO_SWF_ABC_Code($this);
        $code->parse($code_data);
        $info["code"]             = $code;
        $exception_count          = $bit->get_u30();
        $exception = [];
        for ($i = 0; $i < $exception_count; $i++) {
            $exception []= $this->parse_exception_info($bit);
        }
        $info["exception"] = $exception;
        $trait_count              =  $bit->get_u30();
        $trait = [];
        for ($i = 0; $i < $trait_count; $i++) {
            $trait []= $this->parse_traits_info($bit);
        }
        $info["trait"] = $trait;
        return $info;
    }
    function parse_exception_info($bit) {
        $info = [];
        $info["from"] = $bit->get_u30();
        $info["to"] = $bit->get_u30();
        $info["target"] = $bit->get_u30();
        $info["exc_type"] = $bit->get_u30();
        $info["var_name"] = $bit->get_u30();
        return $info;
    }
    function dump($opts = array()) {
        echo "    minor_version: ".$this->_minor_version;
        echo "  major_version: ".$this->_major_version;
        echo "\n";
        $this->dump_cpool_info($this->_constant_pool);
        $method_count = count($this->method);
        echo "    method(count=$method_count):\n";
        foreach ($this->method as $idx => $info) {
            echo "    [$idx]";
            $this->dump_method_info($info);
        }
        $metadata_count = count($this->metadata);
        echo "    metadata(count=$metadata_count):\n";
        foreach ($this->metadata as $idx => $info) {
            echo "    [$idx]";
            $this->dump_metadata_info($info);
        }
        $class_count = count($this->instance);
        echo "    instance(count=$class_count):\n";
        foreach ($this->instance as $idx => $info) {
            echo "    [$idx]";
            $this->dump_instance_info($info);
        }
        echo "    class(count=$class_count):\n";
        foreach ($this->klass as $idx => $info) {
            echo "    [$idx]";
            $this->dump_class_info($info);
        }
        $script_count = count($this->script);
        echo "    script(count=$script_count):\n";
        foreach ($this->script as $idx => $info) {
            echo "    [$idx]";
            $this->dump_script_info($info);
        }
        $method_body_count = count($this->method_body);
        echo "    method_body(count=$method_body_count):\n";
        foreach ($this->method_body as $idx => $info) {
            echo "    [$idx]";
            $this->dump_method_body_info($info);
        }
    }
    function dump_cpool_info($info) {
        echo "    cpool_info:\n";
        foreach (['integer', 'uinteger', 'double', 'string'] as $key) {
            $count = count($info[$key]);
            echo "        $key(count:$count)\n";
            foreach ($info[$key] as $i => $v) {
                echo "    [$i] $v\n";
            }
        }
        $namespace_count = count($info['namespace']);
        echo "        namespace(count:$namespace_count)\n";
        foreach ($info['namespace'] as $i => $v) {
            if (count($v) === 0) {
                echo "    [$i] (any namespace)\n";
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
        echo "        ns_set(count:$ns_set_count)\n";
        foreach ($info['ns_set'] as $i => $v) {
            echo "    [$i] ";
            foreach ($v as $v2) {
                echo " $v2";
            }
            echo "\n";
        }
        $multiname_count = count($info['multiname']);
        echo "        multiname(count:$multiname_count)\n";
        foreach ($info['multiname'] as $i => $v) {
            if (count($v) === 0) {
                echo "    [$i] (empty)";
            } else {
                $kind = $v['kind'];
                $kindName = self::getCONSTANT_name($kind);
                echo "    [$i] kind: $kind ($kindName)\n";
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
            echo " $param_type";
        }
        echo "\n";
        $name = $info["name"];
        $flags = $info["flags"];
        echo "         name: $name  flags: $flags\n";
        if ($flags & 0x08) {  // HAS_OPTIONAL
            $this->dump_option_info($info["options"]);
        }
        if ($flags & 0x80) {  // HAS_PARAM_NAMES
            $this->dump_param_info($info["param_names"]);
        }
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
    function dump_metadata_info($info) {
        $name = $info["name"];
        $item_count = $info["item_count"];
        echo "    name: $name  item_count:$item_count\n";
        echo "    items:";
        foreach ($info["items"] as $idx => $item) {
            echo "[$idx] ";
            $this->dump_item_info($item);
        }
        echo "\n";
    }
    function dump_item_info($info) {
        echo "key: ".$info["key"]." value: ".$info["value"];
    }
    function dump_instance_info($info) {
        $name        = $info["name"];
        $super_name  = $info["super_name"];
        $nameName = $this->getMultiname_name($name);
        $super_nameName = $this->getMultiname_name($super_name);
        $flags       = $info["flags"];
        echo "  name: $name($nameName)  super_name:$super_name($super_nameName)\n";
        printf("  flags: 0x%02x", $flags);
        if ($flags % 0x08) {
            $protectedNs = $info["protectedNs"];
            echo "  protectedNs: $protectedNs";
        }
        echo "\n";
        $intrf_count = $info["intrf_count"];
        echo "        interface(count=$intrf_count):";
        foreach ($info["interface"] as $idx => $intrf) {
            $intrfName = $this->getMultiname_name($intrf);
            echo " [$idx]$intrf($intrfName)";
        }
        echo "\n";
        $iinit = $info["iinit"];
        $trait_count = count($info["trait"]);
        echo "        iinit:$iinit\n";
        echo "        trait(count=$trait_count):\n";
        foreach ($info["trait"] as $idx => $trait) {
            echo "            [$idx]";
            $this->dump_traits_info($trait);
        }
    }
    function dump_traits_info($info) {
        $name = $info["name"];
        $kind = $info["kind"];
        $nameName = $this->getMultiname_name($name);
        echo " name:$name($nameName) ";
        printf("kind:0x%02x\n", $kind);
        echo "              ";
        switch ($kind & 0x0F) {
        case 0:  // Trait_Slot
        case 6:  // Trait_Const
            echo "slot_id:".$info["slot_id"]." type_name:".$info["type_name"]." vindex:".$info["vindex"];
            if ($info["vindex"] > 0) {
                echo " ".$info["vkind"];
            }
            echo "\n";
            break;
        case 1:  // Trait_Method
        case 2:  // Trait_Getter
        case 3:  // Trait_Setter
            echo "disp_id:".$info["disp_id"]." method:".$info["method"]."\n";
            break;
        case 4:  // Trait_Class
            echo "slot_id:".$info["slot_id"]." classi:".$info["classi"]."\n";
            break;
        case 5:  // Trait_Function
            echo "slot_id:".$info["slot_id"]." function:".$info["function"]."\n";
            break;
        }
        if ($kind & 0x40) {  // ATTR_Metadata
            $metadata_count = count($info["metadata"]);
            echo "              metadata(count=$metadata_count):";
            foreach ($info["metadata"] as $i => $data) {
                echo " [$i] $data";
            }
            echo "\n";
        }
    }
    function dump_class_info($info) {
        $cinit = $info["cinit"];
        $trait_count = count($info["traits"]);
        echo " cinit: $cinit  trait_count: $trait_count\n";
        foreach ($info["traits"] as $idx => $trait) {
            $this->dump_traits_info($trait);
        }
    }
    function dump_script_info($info) {
        $init = $info["init"];
        $trait_count = count($info["trait"]);
        echo " init:$init trait(count=$trait_count):\n";
        foreach ($info["trait"] as $trait) {
            $this->dump_traits_info($trait);
        }
    }
    function dump_method_body_info($info) {
        echo " method:".$info["method"]." max_stack:".$info["max_stack"]." local_count:".$info["local_count"]." init_scope_depth:".$info["init_scope_depth"]." max_scope_depth:".$info["max_scope_depth"]."\n";
        $info["code"]->dump();
        $exception_count = count($info["exception"]);
        echo "        exception_count:$exception_count";
        foreach ($info["exception"] as $exception) {
            $this->dump_exception_info($exception);
        }
        echo "\n";
        $trait_count = count($info["trait"]);
        echo "        trait(count=$trait_count):\n";
        foreach ($info["trait"] as $trait) {
            $this->dump_traits_info($trait);
        }
    }
    function dump_exception_info($info) {
        echo "  from:".$info["from"]." info:".$info["to"]." target:".$info["target"]." exc_type:".$info["exc_type"]." var_name:".$info["var_name"];
    }
    function build() {
        
    }
    //
    function getInstanceByName($name) {
        $name_match = null;
        foreach ($this->instance as $inst) {
            $multiname = $this->_constant_pool["multiname"][$inst["name"]];
            $multiname_name = $this->getString_name($multiname["name"]);
            if ($multiname_name === $name) {
                return $inst;
            }
        }
        return null;
    }
    function getFrameAndCodeByInstance($inst) {
        $frameMethodArray = [];
        assert(! is_null($inst));
        foreach ($inst["trait"] as $trait) {
            $kind = $trait["kind"];
            switch ($kind & 0x0F) {
            case 2:  // Trait_Getter
            case 3:  // Trait_Setter
                break;
            case 1:  // Trait_Method
                $trait_multiname = $this->_constant_pool["multiname"][$trait["name"]];
                $name = $this->getString_name($trait_multiname["name"]);
                if (substr($name, 0, 5) === "frame") {
                    $frame = intval(substr($name, 5));
                    $frameMethodArray []= [$frame, $trait["method"]];
                }
            }

        }
        return $frameMethodArray;
    }
    function getCodeByMethodId($methodId) {
        return $this->method_body[$methodId]["code"];
    }
}
