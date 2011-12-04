<?php

require_once dirname(__FILE__).'/../SWF.php';
require_once dirname(__FILE__).'/../SWF/Tag/Shape.php';

class IO_SWF_Tag {
    var $swfInfo;
    var $code = 0;
    var $content = null;
    var $tag = null;
    var $byte_offset, $byte_size;
    function __construct($swfInfo) {
        $this->swfInfo = $swfInfo;
    }
    function getTagInfo($tagCode, $label) {
        static $tagMap = array(
         // code => array(name , klass)
             0 => array('name' => 'End'),
             1 => array('name' => 'ShowFrame'),
             2 => array('name' => 'DefineShape',  'klass' => 'Shape'),
//             3 => array('name' => 'FreeCharacter'), // ???
             4 => array('name' => 'PlaceObject', 'klass' => 'Place'),
             5 => array('name' => 'RemoveObject', 'klass' => 'Remove'),
             6 => array('name' => 'DefineBits', 'klass' => 'Jpeg'),
             7 => array('name' => 'DefineButton', 'klass' => 'Button'),
             8 => array('name' => 'JPEGTables', 'klass' => 'Jpeg'),
             9 => array('name' => 'SetBackgroundColor', 'klass' => 'BGColor'),
            10 => array('name' => 'DefineFont'),
            11 => array('name' => 'DefineText', 'klass' => 'Text'),
            12 => array('name' => 'DoAction', 'klass' => 'Action'),
            13 => array('name' => 'DefineFontInfo'),
            14 => array('name' => 'DefineSound'),
            15 => array('name' => 'StartSound'),
            // 16 missing
            17 => array('name' => 'DefineButtonSound'),
            18 => array('name' => 'SoundStreamHead'),
            19 => array('name' => 'SoundStreamBlock'),
            20 => array('name' => 'DefineBitsLossless', 'klass' => 'Lossless'),
            21 => array('name' => 'DefineBitsJPEG2', 'klass' => 'Jpeg'),
            22 => array('name' => 'DefineShape2', 'klass' => 'Shape'),
            24 => array('name' => 'Protect'),
            // 25 missing
            26 => array('name' => 'PlaceObject2', 'klass' => 'Place'),
            // 27 missing
            28 => array('name' => 'RemoveObject2', 'klass' => 'Remove'),
            // 29,30,31 missing
            32 => array('name' => 'DefineShape3', 'klass' => 'Shape'),
            33 => array('name' => 'DefineText2', 'klass' => 'Text'),
            34 => array('name' => 'DefineButton2', 'klass' => 'Button'),
            35 => array('name' => 'DefineBitsJPEG3', 'klass' => 'Jpeg'),
            36 => array('name' => 'DefineBitsLossless2', 'klass' => 'Lossless'),
            37 => array('name' => 'DefineEditText', 'klass' => 'EditText'),
            // 38 missing
            39 => array('name' => 'DefineSprite', 'klass' => 'Sprite'),
            // 40,41,42 missing
            43 => array('name' => 'FrameLabel', 'klass' => 'FrameLabel'),
            // 44 missing
            45 => array('name' => 'SoundStreamHead2'),
            46 => array('name' => 'DefineMorphShape', 'klass' => 'Shape'),
            48 => array('name' => 'DefineFont2'),
            56 => array('name' => 'Export'),
            57 => array('name' => ''),
            58 => array('name' => ''),
            59 => array('name' => 'DoInitAction', 'klass' => 'Action'),
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
            777 => array('name' => 'Reflex'), // swftools ?
        );
        if (isset($tagMap[$tagCode][$label])) {
           return $tagMap[$tagCode][$label];
        }
        return false;
    }
    function parse(&$reader, $opts = array()) {
        list($this->byte_offset, $dummy) = $reader->getOffset();
        $tagAndLength = $reader->getUI16LE();
        $this->code = $tagAndLength >> 6;
        $length = $tagAndLength & 0x3f;
        if ($length == 0x3f) { // long format
            $length = $reader->getUI32LE();
        }
        $this->content = $reader->getData($length);
        list($byte_offset, $dummy) = $reader->getOffset();
        $this->byte_size = $byte_offset - $this->byte_offset;
    }
    function dump($opts = array()) {
        $code = $this->code;
        $name = $this->getTagInfo($code, 'name');
        if ($name === false) {
           $name = 'unknown';
        }
        $length = strlen($this->content);
        echo "Code: $code($name)  Length: $length".PHP_EOL;
        $opts['Version'] = $this->swfInfo['Version'];
        if ($this->parseTagContent($opts)) {
            $this->tag->dumpContent($code, $opts);
        }
        if (empty($opts['hexdump']) === false) {
           $bitio =& $opts['bitio'];
           $bitio->hexdump($this->byte_offset, $this->byte_size);
        }
    }
    function build($opts = array()) {
        $code = $this->code;
        if (is_null($this->content)) {
            $this->content = $this->buildTagContent();
        }
        $length = strlen($this->content);
        $writer = new IO_Bit();
        switch ($code) {
          case 6:  // DefineBitsJPEG
          case 21: // DefineBitsJPEG2
          case 35: // DefineBitsJPEG3
          case 20: // DefineBitsLossless
          case 36: // DefineBitsLossless2
          case 19: // SoundStreamBlock
            $longFormat = true;
            break;
          default:
            $longFormat = false;
            break;
        }
        if (($longFormat === false) && ($length < 0x3f)) {
            $tagAndLength = ($code << 6) | $length;
            $writer->putUI16LE($tagAndLength);
        } else {
            $tagAndLength = ($code << 6) | 0x3f;
            $writer->putUI16LE($tagAndLength);
            $writer->putUI32LE($length);
        }
        return $writer->output() . $this->content;
    }
    function parseTagContent() {
        if (is_null($this->tag) === false) {
            return true;
        }
        $code = $this->code;
        $klass = self::getTagInfo($code, 'klass');
        if ($klass === false) {
            return false; // no parse
        }
        require_once dirname(__FILE__)."/Tag/$klass.php";
        $klass = "IO_SWF_Tag_$klass";
        $obj = new $klass($this->swfInfo);
        $opts['Version'] = $this->swfInfo['Version'];
        $obj->parseContent($code, $this->content, $opts);
        $this->tag = $obj;
        return true;
    }
    function buildTagContent() {
            if ((is_null($this->content) === false)) {
            return $this->content;
        }
        if (is_null($this->tag)) {
            return false; // throw Exception!
        }
        $code = $this->code;
        $opts['Version'] = $this->swfInfo['Version'];
        $this->content = $this->tag->buildContent($code, $opts); // XXX
        return $this->content;
    }

    function bitmapSize() {
        $code = $this->code;
        if ($this->parseTagContent() === false) {
            throw new IO_SWF_Exception("failed to parseTagContent");
        }
        switch ($code) {
        case 6:  // DefineBitsJPEG
        case 21: // DefineBitsJPEG2
        case 35: // DefineBitsJPEG3
            ;
            break;
        case 20: // DefineBitsLossless
        case 36: // DefineBitsLossless2
            break;
        default:
            break;
        }
    }
    function replaceCharacterId($trans_table) {
        $new_cid = $trans_table[$this->characterId];
        if ($new_cid == $this->characterId) {
            return true; // no change
        }
        $this->characterId = $new_cid;
        if (isset($this->content)) {
            $this->content[0] = chr($new_cid & 0xff);
            $this->content[1] = chr($new_cid >> 8);
        }
        if (isset($this->tag)) {
            switch ($this->code) {
            case 6:  // DefineBits
            case 21: // DefineBitsJPEG2
            case 35: // DefineBitsJPEG3
            case 20: // DefineBitsLossless
            case 36: // DefineBitsLossless2
            case 2:  // DefineShape (ShapeId)
            case 22: // DefineShape2 (ShapeId)
            case 32: // DefineShape3 (ShapeId)
            case 46: // DefineMorphShape (ShapeId)
            case 11: // DefineText
            case 33: // DefineText2
            case 37: // DefineTextEdit
            case 39: // DefineSprite
                foreach (array('_CharacterID', '_spriteId', '_shapeId', 'CharacterID') as $id_prop_name) {
                    if (isset($this->tag->$id_prop_name)) {
                        $this->tag->$id_prop_name = $new_cid;
                        break;
                    }
                }
                break;
            }
        }
        return true;
    }
    function replaceReferenceId($trans_table) {
        if ($this->parseTagContent() === false) {
            new IO_SWF_Exception("parseTagContent failed");
        }
        switch ($this->code) {
        case 4:  // PlaceObject
        case 5:  // RemoveObject
        case 26: // PlaceObject2 (Shape Reference)
            if (isset($this->tag->_characterId)) {
                $new_cid = $trans_table[$this->tag->_characterId];
                if ($this->tag->_characterId != $new_cid) {
                    $this->tag->_characterId = $new_cid;
                    $this->content = null;
                }
            }
            break;
        case 2:  // DefineShape   (Bitmap ReferenceId)
        case 22: // DefineShape2　 (Bitmap ReferenceId)
        case 32: // DefineShape3    (Bitmap ReferenceId)
        case 46: // DefineMorphShape (Bitmap ReferenceId)
            if ($this->parseTagContent() === false) {
                throw new IO_SWF_Exception("failed to parseTagContent");
            }
            $modified = false;
            if (is_null($this->tag->_fillStyles) === false) {
                foreach ($this->tag->_fillStyles as &$fillStyle) {
                    if (isset($fillStyle['BitmapId'])) {
                        if ($fillStyle['BitmapId'] != 65535) {
                            $new_id = $trans_table[$fillStyle['BitmapId']];
                            if ($fillStyle['BitmapId'] != $new_id) {
                                $modified = true;
                                $fillStyle['BitmapId'] = $new_id;
                            }
                        }
                    }
                }
            }
            if (is_null($this->tag->_shapeRecords) === false) {
                foreach ($this->tag->_shapeRecords as &$shapeRecord) {
                    if (isset($shapeRecord['FillStyles'])) {
                        foreach ($shapeRecord['FillStyles'] as &$fillStyle) {
                            if (isset($fillStyle['BitmapId'])) {
                                if ($fillStyle['BitmapId'] != 65535) {
                                    $new_id = $trans_table[$fillStyle['BitmapId']];
                                    if ($fillStyle['BitmapId'] != $new_id) {
                                        $modified = true;
                                        $fillStyle['BitmapId'] = $new_id;
                                    }
                                }
                            }
                        }
                    }
                }
            }
            if ($modified) {
                $this->content = null;
            }
            break;
        }
        return true;
    }
}
