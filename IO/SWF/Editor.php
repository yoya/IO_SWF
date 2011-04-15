<?php

/*
 * 2010/8/12- (c) yoya@awm.jp
 */

require_once dirname(__FILE__).'/../SWF.php';
require_once dirname(__FILE__).'/../SWF/Tag/Shape.php';

class IO_SWF_Editor extends IO_SWF {
    // var $_headers = array(); // protected
    // var $_tags = array();    // protected

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
                    if (isset($replaceTag->code)) {
                        $tag->code = $replaceTag->code;
                    }
                    $tag->length = strlen($replaceTag->content);
                    $tag->content = $replaceTag>content;
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
                $opts = array('hasShapeId' => true);
                $shape->parseContent($code, $tag->content, $opts);
                $shape->deforme($threshold);
                $tag->content = $shape->buildContent($code, $opts);
                break;
            }
        }
    }
}
