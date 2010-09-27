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

$erroneous_header = pack('CCCC', 0xFF, 0xD9, 0xFF, 0xD8);
$giffile = $argv[3];

// gif2lossless format translation

$im = imagecreatefromgif($giffile);

if ($im === false) {
    echo "$giffile is not GIF file\n";
    exit (1);
}
$colormap_num = imagecolorstotal($im);

$colormap = '';

$transparent_index = imagecolortransparent($im);

if ($transparent_index < 0) {
    for ($i = 0 ; $i < $colormap_num ; $i++) {
        $colormap .=  chr($rgb['red']);
        $colormap .=  chr($rgb['green']);
        $colormap .=  chr($rgb['blue']);
    }
} else {
    for ($i = 0 ; $i < $colormap_num ; $i++) {
        $rgb = imagecolorsforindex($im, $i);
        $colormap .=  chr($rgb['red']);
        $colormap .=  chr($rgb['green']);
        $colormap .=  chr($rgb['blue']);
        if ($i == $transparent_index) {
            $colormap .=  chr(0);
        } else {
            $colormap .=  chr(255);
        }
    }
}

$indices = '';
$i = 0;
$width  = imagesx($im);
$height = imagesy($im);

for ($y = 0 ; $y < $height ; $y++) {
    for ($x = 0 ; $x < $width ; $x++) {
        $indices .= chr(imagecolorat($im, $x, $y));
        $i++;
    }
    while (($i % 4) != 0) {
        $indices .= chr(0);
        $i++;
    }
}

// DefineBits,DefineBitsJPEG2,3, DefineBitsLossless,DefineBitsLossless2
$tag_code = array(6, 21, 35, 20, 36);

if ($transparent_index < 0) {
    $tagCode = 20; // DefineBitsLossless
} else {
    $tagCode = 36; // DefineBitsLossless2
}
$format = chr(3); // palett format
$content = pack('v', $image_id).$format.pack('v', $width).pack('v', $height);
$content .= chr($colormap_num - 1).gzcompress($colormap.$indices);

// 20: DefineBitsLossless
$tag = array('Code' => $tagCode,
             'Content' => $content);
$ret = $swf->replaceTagByCharacterId($tag_code, $image_id, $tag);

if ($ret == 0) {
    echo "Error: not found tag_code=".implode(',',$tag_code)." and image_id=$image_id tag".PHP_EOL;
    exit (1);
}

echo $swf->build();

exit(0);
