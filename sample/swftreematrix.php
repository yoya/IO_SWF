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
            $multipliedMatrix = multiplyMatrix($matrix, $parentMatrix);
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

function multiplyMatrix($mat1, $mat2) {
    $matrix = [
        'ScaleX' => (
            $mat1['ScaleX'] * $mat2['ScaleX'] / 0x10000 +
            $mat1['RotateSkew0'] * $mat2['RotateSkew1'] / 0x10000 ),
        'RotateSkew0' => (
            $mat1['ScaleX'] * $mat2['RotateSkew0'] / 0x10000 +
            $mat1['RotateSkew0'] * $mat2['ScaleY'] / 0x10000 ),
        'TranslateX' => (
            $mat1['ScaleX'] * $mat2['TranslateX']  / 0x10000 / 20 +
            $mat1['RotateSkew0'] * $mat2['TranslateY'] / 0x10000 / 20 +
            $mat1['TranslateX'] ),
        'RotateSkew1' => (
            $mat1['RotateSkew1'] * $mat2['ScaleX'] / 0x10000 +
            $mat1['ScaleY'] * $mat2['RotateSkew1'] / 0x10000 ),
        'ScaleY' => (
            $mat1['RotateSkew1'] * $mat2['RotateSkew0'] / 0x10000+
            $mat1['ScaleY'] * $mat2['ScaleY'] / 0x10000 ),
        'TranslateY' => (
            $mat1['RotateSkew1'] * $mat2['TranslateX'] / 0x10000 / 20 +
            $mat1['ScaleY'] * $mat2['TranslateY']  / 0x10000 / 20 +
            $mat1['TranslateY'] ),
    ];
    return $matrix;
}
