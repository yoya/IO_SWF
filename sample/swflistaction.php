<?php

require 'IO/SWF/Editor.php';

if ($argc != 2) {
    echo "Usage: php swflistaction.php <swf_file>\n";
    echo "ex) php swflistaction.php test.swf\n";
    exit(1);
}

assert(is_readable($argv[1]));

$swfdata = file_get_contents($argv[1]);

$swf = new IO_SWF_Editor();

$swf->parse($swfdata);

$currentFrame = 1;

foreach ($swf->_tags as $idx => $tag) {
    $tag_code = $tag->code;
    if ($tag_code == 1) { // ShowFrame
        $currentFrame++;
        continue;
    }
    if (($tag_code == 12) || ($tag_code == 59)) { // DoAction, DoInitAction
        if ($tag->parseTagContent() === false) {
            echo "Unknown Action Tag\n";
            exit(1);
        }
        $action_num = count($tag->tag->_actions);
        echo "0:$currentFrame => $action_num\n";
    }
    if ($tag_code == 39) { // DefineSprite
        if ($tag->parseTagContent() === false) {
            echo "Unknown DefineSprite!!!\n";
            exit(1);
        }
        $spriteId = $tag->tag->_spriteId;
        $currentFrameInSprite = 1;
        foreach ($tag->tag->_controlTags as &$tag_in_sprite) {
            $tag_code_in_sprite = $tag_in_sprite->code;
            if ($tag_code_in_sprite == 1) { // ShowFrame
                $currentFrameInSprite++;
                continue;
            }
            if (($tag_code_in_sprite == 12) || ($tag_code_in_sprite == 59)) { // DoAction, DoInitAction
                if ($tag_in_sprite->parseTagContent() === false) {
                    echo "Unknown Action Tag\n";
                    exit(1);
                }
                $action_num = count($tag_in_sprite->tag->_actions);
                echo "$spriteId:$currentFrameInSprite => $action_num\n";
            }
        }
    }

}

exit(0);
