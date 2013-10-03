<?php

/*
 * 2011/10/06 - (c) takebe@cityfujisawa.ne.jp
 */

require_once dirname(__FILE__).'/Exception.php';
require_once dirname(__FILE__).'/../SWF.php';
require_once dirname(__FILE__).'/Tag/Shape.php';
require_once dirname(__FILE__).'/Tag/Action.php';
require_once dirname(__FILE__).'/Tag/Sprite.php';
require_once dirname(__FILE__).'/Lossless.php';
require_once dirname(__FILE__).'/../SWF/Bitmap.php';

class IO_SWF_ActionEditor extends IO_SWF {
    static function parseTagContent($tag, $opts) {
        $code = $tag->code;
        $name = $tag->getTagInfo($code, 'name');
        if ($name === false) {
            $name = 'unknown';
        }
        $length = strlen($tag->content);
        $opts['Version'] = $tag->swfInfo['Version'];
        if ($code == 12 || $code == 59) {
            $tag->parseTagContent($opts);
            $tag->content = null;  // remove original binary
        }
        if ($code == 39) {  // Sprite
            $tag->parseTagContent($opts);
            $tag->content = null;  // remove original binary
            foreach ($tag->tag->_controlTags as $control_tag) {
                self::parseTagContent($control_tag, $opts);
            }
        }
    }

    function parseAllTagContent($opts) {
        foreach ($this->_tags as $tag) {
            self::parseTagContent($tag, $opts);
        }
    }

    function findActionTagInTags($frame, $tags) {
        $frame_num = 1;
        foreach ($tags as $tag) {
            if ($frame_num == $frame && ($tag->code == 12 || $tag->code == 59)) {
                return $tag;
            }
            if ($tag->code == 1) {  // ShowFrame
                $frame_num++;
            }
        }
        return null;
    }

    function findSpriteInTags($sprite_id, $tags) {
        foreach ($tags as $tag) {
            if ($tag->code == 39) {  // Sprite
                if ($tag->tag->_spriteId == $sprite_id) {
                    return $tag;
                } else {
                    $result = $this->findSpriteInTags($sprite_id, $tag->tag->_controlTags);
                    if ($result) {
                        return $result;
                    }
                }
            }
        }
        return null;
    }

    function findSprite($sprite_id) {
        if ($sprite_id == 0) {
            return null;
        } else {
            return $this->findSpriteInTags($sprite_id, $this->_tags);
        }
    }

    function insertAction($sprite_id, $frame, $pos, $action) {
        if ($sprite_id == 0) {
            $tags =& $this->_tags;
        } else {
            $sprite = $this->findSprite($sprite_id);
            if (!$sprite) {
                return null;
            }
            $tags =& $sprite->tag->_controlTags;
        }
        $action_tag = $this->findActionTagInTags($frame, $tags);
        if (!$action_tag) {
            if ($pos > 1) { // 1 origin
                return null;
            }
            $currentFrame = 0;
            $found = false;
            foreach ($tags as $tagidx => $tag) {
                if ($tag->code == 1) {
                    $currentFrame ++;
                }
                if ($currentFrame >= $frame) {
                    $found = true;
                    break;
                }
            }
            if ($found === false) {
                return null;
            }
            $action_tag = new IO_SWF_Tag($tags[0]->swfInfo);
            $action_tag->code = 12; // DoAction
            $action_tag->content = '';
            $action_tag->parseTagContent();
            $action_tag->content = null;
            array_splice($tags, $tagidx, 0, array($action_tag));
        }

        $action_tag->tag->insertAction($pos, $action);

        return true;
    }

    function insertSimpleTrace($sprite_id, $frame, $pos, $str) {
        $push_str = array(
            'Code' => 0x96,
            'Length' => strlen($str) + 2,
            'Values' => array(
                array(
                    'Type' => 0,
                    'String' => $str
                    )
                )
            );
        $this->insertAction($sprite_id, $frame, $pos, $push_str);

        $trace = array(
            'Code' => 0x26,
            'Length' => 0
            );
        $this->insertAction($sprite_id, $frame, $pos + 1, $trace);
    }

    function insertVarDumpTrace($sprite_id, $frame, $pos, $var) {
        $str = "(^_^)/ $var = ";
        $push_str = array(
            'Code' => 0x96,
            'Length' => strlen($str) + 2,
            'Values' => array(
                array(
                    'Type' => 0,
                    'String' => $str
                    )
                )
            );
        $this->insertAction($sprite_id, $frame, $pos, $push_str);
        $str = $var;
        $push_str = array(
            'Code' => 0x96,
            'Length' => strlen($str) + 2,
            'Values' => array(
                array(
                    'Type' => 0,
                    'String' => $str
                    )
                )
            );
        $this->insertAction($sprite_id, $frame, $pos + 1, $push_str);
        $get_variable = array(
            'Code' => 0x1c,
            'Length' => 0
            );
        $this->insertAction($sprite_id, $frame, $pos + 2, $get_variable);
        $string_add = array(
            'Code' => 0x21,
            'Length' => 0
            );
        $this->insertAction($sprite_id, $frame, $pos + 3, $string_add);
        $trace = array(
            'Code' => 0x26,
            'Length' => 0
            );
        $this->insertAction($sprite_id, $frame, $pos + 4, $trace);
    }

    function rebuild() {
        foreach ($this->_tags as $tag) {
            $tag->buildTagContent();
        }
    }
}
