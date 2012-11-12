<?php

require 'IO/SWF/Editor.php';

if ($argc != 2) {
    echo "Usage: php swfdeleteaction.php <swf_file>\n";
    echo "ex) php swfdeleteaction.php test.swf\n";
    exit(1);
}

assert(is_readable($argv[1]));

$swfdata = file_get_contents($argv[1]);

$swf = new IO_SWF_Editor();

$swf->parse($swfdata);

foreach ($swf->_tags as $idx => &$tag) {
    $tag_code = $tag->code;
    if (($tag_code == 12) || ($tag_code == 59)) { // DoAction, DoInitAction
        unset($swf->_tags[$idx]);
    }
    if ($tag_code == 39) { // DefineSprite
        if ($tag->parseTagContent() === false) {
            echo "Unknown DefineSprite!!!\n";
            exit(1);
        }
        foreach ($tag->tag->_controlTags as $idx_in_sprite => &$tag_in_sprite) {
            $tag_code_in_sprite = $tag_in_sprite->code;
            // DoAction, DoInitAction
            if (($tag_code_in_sprite == 12) || ($tag_code_in_sprite == 59)) {
                unset($tag->tag->_controlTags->_tags[$idx_in_sprite]);
                $tag_in_sprite->content = "\0";
                $tag->content = null;
            }
        }
    }
}

echo $swf->build();

exit(0);
