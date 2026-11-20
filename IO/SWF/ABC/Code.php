<?php

require_once dirname(__FILE__).'/Bit.php';  // IO_SWF_ABC_Bit
require_once dirname(__FILE__).'/../Exception.php';
require_once dirname(__FILE__).'/Code/Converter.php';

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
    // AS3 のバイトコードを AS1 のアクション列 (DoAction タグ) に変換する
    // 変換の本体は IO_SWF_ABC_Code_Converter (Code/Converter.php)
    function ABCCodetoActionTag($version, $ctx, $opts) {
        $this->codeContext = $ctx;
        $this->opts = $opts;
        $converter = new IO_SWF_ABC_Code_Converter($this, $version, $opts);
        list($actions, $labels, $branches) = $converter->convert();
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

    // イベントハンドラを、ボタン (BUTTONCONDACTION) のアクション列に変換する。
    // this はメインタイムライン (絶対パス) として扱い、途中の return は末尾への Jump にする。
    // 分岐先はバイト位置で確定させ、末尾の End は付けない (BUTTONCONDACTION が終端を書く)。
    function ABCCodetoButtonActions($version, $ctx, $opts) {
        $opts['thisBase'] = 'root';
        $opts['returnAsJump'] = true;
        $action_tag = $this->ABCCodetoActionTag($version, $ctx, $opts);
        $bin = $action_tag->tag->buildContent(12, []);
        $resolved = new IO_SWF_Tag_Action();
        $resolved->parseContent(12, $bin);
        $actions = $resolved->_actions;
        if (count($actions) && $actions[count($actions) - 1]['Code'] === 0x00) {
            array_pop($actions);
        }
        return $actions;
    }
}
