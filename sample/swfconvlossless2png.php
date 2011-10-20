<?php

require 'IO/SWF/Editor.php';

if ($argc != 2) {
    echo "Usage: php swfconvlossless2png.php <swf_file>\n";
    echo "ex) php swfconvlossless2png.php test.swf\n";
    exit(1);
}

assert(is_readable($argv[1]));

$swfdata = file_get_contents($argv[1]);

$swf = new IO_SWF_Editor();

$swf->parse($swfdata);

foreach ($swf->_tags as &$tag) {
    $tag_code = $tag->code;
    if (($tag_code == 20) || // DefineBitsLossless
        ($tag_code == 36)) { // DefineBitsLossless2
        if ($tag->parseTagContent()) {
            $cid = $tag->tag->_CharacterID;
            $format = $tag->tag->_BitmapFormat;
            $width =  $tag->tag->_BitmapWidth;
            $height = $tag->tag->_BitmapHeight;
            $lossless_bitmap_data = gzuncompress($tag->tag->_ZlibBitmapData);
            if ($format == 3) {
                $palette_num = $tag->tag->_BitmapColorTableSize;
                if ($tag_code == 20) { // DefineBisLossless
                    $palette_bytesize = 3 * $palette_num;
                } else {
                    $palette_bytesize = 4 * $palette_num;
                }
                $palette_data = substr($lossless_bitmap_data, 0, $palette_bytesize);
                $lossless_bitmap_data = substr($lossless_bitmap_data, $palette_bytesize);
            } else {
                $palette_num = 0;
                $palette_data = null;
            }
            $png_data = IO_SWF_Lossless::Lossless2PNG($tag_code, $format,
                                                      $width, $height,
                                                      $palette_num,
                                                      $palette_data,
                                                      $lossless_bitmap_data);
            $jpeg_tag = new IO_SWF_Tag_Jpeg();
            $jpeg_tag->code = 21; // DefineBitsJPEG2
            $jpeg_tag->_CharacterID = $cid;
            $jpeg_tag->_JPEGData = $png_data; // SWF8 spec
            
            $tag->code = 21; // DefineBitsJPEG2
            $tag->tag = $jpeg_tag;
            
            $tag->content = null;
        }
    }
}

echo $swf->build();

exit(0);
