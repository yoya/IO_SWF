<?php
/* 
 * 2019/8/12/19- (c) yoya@awm.jp
 */

require 'IO/SWF/Editor.php';

if ($argc != 2) {
    echo "Usage: php swfwireframe.php <swf_file>\n";
    echo "ex) php swfwireframe.php test.swf\n";
    exit(1);
}

assert(is_readable($argv[1]));

$swfdata = file_get_contents($argv[1]);

$swf = new IO_SWF_Editor();

$swf->parse($swfdata);

$swf->setCharacterId();
$swf->setReferenceId();

$opts = array('Version' => $swf->_headers['Version']); // for parser
$frameCount = 0;
$headFrame = true;

swftreematrix($swf, $swf->_tags, identMatrix(), 0);

exit(0);

function swftreematrix($swf, $tags, $parentMatrix, $indent) {
    $indentStr = str_repeat(" ", 4*$indent);
    $headFrame = true;
    $frameCount = 0;
    foreach ($tags as $idx => $tag) {
        if ($headFrame) {
            echo "$indentStr====Frame[$frameCount]====\n";
            $headFrame = false;
        }
        $code = $tag->code;        
        if ($code === 0) {  // End
            break;
        }
        if ($code === 1) {  // ShowFrame
            $frameCount++;
            $headFrame = true;
            continue;
        }
        $name = $tag->getTagInfo($code, "name");
        $klass = $tag->getTagInfo($code, "klass");
        echo $indentStr;
        echo "$code $name($klass)\n";
        switch ($klass) {
        case "Place":
            if (! $tag->parseTagContent()) {
                throw Exception("can't parse sprite tag content");
            }
            $cid = $tag->tag->_characterId;
            if (isset($tag->tag->_matrix)) {
                $matrix = $tag->tag->_matrix;
            } else {
                $matrix = identMatrix();
            }
            echo "$name\n";
            $multipliedMatrix = multiplyMatrix([$matrix, $parentMatrix]);
            dumpMatrix([$matrix, $parentMatrix, $multipliedMatrix], 1).PHP_EOL;
            $target_tag = $swf->getTagByCharacterId($cid);
            swftreematrix($swf, [$target_tag], $multipliedMatrix, $indent+1);
            break;
        case "Sprite":
            if (! $tag->parseTagContent()) {
                throw Exception("can't parse sprite tag content");
            }
            swftreematrix($swf, $tag->tag->_controlTags, $parentMatrix,
                          $indent+1);
            break;
        case "Shape":
            if (! $tag->parseTagContent()) {
                throw Exception("can't parse sprite tag content");
            }
            // var_dump($tag->tag->_shapeBounds);
            break;
        }
    }
}

function dumpMatrix($matrixArray, $indent) {
    $indentStr = str_repeat(" ", 4*$indent);
    $text_fmt = " %3.3f %3.3f %3.2f |";
    $indentStr = str_repeat(" ", 4*$indent - 1);
    $text1 = $text2 = $indentStr."|";
    foreach ($matrixArray as $matrix) {
        $text1 .= sprintf($text_fmt, $matrix['ScaleX'] / 0x10000,
                          $matrix['RotateSkew0'] / 0x10000,
                          $matrix['TranslateX'] / 20);
        $text2 .= sprintf($text_fmt, $matrix['RotateSkew1'] / 0x10000,
                          $matrix['ScaleY'] / 0x10000,
                          $matrix['TranslateY'] / 20);
    }
    echo $text1.PHP_EOL.$text2.PHP_EOL;
}

function identMatrix() {
    $matrix = [
        'ScaleX' => 0x10000,
        'RotateSkew0' => 0,
        'TranslateX' => 0,
        'RotateSkew1' => 0,
        'ScaleY' => 0x10000,
        'TranslateY' => 0,
    ];
    return $matrix;
}

function multiplyMatrix($matrixArray) {
    foreach ($matrixArray as $i => $matrix) {
        if ($i === 0) {
            $prevMatrix = $matrix;
            continue;
        }
        $matrix = [
            'ScaleX' =>
            $prevMatrix['ScaleX'] * $matrix['ScaleX'] / 0x10000 +
            $prevMatrix['RotateSkew0'] * $matrix['RotateSkew1'] / 0x10000,
            'RotateSkew0' =>
            $prevMatrix['ScaleX'] * $matrix['RotateSkew0'] / 0x10000+
            $prevMatrix['RotateSkew0'] * $matrix['ScaleY'] / 0x10000,
            'TranslateX' =>
            $prevMatrix['ScaleX'] * $matrix['TranslateX']  / 0x10000 / 20 +
            $prevMatrix['RotateSkew0'] * $matrix['TranslateY'] / 0x10000 / 20 +
            $prevMatrix['TranslateX'],
            'RotateSkew1' =>
            $prevMatrix['RotateSkew1'] * $matrix['ScaleX'] / 0x10000+
            $prevMatrix['ScaleY'] * $matrix['RotateSkew1'] / 0x10000,
            'ScaleY' =>
            $prevMatrix['RotateSkew1'] * $matrix['RotateSkew0'] / 0x10000+
            $prevMatrix['ScaleY'] * $matrix['ScaleY'] / 0x10000,
            'TranslateY' =>
            $prevMatrix['RotateSkew1'] * $matrix['TranslateX'] / 0x10000 / 20 +
            $prevMatrix['ScaleY'] * $matrix['TranslateY']  / 0x10000 / 20 + 
            $prevMatrix['TranslateY'],
        ];
        $prevMatrix = $matrix;
    }
    return $matrix;
}
