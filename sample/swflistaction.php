<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require 'IO/SWF/Editor.php';
}

if ($argc != 2) {
    echo "Usage: php swflistaction.php <swf_file>\n";
    echo "ex) php swflistaction.php test.swf\n";
    exit(1);
}

$filename = $argv[1];

if ($filename === "-") {
    $filename = "php://stdin";
}

$swfdata = file_get_contents($filename);

$swf = new IO_SWF_Editor();

$swf->parse($swfdata);

swflistaction($swf->_tags);

function swflistaction($swftags) {
    $currentFrame = 1;
    foreach ($swftags as $idx => $tag) {
        $tag_code = $tag->code;
        if ($tag_code == 1) { // ShowFrame
            $currentFrame++;
            continue;
        }
        if (($tag_code == 12) || ($tag_code == 59)) { // DoAction, DoInitAction
            try {
                if ($tag->parseTagContent() === false) {
                    echo "Unknown Action Tag\n";
                    exit(1);
                }
                $action_num = count($tag->tag->_actions);
            } catch (IO_Bit_Exception $e) {
                if (isset($tag_in_sprite->tag->_actions)) {
                    $action_num = count($tag_in_sprite->tag->_actions);
                    $action_num .= "(at least)";
                } else {
                    $action_num = "(parse error)";
                }
            }
            $length = strlen($tag->content);
            echo "spriteId:0(root)  frame:$currentFrame\t=> instruction:$action_num  length=$length\n";
        }
        if ($tag_code == 39) { // DefineSprite
            if ($tag->parseTagContent() === false) {
                echo "Unknown DefineSprite!!!\n";
                exit(1);
            }
            $spriteId = $tag->tag->_spriteId;
            $currentFrameInSprite = 1;
            foreach ($tag->tag->_controlTags as $tag_in_sprite) {
                $tag_code_in_sprite = $tag_in_sprite->code;
                if ($tag_code_in_sprite == 1) { // ShowFrame
                    $currentFrameInSprite++;
                    continue;
                }
                if (($tag_code_in_sprite == 12) || ($tag_code_in_sprite == 59)) { // DoAction, DoInitAction
                    try {
                        if ($tag_in_sprite->parseTagContent() === false) {
                            echo "Unknown Action Tag\n";
                            exit(1);
                        }
                        $action_num = count($tag_in_sprite->tag->_actions);
                    } catch (IO_Bit_Exception $e) {
                        if (isset($tag_in_sprite->tag->_actions)) {
                            $action_num = count($tag_in_sprite->tag->_actions);
                            $action_num .= "(at least)";
                        } else {
                            $action_num = "(parse error)";
                        }
                    }
                    $length = strlen($tag_in_sprite->content);
                    echo "spriteId:$spriteId  frame:$currentFrameInSprite\t=> instruction:$action_num  length=$length\n";
                }
            }
        }
    }
}

exit(0);
