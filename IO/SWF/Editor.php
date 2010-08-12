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
    function replaceTagContentByCharacterId($tagCode, $characterId, $content) {
        foreach ($this->_tags as &$tag) {
            if (($tag['Code'] == $tagCode) && isset($tag['CharacterId'])) {
                if ($tag['CharacterId'] == $characterId) {
                    $tag['Length'] = strlen($content);
                    $tag['Content'] = $content;
                    break;
                }
            }
        }
    }
}
