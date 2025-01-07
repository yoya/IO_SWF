<?php

/*
 * 2010/8/12- (c) yoya@awm.jp
 */

require_once dirname(__FILE__).'/Exception.php';
require_once dirname(__FILE__).'/../SWF.php';
require_once dirname(__FILE__).'/Tag/Shape.php';
require_once dirname(__FILE__).'/Tag/Action.php';
require_once dirname(__FILE__).'/Tag/Sprite.php';
require_once dirname(__FILE__).'/Tag/Sound.php';
require_once dirname(__FILE__).'/Lossless.php';
require_once dirname(__FILE__).'/JPEG.php';
require_once dirname(__FILE__).'/Bitmap.php';
require_once dirname(__FILE__).'/ABC/Code/Context.php';

class IO_SWF_Editor extends IO_SWF {
    // var $_headers = array(); // protected
    // var $_tags = array();    // protected
    var $shape_adjust_mode = self::SHAPE_BITMAP_NONE;

    const SHAPE_BITMAP_NONE           = 0;
    const SHAPE_BITMAP_MATRIX_RESCALE = 1;
    const SHAPE_BITMAP_RECT_RESIZE    = 2;
    const SHAPE_BITMAP_TYPE_TILED     = 4;

    var $setCharacterIdDone = false;
    var $setReferenceIdDone = false;

    function rebuild() {
        foreach ($this->_tags as &$tag) {
            if ($tag->parseTagContent()) {
                $tag->content = null;
            }
        }
    }

    function setCharacterId() {
        if ($this->setCharacterIdDone) {
            return ;
        }
        foreach ($this->_tags as &$tag) {
            if (is_null($tag->content)) {
                throw new IO_SWF_Exception("setCharacterId method must be called at next of parse");
            }
            $content_reader = new IO_Bit();
            $content_reader->input($tag->content);
            switch ($tag->code) {
              case 6:  // DefineBits
              case 21: // DefineBitsJPEG2
              case 35: // DefineBitsJPEG3
              case 20: // DefineBitsLossless
              case 36: // DefineBitsLossless2
              case 2:  // DefineShape (ShapeId)
              case 22: // DefineShape2 (ShapeId)
              case 32: // DefineShape3 (ShapeId)
              case 83: // DefineShape4 (ShapeId)
              case 46: // DefineMorphShape (ShapeId)
              case 10: // DefineFont
              case 48: // DefineFont2
              case 75: // DefineFont3
              case 13: // DefineFontInfo
              case 73: // DefineFontAlignZones
              case 11: // DefineText
              case 33: // DefineText2
              case 37: // DefineTextEdit
              case 39: // DefineSprite
              case 34: // DefineButton2
              case 60: // DefineVideoStream
                $tag->characterId = $content_reader->getUI16LE();
                break;
            }
        }
        $this->setCharacterIdDone = true;
    }

    function _setReferenceId(&$tag) {
        $content_reader = new IO_Bit();
        $content_reader->input($tag->content);
        switch ($tag->code) {
        case 4:  // PlaceObject
        case 5:  // RemoveObject
            $tag->referenceId = $content_reader->getUI16LE();
            break;
        case 26: // PlaceObject2 (Shape Reference)
            $tag->placeFlag = $content_reader->getUI8();
            if ($tag->placeFlag & 0x02) {
                $content_reader->getUI16LE(); // depth
                $tag->referenceId = $content_reader->getUI16LE();
            }
            break;
        case 2:  // DefineShape  (Bitmap ReferenceId)
        case 22: // DefineShape2　(Bitmap ReferenceId)
        case 32: // DefineShape3   (Bitmap ReferenceId)
        case 83: // DefineShape4    (Bitmap ReferenceId)
        case 46: // DefineMorphShape (Bitmap ReferenceId)
            $refIds = array();
            if ($tag->parseTagContent() === false) {
                throw new IO_SWF_Exception("failed to parseTagContent");
            }
            if (is_null($tag->tag->_fillStyles) === false) {
                foreach ($tag->tag->_fillStyles as $fillStyle) {
                    if (isset($fillStyle['BitmapId'])) {
                        if ($fillStyle['BitmapId'] != 65535) {
                            $refIds []= $fillStyle['BitmapId'];
                        }
                    }
                }
                $tag->referenceId = $refIds;
            }
            if (is_null($tag->tag->_shapeRecords) === false) {
                foreach ($tag->tag->_shapeRecords as $shapeRecord) {
                    if (isset($shapeRecord['FillStyles'])) {
                        foreach ($shapeRecord['FillStyles'] as $fillStyle) {
                            if (isset($fillStyle['BitmapId'])) {
                                if ($fillStyle['BitmapId'] != 65535) {
                                    $refIds []= $fillStyle['BitmapId'];
                                }
                            }
                        }
                    }
                }
                $tag->referenceId = $refIds;
            }
            break;
        case 34: // DefineButton2
            $refIds = array();       
            if ($tag->parseTagContent() === false) {
                throw new IO_SWF_Exception("failed to parseTagContent");
            }
            if (is_null($tag->tag->_characters) === false) {
                foreach ($tag->tag->_characters as $character) {
                    $refIds []= $character['CharacterID'];
                }
                $tag->referenceId = $refIds;
            }
            break;
        case 11: // DefineText
        case 33: // DefineText2
            if ($tag->parseTagContent() === false) {
                throw new IO_SWF_Exception("failed to parseTagContent");
            }
            if (is_null($tag->tag->_TextRecords) === false) {
                $refIds = array();
                foreach ($tag->tag->_TextRecords as $textRecord) {
                    if (isset($textRecord['FontID'])) {
                        $refIds []= $textRecord['FontID'];
                    }
                }
                if (count($refIds) > 0) {
                    $tag->referenceId = $refIds;
                }
            }
            break;
        case 61: // VideoFrame
            $tag->referenceId = $content_reader->getUI16LE(); // StreamID
            break;
        }
        return true;
    }

    function setReferenceId() {
        if ($this->setReferenceIdDone) {
            return ;
        }
        foreach ($this->_tags as &$tag) {
            if ($tag->code == 39) { // DefineSprite
                if ($tag->parseTagContent() === false) {
                    throw new IO_SWF_Exception("failed to parseTagContent");
                }
                $refIds = array();
                foreach ($tag->tag->_controlTags as &$tag_in_sprite) {
                    $this->_setReferenceId($tag_in_sprite);
                    if (isset($tag_in_sprite->referenceId)) {
                        $refIds_in_sprite = $tag_in_sprite->referenceId;
                        if (is_array($refIds_in_sprite)) {
                            $refIds += $refIds_in_sprite;
                        } else {
                            $refIds []= $refIds_in_sprite;
                        }
                    }
                }
                if (count($refIds) > 0) {
                    $tag->referenceId = $refIds;
                }
            } else {
                $this->_setReferenceId($tag);
            }
        }
        unset($tag);
        $this->setReferenceIdDone = true;
    }

    function getTagByCharacterId($characterId) {
        foreach ($this->_tags as $tag) {
            if (isset($tag->characterId)) {
                if ($tag->characterId == $characterId) {
                    return $tag;
                }
            }
        }
        return null;
    }
    function getTagsByReferenceId($referenceId) {
        return $this->_getTagsByReferenceId($referenceId, $this->_tags);
    }
    function _getTagsByReferenceId($referenceId, $input_tags) {
        $tags = [];
        foreach ($input_tags as $tag) {
            if (isset($tag->referenceId)) {
                // echo "code:".$tag->code." Ids:" . (is_array($refId)? join(",", $refId): $refId)."\n";
                $refId = $tag->referenceId;
                if (is_array($refId)) {
                    foreach ($refId as $id) {
                        if ($id == $referenceId) {
                            $tags []= $tag;
                            break;
                        }
                    }
                } else {
                    if ($refId == $referenceId) {
                        $tags []= $tag;
                    }
                }
            }
            if ($tag->code == 39) { // DefineSprite
                if ($tag->parseTagContent() === false) {
                    throw new IO_SWF_Exception("failed to parseTagContent");
                }
                $tags_sprite = $this->_getTagsByReferenceId($referenceId, $tag->tag->_controlTags);
                $tags = array_merge($tags, $tags_sprite);
            }
        }
        return $tags;
    }

    function replaceTagContent($tagCode, $content, $limit = 1) {
        $count = 0;
        foreach ($this->_tags as &$tag) {
            if ($tag->code == $tagCode) {
                $tag->content = $content;
                $count += 1;
                if ($limit <= $count) {
                    break;
                }
            }
        }
        return $count;
    }

    function getTagContent($tagCode) {
        $count = 0;
        foreach ($this->_tags as &$tag) {
            if ($tag->code == $tagCode) {
                return $tag->content;
            }
        }
        return false;
    }

    function replaceTagContentByCharacterId($tagCode, $characterId, $content_after_character_id) {
        if (! is_array($tagCode)) {
            $tagCode = array($tagCode);
        }
        $ret = false;
        foreach ($this->_tags as &$tag) {
            if (in_array($tag->code, $tagCode) && isset($tag->characterId)) {
                if ($tag->characterId == $characterId) {
                    $tag->content = pack('v', $characterId).$content_after_character_id;
                    $ret = true;
                    break;
                }
            }
        }
        return $ret;
    }

    function replaceTagByCharacterId($tagCode, $characterId, $replaceTag) {
        if (! is_array($tagCode)) {
            $tagCode = array($tagCode);
        }
        $ret = 0;
        foreach ($this->_tags as &$tag) {
            if (in_array($tag->code, $tagCode) && isset($tag->characterId)) {
                if ($tag->characterId == $characterId) {
                    if (isset($replaceTag['Code'])) {
                        $tag->code = $replaceTag['Code'];
                    }
                    $tag->length = strlen($replaceTag['Content']);
                    $tag->content = $replaceTag['Content'];
                    $ret = 1;
                    break;
                }
            }
        }
        return $ret;
    }

    function replaceBitmapTagByCharacterId($tagCode, $characterId, $replaceTag) {
        if (! is_array($tagCode)) {
            $tagCode = array($tagCode);
        }
        $ret = 0;
        foreach ($this->_tags as &$tag) {
            if (in_array($tag->code, $tagCode) && isset($tag->characterId)) {
                if ($tag->characterId == $characterId) {
                    if (isset($replaceTag['Code'])) {
                        $tag->code = $replaceTag['Code'];
                    }
                    $tag->length = strlen($replaceTag['Content']);
                    $tag->content = $replaceTag['Content'];
                    $ret = 1;
                    break;
                }
            }
        }
        return $ret;
    }

    function getTagContentByCharacterId($tagCode, $characterId) {
        foreach ($this->_tags as $tag) {
            if (($tag->code == $tagCode) && isset($tag->characterId)) {
                if ($tag->characterId == $characterId) {
                    return $tag->content;
                    break;
                }
            }
        }
        return null;
    }

    function deformeShape($threshold) {
        foreach ($this->_tags as &$tag) {
            $code = $tag->code;
            switch($code) {
              case 2: // DefineShape
              case 22: // DefineShape2
              case 32: // DefineShape3
                $shape = new IO_SWF_Tag_Shape();
                $shape->parseContent($code, $tag->content);
                $shape->deforme($threshold);
                $tag->content = $shape->buildContent($code); // XXX
                break;
            }
        }
    }

    function setActionVariables($trans_table_or_key_str, $value_str = null) {
        if(is_array($trans_table_or_key_str)) {
            $trans_table = $trans_table_or_key_str;
        } else {
            $trans_table = array($trans_table_or_key_str => $value_str);
        }
        foreach ($this->_tags as $tagidx => &$tag) {
            $code = $tag->code;
            switch($code) {
              case 12: // DoAction
              case 59: // DoInitAction
                  $action = new IO_SWF_Tag_Action();
                  $action->parseContent($code, $tag->content);
                break 2;
              case 1: // ShowFrame
                break 2;
            }
        }
        if (isset($action) === false) {
            // 1 frame 目に Action タグがないので新規作成
            $bytecode = '';
            foreach ($trans_table as $key_str => $value_str) {
                $key_strs   = explode("\0", $key_str);   // \0 除去
                $value_strs = explode("\0", $value_str); // \0 除去
                $key_data   = chr(0).$key_strs[0]."\0";
                $value_data = chr(0).$value_strs[0]."\0";
                // Push
                $bytecode .= chr(0x96).pack('v', strlen($key_data)).$key_data;
                // Push
                $bytecode .= chr(0x96).pack('v', strlen($value_data)).$value_data;
                // SetVarables
                $bytecode .= chr(0x1d);
                // End
                $bytecode .= chr(0);
            }
            $tag_action = new IO_SWF_Tag();
            $tag_action->code = 12; // DoAction
            $tag_action->content = $bytecode;
            // 新規タグ挿入
            array_splice($this->_tags, $tagidx, 0, array($tag_action));
        } else { // 既にある Action タグに bytecode 追加。
            foreach ($trans_table as $key_str => $value_str) {
                $action_rec = array('Code' => 0x96, // Push
                                      'Values' => array(
                                          array('Type' => 0,
                                                'String' => $key_str)));
                $action->insertAction(0, $action_rec);
                $action_rec = array('Code' => 0x96, // Push
                                      'Values' => array(
                                          array('Type' => 0,
                                                'String' => $value_str)));
                $action->insertAction(1, $action_rec);
                $action_rec = array('Code' => 0x1d); // SetVariable
                $action->insertAction(2, $action_rec);
            }
            $tag->content = $action->buildContent($code);
//            $tag->content = null;
        }
    }

    function replaceActionStrings($trans_table_or_from_str, $to_str = null) {
        $opts = array('Version' => $this->_headers['Version']); // for parser
        if(is_array($trans_table_or_from_str)) {
            $trans_table = $trans_table_or_from_str;
        } else {
            $trans_table = array($trans_table_or_from_str => $to_str);
        }
        foreach ($this->_tags as &$tag) {
            $code = $tag->code;
            switch($code) {
              case 12: // DoAction
              case 59: // DoInitAction
                $tag->parseTagContent($opts);
                if ($tag->tag->replaceActionStrings($trans_table)) {
                    $tag->content = null;
                }
                break;
              case 34: // DefineButton2
                $tag->parseTagContent($opts);
                if (is_null($tag->tag->_actions) === false) {
                    foreach ($tag->tag->_actions as &$buttoncondaction) {
                        if (isset($buttoncondaction['Actions'])) {
                            foreach ($buttoncondaction['Actions'] as &$action) {
                                if (IO_SWF_Type_Action::replaceActionString($action, $trans_table)) {
                                    $tag->content = null;
                                }
                            }
                            unset($action);
                        }
                    }
                    unset($buttoncondaction);
                }
                break;
              case 39: // Sprite
                $tag->parseTagContent($opts);
                foreach ($tag->tag->_controlTags as &$tag_in_sprite) {
                    $code_in_sprite = $tag_in_sprite->code;
                    switch ($code_in_sprite) {
                      case 12: // DoAction
                      case 59: // DoInitAction
                        $tag_in_sprite->parseTagContent($opts);
                        if ($tag_in_sprite->tag->replaceActionStrings($trans_table)) {
                            $tag_in_sprite->content = null;
                            $tag->content = null;
                        }
                        break;
                    }
                }
                unset($tag_in_sprite);
                break;
            }
        }
    }

    function replaceBitmapData($bitmap_id, $bitmap_data, $jpeg_alphadata = null) {
        $this->setCharacterId();
        // TODO: 後で IO_SWF_Bitmap::detect_bitmap_format を使うよう書き換える
        if ((strncmp($bitmap_data, 'GIF', 3) == 0) ||
            (strncmp($bitmap_data, "\x89PNG", 4) == 0)) {
            $tag = IO_SWF_Lossless::BitmapData2Lossless($bitmap_id, $bitmap_data);
            $new_width = $tag['width'];
            $new_height = $tag['height'];
        } else if (strncmp($bitmap_data, "\xff\xd8\xff", 3) == 0) {
            $erroneous_header = pack('CCCC', 0xFF, 0xD9, 0xFF, 0xD8);
            if (is_null($jpeg_alphadata)) {
                // 21: DefineBitsJPEG2
                $content = pack('v', $bitmap_id).$erroneous_header.$bitmap_data;
                $tag = array('Code' => 21,
                             'Content' => $content);
            } else {
                // 35: DefineBitsJPEG3
                $jpeg_data = $erroneous_header.$bitmap_data;
                $compressed_alphadata = gzcompress($jpeg_alphadata);
                $content = pack('v', $bitmap_id).pack('V', strlen($jpeg_data)).$jpeg_data.$compressed_alphadata;
                $tag = array('Code' => 35,
                             'Content' => $content);
            }
            list($new_width, $new_height) = IO_SWF_Bitmap::get_jpegsize($bitmap_data);
        } else {
            throw new IO_SWF_Exception("Unknown Bitmap Format: ".bin2hex(substr($bitmap_data, 0, 4)));
        }
        if ($this->shape_adjust_mode > 0) {
            $ret = $this->applyShapeAdjustModeByRefId($bitmap_id, $new_width, $new_height);
        }
        // DefineBits,DefineBitsJPEG2,3, DefineBitsLossless,DefineBitsLossless2
        $tag_code = array(6, 21, 35, 20, 36);
        if ($this->shape_adjust_mode > 0) {
            $tag['shape_adjust_mode'] = $this->shape_adjust_mode;
        }
        $ret = $this->replaceBitmapTagByCharacterId($tag_code, $bitmap_id, $tag);
//        $ret = $this->replaceTagByCharacterId($tag_code, $bitmap_id, $tag);
        return $ret;
    }

    function getJpegData($bitmap_id) {
        $this->setCharacterId();
        $tag = $this->getTagByCharacterId($bitmap_id);
        $tag_code = $tag->code;
        if (($tag_code != 6) && // DefineBits
            ($tag_code != 21) && // DefineBitsJPEG2
            ($tag_code != 35)) { // DefineBitsJPEG3
            return false;
        }
        if (! $tag->parseTagContent()) {
            return false;
        }
        $jpegData = $tag->tag->_JPEGData;

        $jpeg = new IO_SWF_JPEG();
        $jpeg->input($jpegData);
        $jpegTables = $this->getTagContent(8); // JPEGTables
        $ret = $jpeg->getStdJpegData($jpegTables);
        return $ret;
    }

    function getJpegAlpha($bitmap_id) {
        $this->setCharacterId();
        $tag = $this->getTagByCharacterId($bitmap_id);
        $tag_code = $tag->code;
        if ($tag_code != 35) { // DefineBitsJPEG3
            return false;
        }
        if (! $tag->parseTagContent()) {
            return false;
        }
        $jpegAlpha = gzuncompress($tag->tag->_ZlibBitmapAlphaData);
        return $jpegAlpha;
	}

    function getPNGData($bitmap_id) {
        $this->setCharacterId();
        $tag = $this->getTagByCharacterId($bitmap_id);
        $tag_code = $tag->code;
        if (($tag_code != 20) && // DefineBitsLossless
            ($tag_code != 36)) { // DefineBitsLossless2
            return false;
        }
        return $tag->getPNGData();
    }

    function getSoundData($sound_id) {
        $this->setCharacterId();
        $tag = $this->getTagByCharacterId($sound_id);
        $tag_code = $tag->code;
        if ($tag_code != 14) { // DefineSound
            return false;
        }
        return $tag->getSoundData();
    }

    function getVideoStream($video_id) {
        $this->setCharacterId();
        $tag = $this->getTagByCharacterId($video_id);
        $tag_code = $tag->code;
        if ($tag_code !== 60) { // // DefineVideoStream
            fprintf(STDERR, "stream_id:$video_id tag->code:$tag_code != 60\n");
            return false;
        }
        if (! $tag->parseTagContent()) {
            fprintf(STDERR, "failed parseTagContent\n");
            return false;
        }
        return $tag->tag;
    }

    function getVideoFrames($video_id) {
        $opts = ['_CodecID' => []];
        $this->setCharacterId();
        $this->setReferenceId();
        $tag = $this->getTagByCharacterId($video_id);
        $tag_code = $tag->code;
        if ($tag_code !== 60) { // // DefineVideoStream
            fprintf(STDERR, "stream_id:$video_id tag->code:$tag_code != 60\n");
            return false;
        }
        if (! $tag->parseTagContent()) {
            fprintf(STDERR, "failed parseTagContent\n");
            return false;
        }
        $opts['_CodecID'][$tag->tag->_CharacterID] = $tag->tag->_CodecID;
        //
        $tags = $this->getTagsByReferenceId($video_id);  // VideoFrames
        $frames = [];
        foreach ($tags as $tag) {
            $tag_code = $tag->code;
            if ($tag_code === 61) { // VideFrame
                if (! $tag->parseTagContent($opts)) {
                    fprintf(STDERR, "failed parseTagContent\n");
                    return false;
                }
                $frame = ["Data" => $tag->getVideoData($opts)];
                $alphaData = $tag->getVideoAlphaData($opts);
                if ($alphaData !== false) {
                    $frame["AlphaData"] = $alphaData;
                }
                $frames []= $frame;
            }
        }
        return $frames;
    }

    function applyShapeAdjustModeByRefId($bitmap_id, $new_height, $old_height) {
        $shape_adjust_mode = $this->shape_adjust_mode;
        switch ($shape_adjust_mode) {
          case self::SHAPE_BITMAP_NONE:
            return false;
          case self::SHAPE_BITMAP_MATRIX_RESCALE:
          case self::SHAPE_BITMAP_RECT_RESIZE:
            
          case self::SHAPE_BITMAP_TYPE_TYLED:
            break ;
          default:
            trigger_error("Illegal shape_adjust_mode($shape_adjust_mode)");
            return false;
        }
        
        switch ($shape_adjust_mode) {
          case self::SHAPE_BITMAP_MATRIX_RESCALE:
            break ;
          case self::SHAPE_BITMAP_RECT_RESIZE:
            break ;
          case self::SHAPE_BITMAP_TYPE_TYLED:
            break ;
          default:
            trigger_error("Illegal shape_adjust_mode($shape_adjust_mode)");
            return false;
        }
        return true;
    }

    function countShapeEdges($opts = array()) {
        $count_table = array();
        foreach ($this->_tags as $tag) {
            $code = $tag->code;
            switch ($code) {
              case 2: // DefineShape
              case 22: // DefineShape2
              case 32: // DefineShape3
              case 46: // DefineMorphShape
                $shape = new IO_SWF_Tag_Shape();
                $shape->parseContent($code, $tag->content);
                list($shape_id, $edges_count) = $shape->countEdges();
                $count_table[$shape_id] = $edges_count;
            }
        }
        return $count_table;
    }

    function countShapeRecords($opts = array()) {
        $count_table = array();
        foreach ($this->_tags as $tag) {
            $code = $tag->code;
            switch ($code) {
              case 2: // DefineShape
              case 22: // DefineShape2
              case 32: // DefineShape3
              case 46: // DefineMorphShape
                $shape = new IO_SWF_Tag_Shape();
                $shape->parseContent($code, $tag->content);
                list($shape_id, $record_count) = $shape->countRecords();
                $count_table[$shape_id] = $record_count;
            }
        }
        return $count_table;
    }

    function sliceShapeRecords($shape_id, $start, $end) {
        $count_table = array();
        $ret = false;
        foreach ($this->_tags as &$tag) {
            $code = $tag->code;
            switch ($code) {
              case 2: // DefineShape
              case 22: // DefineShape2
              case 32: // DefineShape3
              case 46: // DefineMorphShape
                $tag->parseTagContent();
                if ($tag->tag->_shapeId != $shape_id) {
                    break;
                }
                $ret = $tag->tag->sliceRecords($start, $end);
                $tag->content = null;
                break 2;
            }
        }
        return $ret;
    }

    function setShapeAdjustMode($mode) {
        $this->shape_adjust_mode = $mode;
    }

    function searchMovieClipTagByCID($cid, $opts) {
        foreach ($this->_tags as $tag_idx => $tag) {        
            if ($tag->code == 39) { // DefineSprite
                if ($tag->parseTagContent($opts)) {
                    if ($tag->tag->_spriteId == $cid) {
                        return $tag_idx;
                    }
                }
            }
        }
        return false;
    }

    function searchMovieClipTagByTargetPath($target_path, $opts) {
        /*
         * scanning for target sprite tag
         */
        $target_sprite_tag_idx = false;
        $target_path_list = explode('/', $target_path);
        $tag_scan_state = 1; // 1:scan for name 2: scan for character id
        $tag_scan_character_id = -1;
        foreach (array_reverse(array_keys($this->_tags)) as $tag_idx) {
            $tag = $this->_tags[$tag_idx];
            $code = $tag->code;
            switch($tag_scan_state) {
              case 1: // scan for name
                if ($code == 26) { // PlaceObject2
                    if ($tag->parseTagContent($opts) &&
                        isset($tag->tag->_name) &&
                        ($tag->tag->_name == $target_path_list[0])) {
                        array_shift($target_path_list);
                        $tag_scan_character_id = $tag->tag->_characterId;
                        $tag_scan_state = 2; // scan for character id
                    }
                }
                break;
              case 2: // scan for character id
                if ($code == 39) { // DefineSprite
                    if (isset($tag->characterId) && ($tag->characterId == $tag_scan_character_id)) {
                        if (count($target_path_list) === 0) {
                            $target_sprite_tag_idx = $tag_idx;
                            break; // sprite tag found !!
                        }
                        if ($tag->parseTagContent($opts)) {
                            foreach ($tag->tag->_controlTags as $tag_in_sprite) {
                                // PlaceObject2 in DefineSprite
                                if ($tag_in_sprite->code == 26) {
                                    if ($tag_in_sprite->parseTagContent($opts) &&
                                        isset($tag_in_sprite->tag->_name) &&
                                        ($tag_in_sprite->tag->_name == $target_path_list[0])) {
                                        array_shift($target_path_list);
                                        $tag_scan_character_id = $tag_in_sprite->tag->_characterId;
                                    }
                                }
                            }
                        }
                    }
                }
            }
            if ($target_sprite_tag_idx !== false) {
                break; // target sprite found
            }
        }
        return $target_sprite_tag_idx;
    }

    function replaceMovieClip($target_path, $mc_swfdata) {
        $this->setCharacterId();
        $mc_tag_idx = null;
        if ($target_path === '') {
            trigger_error('target_path is null string');
            return false;
        }
        $opts = array('Version' => $this->_headers['Version']); // for parser
        if (is_numeric($target_path)) {
            $cid = $target_path;
            $target_sprite_tag_idx = $this->searchMovieClipTagByCID($cid, $opts);
        } else {
            $target_sprite_tag_idx = $this->searchMovieClipTagByTargetPath($target_path, $opts);
        }
        if ($target_sprite_tag_idx === false) {
            trigger_error("target_path symbol not found($target_path)");
            return false;
        }

        /*
         * base swf character id check
         */
        $used_character_id_table = array();
        foreach ($this->_tags as $tag) {
            if (isset($tag->characterId)) {
                $used_character_id_table[$tag->characterId] = true;
            }
        }
        /*
         * new sprite tag character id renumbering
         */
        $character_id_trans_table = array();
        $mc_swf = new IO_SWF_Editor();
        $mc_swf->parse($mc_swfdata);
        $mc_swf->setCharacterId();
        $mc_swf->setReferenceId();
        $mc_character_tag_list = array();
        foreach ($mc_swf->_tags as $tag_idx => &$tag) {
            switch ($tag->code) {
              case 8: //JPEGTables
              case 9: // SetBackgroundColor
              case 69: // FileAttributes
                unset($mc_swf->_tags[$tag_idx]); // delete
                continue 2;
            }
            if (isset($tag->characterId)) {
//                echo "code={$tag->code}\n";
                $cid = $tag->characterId;
                if (isset($used_character_id_table[$cid]) &&
                    (isset($character_id_trans_table[$cid]) === false)) {
                    $new_cid = $cid;
                    while (isset($used_character_id_table[$new_cid])) {
                        $new_cid++;
                    }
                    $character_id_trans_table[$cid] = $new_cid;
                    $used_character_id_table[$new_cid] = true;
                }
                if (isset($character_id_trans_table[$cid])) {
                    $tag->replaceCharacterId($character_id_trans_table);
                }
                $mc_character_tag_list[] = $tag;
                unset($mc_swf->_tags[$tag_idx]); // delete
            }
            if (isset($tag->referenceId)) {
                $tag->replaceReferenceId($character_id_trans_table);
            }
            if ($tag->code == 39) { // DefineSprite
                if ($tag->parseTagContent()) {
                    foreach ($tag->tag->_controlTags as &$tag_in_sprite) {
                        $tag_in_sprite->replaceReferenceId($character_id_trans_table);
                    }
                    $tag->content = null;
                }
            }
        }
        unset($tag);
        /*
         * replace
         */
        $sprite_tag_ref =& $this->_tags[$target_sprite_tag_idx];
        if ($sprite_tag_ref->parseTagContent() === false) {
            return false;
        }
        $frameCount = 0;
        $controlTags = array_values($mc_swf->_tags);
        foreach ($controlTags as $tag) {
            if ($tag->code == 1) {
                $frameCount++;
            }
        }
        $sprite_tag_ref->tag->_frameCount = $frameCount;
        $sprite_tag_ref->tag->_controlTags = $controlTags;
        $sprite_tag_ref->content = null;
        /*
         * character tag insert
         */
        array_splice($this->_tags, $target_sprite_tag_idx, 0, $mc_character_tag_list);
        return true;
    }

    function getMovieClip($target_path) {
        return $this->getOrGrepMovieClip($target_path, false);
    }

    function grepMovieClip($target_path) {
        return $this->getOrGrepMovieClip($target_path, true);
    }

    function getOrGrepMovieClip($target_path, $is_grep) {
        $this->setCharacterId();
        $mc_tag_idx = null;
        if ($target_path === '') {
            trigger_error('target_path is null string');
            return false;
        }
        $opts = array('Version' => $this->_headers['Version']); // for parser
        if (is_numeric($target_path)) {
            $cid = $target_path;
            $target_sprite_tag_idx = $this->searchMovieClipTagByCID($cid, $opts);
        } else {
            $target_sprite_tag_idx = $this->searchMovieClipTagByTargetPath($target_path, $opts);
        }
        if ($target_sprite_tag_idx === false) {
            trigger_error("target_path symbol not found($target_path)");
            return false;
        }
        $target_sprite_tag = $this->_tags[$target_sprite_tag_idx];
        if ($target_sprite_tag->parseTagContent($opts) === false) {
            trigger_error("target_sprite_tag parse failed");
            return false;
        }
        $new_main_tags = array();
        foreach ($this->_tags as $tag_idx => $tag) {
            if ($tag_idx <= $target_sprite_tag_idx) {
                switch ($tag->code) {
                case 1: // ShowFrame
                case 4: // PlaceObject
                case 5: // RemoveObject
                case 9: // SetBackgroundColor
                case 12: // DoAction
                case 26: // PlaceObject2
                case 28: // RemoveObject2
                case 43: // FrameLabel
                case 59: // DoInitAction
                    break; // skip non Define Tags;
                default:
                    $new_main_tags []= $tag;
                }
            } else {
                break;
            }
        }
        if ($is_grep) { // is grep
            $new_main_tags []= $target_sprite_tag;
            foreach ($this->_tags as $tag_idx => $tag) {
                if ($tag_idx <= $target_sprite_tag_idx) {
                    continue;
                }
                if ($tag->code == 26) { // PlaceObject
                    if (($tag->parseTagContent($opts) === false) ||
                        is_null($tag->tag->_characterId) === false) {
                        continue;
                    }
                    if ($tag->tag->_characterId == $target_sprite_tag->tag->_spriteId) {
                        $new_main_tags []= $tag;
                        $end_tag = new IO_SWF_Tag();
                        $end_tag->code = 0;
                        $new_main_tags []= $end_tag;
                        break;
                    }
                }
            }
        } else { // is get
            // movieclip to maintimeline
            foreach ($target_sprite_tag->tag->_controlTags as $tag_in_sprite) {
                $new_main_tags []= $tag_in_sprite;
            }
        }
        $swf = clone $this;
        $swf->_tags = $new_main_tags;
        $swf->purgeUselessContents();
        return $swf->build();
    }

    function listMovieClip_r($prefix, $characterId, $name, $parent_cids, &$spriteTable) {
        $spriteId = $characterId;
        $spriteTable[$spriteId]['name'] = $name;
        if (is_null($prefix)) {
            $path = $name;
        } else {
            $path = $prefix.'/'.$name;
        }
        if (isset($spriteTable[$spriteId]['path_list']) === false) {
            $spriteTable[$spriteId]['path_list'] = array();
        }
        $spriteTable[$spriteId]['path_list'] []= array('path' => $path, 'parent_cids' => $parent_cids);
        foreach ($spriteTable[$spriteId]['Places'] as $place) {
            $this->listMovieClip_r($path, $place['cid'], $place['name'], array_merge($parent_cids, array($spriteId)), $spriteTable);
        }
        return true;
    }

    function listMovieClip() {
        $spriteTable = array();
        foreach ($this->_tags as $tag) {
            $opts = array();
            switch ($tag->code) {
            case 26: //  PlaceObject2
                $tag->parseTagContent($opts);
                if (is_null($tag->tag->_name) === false) {
                    $cid = $tag->tag->_characterId;
                    $name = $tag->tag->_name;
                    $this->listMovieClip_r(null, $cid, $name, array(), $spriteTable);
                }
                break;
            case 39: // DefineSprite
                $tag->parseTagContent($opts);
                $spriteId = $tag->tag->_spriteId;
                $spriteTable[$spriteId] = array('FrameCount' => $tag->tag->_frameCount, 'TagCount' => count($tag->tag->_controlTags), 'Places' => array());
                foreach ($tag->tag->_controlTags as &$tag_in_sprite) {
                    if ($tag_in_sprite->code == 26) { // PlaceObject2
                        $tag_in_sprite->parseTagContent();
                        if (is_null($tag_in_sprite->tag->_name) === false) {
                            $cid = $tag_in_sprite->tag->_characterId;
                            $name = $tag_in_sprite->tag->_name;
                            if (isset($spriteTable[$spriteId]['name'])) {
                                $parent_name = $spriteTable[$spriteId]['name'];
                            } else {
                                $parent_name = '*';
                            }
                            $this->listMovieClip_r($parent_name, $cid, $name, array($spriteId), $spriteTable);
                            $spriteTable[$spriteId]['Places'][]= array('cid' => $cid, 'name' => $name);
                        }
                    }
                }
                unset($tag_in_sprite);
                break;
            }
        }
        unset($tag);
        return $spriteTable;
    }

    function selectByCIDs($cids) {
        $cid_table = array();
        foreach ($cids as $cid) {
            $cid_table[$cid] = true;
        }
        $this->setCharacterId();
        $this->setReferenceId();

        $swf = clone $this;

        foreach ($swf->_tags as $idx => &$tag) {
            $tag_keep = true;
            if (isset($tag->referenceId)) {
                $tag_keep = false;
                $refid = $tag->referenceId;
                if (is_array($refid)) {
                    foreach ($refid as $id) {
                        if (isset($cid_table[$id])) {
                            $tag_keep = true;
                        }
                    }
                } else {
                    if (isset($cid_table[$refid])) {
                        $tag_keep = true;
                    }
                }
                if ($tag_keep && isset($tag->characterId)) {
                    $cid_table[$tag->characterId] = true;
                }
            } else if ($tag->code == 26) { // PlaceObject
                $tag_keep = false;
            }
            if ($tag->code == 39) { // DefineSprite
                if ($tag->parseTagContent() === false) {
                    throw new IO_SWF_Exception("failed to parseTagContent");
                }
                foreach ($tag->tag->_controlTags as $idx_in_sprite => &$tag_in_sprite) {
                    $tag_in_sprite_keep = true;
                    if (isset($tag_in_sprite->referenceId)) {
                        $refid = $tag_in_sprite->referenceId;
                        if (is_array($refid)) {
                            foreach ($refid as $id) {
                                if (isset($cid_table[$id])) {
                                    $tag_in_sprite_keep = true;
                                }
                            }
                        } else {
                            if (isset($cid_table[$refid])) {
                                $tag_in_sprite_keep = true;
                            }
                        }
                        if ($tag_keep && isset($tag_in_sprite->characterId)) {
                            $cid_table[$tag_in_sprite->characterId] = true;
                        }
                    } else if ($tag_in_sprite->code == 26) { // PlaceObject2
                        $tag_in_sprite_keep = false;
                    } else if ($tag_in_sprite->code == 5 || $tag_in_sprite->code == 28) { // RemoveObject
                        $tag_in_sprite_keep = false;
                    }
                    if ($tag_in_sprite_keep === false) {
                        unset($tag->tag->_controlTags[$idx_in_sprite]);
                        $tag->content = null; // XXX
                    }
                }
                unset($tag_in_sprite);
            } else if (isset($tag->characterId)) {
                if (isset($cid_table[$tag->characterId])) {
                    $tag_keep = true;
                } else {
                    $tag_keep = false;
                }
            }
            if ($tag->code == 5 || $tag->code == 28) { // RemoveObject
                $tag_keep = false;
            }
            if ($tag_keep === false) {
                unset($swf->_tags[$idx]);
            }
        }
        unset($tag);
        // $swf->purgeUselessContents();
        return $swf->build();
    }

    function purgeUselessContents() {
        $this->setCharacterId();
        $this->setReferenceId();
        $used_character_id_table = array();
        foreach (array_reverse(array_keys($this->_tags)) as $tag_idx) {
            $tag = $this->_tags[$tag_idx];
            if (isset($tag->characterId)) {
                $cid = $tag->characterId;
                if (isset($used_character_id_table[$cid]) === false) {
                    unset($this->_tags[$tag_idx]);
                }
            }
            if (isset($this->_tags[$tag_idx]) && isset($tag->referenceId)) {
                $refid = $tag->referenceId;
                if (is_array($refid)) {
                    foreach ($refid as $id) {
                        $used_character_id_table[$id] = true;
                    }
                } else {
                    $used_character_id_table[$refid] = true;
                }
            } 
        }
    }

    function replaceEditString($id, $initialText) {
        $this->setCharacterId();
        foreach ($this->_tags as &$tag) {
            if ($tag->code == 37) { // DefineEditText
                if ($tag->characterId === (int) $id) {
                    if ($tag->parseTagContent() === false) {
                        return false;                        
                    }
                    $tag->tag->InitialText = $initialText;
                    $tag->content = null;
                    return true;
                } else {
                    if ($tag->parseTagContent() === false) {
                        return false;
                    }
                    if ($tag->tag->VariableName === $id) {
                        $tag->tag->InitialText = $initialText;
                        $tag->content = null;
                        return true;
                    }
                }
            }
        }
        trigger_error("Can't found EditText($id)");
        return false;
    }

    function getEditString($id) {
        $this->setCharacterId();
        foreach ($this->_tags as &$tag) {
            if ($tag->code == 37) { // DefineEditText
                if ($tag->characterId === (int) $id) {
                    if ($tag->parseTagContent() === false) {
                        return false;
                    }
                    if (isset($tag->tag->InitialText)) {
                        return $tag->tag->InitialText;
                    } else {
                        return null;
                    }
                } else {
                    if ($tag->parseTagContent() === false) {
                        return false;
                    }
                    if ($tag->tag->VariableName === $id) {
                        if (isset($tag->tag->InitialText)) {
                            return $tag->tag->InitialText;
                        } else {
                            return null;
                        }
                    }
                }
            }
        }
        trigger_error("Can't found EditText($id)");
        return false;
    }

    function downgrade($swfVersion, $limitSwfVersion, $opts) {
        if (($swfVersion < 3) || ($limitSwfVersion < 3)) {
            throw new Exception("swfVersion:$swfVersion, limitSwfVersion:$limitSwfVersion must be >= 3");
        }
        $opts['preserveStyleState'] = ! empty($opts['preserveStyleState']);

        $origVersion = $this->_headers['Version'];
        $this->_headers['Version'] = $swfVersion;
        $tagInfoList = $this->_tags[0]->getTagInfoList();
        $tagsEachKrass = []; // desc sort by tagNo (version as a result)
        $doABC = null;
        foreach ($tagInfoList as $tagNo => $tagInfo) {
            if (isset($tagInfo["klass"])) {
                $klass = $tagInfo["klass"];
                $version = $tagInfo["version"];
                $klass_kind = $klass . (isset($tagInfo["kind"])? ("_".$tagInfo["kind"]): "");
                if (isset($tagsEachKrass[$klass_kind]) === false) {
                    $tagsEachKrass[$klass_kind] = [];
                }
                array_unshift($tagsEachKrass[$klass_kind], [$tagNo, $version]);
            }
        }
        if (($origVersion >= 9) && ($limitSwfVersion <= 8)) {
            $this->downgradeABCTags($this->_tags, $swfVersion, $limitSwfVersion, $opts);
        }
        $this->downgradeTags($this->_tags, $tagsEachKrass, $swfVersion, $limitSwfVersion, $opts);
    }

    function downgradeABCTags(&$tags, $swfVersion, $limitSwfVersion, $opts) {
        $doABC = null;
        $spriteList = [];
        // downgrade DoABC tag
        foreach ($tags as $idx => &$tag) {
            $tagCode = $tag->code;
            if ($tagCode === 39) {  // DefineSprite
                if ($tag->parseTagContent($opts) === false) {
                    throw new IO_SWF_Exception("failed to parseTagContent");
                }
                $spriteId = $tag->tag->_spriteId;
                $spriteList[$spriteId] = $tag;
            }
            if ($tagCode === 82) {  // DoABC
                if ($tag->parseTagContent($opts) === false) {
                    throw new IO_SWF_Exception("failed to parseTagContent");
                }
                $doABC = $tag;
            }
            if ($tagCode === 76) {  // SymbolClass
                if ($tag->parseTagContent() === false) {
                    throw new IO_SWF_Exception("failed to parseTagContent");
                }
                $this->ABCtoAction($tags, $doABC, $tag, $spriteList, $opts);
            }
        }
    }

    function downgradeTags(&$tags, $tagsEachKrass, $swfVersion, $limitSwfVersion, $opts) {
        $eliminate = $opts["eliminate"];
        // downgrade other tags.
        foreach ($tags as $idx => &$tag) {
            $tagCode = $tag->code;
            if ($tagCode <= 1) {  // End(0), ShowFrame(1)
                continue;
            }
            if ($tagCode === 69) {  // FileAttribute
                if ($tag->parseTagContent() === false) {
                    throw new IO_SWF_Exception("failed to parseTagContent");
                }
                if ($swfVersion < 10) {
                    $tag->tag->UseDirectBlit = 0;
                    $tag->tag->UseGPU        = 0;
                    $tag->tag->HasMetadata   = 0;
                }
                if ($swfVersion < 9) {
                    $tag->tag->ActionScript3 = 0;
                }
                $tag->content = null;
            }
            $tagVersion = $tag->getTagInfo($tagCode, "version");
            $tagName = $tag->getTagInfo($tagCode, "name");
            if ($tag->getTagInfo($tagCode, "klass") === false) {
                if ($tagVersion > $limitSwfVersion) {
                    if ($eliminate) {
                        unset($tags[$idx]);
                        fprintf(STDERR, "Eliminate: ");
                    }
                }
                fprintf(STDERR, "%s(%d) tagVersion:%d limitSwfVersion:%d\n", $tagName, $tagCode, $tagVersion, $limitSwfVersion);
                continue;
            }
            $klass = $tag->getTagInfo($tagCode, "klass");
            $kind = $tag->getTagInfo($tagCode, "kind");
            $tagVersion = $tag->getTagInfo($tagCode, "version");
            $klass_kind = $klass . ($kind? ("_".$kind): "");
            if ($tagCode === 39) {  // DefineSprite
                if ($tag->parseTagContent($opts) === false) {
                    throw new IO_SWF_Exception("failed to parseTagContent");
                }
                $this->downgradeTags($tag->tag->_controlTags, $tagsEachKrass,
                                     $swfVersion, $limitSwfVersion, $opts);
                $tag->content = null;
                continue;
            }
            if ($tagVersion <= $limitSwfVersion) {
                continue;
            }
            if ($tag->parseTagContent($opts) === false) {
                throw new IO_SWF_Exception("failed to parseTagContent");
            }
            $tag->content = null;
            foreach ($tagsEachKrass[$klass_kind] as $tagNoVer) {
                list($no, $ver) = $tagNoVer;
                if ($ver <= $limitSwfVersion) {
                    if ($opts['debug'] && ($tag->code !== $no)) {
                        $name = $tag->getTagInfo($no, "name");
                        fprintf(STDERR, "downgrade tag %d(%s) to %d(%s)\n",
                                $tag->code, $tag->getTagName(), $no ,$name);
                    }
                    $tag->code = $no;
                    continue 2;
                }
            }
            if ($opts['debug']) {
                $t = $tags[$idx];
                if ($eliminate) {
                    fprintf(STDERR, "Eliminated: %d(%s)\n", $t->code, $t->getTagName());
                } else {
                    fprintf(STDERR, "Not Eliminated: %d(%s)\n", $t->code, $t->getTagName());
                }
                fprintf(STDERR, "%s(%d) tagVersion:%d > limitSwfVersion:%d\n", $tagName, $tagCode, $tagVersion, $limitSwfVersion);
            }
            if ($eliminate) {
                unset($tags[$idx]);
            }
        }
    }

    function ABCtoAction(&$tags, $doABC, $symbolTag, &$spriteList, $opts) {
        $abc = $doABC->tag->_ABC;
        $codeContext = new IO_SWF_ABC_Code_Context();
        foreach ($symbolTag->tag->_Symbols as $tagAndName) {
            $spriteId = $tagAndName["Tag"];
            $symbolName = $tagAndName["Name"];
            list($ns, $name) = explode(".", $symbolName);
            // echo "$spriteId => $ns :: $name\n";
            $inst = $abc->getInstanceByName($name);
            if (is_null($inst)) {
                throw new IO_SWF_Exception("spriteId:$spriteId instance not found by ns:$ns name:$name");
            }
            $frameMethodArray = $abc->getFrameAndCodeByInstance($inst);
            foreach ($frameMethodArray as $methodArray) {
                list($frame, $methodId) = $methodArray;
                // echo "spriteId:$spriteId frame:$frame methodId:$methodId\n";
                $code = $abc->getCodeByMethodId($methodId);
                $codeContext->spriteId = $spriteId;
                $codeContext->ns = $ns;
                $codeContext->name = $name;
                $actionTag = $code->ABCCodetoActionTag($this->_headers['Version'], $codeContext, $opts);
                $target_tags = null;
                if ($spriteId === 0) {
                    $target_tags = & $this->_tags;
                } else if (isset($spriteList[$spriteId])) {
                    $target_tags = & $spriteList[$spriteId]->tag->_controlTags;
                    $spriteList[$spriteId]->content = null;
                } else {
                    throw new Exception("not found sprite:$spriteId");
                }
                $f = 1;
                $offset = 0;
                foreach ($target_tags as $tag) {
                    if ($frame <= $f) {
                        break;
                    }
                    if ($tag->code === 1) {  // ShowFrame
                        $f++;
                    }
                    $offset++;
                }
                // insert action tag
                array_splice($target_tags, $offset, 0, [$actionTag]);
                unset($target_tags);  // remove reference
            }
        }
    }
    /*
     * DoABC 内の method と実際に動作する Frame の対応表
     */
    function listABCmethodIdToActionFrame($doABC, $symbolTag) {
        $actionFrameList = [];
        $abc = $doABC->tag->_ABC;
        foreach ($symbolTag->tag->_Symbols as $tagAndName) {
            $spriteId = $tagAndName["Tag"];
            $symbolName = $tagAndName["Name"];
            list($ns, $name) = explode(".", $symbolName);
            // echo "### $spriteId => $ns :: $name\n";
            $inst = $abc->getInstanceByName($name);
            $frameMethodArray = $abc->getFrameAndCodeByInstance($inst);
            foreach ($frameMethodArray as $methodArray) {
                list($frame, $methodId) = $methodArray;
                // echo "### spriteId:$spriteId frame:$frame methodId:$methodId\n";
                if (! isset($actionFrameList[$spriteId])) {
                    $actionFrameList[$spriteId] = [];
                }
                if (isset($actionFrameList[$spriteId][$frame])) {
                    throw new IO_SWF_Exception("actionFrameList duplicate spriteId:$spriteId frame:$frame");
                }
                $actionFrameList[$spriteId][$frame] = $methodId;
            }
        }
        return $actionFrameList;
    }
    function dump_method_body_info_by_idx($doABC, $idx) {
        $abc = $doABC->tag->_ABC;
        $method_body_count = count($abc->method_body);
        echo "    method_body($idx/$method_body_count):\n";
        $info = $abc->method_body[$idx];
        $abc->dump_method_body_info($info);
    }
    function list_method_body_info($doABC) {
        $abc = $doABC->tag->_ABC;
        return $abc->method_body;
    }
}
