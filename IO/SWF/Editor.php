<?php

/*
 * 2010/8/12- (c) yoya@awm.jp
 */

require_once dirname(__FILE__).'/../SWF.php';
require_once dirname(__FILE__).'/../SWF/Tag/Shape.php';
require_once dirname(__FILE__).'/../SWF/Tag/Action.php';
require_once dirname(__FILE__).'/../SWF/Tag/Sprite.php';
require_once dirname(__FILE__).'/../SWF/Lossless.php';

class IO_SWF_Editor extends IO_SWF {
    // var $_headers = array(); // protected
    // var $_tags = array();    // protected

    function rebuild() {
        foreach ($this->_tags as &$tag) {
            if ($tag->parseTagContent()) {
                $tag->content = null;
                $tag->buildTagContent();
            }
        }
    }

    function setCharacterId() {
        foreach ($this->_tags as &$tag) {
            $content_reader = new IO_Bit();
            $content_reader->input($tag->content);
            switch ($tag->code) {
              case 4:  // PlaceObject
              case 5:  // RemoveObject
              case 6:  // DefineBits
              case 21: // DefineBitsJPEG2
              case 35: // DefineBitsJPEG3
              case 20: // DefineBitsLossless
              case 46: // DefineMorphShape
              case 2:  // DefineShape (ShapeId)
              case 22: // DefineShape2 (ShapeId)
              case 11: // DefineText
              case 33: // DefineText2
              case 37: // DefineTextEdit
              case 39: // DefineSprite
                $tag->characterId = $content_reader->getUI16LE();
                break;
              case 26: // PlaceObject2 (PlaceFlagHasCharacter)
                $tag->placeFlag = $content_reader->getUI8();
                if ($tag->placeFlag & 0x02) {
                    $tag->characterId = $content_reader->getUI16LE();
                }
                break;
            }
        }
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
                $tag->content = $shape->buildContent($code);
                break;
            }
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
//            case 59: // DoAction
                $action = new IO_SWF_Tag_Action();
                $action->parseContent($code, $tag->content);
                $action->replaceActionStrings($trans_table);
                $tag->content = $action->buildContent($code);
                break;
            case 39: // Sprite
                $sprite = new IO_SWF_Tag_Sprite();
                $sprite->parseContent($code, $tag->content);
                foreach ($sprite->_controlTags as &$tag_in_sprite) {
                    $code_in_sprite = $tag_in_sprite->code;
                    switch ($code_in_sprite) {
              case 12: // DoAction
//            case 59: // DoAction
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
        if ((strncmp($bitmap_data, 'GIF', 3) == 0) ||
            (strncmp($bitmap_data, "\x89PNG", 4) == 0)) {
            $tag = IO_SWF_Lossless::BitmapData2Lossless($bitmap_id, $bitmap_data);
        } else if (strncmp($bitmap_data, "\xff\xd8\xff", 3) == 0) {
            $erroneous_header = pack('CCCC', 0xFF, 0xD9, 0xFF, 0xD8);
            if (is_null($jpeg_alphadata)) {
                // 21: DefineBitsJPEG2
                $content = $erroneous_header.$bitmap_data;
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
        } else {
            throw new IO_SWF_Exception("Unknown Bitmap Format: ".bin2hex(substr($bitmap_data, 0, 4)));
        }
        // DefineBits,DefineBitsJPEG2,3, DefineBitsLossless,DefineBitsLossless2
        $tag_code = array(6, 21, 35, 20, 36);
        $ret = $this->replaceTagByCharacterId($tag_code, $bitmap_id, $tag);
        return $ret;
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
}
