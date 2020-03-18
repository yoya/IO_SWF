<?php

/*
 * Bitmap Utility Routine
 */

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/Bit.php';
}
require_once dirname(__FILE__).'/Exception.php';

class IO_SWF_Bitmap {
    const FORMAT_UNKNOW = 0;
    const FORMAT_JPEG = 1;
    const FORMAT_PNG = 2;
    const FORMAT_GIF = 4;

    static function detect_bitmap_format($bitmap_data) {
        if (strncmp($bitmap_data, "\xff\xd8\xff", 3) == 0) {
            return self::FORMAT_JPEG;
        } else if (strncmp($bitmap_data, "\x89PNG", 4) == 0) {
            return self::FORMAT_PNG;
        } else if (strncmp($bitmap_data, 'GIF', 3) == 0) {
            return self::FORMAT_GIF;
        }
        return self::FORMAT_UNKNOWN;
    }

    static function get_jpegsize($jpegdata) {
        $chunk_length = 0;
        $jpegdata_len = strlen($jpegdata);
        for ($idx = 0 ; (($idx + 8) < $jpegdata_len) ; $idx += $chunk_length) {
            $marker1 = ord($jpegdata[$idx]);
            if ($marker1 != 0xFF) {
                break;
            }
            $marker2 = ord($jpegdata[$idx + 1]);
            switch ($marker2) {
              case 0xD8: // SOI (Start of Image)
              case 0xD9: // EOI (End of Image)
                $chunk_length = 2;
                break;
              case 0xDA: // SOS
                throw new IO_SWF_Exception("encounter SOS before SOF");
              case 0xC0: // SOF0
              case 0xC1: // SOF1
              case 0xC2: // SOF2
              case 0xC3: // SOF3
              case 0xC5: // SOF5
              case 0xC6: // SOF6
              case 0xC7: // SOF7
              case 0xC9: // SOF9
              case 0xCA: // SOF10
              case 0xCB: // SOF11
              case 0xCD: // SOF13
              case 0xCE: // SOF14
              case 0xCF: // SOF15
                $width  = 0x100 * ord($jpegdata[$idx + 7]) + ord($jpegdata[$idx + 8]);
                $height = 0x100 * ord($jpegdata[$idx + 5]) + ord($jpegdata[$idx + 6]);
                return array($width, $height); // success
              default:
                $chunk_length = 0x100 * ord($jpegdata[$idx + 2]) + ord($jpegdata[$idx + 3]) + 2;
                if ($chunk_length == 0) { // fail safe;
                    break;
                }
            }
        }
        return false; // NG
    }

    static function get_pngsize($pngdata) {
        $pngdata_len = strlen($pngdata);
        if ($pngdata_len< 24) {
            fprintf(stderr, "IO_SWF_Bitmap::get_pngsize: data_len(%lu) < 16\n", $pngdata_len);
            return 1;
        }
        $width = (((ord($pngdata[16])*0x100) + ord($pngdata[17]))*0x100 + ord($pngdata[18]))*0x100 + ord($pngdata[19]);
        $height =(((ord($pngdata[20])*0x100) + ord($pngdata[21]))*0x100 + ord($pngdata[22]))*0x100 + ord($pngdata[23]);
        return array('width' => $width, 'height' => $height); // success
    }

    static function get_gifsize($gifdata) {
        $gifdata_len = strlen($gifdata);
        if ($gifdata_len < 10) {
            fprintf(stderr, "IO_SWF_Bitmap::get_gifsize: data_len(%lu) < 10\n", $gifdata_len);
            return false;
        }
        $width  = 0x100 * ord($gifdata[7]) + ord($gifdata[6]);
        $height = 0x100 * ord($gifdata[9]) + ord($gifdata[8]);
        return array('width' => $width, 'height' => $height); // success
    }

    static function get_bitmapsize($bitmapdata) {
        if (strncmp($bitmapdata, "\xff\xd8\xff", 3) == 0) { // JPEG
            return self::get_jpegsize($bitmapdata);
        } elseif (strncmp($bitmapdata,"\x89PNG", 4) == 0) { // PNG
            return self::get_pngsize($bitmapdata);
        } elseif (strncmp($bitmapdata, 'GIF', 3) == 0) { // GIF
            return self::get_gifsize($bitmapdata);
        }
        return false; // NG
    }
  }
