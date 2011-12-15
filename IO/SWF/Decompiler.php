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

class IO_SWF_Decompiler extends IO_SWF {
    static function getNPushPop($action) {
        switch ($action['Code']) {
        case 0x81:  // GotoFrame
        case 0x82:  // GetURL
        case 0x04:  // NextFrame
        case 0x05:  // PreviousFrame
        case 0x06:  // Play
        case 0x07:  // Stop
        case 0x08:  // ToggleQuality
        case 0x09:  // StopSounds
        case 0x8A:  // WaitForFrame
        case 0x8B:  // SetTarget
        case 0x8C:  // GoToLabel
            return array(0, 0);

        case 0x96:  // Push
            return array(count($action['Values']), 0);

        case 0x17:  // Pop
        case 0x9E:  // Call
        case 0x20:  // SetTarget2
        case 0x26:  // Trace
        case 0x9F:  // GotoFrame2
            return array(0, 1);

        case 0x0A:  // Add
        case 0x0B:  // Subtract
        case 0x0C:  // Multiply
        case 0x0D:  // Divide
        case 0x0E:  // Equals
        case 0x0F:  // Less
        case 0x10:  // And
        case 0x11:  // Or
        case 0x13:  // StringEquals
        case 0x21:  // StringAdd
        case 0x22:  // GetProperty
            return array(1, 2);

        case 0x12:  // Not
        case 0x14:  // StringLength
        case 0x18:  // ToInteger
        case 0x1C:  // GetVariable
        case 0x30:  // RandomNumber
            return array(1, 1);

        case 0x1D:  // SetVariable
            return array(0, 2);
                         
        case 0x15:  // StringExtract
            return array(1, 3);

        case 0x23:  // SetProperty
        case 0x24:  // CloneSprite
            return array(0, 3);

        case 0x34:  // GetTime
            return array(1, 0);

        default:
            return array(0, 0);
        }
    }

    static function treeToString($tree, $is_top_level = 0) {
        $action_str = IO_SWF_Type_Action::getCodeName($tree[0]['Code']);

        switch ($tree[0]['Code']) {
        case 0x81:  // GotoFrame
            $action_str .= " " . $tree[0]['Frame'];
            break;
        case 0x8B:  // SetTarget
            $action_str .= ' "' . $tree[0]['TargetName'] . '"';
            break;
        case 0x8C:  // GoToLabel
            $action_str .= ' "' . $tree[0]['Label'] . '"';
            break;
        case 0x96:  // Push
            if ($is_top_level == 0
                && count($tree[0]['Values']) == 1
                && $tree[0]['Values'][0]['Type'] == 0) {  // String
                    return '"' . $tree[0]['Values'][0]['String'] . '"';
            } else {
                foreach ($tree[0]['Values'] as $value) {
                    unset($value['Type']);
                    list($type_name) = array_keys($value);
                    $action_str .= " " . $value[$type_name];
                }
            }
            break;
        case 0x1C:  // GetVariable
            if (count($tree[1]) == 1
                && $is_top_level == 0
                && $tree[1][0][0]['Code'] == 0x96) {  // Push
                if (count($tree[1][0][0]['Values']) == 1) {
                    return $tree[1][0][0]['Values'][0]['String'];
                }
            }
            break;

        case 0x1D:  // SetVariable
            if (count($tree[1]) == 2
                && $tree[1][1][0]['Code'] == 0x96) {  // Push
                if (count($tree[1][1][0]['Values']) == 1
                    && count($tree[1]) == 2) {
                    return $tree[1][1][0]['Values'][0]['String']
                        . " = "
                        . self::treeToString($tree[1][0]);
                }
            }
        }            

        $str = "";
        $delim = "";
        
        for ($i = 0; $i < count($tree[1]); $i++) {
            $str = self::treeToString($tree[1][$i])
                . $delim . $str;
            $delim = ", ";
        }
        
        if (count($tree[1]) > 0) {
            return $action_str . "(" . $str . ")";
        } else {
            return $action_str;
        }
    }

    function reorderPush(&$actions, $index, $push_index) {
        // move a push at $push_index to $index
        $push = array_splice($actions, $push_index, 1);
        array_splice($actions, $index, 0, $push);

        return $actions;
    }

    function makeActionTree($index, &$actions) {
        // echo "makeActionTree: $index\n";

        $idx = $index;
        $action1 = $actions[$index--];

        $n_push_pop = self::getNPushPop($action1);
        $n_push = $n_push_pop[0];
        $n_pop = $n_push_pop[1];

        $action_trees = array();

        /*
        if ($n_pop != 0) {
            $subtree_index = $index;
            do {
                // skip subtree of n_push == 0
                $tree = self::makeActionTree($subtree_index, $actions);
                $subtree_index = $tree['index'];
            } while ($tree['n_push'] == 0);
            self::reorderPush($actions, $index, $tree['index']);
            exit();
        }
        */

        for ($i = 0; $i < $n_pop && $index >= 0; ) {
            $tree = self::makeActionTree($index, $actions);

            if ($tree['n_push'] != 1) {
                return array(
                    'n_push' => $n_push,
                    'action_tree' => array($action1, array()),
                    'index' => $idx - 1
                    );
            }

            $action_tree1 = $tree['action_tree'];
            $index = $tree['index'];

            $action_trees[] = $action_tree1;

            $i += $tree['n_push'];
        }

        return array(
            'n_push' => $n_push,
            'action_tree' => array($action1, $action_trees),
            'index' => $index
            );
    }

    static function dumpBasicBlock($actions, $end, $opts) {
        // echo "\tBEGIN BLOCK\n";

        $str = "";
        $index = count($actions) - 1;
        $start = $end - $index;
        while ($index >= 0) {
            $result = self::makeActionTree($index, $actions);
            $tree = $result['action_tree'];
            $tree_str = self::treeToString($tree, 1);
            $index = $result['index'];
            $pc = $start + $index + 1;
            $str = "$pc\t$tree_str\n" . $str;
        }

        echo $str;

        // echo "\tEND BLOCK\n";
    }

    static function decomposePush($tag) {
        $count = count($tag->tag->_actions);
        $actions = array();
        $labels = array();
        $branches = array();
        $offset = 0;

        for ($i = 0; $i < $count; $i++) {
            $action = $tag->tag->_actions[$i];
            if ($action['Code'] == 0x96) {
                for ($j = 0; $j < count($action['Values']); $j++) {
                    $new_action = array();
                    $new_action['Code'] = $action['Code'];
                    $new_action['Length'] = 2 + strlen($action['Values'][$j]['String']);
                    $new_action['Values'] = array();
                    $new_action['Values'][0] = array();
                    $new_action['Values'][0]['Type'] = $action['Values'][$j]['Type'];
                    $new_action['Values'][0]['String'] = $action['Values'][$j]['String'];
                    $actions[] = $new_action;
                }
                $offset += count($action['Values']) - 1;
            } else {
                $actions[] = $action;
            }
            if (isset($tag->tag->_labels[$i])) {
                $labels[$i + $offset] = $tag->tag->_labels[$i];
            }
            if (isset($tag->tag->_branches[$i])) {
                $branches[$i + $offset] = $tag->tag->_branches[$i];
            }
        }

        $tag->tag->_actions = $actions;
        $tag->tag->_branches = $branches;
        $tag->tag->_labels = $labels;
    }

    static function dumpActionTag($tag, $frame, $opts) {
        echo "    Actions: (in frame $frame)";
        if ($tag->code == 59) {  // DoInitAction
            echo " SpriteID=".$tag->tag->_spriteId;
        }
        echo "\n";

        $basic_block = array();

        // self::decomposePush($tag);

        for ($i = 0; $i < count($tag->tag->_actions); $i++) {
            $action = $tag->tag->_actions[$i];
            if (isset($tag->tag->_labels[$i])) {
                self::dumpBasicBlock($basic_block, $i, $opts);
                $basic_block = array();
                echo "    (LABEL" . $tag->tag->_labels[$i] . "):\n";
            }
            $action_str = IO_SWF_Type_Action::getCodeName($action['Code']);
            if (isset($tag->tag->_branches[$i])) {
                self::dumpBasicBlock($basic_block, $i, $opts);
                $basic_block = array();
                echo "\t$action_str";
                echo " (LABEL" . $tag->tag->_branches[$i] . ")\n";
            } else {
                $basic_block[] = $action;
            }
        }
        if (count($basic_block) != 0) {
            self::dumpBasicBlock($basic_block, $i, $opts);
        }
        if (isset($tag->tag->_labels[$i])) {
            echo "    (LABEL" . $tag->tag->_labels[$i] . "):\n";
        }
    }

    static function dumpSpriteTag($tag, $opts) {
        $code = $tag->code;
        $name = $tag->getTagInfo($code, 'name');
        if ($name === false) {
            $name = 'unknown';
        }
        $length = strlen($tag->content);
        echo "Code: $code($name)  Length: $length".PHP_EOL;
        echo "\tSprite: SpriteID={$tag->tag->_spriteId} FrameCount={$tag->tag->_frameCount}\n";

        $frame_num = 1;
        for ($i = 0; $i < count($tag->tag->_controlTags); $i++) {
            $control_tag = $tag->tag->_controlTags[$i];
            switch ($control_tag->code) {
            case 39:  // Sprite
                self::dumpSpriteTag($control_tag, $opts);
                break;

            case 12:  // Action
            case 59:  // InitAction
                self::dumpActionTag($control_tag, $frame_num, $opts);
                break;

            case 1:   // ShowFrame
                $frame_num++;
                break;

            case 43:  // FrameLabel
                $control_tag->dump();
                break;

            default:
                break;
            }
        }
    }

    static function parseTagContent($tag, $opts) {
        $code = $tag->code;
        $name = $tag->getTagInfo($code, 'name');
        if ($name === false) {
            $name = 'unknown';
        }
        $length = strlen($tag->content);
        $opts['Version'] = $tag->swfInfo['Version'];
        $tag->parseTagContent($opts);

        if ($code == 39) {  // Sprite
            foreach ($tag->tag->_controlTags as $control_tag) {
                self::parseTagContent($control_tag, $opts);
            }
        }
    }

    function dump($opts = array()) {
        if (empty($opts['hexdump']) === false) {
            $bitio = new IO_Bit();
            $bitio->input($this->_swfdata);
        }
        /* SWF Header */
        echo 'Signature: '.$this->_headers['Signature'].PHP_EOL;
        echo 'Version: '.$this->_headers['Version'].PHP_EOL;
        echo 'FileLength: '.$this->_headers['FileLength'].PHP_EOL;
        echo 'FrameSize: '. IO_SWF_Type_RECT::string($this->_headers['FrameSize'])."\n";
        echo 'FrameRate: '.($this->_headers['FrameRate'] / 0x100).PHP_EOL;
        echo 'FrameCount: '.$this->_headers['FrameCount'].PHP_EOL;

        if (empty($opts['hexdump']) === false) {
            $bitio->hexdump(0, $this->_header_size);
            $opts['bitio'] =& $bitio; // for tag
        }
        $opts['indent'] = 0;
        /* SWF Tags */
        
        echo 'Tags:'.PHP_EOL;
        foreach ($this->_tags as $tag) {
            self::parseTagContent($tag, $opts);
        }

        $frame_num = 1;
        $sprite_tags = array();
        for ($i = 0; $i < count($this->_tags); $i++) {
            $tag = $this->_tags[$i];
            switch ($tag->code) {
            case 39:  // Sprite
                $sprite_tags[] = $tag;
                break;

            case 12:  // Action
            case 59:  // InitAction
                self::dumpActionTag($tag, $frame_num, $opts);
                break;

            case 1:   // ShowFrame
                $frame_num++;
                break;

            case 43:  // FrameLabel
                $tag->dump();
                break;

            default:
                break;
            }
        }

        foreach ($sprite_tags as $tag) {
            self::dumpSpriteTag($tag, $opts);
        }
    }
}
