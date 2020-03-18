<?php

require_once dirname(__FILE__).'/../SWF.php';
require_once dirname(__FILE__).'/../SWF/Tag/Shape.php';
require_once dirname(__FILE__).'/Lossless.php';
require_once dirname(__FILE__).'/JPEG.php';

class IO_SWF_Tag {
    var $swfInfo;
    var $code = 0;
    var $content = null;
    var $tag = null;
    var $byte_offset, $byte_size;
    static $tagMap = array(
        // code => array(name , klass)
        0 => array('name' => 'End', 'version' => 1),
        1 => array('name' => 'ShowFrame', 'version' => 1),
        2 => array('name' => 'DefineShape',  'klass' => 'Shape', 'version' => 1 ),
//             3 => array('name' => 'FreeCharacter'), // ???
        4 => array('name' => 'PlaceObject', 'klass' => 'Place', 'version' => 1),
        5 => array('name' => 'RemoveObject', 'klass' => 'Remove', 'version' => 1),
        6 => array('name' => 'DefineBits', 'klass' => 'Jpeg', 'version' => 1),
        7 => array('name' => 'DefineButton', 'klass' => 'Button', 'version' => 1),
        8 => array('name' => 'JPEGTables', 'klass' => 'Jpeg', 'version' => 1),
        9 => array('name' => 'SetBackgroundColor', 'klass' => 'BGColor', 'version' => 1),
        10 => array('name' => 'DefineFont', 'version' => 1),
        11 => array('name' => 'DefineText', 'klass' => 'Text', 'version' => 1),
        12 => array('name' => 'DoAction', 'klass' => 'Action', 'version' => 3),
        13 => array('name' => 'DefineFontInfo', 'version' => 1),
        14 => array('name' => 'DefineSound', 'klass' => 'Sound', 'version' => 1),
        15 => array('name' => 'StartSound', 'version' => 1),
        // 16 missing
        17 => array('name' => 'DefineButtonSound', 'version' => 2),
        18 => array('name' => 'SoundStreamHead', 'version' => 1),
        19 => array('name' => 'SoundStreamBlock', 'version' => 1),
        20 => array('name' => 'DefineBitsLossless', 'klass' => 'Lossless', 'version' => 2),
        21 => array('name' => 'DefineBitsJPEG2', 'klass' => 'Jpeg', 'version' => 2),
        22 => array('name' => 'DefineShape2', 'klass' => 'Shape', 'version' => 2),
        24 => array('name' => 'Protect', 'version' => 2),
        // 25 missing
        26 => array('name' => 'PlaceObject2', 'klass' => 'Place', 'version' => 3),
        // 27 missing
        28 => array('name' => 'RemoveObject2', 'klass' => 'Remove', 'version' => 3),
        // 29,30,31 missing
        32 => array('name' => 'DefineShape3', 'klass' => 'Shape', 'version' => 3),
        33 => array('name' => 'DefineText2', 'klass' => 'Text', 'version' => 3),
        34 => array('name' => 'DefineButton2', 'klass' => 'Button', 'version' => 3),
        35 => array('name' => 'DefineBitsJPEG3', 'klass' => 'Jpeg', 'version' => 3),
        36 => array('name' => 'DefineBitsLossless2', 'klass' => 'Lossless', 'version' => 3),
        37 => array('name' => 'DefineEditText', 'klass' => 'EditText', 'version' => 4),
        // 38 missing
        39 => array('name' => 'DefineSprite', 'klass' => 'Sprite', 'version' => 3),
        // 40,41,42 missing
        43 => array('name' => 'FrameLabel', 'klass' => 'FrameLabel', 'version' => 3),
        // 44 missing
        45 => array('name' => 'SoundStreamHead2', 'version' => 3),
        46 => array('name' => 'DefineMorphShape', 'klass' => 'Shape', 'version' => 3),
        48 => array('name' => 'DefineFont2', 'klass' => 'Font', 'version' => 3),
        56 => array('name' => 'ExportAssets', 'version' => 5),
        57 => array('name' => '', 'version' => null),
        58 => array('name' => '', 'version' => null),
        59 => array('name' => 'DoInitAction', 'klass' => 'Action', 'version' => 6),
        //
        60 => array('name' => 'DefineVideoStream', 'version' => 6),
        61 => array('name' => 'videoFrame', 'version' => 6),
        62 => array('name' => 'DefineFontInfo2', 'version' => 6),
        // 63 missing
        64 => array('name' => 'EnableDebugger2', 'version' => 6),
        65 => array('name' => 'ScriptLimits', 'version' => 7),
        66 => array('name' => 'SetTabIndex', 'version' => 7),
        // 67,68 missing
        69 => array('name' => 'FileAttributes', 'klass' => 'FileAttributes', 'version' => 8),
        70 => array('name' => 'PlaceObject3', 'klass' => 'Place', 'version' => 8),
        71 => array('name' => 'ImportAssets2', 'version' => 8),
        // 72 missing
        73 => array('name' => 'DefineFontAlignZones', 'version' => 8),
        74 => array('name' => 'CSMTextSettings', 'version' => 8),
        75 => array('name' => 'DefineFont3', 'klass' => 'Font', 'version' => 8),
        76 => array('name' => 'SymbolClass', 'klass' => 'SymbolClass', 'version' => 9),
        77 => array('name' => 'MetaData', 'version' => 1),
        78 => array('name' => 'DefineScalingGrid', 'version' => 8),
        // 79,80,81 missing
        82 => array('name' => 'DoABC', 'klass' => 'ABC', 'version' => 9),
        83 => array('name' => 'DefineShape4', 'klass' => 'Shape', 'version' => 8),
        84 => array('name' => 'DefineMorphShape2', 'version' => 8),
        // 85 missing
        86 => array('name' => 'DefineSceneAndFrameLabelData', 'version' => 9),
        87 => array('name' => 'DefineBinaryData', 'version' => 9),
        88 => array('name' => 'DefineFontName', 'version' => 9),
        89 => array('name' => 'StartSound2', 'version' => 9),
        90 => array('name' => 'DefineBitsJPEG4', 'version' => 10),
        91 => array('name' => 'DefineFont4', 'version' => 10),
        777 => array('name' => 'Reflex', 'version' => null), // swftools ?
        );

    function __construct($swfInfo) {
        $this->swfInfo = $swfInfo;
    }

    function getTagInfoList() {
        return self::$tagMap;
    }

    function getTagInfo($tagCode, $label) {
        if (isset(self::$tagMap[$tagCode][$label])) {
           return self::$tagMap[$tagCode][$label];
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
        list($this->byte_offset_content, $dummy) = $reader->getOffset();
        $this->content = $reader->getData($length);

        $reader->byteAlign();
        list($byte_offset, $dummy) = $reader->getOffset();
/*
        if ($length != $byte_offset - $this->byte_offset_content) {
            throw new IO_SWF_Exception("Tag length:$length != byte_offset:$byte_offset - byte_offset_content:{$this->byte_offset_content}");
        }
*/
        $this->byte_size = $byte_offset - $this->byte_offset;
    }

    function dump(&$opts = array()) {
        $code = $this->code;
        $name = $this->getTagInfo($code, 'name');
        if ($name === false) {
           $name = 'unknown';
        }
        $length = strlen($this->content);
        echo "Code: $code($name)  Length: $length";
        if ($code === 1) {  // ShowFrame
            $frameNum = $opts["FrameNum"]++;
            echo " (FrameNum=$frameNum)";
        }
        echo PHP_EOL;
        $opts['Version'] = $this->swfInfo['Version'];
        $opts['tagCode'] = $code;
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

    function parseTagContent($opts = array()) {
        if (is_null($this->tag) === false) {
            return true;
        }
        if (is_null($this->content)) {
            throw new IO_SWF_Exception("no tag and no content in ".var_export($this, true));
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
        $opts['tagCode'] = $code;
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
        $opts['tagCode'] = $code;
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
              case 2: // DefineShape (ShapeId)
              case 6: // DefineBits
              case 7: // DefineButton
              case 10: // DefineFont (FontID)
              case 11: // DefineText
              case 13: // DefineFontInfo (FontID)
              case 14: // DefineSound
              case 17: // DefineButtonSound
              case 18: // SoundStreamHead"
              case 19: // SoundStreamBlock
              case 20: // DefineBitsLossless
              case 21: // DefineBitsJPEG2
              case 22: // DefineShape2 (ShapeId)
              case 32: // DefineShape3 (ShapeId)
              case 33: // DefineText2
              case 34: // DefineButton2
              case 35: // DefineBitsJPEG3
              case 36: // DefineBitsLossless2
              case 37: // DefineEditText
              case 39: // DefineSprite (SpriteId)
              case 46: // DefineMorphShape
              case 48: // DefineFont2 (FontID)
              case 73: // DefineFontAlignZones (FontID)
              case 75: // DefineFont3 (FontID)
              case 83: // DefineShape4 (ShapeId)
              case 84: // DefineMorphShape2 (ShapeId)
              case 88: // DefineFontName (FontID)
                  foreach (array('_CharacterID', '_spriteId', '_shapeId', 'CharacterID', '_buttonId') as $id_prop_name) {
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
          case 34: // DefineButton2
            if (isset($this->tag->_characterId)) {
                $new_cid = $trans_table[$this->tag->_characterId];
                if ($this->tag->_characterId != $new_cid) {
                    $this->tag->_characterId = $new_cid;
                    $this->content = null;
                }
            } else if (isset($this->tag->_buttonId)) {
                $new_cid = $trans_table[$this->tag->_buttonId];
                if ($this->tag->_buttonId != $new_cid) {
                    $this->tag->_buttonId = $new_cid;
                    $this->content = null;
                }
            }
            break;
          case 2:  // DefineShape   (Bitmap ReferenceId)
          case 22: // DefineShape2　 (Bitmap ReferenceId)
          case 32: // DefineShape3    (Bitmap ReferenceId)
          case 46: // DefineMorphShape (Bitmap ReferenceId)
            if ($this->parseTagContent() === false) {
                new IO_SWF_Exception("parseTagContent failed");
            }
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

    function getJpegData($jpegTables) {
        $tag_code = $this->code;
        if (($tag_code != 6) && // DefineBits
            ($tag_code != 21) && // DefineBitsJPEG2
            ($tag_code != 35)) { // DefineBitsJPEG3
            return false;
        }
        if (! $this->parseTagContent()) {
            return false;
        }
        $jpegData = $this->tag->_JPEGData;

        if (($tag_code == 6) && ($jpegTables !== false)) { // DefineBits
            $jpegData .= $jpegTables;
        }
        $jpeg = new IO_SWF_JPEG();
        $jpeg->input($jpegData);

        $ret = $jpeg->getStdJpegData();
        return $ret;
    }

    function getPNGData() {
        $tag_code = $this->code;
        if (($tag_code != 20) && // DefineBitsLossless
            ($tag_code != 36)) { // DefineBitsLossless2
            return false;
        }
        if (! $this->parseTagContent()) {
            return false;
        }
        $cid = $this->tag->_CharacterID;
        $format = $this->tag->_BitmapFormat;
        $width =  $this->tag->_BitmapWidth;
        $height = $this->tag->_BitmapHeight;
        $lossless_bitmap_data = gzuncompress($this->tag->_ZlibBitmapData);

        if ($format == 3) {
            $palette_num = $this->tag->_BitmapColorTableSize;
            if ($tag_code == 20) { // DefineBisLossless
                $palette_bytesize = 3 * $palette_num;
            } else {
                $palette_bytesize = 4 * $palette_num;
            }
            $palette_data = substr($lossless_bitmap_data, 0, $palette_bytesize);
            $lossless_bitmap_data = substr($lossless_bitmap_data, $palette_bytesize);
        } else {
            $palette_num = 0;
            $palette_data = null;
        }
        $png_data = IO_SWF_Lossless::Lossless2PNG($tag_code, $format,
                                                  $width, $height,
                                                  $palette_num,
                                                  $palette_data,
                                                  $lossless_bitmap_data);
        return $png_data;
    }

    function getSoundData() {
        $tag_code = $this->code;
        if ($tag_code != 14) { // DefineSound
            return false;
        }
        if (! $this->parseTagContent()) {
            return false;
        }
        $soundData = $this->tag->SoundData;
        return $soundData;
    }
}
