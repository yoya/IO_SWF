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
    $actionFrameList = $swf->listABCmethodIdToActionFrame($abcTag, $symbolTag);
}

$actionFrameDumped = [];

// var_dump($actionFrameList);

function getABCmethodIdxBySpriteAndFrame($actionFrameList, $spriteId, $frame) {
    foreach ($actionFrameList as $idx => $spriteIdAndFrame) {
        if (($spriteIdAndFrame[0] === $spriteId) &&
            ($spriteIdAndFrame[1] === $frame)) {
            return $idx;
        }
    }
    return null;
}

$frameNum = 1;
echo "=== SpriteId: 0 (Main Timeline)\n";
foreach ($tags as $idx => $tag) {
    if ($tag->code === 1) {  // ShowFrame
        $frameNum ++;
                // dump abc method
        $spriteId = 0;
        $frame = $frameNum;
        $methodIdx = getABCmethodIdxBySpriteAndFrame($actionFrameList, $spriteId, $frame);
        if (! is_null($methodIdx)) {
            $swf->dump_method_body_info_by_idx($abcTag, $methodIdx);
            $actionFrameDumped[$methodIdx] = true;
        }
    } else if ($tag->hasAction()) {
        echo "=== frame:$frameNum\n";
        $tag->dump($opts, $opts);
    } else if ($tag->isSprite()) {
        $spriteId = $tag->tag->_spriteId;
        echo "=== SpriteId: $spriteId\n";
        $spriteFrameNum = 1;
        $opts['indent'] = 1;
        foreach ($tag->tag->_controlTags as $control_tag) {
            if ($control_tag->code === 1) { // ShowFrame
                $spriteFrameNum++;
                // dump abc method
                $frame = $spriteFrameNum;
                $methodIdx = getABCmethodIdxBySpriteAndFrame($actionFrameList, $spriteId, $frame);
                if (! is_null($methodIdx)) {
                    echo "    === frame:$spriteFrameNum\n";
                    $swf->dump_method_body_info_by_idx($abcTag, $methodIdx);
                    $actionFrameDumped[$methodIdx] = true;
                }
            } else if ($control_tag->hasAction()) {
                echo "    === frame:$spriteFrameNum\n";
                $$control_tag->dump($opts, $opts);
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
