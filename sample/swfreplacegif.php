<?php

require_once 'IO/SWF/Editor.php';

if ($argc != 4) {
    echo "Usage: php swfreplacegif.php <swf_file> <image_id> <gif_file>\n";
    echo "ex) php swfreplacegif.php test.swf 1 test.gif\n";
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

$giffile = $argv[3];

// gif2lossless format translation

$im = imagecreatefromgif($giffile);

if ($im === false) {
    echo "$giffile is not GIF file\n";
    exit (1);
}

$colortable_num = imagecolorstotal($im);
$transparent_index = imagecolortransparent($im);

$colortable = '';

if ($transparent_index < 0) {
    for ($i = 0 ; $i < $colortable_num ; $i++) {
        $rgb = imagecolorsforindex($im, $i);
        $colortable .= chr($rgb['red']);
        $colortable .= chr($rgb['green']);
        $colortable .= chr($rgb['blue']);
    }
} else {
    for ($i = 0 ; $i < $colortable_num ; $i++) {
        $rgb = imagecolorsforindex($im, $i);
        $colortable .= chr($rgb['red']);
        $colortable .= chr($rgb['green']);
        $colortable .= chr($rgb['blue']);
        $colortable .= ($i == $transparent_index)?chr(0):chr(255);
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

// DefineBits,DefineBitsJPEG2,3, DefineBitsLossless,DefineBitsLossless2
$tag_code = array(6, 21, 35, 20, 36);

$format = 3; // palette format
$content = pack('v', $image_id).chr($format).pack('v', $width).pack('v', $height);
$content .= chr($colortable_num - 1).gzcompress($colortable.$pixeldata);

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
