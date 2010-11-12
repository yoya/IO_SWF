<?php

require_once 'IO/SWF/Editor.php';

if ($argc != 4) {
    echo "Usage: php swfreplacepng.php <swf_file> <image_id> <png_file>\n";
    echo "ex) php swfreplacepng.php test.swf 1 test.png\n";
    exit(1);
}

assert(is_readable($argv[1]));
assert(is_numeric($argv[2]));
assert(is_readable($argv[3]));

$swfdata = file_get_contents($argv[1]);
$image_id = (int) $argv[2];

$swf = new IO_SWF_Editor();
$swf->parse($swfdata);
$swf->setCharacterId($swfdata);

$pngfile = $argv[3];

// png2lossless format translation

$im = imagecreatefrompng($pngfile);

if ($im === false) {
    echo "$pngfile is not PNG file\n";
    exit (1);
}

$colortable_num = imagecolorstotal($im);

if ((imageistruecolor($im) === false) && ($colortable_num <= 256)) {
    $format = 3; // palette format
    $transparent_exists = false;
    for ($i = 0 ; $i < $colortable_num ; $i++) {
        $rgba = imagecolorsforindex($im, $i);
        if (array_key_exists('alpha', $rgba) && ($rgba['alpha'] < 255)) {
            $transparent_exists = true;
            break;
        }
    }
    $colortable = '';
    if ($transparent_exists == false) {
        for ($i = 0 ; $i < $colortable_num ; $i++) {
            $rgb = imagecolorsforindex($im, $i);
            $colortable .= chr($rgb['red']);
            $colortable .= chr($rgb['green']);
            $colortable .= chr($rgb['blue']);
        }
    } else {
        for ($i = 0 ; $i < $colortable_num ; $i++) {
            $rgba = imagecolorsforindex($im, $i);
            $colortable .= chr($rgba['red']);
            $colortable .= chr($rgba['green']);
            $colortable .= chr($rgba['blue']);
            $colortable .= chr($rgba['alpha']);
        }
    }
    
    $pixeldata = '';
    $i = 0;
    $width  = imagesx($im);
    $height = imagesy($im);
    
    for ($y = 0 ; $y < $height ; $y++) {
        for ($x = 0 ; $x < $width ; $x++) {
            $pixeldata .= chr(imagecolorat($im, $x, $y));
            $i++;
        }
        while (($i % 4) != 0) {
            $pixeldata .= chr(0);
            $i++;
        }
    }
} else { // truecolor
    $format = 5; // trurcolor format
    $transparent_exists = false;
    for ($y = 0 ; $y < $height ; $y++) {
        for ($x = 0 ; $x < $width ; $x++) {
            $i = imagecolorat($im, $x, $y);
            $rgba = imagecolorsforindex($im, $i);
            if (array_key_exists('alpha', $rgba) && ($rgba['alpha'] < 255)) {
                $transparent_exists = true;
                break;
            }
        }
    }
    $pixeldata = '';
    if ($transparent_exists == false) {
        for ($y = 0 ; $y < $height ; $y++) {
            for ($x = 0 ; $x < $width ; $x++) {
                $i = imagecolorat($im, $x, $y);
                $rgb = imagecolorsforindex($im, $i);
                $colortable .= chr($rgb['red']);
                $colortable .= chr($rgb['green']);
                $colortable .= chr($rgb['blue']);
            }
        }
    } else {
        for ($y = 0 ; $y < $height ; $y++) {
            for ($x = 0 ; $x < $width ; $x++) {
                $i = imagecolorat($im, $x, $y);
                $rgba = imagecolorsforindex($im, $i);
                $alpha = chr($rgba['alpha']);
                $colortable .= chr($rgba['red'])   * $alpha / 255;
                $colortable .= chr($rgba['green']) * $alpha / 255;
                $colortable .= chr($rgba['blue'])  * $alpha / 255;
                $colortable .= $alpha;
            }
        }
    }
}

imagedestroy($im);

// DefineBits,DefineBitsJPEG2,3, DefineBitsLossless,DefineBitsLossless2
$tag_code = array(6, 21, 35, 20, 36);

$content = pack('v', $image_id).chr($format).pack('v', $width).pack('v', $height);
if ($format == 3) {
    $content .= chr($colortable_num - 1).gzcompress($colortable.$pixeldata);
} else {
    $content .= chr($colortable_num - 1).gzcompress($pixeldata);
}

if ($transparent_index < 0) {
    $tagCode = 20; // DefineBitsLossless
} else {
    $tagCode = 36; // DefineBitsLossless2
}
$tag = array('Code' => $tagCode,
             'Content' => $content);
$ret = $swf->replaceTagByCharacterId($tag_code, $image_id, $tag);

if ($ret == 0) {
    echo "Error: not found tag_code=".implode(',',$tag_code)." and image_id=$image_id tag".PHP_EOL;
    exit (1);
}

echo $swf->build();

exit(0);
