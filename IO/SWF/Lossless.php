<?php

class IO_SWF_Lossless {
    /* PNG と GIF の Bitmap データを Lossless 形式に変換する
     * return array('Code' => ..., 'Content' => ...)
     */
    function BitmapData2Lossless($bitmap_id, $bitmap_data) {
        $im = imagecreatefromstring($bitmap_data);
        if ($im === false) {
            throw new IO_SWF_Exception("not Bitmap Image");
        }

        $colortable_size = imagecolorstotal($im);
        
        if ((imageistruecolor($im) === false) && ($colortable_size <= 256)) {
            $format = 3; // palette format
            $transparent_exists = false;
            for ($i = 0 ; $i < $colortable_size ; $i++) {
                $rgba = imagecolorsforindex($im, $i);
                if (array_key_exists('alpha', $rgba) && ($rgba['alpha'] > 0)) {
                    $transparent_exists = true;
                    break;
                }
            }
            $colortable = '';
            if ($transparent_exists == false) {
                for ($i = 0 ; $i < $colortable_size ; $i++) {
                    $rgb = imagecolorsforindex($im, $i);
                    $colortable .= chr($rgb['red']);
                    $colortable .= chr($rgb['green']);
                    $colortable .= chr($rgb['blue']);
                }
            } else {
                for ($i = 0 ; $i < $colortable_size ; $i++) {
                    $rgba = imagecolorsforindex($im, $i);
                    $alpha = $rgba['alpha'];
                    $alpha = 2 * (127 - $alpha);
                    $colortable .= chr($rgba['red']  * $alpha / 255);
                    $colortable .= chr($rgba['green']* $alpha / 255);
                    $colortable .= chr($rgba['blue'] * $alpha / 255);
                    $colortable .= chr($alpha);
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
            
            $width  = imagesx($im);
            $height = imagesy($im);
            for ($y = 0 ; $y < $height ; $y++) {
                for ($x = 0 ; $x < $width ; $x++) {
                    $i = imagecolorat($im, $x, $y);
                    $rgba = imagecolorsforindex($im, $i);
                    if (array_key_exists('alpha', $rgba) && ($rgba['alpha'] > 0)) {
                        $transparent_exists = true;
                        break;
                    }
                }
            }
            $pixeldata = '';
            if ($transparent_exists === false) {
                for ($y = 0 ; $y < $height ; $y++) {
                    for ($x = 0 ; $x < $width ; $x++) {
                        $i = imagecolorat($im, $x, $y);
                        $rgb = imagecolorsforindex($im, $i);
                        $pixeldata .= 0; // Always 0
                        $pixeldata .= chr($rgb['red']);
                        $pixeldata .= chr($rgb['green']);
                        $pixeldata .= chr($rgb['blue']);
                    }
                }
            } else {
                for ($y = 0 ; $y < $height ; $y++) {
                    for ($x = 0 ; $x < $width ; $x++) {
                        $i = imagecolorat($im, $x, $y);
                        $rgba = imagecolorsforindex($im, $i);
                        $alpha = $rgba['alpha'];
                        $alpha = 2 * (127 - $alpha);
                        $pixeldata .= chr($alpha);
                        $pixeldata .= chr($rgba['red']  * $alpha / 255);
                        $pixeldata .= chr($rgba['green']* $alpha / 255);
                        $pixeldata .= chr($rgba['blue'] * $alpha / 255);
                    }
                }
            }
        }
        
        imagedestroy($im);
        $content = pack('v', $bitmap_id).chr($format).pack('v', $width).pack('v', $height);
        if ($format == 3) {
            $content .= chr($colortable_size - 1).gzcompress($colortable.$pixeldata);
        } else {
            $content .= gzcompress($pixeldata);
        }
        
        if ($transparent_exists === false) {
            $tagCode = 20; // DefineBitsLossless
        } else {
            $tagCode = 36; // DefineBitsLossless2
        }
        $tag = array('Code' => $tagCode,
                     'width' => $width,
                     'height' => $height,
                     'Content' => $content);
        return $tag;
    }
}
