<?php

require_once dirname(__FILE__).'/Bit.php';  // IO_SWF_ABC_Bit
require_once dirname(__FILE__).'/../Exception.php';

class IO_SWF_ABC_Code {
    var $codeData = null;
    var $codeArray = [];
    var $abc = null;
    var $codeContext = null;
    var $opts = [];
    var $labels = [];    // 飛び先につけるラベル。自分自身の idx
    var $branches = [];  // 飛び元(分岐命令)につける。飛び先 idx
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
        0x12 => ["iffalse"       , ["s24"]      ],  // 18
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
        0x20 => ["pushnull"      , []           ],  // 32
        0x21 => ["pushundefined" , []           ],  // 33
        0x24 => ["pushbyte"      , ["ubyte"]    ],  // 36
        0x25 => ["pushshort"     , ["u30"]      ],  // 37
        0x26 => ["pushtrue"      , []           ],  // 38
        0x27 => ["pushfalse"     , []           ],  // 39
        0x29 => ["pop"           , []           ],  // 41
        0x2A => ["dup"           , []           ],  // 42
        0x2B => ["swap"          , []           ],  // 43
        0x2C => ["pushstring"    , ["u30"]      , ["string"]   ],  // 44
        0x2F => ["pushdouble"    , ["u30"]      ],  // 47
        0x30 => ["pushscope"     , []           ],  // 48
        0x41 => ["call"          , ["u30"]      ],  // 65
        0x46 => ["callproperty"  , ["u30","u30"], ["multiname"]],  // 70
        0x47 => ["returnvoid"    , []           ],  // 71
        0x48 => ["returnvalue"   , []           ],  // 72
        0x49 => ["construct"     , ["u30"]      ],  // 73
        0x4a => ["constructprop" , ["u30","u30"], ["multiname"]],  // 74
        0x4f => ["callpropvoid"  , ["u30","u30"], ["multiname"]],  // 79
        0x56 => ["newarray"      , ["u30"]      , ["multiname"]],  // 86
        0x58 => ["newclass"      , ["u30"]      ],  // 88
        0x5a => ["newcatch"      , ["u30"]      ],  // 90
        0x5d => ["findpropstrict", ["u30"]      , ["multiname"]],  // 93
        0x5e => ["findproperty"  , ["u30"]      , ["multiname"]],  // 94
        0x60 => ["getlex"        , ["u30"]      , ["multiname"]],  // 96
        0x61 => ["setproperty"   , ["u30"]      , ["multiname"]],  // 97
        0x62 => ["getlocal"      , ["u30"]      ],  // 98
        0x63 => ["setlocal"      , ["u30"]      ],  // 99
        0x65 => ["getscopeobject", ["u30"]      ],  // 101 (u30?)
        0x66 => ["getproperty"   , ["u30"]      , ["multiname"]],  // 102
        0x68 => ["initproperty"  , ["u30"]      , ["multiname"]],  // 104
        0x6d => ["setslot"       , ["u30"]      ],  // 109
        0x75 => ["convert_d"     , []           ],  // 117
        0x76 => ["convert_b"     , []           ],  // 118
        0x80 => ["coerce"        , ["u30"]      , ["multiname"]],  // 128
        0x82 => ["coerce_a"      , []           ],  // 130
        0x86 => ["astype"        , ["u30"]      ],  // 134
        0x87 => ["astypelate"    , []           ],  // 135
        0x90 => ["negate"        , []           ],  // 144
        0x91 => ["increment"     , []           ],  // 145
        0x92 => ["inclocal"      , ["u30"]      ],  // 146
        0x93 => ["decrement"     , []           ],  // 147
        0x94 => ["bitnot"        , []           ],  // 148
        0x96 => ["not"           , []           ],  // 150
        0xa0 => ["add"           , []           ],  // 160
        0xa1 => ["subtract"      , []           ],  // 161
        0xa2 => ["multiply"      , []           ],  // 162
        0xa3 => ["divide"        , []           ],  // 163
        0xa4 => ["modulo"        , []           ],  // 164
        0xa8 => ["bitand"        , []           ],  // 168
        0xa9 => ["bitor"         , []           ],  // 169
        0xaa => ["bitxor"        , []           ],  // 170
        0xab => ["equals"        , []           ],  // 171
        0xad => ["lessthan"      , []           ],  // 173
        0xae => ["lessequals"    , []           ],  // 174
        0xaf => ["greaterthan"   , []           ],  // 175
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
        if ((0x0c <= $code) && ($code <= 0x1a)) {
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
                case "d64":
                    $v = $bit->get_d64();
                    break;
                case "s24...":
                    foreach (range(0, $v) as $i) {
                        $bit->get_s24();
                    }
                    break;
                default:
                    throw new IO_SWF_Exception("unknown type:$argType");
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
        // 分岐命令の飛び先をラベル化
        foreach ($this->codeArray as $idx => $code) {
            $bit = new IO_SWF_ABC_Bit();
            $bit->input($code["bytes"]);
            $inst = $bit->getUI8();
            if ($this->isBranchInstruction($inst)) {
                $v = $bit->get_s24();  // 分岐命令は 24 のはず
                $branchIdx = $this->getCodeArrayIndexByBranchOffset($idx, $v);
                $this->labels[$branchIdx] = $branchIdx;
                $this->branches[$idx] = $branchIdx;
            }
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
            if (isset($this->labels[$idx])) {
                echo "    LABEL:[".$this->labels[$idx]."]\n";
            }
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
            if (isset($this->branches[$idx])) {
                echo " (Branchs:[".$this->branches[$idx]."])";
            }
            echo "\n";
            $bit = null;
        }
    }
    function ABCCodetoActionTag($version, $ctx, $opts) {
        $this->codeContext = $ctx;
        $this->opts = $opts;
        // preprocess, set stack value & propertyMap
        $propertyMap = $ctx->propertyMap;  // [name, valuetype]
        $debugPropertyArray = [];
        foreach ($this->codeArray as &$code) {
            $bit = new IO_SWF_ABC_Bit();
            $bit->input($code["bytes"]);
            $inst = $bit->getUI8();
            $code["inst"] = $inst;
            switch ($inst) {
            case 0x24:  // pushbyte
                $code["value"] = $bit->getUI8();
                $code["valuetype"] = "byte";
                break;
            case 0x25:  // pushshort
                $code["value"] = $bit->get_u30();
                $code["valuetype"] = "short";
                break;
            case 0x2C:  // pushstring
                $v = $bit->get_u30();
                $code["value"] = $this->abc->getString_name($v);
                $code["valuetype"] = "string";
                break;
            case 0x2F:  // pushdouble
                $code["value"] = $bit->get_u30();
                $code["valuetype"] = "double";
                break;
            case 0x46:  // callproperty
                $index = $bit->get_u30();
                $info = $this->abc->getMultiname($index);
                $code["name"] = $this->abc->getString_name($info["name"]);
                $code["arg_count"] = $bit->get_u30();
                break;
            case 0x4f:  // callpropvoid
                $index = $bit->get_u30();
                $info = $this->abc->getMultiname($index);
                $name = $this->abc->getString_name($info["name"]);
                $code["name"] = $name;
                break;
            case 0x61:  // setproperty
            case 0x66:  // getproperty
            case 0x68:  // initproperty
                $index = $bit->get_u30();
                $info = $this->abc->getMultiname($index);
                if (! isset($info["name"])) {
                    fprintf(STDERR, "propertyMap isset name failed:".print_r($info, true));
                    $info["name"] = "(dummy)";
                }
                $name = $this->abc->getString_name($info["name"]);
                $code["name"] = $name;
                $propertyMap[$index] = ["name" => $name,
                                        "valuetype" => null];
                if ($opts['debug']) {
                    $debugPropertyArray []= "[". $index."]".$name;
                }
                break;
            }
        }
        unset($code); // remove reference.
        if ($opts['debug']) {
            if (count($debugPropertyArray) > 0) {
                fprintf(STDERR, "init propertyMap:");
                foreach ($debugPropertyArray as $entry) {
                    fprintf(STDERR, " $entry");
                }
                fprintf(STDERR, "=> total:".count($propertyMap)."\n");
            }
        }
        // convert code AS3 = AS1 with abc stack.
        $actions = [];   // 最終的な ABC コード
        $abcQueue = [];  // 前の命令を巻き添えにする命令があるので一旦キューに
        $abcStack = [];  // 型の整合性をとる為の情報
        // AS1 の方の labels, branches. AS3 の this-> とは別。
        $labels = [];    // 分岐命令の飛び先命令のNo
        $branches = [];  // 分岐命令の跳び元命令No=>飛び先オフセット
        $skip_count = 0;
        $nextLabel = null;
        $nextBranche = null;
        foreach ($this->codeArray as $idx => $code) {
            if (isset($this->labels[$idx])) {
                $nextLabel = $this->labels[$idx];
            }
            if (isset($this->branches[$idx])) {
                $nextBranche = $this->branches[$idx];
            }
            if ($skip_count > 0) { $skip_count--; continue; }
            $bit = new IO_SWF_ABC_Bit();
            $bit->input($code["bytes"]);
            $inst = $bit->getUI8();
            if ($opts['debug']) {
                echo "DEBUG: ABCCodetoActionTag[$idx]: $inst(".$this->getInstructionName($inst).")\n";
            }
            switch ($inst) {
            case 0x10:  // jump
                $this->flushABCQueue($abcQueue, $abcStack, $actions, $labels, 0);
                if ($nextLabel) {
                    $labels[count($actions)] = $nextLabel;
                    $nextLabel = null;
                }
                if ($nextBranche) {
                    $branches[count($actions)] = $nextBranche;
                    $nextBranche = null;
                }
                $actions []= ["Code" => 0x99,  // Jump
                              "Length" => 2,
                              "BranchOffset" => 0xDEAD]; // temporary
                break;
            case 0x11:  // iftrue
            case 0x12:  // iffalse
                $this->flushABCQueue($abcQueue, $abcStack, $actions, $labels, 0);
                if ($nextLabel) {
                    $labels[count($actions)] = $nextLabel;
                    $nextLabel = null;
                }
                if ($inst === 0x12) {
                    $actions []= ["Code" => 0x12];  // Not
                }
                if ($nextBranche) {
                    $branches[count($actions)] = $nextBranche;
                    $nextBranche = null;
                }
                $actions []= ["Code" => 0x9D,  // If
                              "Length" => 2,
                              "Offset" => 0xDEAD]; // temporary
                // pop: a,b => push:(none)
                array_pop($abcStack);
                array_pop($abcStack);
                break;
            case 0x13:  // ifeq
            case 0x14:  // ifne
            case 0x1a:  // ifstrictne
                $this->flushABCQueue($abcQueue, $abcStack, $actions, $labels, 0);
                if (($inst == 0x14) || ($inst == 0x1a)) {
                    $actions []= ["Code" => 0x12];  // Not
                }
                $actions []= ["Code" => 0x66];  // StrictEqual
                // $actions []= ["Code" => 0x0E];  // Equal
                if ($nextLabel) {
                    $labels[count($actions)] = $nextLabel;
                    $nextLabel = null;
                }
                if ($nextBranche) {
                    $branches[count($actions)] = $nextBranche;
                    $nextBranche = null;
                }
                $actions []= ["Code" => 0x9D,  // If
                              "Length" => 2,
                              "Offset" => 0xDEAD]; // temporary
                // pop: a,b => push:(none)
                array_pop($abcStack);
                array_pop($abcStack);
                break;
            case 0x0e:  // ifngt
            case 0x15:  // iflt
                $this->flushABCQueue($abcQueue, $abcStack, $actions, $labels, 0);
                if ($nextLabel) {
                    $labels[count($actions)] = $nextLabel;
                    $nextLabel = null;
                }
                $actions []= ["Code" => 0x0F];  // Less
                if ($nextBranche) {
                    $branches[count($actions)] = $nextBranche;
                    $nextBranche = null;
                }
                $actions []= ["Code" => 0x9D,  // If
                              "Length" => 2,
                              "Offset" => 0xDEAD]; // temporary
                // pop: a,b => push:(none)
                array_pop($abcStack);
                array_pop($abcStack);
                break;
            case 0x2A:  // dup
                $this->flushABCQueue($abcQueue, $abcStack, $actions, $labels, 0);
                if ($nextLabel) {
                    $labels[count($actions)] = $nextLabel;
                    $nextLabel = null;
                }
                $actions []= ["Code" => 0x2A]; // Dup
                break;
            case 0x46:  // callproperty
                if ($code["name"] === "random") {
                    $this->flushABCQueue($abcQueue, $abcStack, $actions, $labels, 0);
                    if ($nextLabel) {
                        $labels[count($actions)] = $nextLabel;
                        $nextLabel = null;
                    }
                    assert($code["arg_count"] === 0);
                    /*
                      <== AS3: floor(Math.random() * 4)
                      [5] callproperty name:random
                      [6] pushbyte 4
                      [7] multiply
                      [8] callproperty name:floor
                      ==> AS1: random(4)
                      [0] Push(Code:0x96) (Length:3) (String)4
                      [1] RandomNumber(Code:0x30)
                    */
                    if (($this->codeArray[$idx+1]["inst"] === 0x24) || // push
                        ($this->codeArray[$idx+1]["valuetype"] === "byte") ||
                        ($this->codeArray[$idx+2]["inst"] === 0xa2) || // mult
                        ($this->codeArray[$idx+3]["inst"] === 0x46) || // call
                        ($this->codeArray[$idx+3]["name"] === "floor")) {
                        $value_str = (string) $this->codeArray[$idx+1]["value"];
                        $actions []= ["Code" => 0x96, // Push
                                      "Length" => 1 + strlen($value_str) + 1,
                                      "Values" => [
                                          ["Type" => 0,  // String
                                           "String" => $value_str]
                                      ]];
                        $actions []= ["Code" => 0x30];  // RandomNumber
                        $skip_count = 3;  // pushbyte, multiply, callproperty
                        // pop:(none) => push:number
                        array_push($abcStack, ["value" => null,
                                               "valuetype" => "short"]);
                    } else {
                        $tmp = []; // generate error message
                        for ($i = $idx; $i <= ($idx+3); $i++)  {
                            $ii = $this->codeArray[$i]["inst"];
                            $in = $this->getInstructionName($ii);
                            $nn = isset($this->codeArray[$i]["name"])? $this->codeArray[$i]["name"]: null;
                            $vv = isset($this->codeArray[$i]["value"])? $this->codeArray[$i]["value"]: null;
                            $tmp []= (is_null($nn))?
                                   ( is_null($vv)? ("$ii($in)"): ("$ii($in, $vv)") ):
                                   ("$ii($in, $nn)");
                        }
                        $this->dump();
                        throw new IO_SWF_Exception("unknown random instruction pattern: idx:".$idx." inst:".join(",", $tmp));
                    }
                } else if ($code["name"] === "MovieClip") {
                    //
                    $this->flushABCQueue($abcQueue, $abcStack, $actions, $labels, 1);
                    $c = array_shift($abcQueue);
                    if ($nextLabel) {
                        $labels[count($actions)] = $nextLabel;
                        $nextLabel = null;
                    }
                    if ($c["inst"] !== 96) {
                        // getlex root
                        // TODO getlex の name が root かもチェックする
                        $code->dump();
                        throw new IO_SWF_Exception('callproperty unknown pattern. need {getlex, callproperty MovieClip inst:'.$c["inst"]);
                    }
                    // root 参照なので何もしない
                    if ($nextLabel) {
                        $labels[count($actions)] = $nextLabel;
                        $nextLabel = null;
                    }
                } else if ($code["name"] === "substr") {
                    $this->flushABCQueue($abcQueue, $abcStack, $actions, $labels, 0);
                    if ($nextLabel) {
                        $labels[count($actions)] = $nextLabel;
                        $nextLabel = null;
                    }
                    // AS3: substr
                    // AS1: StringExtract(15)
                    $actions []= ["Code" => 0x15];  // StringExtract
                } else {
                    $this->dump();
                    throw new IO_SWF_Exception("support callproperty for random, substr,MovieClip(root) only =>  name:".$code["name"]);
                }
                break;
            case 0x47:  // returnvoid
                if ($nextLabel) {
                    $labels[count($actions)] = $nextLabel;
                    $nextLabel = null;
                }
                $this->flushABCQueue($abcQueue, $abcStack, $actions, $labels, 0);
                if ($nextLabel) {
                    $labels[count($actions)] = $nextLabel;
                    $nextLabel = null;
                }
                $actions []= ["Code" => 0x00]; // End
                break;
            case 0x4f:  // callpropvoid
                $index = $bit->get_u30();  // multiname
                $arg_count = $bit->get_u30();
                $multiname = $this->abc->_constant_pool["multiname"][$index];
                $name = $this->abc->getString_name($multiname["name"]);
                switch ($name) {
                case "gotoAndPlay":
                case "gotoAndStop":
                    /*
                     * AS3:
                     * callpropv name=gotoAndPlay or name=gotoAndStop
                     * AS1:
                     * => Push (/A/)B:C GoToFrame2 SceneBiasFlag=0 PlayFlag=1
                     * => Push (/A/)B:C GoToFrame2 SceneBiasFlag=0 PlayFlag=0
                     * => GoToFrame C ; Play | Stop
                     * => GoToLabel C ; Play | Stop
                    */
                    $push_path = null;
                    $gotofunc = "GotoFrame2";
                    if (count($abcQueue) >= 3) {
                        /*
                         *  getproperty name=A
                         *  getproperty name=B
                         *  pushbyte C
                         *  callpropvoid (GotoAnd*)
                         * => Push /A/B:C
                         * => GotoFrame2
                         */
                        $this->flushABCQueue($abcQueue, $abcStack, $actions, $labels, 3);
                        $a = $abcQueue[0];  // getproperty
                        $b = $abcQueue[1];  // getproperty
                        $c = $abcQueue[2];  // pushbyte || pushshort
                        if (($a["inst"] == 0x66) &&($b["inst"] == 0x66) && ($c["inst"] === 0x24) || ($c["inst"] === 0x25)) {
                            if (isset($a['name']) && ($a['name'] !== "") &&
                                isset($b['name']) && ($b['name'] !== "") &&
                                isset($c['value'])) {
                                // OK
                            } else {
                                $this->dump();
                                throw new IO_SWF_Exception("a b c parameter ".print_r([$a, $b, $c], true));
                            }
                            $push_path = "/".$a["name"]."/".$b["name"].":".$c["value"];
                            array_pop($abcQueue);
                            array_pop($abcQueue);
                            array_pop($abcQueue);
                            array_pop($abcStack);  // stackNum: -2 + 1
                        }
                    }
                    if (is_null($push_path) && (count($abcQueue) >= 2)) {
                        /*
                         *  getproperty name=B
                         *  pushbyte C
                         *  callpropvoid (GotoAnd*)
                         * => Push B:C
                         * => GotoFrame2
                         */
                        $this->flushABCQueue($abcQueue, $abcStack, $actions, $labels, 2);
                        $b = $abcQueue[0];  // getproperty
                        $c = $abcQueue[1];  // pushbyte || pushshort
                        if (($b["inst"] == 0x66) && ($c["inst"] === 0x24) || ($c["inst"] === 0x25)) {
                            if (isset($b['name']) && ($b['name'] !== "") &&
                                isset($c['value'])) {
                                // OK
                            } else {
                                $this->dump();
                                throw new IO_SWF_Exception("b c parameter ".print_r([$b, $c], true));
                            }
                            $push_path = $b["name"].":".$c["value"];
                            // stackNum: -1 + 1
                            array_pop($abcQueue);
                            array_pop($abcQueue);
                            $trackbackDone = true;
                        }
                    }
                    if (is_null($push_path) && (count($abcQueue) >= 1)) {
                        /*
                         *  pushbyte C
                         *  callpropvoid (GotoAnd*)
                         => GoToLabel C (string)
                         => GoToFrame C (short)
                         */
                        $this->flushABCQueue($abcQueue, $abcStack, $actions, $labels, 1);
                        $c = $abcQueue[0];
                        if (($c["inst"] === 0x24) || ($c["inst"] === 0x25) || ($c["inst"] === 0x2C)) {
                            if (! isset($c['value'])) {
                                $this->dump();
                                throw new IO_SWF_Exception("c parameter ".print_r([$b, $c], true));
                            }
                            // pushbyte || pushshort || pushstring
                            // $push_path = ".:".$c["value"];
                            $push_path = $c["value"];
                            array_pop($abcQueue);
                            // この後、pop されるので dummy を入れておく
                            array_push($abcStack, []); // stackNum: +1
                            $trackbackDone = true;
                            if ($c["inst"] === 0x2C) {  // string
                                $gotofunc = "GotoLabel";
                            } else {
                                $gotofunc = "GotoFrame";
                            }
                        }
                    }
                    $this->flushABCQueue($abcQueue, $abcStack, $actions, $labels, 0);
                    if (is_null($push_path) && (count($abcQueue) === 0)) {
                        $push_path = "";
                    }
                    if ($nextLabel) {
                        $labels[count($actions)] = $nextLabel;
                        $nextLabel = null;
                    }
                    // GotoFrame2 の場合、stack から読み込むので、
                    // $push_path が空の時もある
                    if ($gotofunc !== "GotoFrame2" && is_null($push_path)) {
                        if ($opts['strict']) {
                            $this->dump();
                            throw new IO_SWF_Exception("unknown pattern [$idx] callpropvoid $name bytecode");
                        }
                        fprintf(STDERR, "unknown pattern $name bytecode\n");
                        // TODO: pop した path 文字を整形して push し直す処理
                    }
                    $playFlag = ($name === "gotoAndPlay")? 1: 0;
                    switch ($gotofunc) {
                    case "GotoFrame2":
                        if ($push_path !== "") {
                            $actions []= ["Code" => 0x96, // Push
                                          "Length" => 1 + strlen($push_path) + 1,
                                          "Values" => [
                                          ["Type" => 0,  // String
                                           "String" => $push_path]
                                          ]];
                        }
                        $actions []= ["Code" => 0x9F,  // GotoFrame2
                                      "Length" => 1, "SceneBiasFlag" => 0,
                                      "PlayFlag" => $playFlag];
                        // pop: frame => push:(none)
                        array_pop($abcStack);
                        break;
                    case "GotoLabel":
                        $actions []= ["Code" => 0x8C,  // GotoLabel
                                      "Length" => strlen($push_path) + 1,
                                      "Label"=> $push_path];
                        if ($playFlag) {
                            $actions []= ["Code" => 0x06]; // Play
                        } else {
                            $actions []= ["Code" => 0x07]; // Stop
                        }
                        // pop: frame => push:(none)
                        array_pop($abcStack);
                        break;
                    case "GotoFrame":
                        $actions []= ["Code" => 0x81,  // GotoFrame
                                      "Length" => 2,
                                      "Frame"=> $push_path];
                        if ($playFlag) {
                            $actions []= ["Code" => 0x06]; // Play
                        } else {
                            $actions []= ["Code" => 0x07]; // Stop
                        }
                        // pop: frame => push:(none)
                        array_pop($abcStack);
                        break;
                    default:
                        $this->dump();
                        throw new IO_SWF_Exception("illegal gotoFunc type:".$gotofunc);
                    }
                    break;
                case "play":
                    $this->flushABCQueue($abcQueue, $abcStack, $actions, $labels, 0);
                    if ($nextLabel) {
                        $labels[count($actions)] = $nextLabel;
                        $nextLabel = null;
                    }
                    $actions []= ["Code" => 0x06]; // Play
                    break;
                case "stop":
                    $this->flushABCQueue($abcQueue, $abcStack, $actions, $labels, 0);
                    if ($nextLabel) {
                        $labels[count($actions)] = $nextLabel;
                        $nextLabel = null;
                    }
                    $actions []= ["Code" => 0x07]; // Stop
                    break;
                case "navigateToURL":
                    /*
                     * AS3:
                     constructprop  URLRequest
                     callpropvoid   navitateToURL
                     * AS1:
                     GetURL2 (0,0,0)
                     */
                    $this->flushABCQueue($abcQueue, $abcStack, $actions, $labels, 1);
                    if ($nextLabel) {
                        $labels[count($actions)] = $nextLabel;
                        $nextLabel = null;
                    }
                    $c = array_shift($abcQueue);  // constructprop
                    if ($c["inst"] !== 0x4a) {  // TODO: URLRequest チェックも
                        $code->dump();
                        throw new IO_SWF_Exception('callproperty navigateToURL unknown pattern. need constructpropd URLRequest inst:'.$c["inst"]);
                    }
                    $actions []= ["Code" => 0x9A, // GetURL2
                                  'LoadVariablesFlag' => 0,
                                  'LoadTargetFlag' => 0,
                                  'SendVarsMethod' => 0
                    ];
                    break;
                case "addEventListener":
                    $this->flushABCQueue($abcQueue, $abcStack, $actions, $labels, 6);
                    if ($nextLabel) {
                        $labels[count($actions)] = $nextLabel;
                        $nextLabel = null;
                    }
                    break;
                default:
                    if ($opts['strict']) {
                        $this->dump();
                        throw new IO_SWF_Exception("support callpropvoid for gotoAndPlay, play, stop only: unknown function name:".$name);
                    } else {
                        break 2;
                    }
                }
                break;
            case 0x61:  // setproperty
            case 0x68:  // initproperty
                /*
                  AS3:
                  - (???)
                  - setproperty/initproperty B
                  AS1
                  - Push B
                  - Push A
                  SetVariable
                 */
                $index = $bit->get_u30();
                $name = $propertyMap[$index]["name"];
                if (count($abcQueue) >= 1) {
                    $this->flushABCQueue($abcQueue, $abcStack, $actions, $labels, 1);
                }
                if ($nextLabel) {
                    $labels[count($actions)] = $nextLabel;
                    $nextLabel = null;
                }
                $actions []= ["Code" => 0x96, // Push
                              "Length" => 1 + strlen($name) + 1,
                              "Values" => [
                                  ["Type" => 0,  // String
                                   "String" => $name]
                              ]];
                if (count($abcQueue) < 1) {
                    $actions []= ["Code" => 0x4D]; // StackSwap
                } else {
                    $prevCode = array_pop($abcQueue);
                    if ($prevCode["inst"] == 0x2C) { // ひとつ前が pushstring
                        /*
                          AS3:
                          - pushstring A
                          - setproperty/initproperty B
                          AS1
                          - Push B
                          - Push A
                          SetVariable
                        */
                        if (! is_string($prevCode["value"])) {
                            $this->dump();
                            throw new IO_SWF_Exception("require pushstring instrument value type string:".$prevCode["value"]);
                        }
                        $actions []= ["Code" => 0x96, // Push
                                      "Length" => 1 + strlen($prevCode["value"]) + 1,
                                      "Values" => [
                                          ["Type" => 0,  //String
                                           "String" => $prevCode["value"]."\0"]
                                      ]];
                    } else if ($prevCode["inst"] == 0x24) {  // ひとつ前が pushbyte
                        /*
                          AS3:
                          - pushbyte A
                          - setproperty/initproperty B
                          AS1
                          - Push B
                          - Push A
                          SetVariable
                        */
                        if (! is_int($prevCode["value"])) {
                            $this->dump();
                            throw new IO_SWF_Exception("require pushstring instrument value type string:".$prevCode["value"]);
                        }
                        $actions []= ["Code" => 0x96, // Push
                                      "Length" => 1 + 4,
                                      "Values" => [
                                          ["Type" => 7,  // Integer
                                           "Integer" => $prevCode["value"]]
                                      ]];
                    }
                }
                $actions []= ["Code" => 0x1d]; // SetVariable
                // pop: value, push:(none)
                // but skip flush push instruction.
                $propertyMap[$index] = ["value"     => $name,
                                        "valuetype" => 0,  // String
                                        "name"      => $name];
                break;
            case 0x75:  // convert_d
                // do nothing
                // (double に変換する命令だが、AS1/2 には型がない)
                break;
            case 0x93:  // decrement
                $this->flushABCQueue($abcQueue, $abcStack, $actions, $labels, 0);
                if ($nextLabel) {
                    $labels[count($actions)] = $nextLabel;
                    $nextLabel = null;
                }
                $actions []= ["Code" => 0x51]; // Decrement
                break;
            case 0x91:  // increment
                $this->flushABCQueue($abcQueue, $abcStack, $actions, $labels, 0);
                if ($nextLabel) {
                    $labels[count($actions)] = $nextLabel;
                    $nextLabel = null;
                }
                $actions []= ["Code" => 0x50];  // Increment
                break;
            case 0xa1:  // subtract
                $this->flushABCQueue($abcQueue, $abcStack, $actions, $labels, 0);
                if ($nextLabel) {
                    $labels[count($actions)] = $nextLabel;
                    $nextLabel = null;
                }
                $actions []= ["Code" => 0x0B]; // Subtract
                break;
            case 0xc0:  // increment_i
                $this->flushABCQueue($abcQueue, $abcStack, $actions, $labels, 0);
                if ($nextLabel) {
                    $labels[count($actions)] = $nextLabel;
                    $nextLabel = null;
                }
                if (true) {  // ダメなら false にする
                    // TODO: ActionIncrement で良いかも？
                    $actions []= ["Code" => 0x50];  // Increment
                    break;
                }
                /*
                  AS3: i++;
                  [0] increment_i
                  AS1: i = i + 1;
                  [0] push 1
                  [1] add
                 */
                $data = "1";
                $actions []= ["Code" => 0x96, // Push
                              "Length" => 1 + strlen($data) + 1,
                              "Values" => [
                                  ["Type" => 0,  // String
                                   "String" => $data]
                              ]];
                $actions []= ["Code" => 0x0A];  // Add
                break;
            case 0xd0:  // getlocal_0
            case 0xd1:  // getlocal_1
            case 0xd2:  // getlocal_2
            case 0xd3:  // getlocal_3
                // register(local_x) to stack
                array_push($abcQueue, $code);
                break;
            case 0xd4:  // setlocal_0
            case 0xd5:  // setlocal_1
            case 0xd6:  // setlocal_2
            case 0xd7:  // setlocal_3
                $this->flushABCQueue($abcQueue, $abcStack, $actions, $labels, 0);
                if ($nextLabel) {
                    $labels[count($actions)] = $nextLabel;
                    $nextLabel = null;
                }
                // stack to register (local_x)
                break;
            default:
                array_push($abcQueue, $code);
            }
        }
        if (count($labels) || count($branches)) {
            // labels と branches の renumbering
            // 例) label 2=>4, branch 7=>4  (2,7 は AS1 idx, 4 は AS3 idx)
            //   =変換後> label 2=>2, branch 7=>2
            $labelsTrans = [];  // label の移動テーブル
            // 移動テーブルを作る。上記でいうと 4 => 2
            foreach ($labels as $idx => $label) {
                $labelsTrans[$label] = $idx;
            }
            foreach ($labels as $idx => $label) {
                $labels[$idx] = $idx;
            }
            foreach ($actions as $idx => $_action) {
                if (isset($branches[$idx])) {
                    $branches[$idx] = $labelsTrans[$branches[$idx]];
                }
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
    function flushABCQueue(&$abcQueue, &$abcStack, &$actions, &$labels, $remain = 0) {
        //print('flushABCQueue Begin: count(abcQueue)'.count($abcQueue)." remain:$remain\n");
        $opts = $this->opts;
        if (count($abcQueue) < $remain) {
            $c = count($abcQueue);
            $this->dump();
            throw new IO_SWF_Exception("not enough abcQueue:$c need $remain");
        }
        $ctx = $this->codeContext;
        $propertyMap = $ctx->propertyMap;
        // as FIFO
        while (count($abcQueue) > $remain) {
            $code = array_shift($abcQueue);
            //print("flushABCQueue Loop: code:".$code["inst"]." name:".$code["inst"]."\n");
            if (! isset($code["bytes"])) {
                fprintf(STDERR, print_r($code), true);
                throw new IO_SWF_Exception('flushABCQueue: ! isset($code["bytes"])');
            }
            $bit = new IO_SWF_ABC_Bit();
            $bit->input($code["bytes"]);
            $inst = $bit->getUI8();
            if ($opts['debug']) {
                echo "DEBUG: flushABCQueue: $inst(".$this->getInstructionName($inst).")\n";
            }
            switch ($inst) {
            case 0x08:  // kill
                // do nothing
                break;
            case 0x09:  // label
                // do nothing
                break;
            case 0x1d:  // popscope
                // do nothing
                break;
            case 0x24:  // pushbyte
            case 0x25:  // pushshort
            case 0x2C:  // pushstring
            case 0x2F:  // pushdouble
                $data = (string) $code["value"];
                $actions []= ["Code" => 0x96, // Push
                              "Length" => 1 + strlen($data) + 1,
                              "Values" => [
                                  ["Type" => 0,  // String
                                   "String" => $data]
                              ]];
                array_push($abcStack, $code);
                break;
            case 0x30:  // pushscope
                // do nothing
                break;
            case 0x5d:  // findpropstrict
                // do nothing
                break;
            case 0x66:  // getproperty
                //$this->flushABCQueue($abcQueue, $abcStack, $actions, $labels, 0);
                $index = $bit->get_u30();
                if (! isset($propertyMap[$index])) {
                    /*
                    $this->dump();
                    $info = ['codeContext' => $ctx,
                             'index' => $index];
                    throw new IO_SWF_Exception('! isset($propertyMap[$index]'.print_r($info, true));
                    */
                    $info = $this->abc->getMultiname($index);
                    $name = $this->abc->getString_name($info["name"]);
                    $propertyMap[$index] = ["name" => $name,
                                            "valuetype" => null];
                }
                $name = $propertyMap[$index]["name"];
                $actions []= ["Code" => 0x96, // Push
                              "Length" => 1 + strlen($name) + 1,
                              "Values" => [
                                  ["Type" => 0,  // String
                                   "String" => $name]
                              ]];
                $actions []= ["Code" => 0x1C]; // GetVariable
                // pop:(none) => push:value
                array_push($abcStack, $propertyMap[$index]);
                break;
            case 0xa0:  // add
                $a = array_pop($abcStack);
                $b = array_pop($abcStack);
                $c = $this->typeExpantion($a, $b);
                array_push($abcStack, $c);
                //
                if ($c["valuetype"] === "string") {
                    $actions []= ["Code" => 0x21];  // StringAdd
                } else {
                    $actions []= ["Code" => 0x0A];  // Add
                }
                break;
            case 0xa2:  // multiply
                $actions []= ["Code" => 0x0C];  // Multiply
                $a = array_pop($abcStack);
                $b = array_pop($abcStack);
                $c = $this->typeExpantion($a, $b);
                array_push($abcStack, $c);
                break;
            case 0xa4:  // modulo
                $actions []= ["Code" => 0x3F];  // Modulo
                $a = array_pop($abcStack);
                $b = array_pop($abcStack);
                $c = $this->typeExpantion($a, $b);
                array_push($abcStack, $c);
                break;
            case 0x60:  // getlex
                // findpropstict の後に getproperty を実行するのと同じ
                break;
            case 0xd0:  // getlocal_0
            case 0xd1:  // getlocal_1
            case 0xd2:  // getlocal_2
            case 0xd3:  // getlocal_3
                // local scope なので一旦無視
                break;
            default:
                $instName = $this->getInstructionName($inst);
                fprintf(STDERR, "unsupported instruction:$instName($inst)\n");
            }
        }
        $ctx->propertyMap = $propertyMap;
    }
    function typeExpantion($a, $b) {
        $atype = $a["valuetype"];
        $btype = $b["valuetype"];
        $ctype = $atype;
        if ($btype === "string") {
            $ctype = $btype;
        }
        return ["value" => null,  // indefinite value
                "valuetype" => $ctype];
    }
}
