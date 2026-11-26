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
//                        $pixeldata .= chr(0); // Always 0
                        $pixeldata .= chr(255);
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
        
        if (PHP_VERSION_ID < 80000) { // no effect and deprecated since PHP 8.5
            imagedestroy($im);
        }
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

    /* Lossless 形式のデータ PNG データに変換する (一時ファイルを作ります)
     * return (string) $pngdata;
     */
    static function Lossless2PNG($tagCode, $format, $width, $height,
                          $palette_num, $palette_data,
                          $lossless_bitmap_data) {
        if ($format == 3) {
            $im = imagecreate($width, $height);
            $gd_palette = array();
            $padding = 0;
            if ($tagCode == 20) { // DefineBitsLossless
                $palette_bytesize = 3 * $palette_num;
                for ($i = 0, $j = 0; $i <  $palette_num; $i++) {
                    $red   = ord($palette_data[$j++]);
                    $green = ord($palette_data[$j++]);
                    $blue  = ord($palette_data[$j++]);
                    $gd_palette []= imagecolorallocate($im, $red, $green, $blue);
                }
            } else { // DefineBitsLossless2
                imagesavealpha($im, true);
                imagealphablending($im, false);
                for ($i = 0, $j = 0; $i <  $palette_num; $i++) {
                    $red   = ord($palette_data[$j++]);
                    $green = ord($palette_data[$j++]);
                    $blue  = ord($palette_data[$j++]);
                    $alpha = ord($palette_data[$j++]);
                    if ($alpha > 0) {
                        $red   = (int) min($red   * 255 / $alpha, 255);
                        $green = (int) min($green * 255 / $alpha, 255);
                        $blue  = (int) min($blue  * 255 / $alpha, 255);
                    }
                    $alpha_gd = (int) (127 - $alpha / 2);
                    $gd_palette []= imagecolorallocatealpha($im, $red, $green, $blue, $alpha_gd);
                }
            }
            if ($width % 4) {
                $padding = 4 - ($width % 4);
            }
            $i = 0;
            for ($y = 0 ; $y < $height ; $y++) {
                for ($x = 0 ; $x < $width ; $x++) {
                    $color_index = ord($lossless_bitmap_data[$i++]);
                    imagesetpixel($im, $x, $y, $gd_palette[$color_index]);
                }
                $i += $padding;
            }
        } else if ($format == 4) { // PIX15 (DefineBitsLossless only)
            $im = imagecreatetruecolor($width, $height);
            $padding = ($width % 2) ? 2 : 0; // row is padded to 32 bits
            $i = 0;
            for ($y = 0 ; $y < $height ; $y++) {
                for ($x = 0 ; $x < $width ; $x++) {
                    $pix15 = (ord($lossless_bitmap_data[$i]) << 8) | ord($lossless_bitmap_data[$i + 1]);
                    $i += 2;
                    $red   = (($pix15 >> 10) & 0x1f) << 3;
                    $green = (($pix15 >>  5) & 0x1f) << 3;
                    $blue  = ( $pix15        & 0x1f) << 3;
                    $color = imagecolorallocate($im, $red, $green, $blue);
                    imagesetpixel($im, $x, $y, $color);
                }
                $i += $padding;
            }
        } else { // format 5
            $im = imagecreatetruecolor($width, $height);
            if ($tagCode == 20) { // DefineBitsLossless
                $i = 0;
                for ($y = 0 ; $y < $height ; $y++) {
                    for ($x = 0 ; $x < $width ; $x++) {
                        $i++; // reserved X of XRGB
                        $red   = ord($lossless_bitmap_data[$i++]);
                        $green = ord($lossless_bitmap_data[$i++]);
                        $blue  = ord($lossless_bitmap_data[$i++]);
                        $color = imagecolorallocate($im, $red, $green, $blue);
                        imagesetpixel($im, $x, $y, $color);
                    }
                }
            } else { // DefineBitsLossless2
                imagesavealpha($im, true);
                imagealphablending($im, false);
                $i = 0;
                for ($y = 0 ; $y < $height ; $y++) {
                    for ($x = 0 ; $x < $width ; $x++) {
                        $alpha = ord($lossless_bitmap_data[$i++]);
                        $red   = ord($lossless_bitmap_data[$i++]);
                        $green = ord($lossless_bitmap_data[$i++]);
                        $blue  = ord($lossless_bitmap_data[$i++]);
                        if ($alpha > 0) {
                            $red   = (int) min($red   * 255 / $alpha, 255);
                            $green = (int) min($green * 255 / $alpha, 255);
                            $blue  = (int) min($blue  * 255 / $alpha, 255);
                        }
                        $alpha_gd = (int) (127 - $alpha / 2);
                        $color = imagecolorallocatealpha($im, $red, $green, $blue, $alpha_gd);
                        imagesetpixel($im, $x, $y, $color);
                    }
                }
            }
        }
        $filename = tempnam("/tmp", "swfcl2p");
        if (imagepng($im, $filename) === false) {
            return false;
        }
        $png_data = file_get_contents($filename);
        unlink($filename);
        return $png_data;
    }
}
