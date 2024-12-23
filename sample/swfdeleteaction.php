<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require 'IO/SWF/Editor.php';
}

$options = getopt("f:as:l:");

if ((isset($options['f']) === false) ||
    (is_readable($options['f']) === false)) {
    echo "Usage: php swfdeleteaction.php -f <swf_file> [-a] [-s <sprite_id>[:<frame>] -l [<length>]\n";
    echo "ex) php swfdeleteaction.php -f test.swf -a  # (AS1/AS3)\n";
    echo "ex) php swfdeleteaction.php -f test.swf -s 130 -f 20 # delete only sprite:130 frame:20 (AS1 only)\n";
    exit(1);
}
$all = isset($options['a'])?true:false;
if (isset($options['s'])) {
    $spriteId_frameNum = explode(":", $options['s']);
    if (count($spriteId_frameNum) === 1) {
        $spriteId = $spriteId_frameNum[0];
        $frameNum = null;
    } else {
        list($spriteId, $frameNum) = $spriteId_frameNum;
    }
} else {
    $spriteId = null;
    $frameNum = null;
}
$length = isset($options['l'])?$options['l']:null;

$swfdata = file_get_contents($options['f']);

$swf = new IO_SWF_Editor();

$swf->parse($swfdata);

$currentFrame = 1;
foreach ($swf->_tags as $idx => &$tag) {
    $tag_code = $tag->code;
    if ($tag_code == 1) { // ShowFrame
        $currentFrame++;
        continue;
    }
    if (($tag_code == 12) || ($tag_code == 59)  || ($tag_code == 76)) { // DoAction, DoInitAction, SymbolClass
        if ($all ||
            ((is_null($spriteId) === false) && ($spriteId == 0) && (is_null($frameNum) || ($frameNum == $currentFrame))) ||
            ($length == strlen($tag->content))) {
            unset($swf->_tags[$idx]);
        }
    }
    if ($tag_code == 39) { // DefineSprite
        if ($tag->parseTagContent() === false) {
            echo "Unknown DefineSprite!!!\n";
            exit(1);
        }
        $currentFrameInSprite = 1;
        foreach ($tag->tag->_controlTags as $idx_in_sprite => &$tag_in_sprite) {
            $tag_code_in_sprite = $tag_in_sprite->code;
            if ($tag_code_in_sprite == 1) { // ShowFrame
                $currentFrameInSprite++;
                continue;
            }
            // DoAction, DoInitAction
            if (($tag_code_in_sprite == 12) || ($tag_code_in_sprite == 59)) {
                if ($all ||
                    ((is_null($spriteId) === false) && ($spriteId == $tag->tag->_spriteId) && (is_null($frameNum) || ($frameNum == $currentFrameInSprite))) ||
                    ($length == strlen($tag_in_sprite->content))) {
                unset($tag->tag->_controlTags[$idx_in_sprite]);
                    $tag->content = null;
                }
            }
        }
    }
}

echo $swf->build();

exit(0);
