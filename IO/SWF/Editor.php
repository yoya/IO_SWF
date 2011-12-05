<?php

/*
 * 2010/8/12- (c) yoya@awm.jp
 */

require_once dirname(__FILE__).'/Exception.php';
require_once dirname(__FILE__).'/../SWF.php';
require_once dirname(__FILE__).'/Tag/Shape.php';
require_once dirname(__FILE__).'/Tag/Action.php';
require_once dirname(__FILE__).'/Tag/Sprite.php';
require_once dirname(__FILE__).'/Lossless.php';
require_once dirname(__FILE__).'/../SWF/Bitmap.php';

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
                throw IO_SWF_Exception("setCharacterId method must be called at next of parse");
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
                $tag->characterId = $content_reader->getUI16LE();
                break;
            }
        }
        $this->setCharacterIdDone = true;
    }

    function setReferenceId() {
        if ($this->setReferenceIdDone) {
            return ;
        }
        foreach ($this->_tags as &$tag) {
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
                }
                $tag->referenceId = $refIds;
                break;
            }
        }
        $this->setReferenceIdDone = true;
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
        return null;
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
            $let_action = array();
            foreach ($trans_table as $key_str => $value_str) {
                $let_action []= array('Code' => 0x96, // Push
                                      'Values' => array(
                                          array('Type' => 0,
                                                'String' => $key_str)));
                $let_action []= array('Code' => 0x96, // Push
                                      'Values' => array(
                                          array('Type' => 0,
                                                'String' => $value_str)));
                $let_action []= array('Code' => 0x1d); // SetVariable
            }
            $action->_actions = array_merge($let_action, $action->_actions);
            $tag->content = $action->buildContent($code);
        }
    }

    function replaceActionStrings($trans_table_or_from_str, $to_str = null) {
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
                $action = new IO_SWF_Tag_Action();
                $action->parseContent($code, $tag->content);
                $action->replaceActionStrings($trans_table);
                $tag->content = null;
                break;
              case 39: // Sprite
                $sprite = new IO_SWF_Tag_Sprite();
                $sprite->parseContent($code, $tag->content);
                foreach ($sprite->_controlTags as &$tag_in_sprite) {
                    $code_in_sprite = $tag_in_sprite->code;
                    switch ($code_in_sprite) {
                      case 12: // DoAction
                      case 59: // DoInitAction
                        $action_in_sprite = new IO_SWF_Tag_Action();
                        $action_in_sprite->parseContent($code_in_sprite, $tag_in_sprite->content);
                        $action_in_sprite->replaceActionStrings($trans_table);
                        $tag_in_sprite->content = $action_in_sprite->buildContent($code_in_sprite);
                        break;
                    }
                }
                $tag->content = $sprite->buildContent($code);
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
    function setShapeAdjustMode($mode) {
        $this->shape_adjust_mode = $mode;
    }
    function replaceMovieClip($target_path, $mc_swfdata) {
        $this->setCharacterId();
        $mc_tag_idx = null;
        if ($target_path === '') {
            trigger_error('target_path is null string');
            return false;
        }
        $opts = array('Version' => $this->_headers['Version']); // for parser

        /*
         * scanning for target sprite tag
         */
        $target_sprite_tag_idx = -1;
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
            if (0 <= $target_sprite_tag_idx) {
                break; // target sprite found
            }
        }

        if ($target_sprite_tag_idx < 0) {
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
                continue;
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
    function replaceEditString($id, $initialText) {
        $this->setCharacterId();
        foreach ($this->_tags as &$tag) {
            if ($tag->code == 37) { // DefineEditText
                if ($tag->characterId === $id) {
                    if ($tag->parseTagContent() === false) {
                        return false;                        
                    }
                    $tag->tag->initialText = $initialText;
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
}
