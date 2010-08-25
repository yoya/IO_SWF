<?php

/*
 * 2010/8/12- (c) yoya@awm.jp
 */

require_once 'IO/SWF.php';

class IO_SWF_Editor extends IO_SWF {
    // var $_headers = array(); // protected
    // var $_tags = array();    // protected

    function setCharacterId() {
        foreach ($this->_tags as &$tag) {
            $content_reader = new IO_Bit();
            $content_reader->input($tag['Content']);
            switch ($tag['Code']) {
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
              case 33: // DefineText
              case 37: // DefineTextEdit
                $tag['CharacterId'] = $content_reader->getUI16LE();
                break;
              case 26: // PlaceObject2 (PlaceFlagHasCharacter)
                $tag['PlaceFlag'] = $content_reader->getUI8();
                if ($tag['PlaceFlag'] & 0x02) {
                    $tag['CharacterId'] = $content_reader->getUI16LE();
                }
                break;
            }
        }
    }

    function replaceTagContent($tagCode, $content, $limit = 1) {
        $count = 0;
        foreach ($this->_tags as &$tag) {
            if ($tag['Code'] == $tagCode) {
                $tag['Length'] = strlen($content);
                $tag['Content'] = $content;
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
            if ($tag['Code'] == $tagCode) {
                return $tag['Content'];
            }
        }
        return null;
    }
    
    function replaceTagContentByCharacterId($tagCode, $characterId, $content_after_character_id) {
        $ret = 0;
        foreach ($this->_tags as &$tag) {
            if (($tag['Code'] == $tagCode) && isset($tag['CharacterId'])) {
                
                if ($tag['CharacterId'] == $characterId) {
                    $tag['Length'] = 2 + strlen($content_after_character_id);
                    $tag['Content'] = pack('v', $characterId).$content_after_character_id;
                    $ret = 1;
                    break;
                }
            }
        }
        return $ret;
    }
    function getTagContentByCharacterId($tagCode, $characterId) {
        foreach ($this->_tags as $tag) {
            if (($tag['Code'] == $tagCode) && isset($tag['CharacterId'])) {
                if ($tag['CharacterId'] == $characterId) {
                    return $tag['Content'];
                    break;
                }
            }
        }
        return null;
    }
}
