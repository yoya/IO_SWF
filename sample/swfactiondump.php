<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require 'IO/SWF/Editor.php';
}

$options = getopt("f:hla");

if (! isset($options['f']))  {
    echo "Usage: php swfdump.php -f <swf_file> [-h] [-l] [-a]\n";
    echo "ex) php swfdump.php -f test.swf -h\n";
    exit(1);
}

$filename = $options['f'];
if ($filename === "-") {
    $filename = "php://stdin";
}
$swfdata = file_get_contents($filename);

$swf = new IO_SWF_Editor();

$opts = [ 'hexdump'  =>      isset($options['h']),
          'addlabel' =>      isset($options['l']),
          'allmethoddump' => isset($options['a']),
          'abcparse' => true ];

$swf->parse($swfdata, $opts);
$swf->parseAllTagContent($opts);

$tags = $swf->_tags;
$spriteList = [];
$abcTag = $symbolTag = null;

foreach ($tags as $tag) {
    if ($tag->isSprite()) {
        $spriteId = $tag->tag->_spriteId;
        $spriteList[$spriteId] = $tag;
    }
    if ($tag->hasABC()) {
        $abcTag = $tag;
    }
    if ($tag->hasSymbol()) {
        $symbolTag = $tag;
    }
}

$actionFrameList = [];
if ((! is_null($abcTag)) && (! is_null($symbolTag))) {
    // [spriteId][frameid] => methodId の対応表
    $actionFrameList = $swf->listABCmethodIdToActionFrame($abcTag, $symbolTag);
}
$actionFrameDumped = [];

// print_r($actionFrameList);

function getABCmethodIdxBySpriteAndFrame($actionFrameList, $spriteId, $frame) {
    if (isset($actionFrameList[$spriteId][$frame])) {
        return $actionFrameList[$spriteId][$frame];
    }
    return null;
}

$frameNum = 1;
$abcDumped = false;
echo "=== SpriteId: 0 (Main Timeline)\n";

foreach ($tags as $idx => $tag) {
    if ($tag->isDisplayListTag() || $tag->isSprite()) {  // ShowFrame, Control
        if (! $abcDumped) {
            // dump abc method
            $frame = $frameNum;
            $methodIdx = getABCmethodIdxBySpriteAndFrame($actionFrameList, 0, $frame);
            if (! is_null($methodIdx)) {
                echo "=== frame:$frameNum\n";
                $swf->dump_method_body_info_by_idx($abcTag, $methodIdx);
                $actionFrameDumped[$methodIdx] = true;
            }
            $abcDumped = true;
        }
    }
    if ($tag->hasAction()) {
        echo "=== frame:$frameNum\n";
        $tag->dump($opts, $opts);
    }
    if ($tag->code === 1) { // ShowFrame
        $frameNum ++;
        $abcDumped = false;
    }
    if ($tag->isSprite()) {
        $spriteId = $tag->tag->_spriteId;
        echo "=== SpriteId: $spriteId\n";
        $spriteFrameNum = 1;
        $opts['indent'] = 1;
        $abcDumpedInSprite = false;
        foreach ($tag->tag->_controlTags as $control_tag) {
            if ($control_tag->isDisplayListTag()) {
                if (! $abcDumpedInSprite) {
                    // dump abc method
                    $frame = $spriteFrameNum;
                    $methodIdx = getABCmethodIdxBySpriteAndFrame($actionFrameList, $spriteId, $frame);
                    if (! is_null($methodIdx)) {
                        echo "    === frame:$spriteFrameNum\n";
                        $swf->dump_method_body_info_by_idx($abcTag, $methodIdx);
                        $actionFrameDumped[$methodIdx] = true;
                    }
                    $abcDumpedInSprite = true;
                }
            }
            if ($control_tag->hasAction()) {
                echo "    === frame:$spriteFrameNum\n";
                $control_tag->dump($opts, $opts);
            }
            if ($control_tag->code === 1) { // ShowFrame
                $spriteFrameNum++;
                $abcDumpedInSprite = false;
            }
        }
    }
}


// 該当するフレームがなく、何故存在するのか分からない ABC method
// デフォルトで非表示。-a をつけると表示。
if ($opts['allmethoddump'] && (! is_null($abcTag))) {
    echo "=== Etc\n";
    $method_body_info = $swf->list_method_body_info($abcTag);
    foreach ($method_body_info as $idx => $info) {
        if (! isset($actionFrameDumped[$idx])) {
            $swf->dump_method_body_info_by_idx($abcTag, $idx);
        }
    }
}

exit(0);
