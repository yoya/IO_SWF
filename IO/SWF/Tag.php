<?php

require_once dirname(__FILE__).'/../SWF.php';

class IO_SWF_Tag {
    var $code = 0;
    var $length = 0;
    var $longFormat = false;
    var $content = null;
    function getTagInfo($tagCode, $label) {
        static $tagMap = array(
         // code => array(name , klass)
             0 => array('name' => 'End'),
             1 => array('name' => 'ShowFrame'),
             2 => array('name' => 'DefineShape',  'klass' => 'Shape'),
//             3 => array('name' => 'FreeCharacter'), // ???
             4 => array('name' => 'PlaceObject'),
             5 => array('name' => 'RemoveObject'),
             6 => array('name' => 'DefineBitsJPEG'),
             7 => array('name' => 'DefineButton'),
             8 => array('name' => 'JPEGTables'),
             9 => array('name' => 'SetBackgroundColor'),
            10 => array('name' => 'DefineFont'),
            11 => array('name' => 'DefineText'),
            12 => array('name' => 'DoAction'),
            13 => array('name' => 'DefineFontInfo'),
            14 => array('name' => 'DefineSound'),
            15 => array('name' => 'StartSound'),
            // 16 missing
            17 => array('name' => 'DefineButtonSound'),
            18 => array('name' => 'SoundStreamHead'),
            19 => array('name' => 'SoundStreamBlock'),
            20 => array('name' => 'DefineBitsLossless'),
            21 => array('name' => 'DefineBitsJPEG2'),
            22 => array('name' => 'DefineShape2', 'klass' => 'Shape'),
            24 => array('name' => 'Protect'),
	    // 25 missing
            26 => array('name' => 'PlaceObject2'),
	    // 27 missing
            28 => array('name' => 'RemoveObject2'),
	    // 29,30,31 missing
            32 => array('name' => 'DefineShape3', 'klass' => 'Shape'),
            33 => array('name' => 'DefineText2'),
            34 => array('name' => 'DefineButton2'),
            35 => array('name' => 'DefineBitsJPEG3'),
            36 => array('name' => 'DefineBitsLossless2'),
            37 => array('name' => 'DefineEditText'),
	    // 38 missing
            39 => array('name' => 'DefineSprite'),
	    // 40,41,42 missing
            43 => array('name' => 'FrameLabel'),
	    // 44 missing
            45 => array('name' => 'SoundStreamHead2'),
            46 => array('name' => 'DefineMorphShape'),
            48 => array('name' => 'DefineFont2'),
            56 => array('name' => 'Export'),
            57 => array('name' => ''),
            58 => array('name' => ''),
            59 => array('name' => 'DoInitAction'),
	    //
            60 => array('name' => 'DefineVideoStream'),
            61 => array('name' => 'videoFrame'),
            62 => array('name' => 'DefineFontInfo2'),
	    // 63 missing
            64 => array('name' => 'EnableDebugger2'),
            65 => array('name' => 'ScriptLimits'),
            66 => array('name' => 'SetTabIndex'),
	    // 67,68 missing 
            69 => array('name' => 'FileAttributes'),
            70 => array('name' => 'PlaceObject3'),
            71 => array('name' => 'ImportAssets2'),
	    // 72 missing
            73 => array('name' => 'DefineFontAlignZones'),
            74 => array('name' => 'CSMTextSettings'),
            75 => array('name' => 'DefineFont3'),
            76 => array('name' => 'SymbolClass'),
            77 => array('name' => 'MetaData'),
            78 => array('name' => 'DefineScalingGrid'),
	    // 79,80,81 missing
            82 => array('name' => 'DoABC'),
            83 => array('name' => 'DefineShape4'),
            84 => array('name' => 'DefineMorphShape2'),
            // 85 missing
            86 => array('name' => 'DefineSceneAndFrameLabelData'),
            87 => array('name' => 'DefineBinaryData'),
            88 => array('name' => 'DefineFontName'),
            89 => array('name' => 'StartSound2'),
            90 => array('name' => 'DefineBitsJPEG4'),
            91 => array('name' => 'DefineFont4'),
            777 => array('name' => 'Reflex'),
        );
        if (isset($tagMap[$tagCode][$label])) {
           return $tagMap[$tagCode][$label];
        }
        return false;
    }
    function parse(&$reader, $opts = array()) {
        $tagAndLength = $reader->getUI16LE();
        $this->code = $tagAndLength >> 6;
        $length = $tagAndLength & 0x3f;
        if ($length == 0x3f) { // long format
            $length = $reader->getUI32LE();
            $this->LongFormat = true;
        }
        $this->length = $length;
        $this->content = $reader->getData($length);
    }
    function dump($opts = array()) {
        $code = $this->code;
        $length = $this->length;
        $name = $this->getTagInfo($code, 'name');
        if ($name === false) {
           $name = 'unknown';
        }
        echo "Code: $code($name)  Length: $length".PHP_EOL;
        $klass = self::getTagInfo($code, 'klass');
        if ($klass !== false) {
	    $klass = "IO_SWF_Tag_$klass";
            $shape = new $klass();
            $shape->parseContent($code, $this->content);
            $shape->dumpContent($code);
        }
    }
    function build($opts = array()) {
        $code = $this->code;
        $content = $this->content;
        $this->length = strlen($this->content);
        $length = $this->length;
        $writer = new IO_Bit();
        if (($this->longFormat === false) && ($length < 0x3f)) {
            $tagAndLength = ($code << 6) | $length;
            $writer->putUI16LE($tagAndLength);
        } else {
            $tagAndLength = ($code << 6) | 0x3f;
            $writer->putUI16LE($tagAndLength);
            $writer->putUI32LE($length);
        }
        return $writer->output() . $content;
    }
}
