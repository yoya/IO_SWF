<?php
/* 
 * 2019/12/19- (c) yoya@awm.jp
 */

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require 'IO/SWF/Editor.php';
}

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
    $cid = null;
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
        echo "$code $name($klass)";
        if (! $tag->parseTagContent()) {
            echo "\n";
            fprintf(STDERR, "can't parse %s(%s) tag content\n", $name, $klass);
            continue;
        }
        if (isset($tag->tag->_characterId)) {
            $cid = $tag->tag->_characterId;
            echo " ID:$cid";
        } else if (isset($tag->tag->_shapeId)) {
            $cid = $tag->tag->_shapeId;
            echo " ID:$cid";
        } else if (isset($tag->tag->_spriteId)) {
            $cid = $tag->tag->_spriteId;
            echo " ID:$cid";
        }
        echo "\n";
        switch ($klass) {
        case "Place":
            if (isset($tag->tag->_matrix)) {
                $matrix = $tag->tag->_matrix;
            } else {
                $matrix = identMatrix();
            }
            $multipliedMatrix = multiplyMatrix($matrix, $parentMatrix);
            dumpMatrix([$matrix, $parentMatrix, null, $multipliedMatrix], $indent+0.5);
            $target_tag = $swf->getTagByCharacterId($cid);
            if (is_null($target_tag)) {
                throw new Exception("not found object cid:cid:$cid\n");
            } else {
                swftreematrix($swf, [$target_tag], $multipliedMatrix, $indent+1);
            }
            break;
        case "Sprite":
            swftreematrix($swf, $tag->tag->_controlTags, $parentMatrix,
                          $indent+1);
            break;
        case "Shape":
            $rect = $tag->tag->_shapeBounds;
            $multipliedRect = multiplyMatrix($parentMatrix, $rect);
            dumpMatrix([$parentMatrix, $rect, null, $multipliedRect], $indent+0.5).PHP_EOL;
            break;
        }
    }
}

function dumpMatrix($matrixArray, $indent) {
    $indentStr = str_repeat(" ", 4*$indent);
    $text_fmt3 = " %3.3f %3.3f %3.2f |";
    $text_fmt2 = " %3.3f %3.3f |";
    $indentStr = str_repeat(" ", 4*$indent);
    $text1 = $text2 = $indentStr."|";
    foreach ($matrixArray as $matrix) {
        if (isset($matrix['ScaleX'])) {
            $text1 .= sprintf($text_fmt3, $matrix['ScaleX'] / 0x10000,
                              $matrix['RotateSkew0'] / 0x10000,
                              $matrix['TranslateX'] / 20);
            $text2 .= sprintf($text_fmt3, $matrix['RotateSkew1'] / 0x10000,
                              $matrix['ScaleY'] / 0x10000,
                              $matrix['TranslateY'] / 20);
        } else if (isset($matrix['Xmin'])) {
            $text1 .= sprintf($text_fmt2,
                              $matrix['Xmin'] / 20, $matrix['Xmax'] / 20);
            $text2 .= sprintf($text_fmt2,
                              $matrix['Ymin'] / 20, $matrix['Ymax'] / 20);
        } else if ($matrix == null) {
            $text1 .= " _ |";
            $text2 .= " ~ |";
        } else {
            throw new Exception("dumpMatrix: unknown value");
        }
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
    if (isset($mat1['ScaleX']) && isset($mat2['ScaleX'])) {
        // matrix x matrix
        $ret = [
            'ScaleX' => (
                $mat1['ScaleX']      * $mat2['ScaleX']      / 0x10000 +
                $mat1['RotateSkew0'] * $mat2['RotateSkew1'] / 0x10000 ),
            'RotateSkew0' => (
                $mat1['ScaleX']      * $mat2['RotateSkew0'] / 0x10000 +
                $mat1['RotateSkew0'] * $mat2['ScaleY']      / 0x10000 ),
            'TranslateX' => (
                $mat1['ScaleX']      * $mat2['TranslateX'] / 0x10000 / 20 +
                $mat1['RotateSkew0'] * $mat2['TranslateY'] / 0x10000 / 20 +
                $mat1['TranslateX'] ),
            'RotateSkew1' => (
                $mat1['RotateSkew1'] * $mat2['ScaleX']      / 0x10000 +
                $mat1['ScaleY']      * $mat2['RotateSkew1'] / 0x10000 ),
            'ScaleY' => (
                $mat1['RotateSkew1'] * $mat2['RotateSkew0'] / 0x10000 +
                $mat1['ScaleY']      * $mat2['ScaleY']      / 0x10000 ),
            'TranslateY' => (
                $mat1['RotateSkew1'] * $mat2['TranslateX'] / 0x10000 / 20 +
                $mat1['ScaleY']      * $mat2['TranslateY'] / 0x10000 / 20 +
                $mat1['TranslateY'] ),
        ];
    } else if (isset($mat2["Xmin"])) {
        // matrix x rect
        $ret = [
            'Xmin' => (
                $mat1['ScaleX']      * $mat2['Xmin'] / 0x10000 +
                $mat1['RotateSkew0'] * $mat2['Ymin'] / 0x10000 +
                $mat1['TranslateX'] ),
            'Ymin' => (
                $mat1['RotateSkew1'] * $mat2['Xmin'] / 0x10000 +
                $mat1['ScaleY']      * $mat2['Ymin'] / 0x10000 +
                $mat1['TranslateY'] ),
            'Xmax' => (
                $mat1['ScaleX']      * $mat2['Xmin'] / 0x10000 +
                $mat1['RotateSkew0'] * $mat2['Ymin'] / 0x10000 +
                $mat1['TranslateX'] ),
            'Ymax' => (
                $mat1['RotateSkew1'] * $mat2['Xmax'] / 0x10000 +
                $mat1['ScaleY']      * $mat2['Ymax'] / 0x10000 +
                $mat1['TranslateY'] ),
        ];
    } else {
        throw new Exception("multiplyMatrix: unknown value");
    }
    return $ret;
}
