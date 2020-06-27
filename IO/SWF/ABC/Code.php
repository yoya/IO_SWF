<?php

require_once dirname(__FILE__).'/Bit.php';
require_once dirname(__FILE__).'/../Exception.php';

class IO_SWF_ABC_Code {
    var $codeData = null;
    var $codeArray = [];
    var $abc = null;
    var $instructionTable = [
        //         name             Arg type    Arg to pool
        0x08 => ["kill"          , ["u30"]      ],  // 8  (local register)
        0x09 => ["label"         , []           ],  // 9
        0x0c => ["ifnlt"         , ["s24"]      ],  // 12
        0x0d => ["ifnle"         , ["s24"]      ],  // 13
        0x0e => ["ifngt"         , ["s24"]      ],  // 14
        0x0f => ["ifnge"         , ["s24"]      ],  // 15
        0x10 => ["jump"          , ["s24"]      ],  // 16
        0x11 => ["iftrue"        , ["s24"]      ],  // 17
        0x13 => ["ifeq"          , ["s24"]      ],  // 19
        0x14 => ["ifne"          , ["s24"]      ],  // 20
        0x15 => ["iflt"          , ["s24"]      ],  // 21
        0x16 => ["ifle"          , ["s24"]      ],  // 22
        0x17 => ["ifgt"          , ["s24"]      ],  // 23
        0x18 => ["ifge"          , ["s24"]      ],  // 24
        0x19 => ["ifstricteq"    , ["s24"]      ],  // 25 // spec doc wrong
        0x1a => ["ifstrictne"    , ["s24"]      ],  // 26 // spec doc wrong
        0x1b => ["lookupswitch"  , ["s24","u30","s24..."]],  // 27
        0x1d => ["popscope"      , []           ],  // 29
        0x24 => ["pushbyte"      , ["ubyte"]    ],  // 36
        0x25 => ["pushshort"     , ["u30"]      ],  // 37
        0x2A => ["dup"           , []           ],  // 42
        0x2C => ["pushstring"    , ["u30"]      , ["string"]   ],  // 44
        0x30 => ["pushscope"     , []           ],  // 48
        0x41 => ["call"          , ["u30"]      ],  // 65
        0x46 => ["callproperty"  , ["u30","u30"], ["multiname"]],  // 70
        0x47 => ["returnvoid"    , []           ],  // 71
        0x49 => ["construct"     , ["u30"]      ],  // 73
        0x4f => ["callpropvoid"  , ["u30","u30"], ["multiname"]],  // 79
        0x58 => ["newclass"      , ["u30"]      ],  // 88
        0x5d => ["findpropstrict", ["u30"]      , ["multiname"]],  // 93
        0x60 => ["getlex"        , ["u30"]      , ["multiname"]],  // 96
        0x61 => ["setproperty"   , ["u30"]      , ["multiname"]],  // 97
        0x65 => ["getscopeobject", ["u30"]      ],  // 101 (u30?)
        0x66 => ["getproperty"   , ["u30"]      ],  // 102
        0x68 => ["initproperty"  , ["u30"]      ],  // 104
        0x86 => ["astype"        , ["u30"]      ],  // 134
        0x87 => ["astypelate"    , []           ],  // 135
        0x91 => ["increment"     , []           ],  // 145
        0x92 => ["inclocal"      , ["u30"]      ],  // 146
        0x97 => ["bitnot"        , []           ],  // 151
        0xa0 => ["add"           , []           ],  // 160
        0xa2 => ["multiply"      , []           ],  // 162
        0xa8 => ["bitand"        , []           ],  // 168
        0xa9 => ["bitor"         , []           ],  // 169
        0xaa => ["bitxor"        , []           ],  // 170
        0xad => ["lessthan"      , []           ],  // 173
        0xae => ["lessequals"    , []           ],  // 174
        0xb1 => ["instanceof"    , []           ],  // 177
        0xb2 => ["istype"        , ["u30"]      ],  // 178
        0xb3 => ["istypelate"    , []           ],  // 179
        0xb4 => ["in"            , []           ],  // 180
        0xc0 => ["increment_i"   , []           ],  // 192
        0xc2 => ["inclocal_i"    , ["u30"]      ],  // 194
        0xc5 => ["add_i"         , []           ],  // 197
        0xd0 => ["getlocal_0"    , []           ],  // 208
        0xd1 => ["getlocal_1"    , []           ],  // 209
        0xd2 => ["getlocal_2"    , []           ],  // 210
        0xd3 => ["getlocal_3"    , []           ],  // 211
        0xd4 => ["setlocal_0"    , []           ],  // 212
        0xd5 => ["setlocal_1"    , []           ],  // 213
        0xd6 => ["setlocal_2"    , []           ],  // 214
        0xd7 => ["setlocal_3"    , []           ],  // 215
    ];
    function isBranchInstruction($code) {
        if ((0x0c <= $code) && ($code < 0x1a)) {
            return true; // jump or if... instruction
        }
        return false; // others
    }
    function getCodeArrayIndexByBranchOffset($idx, $branchOffset) {
        $offset = $this->codeArray[$idx + 1]["offset"] + $branchOffset;
        foreach ($this->codeArray as $branchIdx => $code) {
            if ($offset === $code["offset"]) {
                return $branchIdx;
            }
        }
        return null;
    }
    function getInstructionEntry($n) {
        if (!isset($this->instructionTable[$n])) {
            throw new IO_SWF_Exception("unknown instruction:$n");
        }
        $entry = $this->instructionTable[$n];
        if (count($entry) < 2) {
            throw new IO_SWF_Exception("table broken instruction:$n");
        }
        return $entry;
    }
    function getInstructionName($n) {
        return $this->getInstructionEntry($n)[0];
    }
    function getInstructionArgsType($n) {
        return $this->getInstructionEntry($n)[1];
    }
    function getInstructionArgsPool($n) {
        $entry =  $this->getInstructionEntry($n);
        if (! isset($entry[2])) {
            return null;
        }
        return $entry[2];
    }
    //
    function __construct($abc) {
        assert($abc instanceof IO_SWF_ABC);
        $this->abc = $abc;
    }
    function parse($codeData) {
        $this->codeData = $codeData;
        $bit = new IO_SWF_ABC_Bit();
        $bit->input($codeData);
        list($baseOffset, $dummy) = $bit->getOffset();
        while ($bit->hasNextData(1)) {
            list($startOffset, $dummy) = $bit->getOffset();
            $inst = $bit->getUI8();
            $argsType = $this->getInstructionArgsType($inst);
            $v = null;
            foreach ($argsType as $argType) {
                switch ($argType) {
                case "u30":
                    $v = $bit->get_u30();
                    break;
                case "s24":
                    $v = $bit->get_s24();
                    break;
                case "ubyte":
                    $v = $bit->getUI8();
                    break;
                case "s24...":
                    foreach (range(0, $v) as $i) {
                        $bit->get_s24();
                    }
                    break;
                default:
                    throw new IO_SWF_Exception("unknown type$argType");
                    break;
                }
            }
            list($nextOffset, $dummy) = $bit->getOffset();
            $offset = $startOffset;
            $size = $nextOffset - $startOffset;
            $this->codeArray []= ["bytes" => substr($codeData, $offset, $size),
                                  "offset" => $offset,
                                  "size" => $size];
        }
    }
    function dump() {
        $codeLength = strlen($this->codeData);
        $codeCount = count($this->codeArray);
        echo "        code(bytesize=$codeLength, nInst=$codeCount):\n";
        foreach ($this->codeArray as $idx => $code) {
            $bytes = $code["bytes"];
            $bit = new IO_SWF_ABC_Bit();
            $bit->input($bytes);
            $inst = $bit->getUI8();
            $instName = $this->getInstructionName($inst);
            $argsType = $this->getInstructionArgsType($inst);
            $argsPool = $this->getInstructionArgsPool($inst);
            echo "        [$idx] $instName";
            foreach ($argsType as $i => $argType) {
                switch ($argType) {
                case "u30":
                    $v = $bit->get_u30();
                    break;
                case "s24":
                    $v = $bit->get_s24();
                    break;
                case "ubyte":
                    $v = $bit->getUI8();
                    break;
                case "s24...":
                    $v = "[";
                    foreach (range(0, $v) as $i) {
                        $vv = $bit->get_s24();
                        $v .= "$vv,";
                    }
                    $v .= "]";
                    break;
                default:
                    throw new IO_SWF_Exception("unknown type$argType");
                    break;
                }
                echo " $v";
                if (isset($argsPool[$i])) {
                    $pool = $argsPool[$i];
                    switch ($pool) {
                    case "multiname":
                        $name = $this->abc->getMultiname_name($v);
                        echo "($name)";
                        break;
                    case "string":
                        $name = $this->abc->getString_name($v);
                        echo "($name)";
                        break;
                    default:
                        throw new IO_SWF_Exception("Unknown pool keyword:$pool");
                    }
                }
            }
            if ($this->isBranchInstruction($inst)) {
                $branchIdx = $this->getCodeArrayIndexByBranchOffset($idx, $v);
                if (is_null($branchIdx)) {
                    $branchIdx = "(no match)";
                }
                echo "=> [$branchIdx]";
            }
            echo "\n";
            $bit = null;
        }
    }
    function ABCCodetoActionTag($version) {
        // property map
        $propertyMap = [];
        foreach ($this->codeArray as $idx => $code) {
            $bytes = $code["bytes"];
            $bit = new IO_SWF_ABC_Bit();
            $bit->input($bytes);
            $inst = $bit->getUI8();
            if ($inst === 0x61) { // setproperty
                $index = $bit->get_u30();
                $info = $this->abc->getMultiname($index);
                $propertyMap[$index] = $this->abc->getString_name($info["name"]);
            }
        }
        // convert code AS3 = AS1 with abc stack.
        $actions = [];
        $abcStack = [];
        $labels = [];
        $branches = [];
        foreach ($this->codeArray as $idx => $code) {
            $bytes = $code["bytes"];
            $bit = new IO_SWF_ABC_Bit();
            $bit->input($bytes);
            $inst = $bit->getUI8();
            $labels[count($actions)] = $code["offset"];
            switch ($inst) {
            case 0x10:  // jump
                $branchOffset = $bit->get_s24();
                $branches[count($actions)] = $code["offset"] + $branchOffset;
                $actions []= ["Code" => 0x99,  // Jump
                              "Length" => 2,
                              "BranchOffset" => 0]; // temporary
                break;
            case 0x1d:  // popscope
                // do nothing
                break;
            case 0x24:  // pushbyte
                $value = $bit->getUI8();
                array_push($abcStack, [$value, "byte"]);
                break;
            case 0x25:  // pushshort
                $value = $bit->get_u30();
                array_push($abcStack, [$value, "short"]);
                break;
            case 0x2C:  // pushshort
                $v = $bit->get_u30();
                $value = $this->abc->getString_name($v);
                array_push($abcStack, [$value, "string"]);
                break;
            case 0x30:  // pushscope
                // do nothing
                break;
            case 0x47:  // returnvoid
                $actions []= ["Code" => 0x00]; // End
                break;
            case 0x4f:  // callproperty
                $index = $bit->get_u30();  // multiname
                $arg_count = $bit->get_u30();
                $multiname = $this->abc->_constant_pool["multiname"][$index];
                $name = $this->abc->getString_name($multiname["name"]);
                switch ($name) {
                case "gotoAndPlay":
                    list($targetFrame, $valuetype) = array_pop($abcStack);
                    if ($valuetype !== "string") {
                        // integer
                        $actions []= ["Code" => 0x81,  // GotoFrame
                                      "Length" => 2,
                                      "Frame" => $targetFrame - 1];
                        $actions []= ["Code" => 0x06]; // Play
                    } else {
                        // string
                        $actions []= ["Code" => 0x96, // Push
                                      "Length" => 1 + strlen($targetFrame) + 1,
                                      "Values" => [
                                          ["Type" => 0,  // String
                                           "String" => $targetFrame]
                                      ]];
                        $actions []= ["Code" => 0x9F,  // GotoFrame2
                                      "SceneBiasFlag" => 0, "PlayFlag" => 1];
                    }
                    break;
                case "play":
                    $actions []= ["Code" => 0x06]; // Play
                    break;
                case "stop":
                    $actions []= ["Code" => 0x07]; // Stop
                    break;
                }
                break;
            case 0x5d:  // findpropstrict
                // do nothing
                break;
            case 0x61:  // setproperty
                $index = $bit->get_u30();
                $name = $propertyMap[$index];
                $actions []= ["Code" => 0x96, // Push
                              "Length" => 1 + strlen($name) + 1,
                              "Values" => [
                                  ["Type" => 0,  // String
                                   "String" => $name]
                              ]];
                $actions []= ["Code" => 0x1D]; // SetVariable
                break;
            case 0x66:  // getproperty
                $index = $bit->get_u30();
                $name = $propertyMap[$index];
                $actions []= ["Code" => 0x96, // Push
                              "Length" => 1 + strlen($name) + 1,
                              "Values" => [
                                  ["Type" => 0,  // String
                                   "String" => $name]
                              ]];
                $actions []= ["Code" => 0x1C]; // GetVariable
                break;
            case 0x68:  // initproperty
                $index = $bit->get_u30();
                $name = $propertyMap[$index];
                $actions []= ["Code" => 0x96, // Push
                              "Length" => 1 + strlen($name) + 1,
                              "Values" => [
                                  ["Type" => 0,  // String
                                   "String" => $name]
                              ]];
                list($value, $valuetype) = array_pop($abcStack);
                $actions []= ["Code" => 0x96, // Push
                              "Length" => 1 + strlen($name) + 1,
                              "Values" => [
                                  ["Type" => 0,  // String
                                   "String" => (string) $value]
                              ]];
                $actions []= ["Code" => 0x1d]; // SetVariable
                break;
            default:
                $instName = $this->getInstructionName($inst);
                fprintf(STDERR, "unsupported instruction:$instName($inst)\n");
            }
        }
        // The branch fitting to the label.
        // Because some AS3 instructions do not convert to AS1 action.
        foreach ($actions as $idx => $act) {
            if (isset($branches[$idx])) {
                foreach ($actions as $i => $a) {
                    if (isset($labels[$i])) {
                        if ($branches[$idx] <= $labels[$i]) {
                            break;
                        }
                    }
                }
                $branches[$idx] = $labels[$i];
            }
        }
        $swfInfo = array('Version' => $version);
        $action_tag = new IO_SWF_Tag($swfInfo);
        $action_tag->code = 12; // DoAction
        $action_tag->content = '';
        $action_tag->parseTagContent();
        $action_tag->tag->_labels = $labels;
        $action_tag->tag->_branches = $branches;
        $action_tag->content = null;
        $action_tag->tag->_actions = $actions;
        return $action_tag;
    }
}
