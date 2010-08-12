<?php

/*
 * 2010/8/12- (c) yoya@awm.jp
 */

require_once 'IO/SWF.php';

class IO_SWF_Editor extends IO_SWF {
    // var $_headers = array(); // protected
    // var $_tags = array();    // protected

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
        foreach ($this->_tags as &$tag) {
            if (($tag['Code'] == $tagCode) && isset($tag['CharacterId'])) {
                
                if ($tag['CharacterId'] == $characterId) {
                    $tag['Length'] = 2 + strlen($content_after_character_id);
                    $tag['Content'] = pack('v', $characterId).$content_after_character_id;
                    break;
                }
            }
        }
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
