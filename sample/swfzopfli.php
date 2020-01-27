<?php
// require => https://github.com/kjdev/php-ext-zopfli

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require 'IO/SWF/Editor.php';
}

function zopfli_recompress($d) {
    $u = zopfli_uncompress($d);
    return zopfli_compress($u, 100);
}

function swftag_recompress(&$tag) {
    $ret = false;
    $zlibProperties = Array('_ZlibBitmapData', '_ZlibBitmapAlphaData');
    foreach ($zlibProperties as $methodName) {
        if (isset($tag->$methodName)) {
            if (strlen($tag->$methodName) > 0) {
                $z = zopfli_recompress($tag->$methodName);
                if ($z !== false) {
                    $tag->$methodName = $z;
                    $ret = true;
                }
            }
        }
    }
    return $ret;
}

if ($argc != 2) {
    echo "Usage: php swfzopfli.php <swf_file>\n";
    echo "ex) php swfzopfli.php test.swf\n";
    exit(1);
}

assert(is_readable($argv[1]));

$swfdata = file_get_contents($argv[1]);

$swf = new IO_SWF_Editor();

$swf->parse($swfdata);

foreach ($swf->_tags as $idx => &$tag) {
    switch ($tag->code) {
    case 26: // DefineLossless
    case 35: // DefineBitsJPEG3
    case 36: // DefineLossless2
        if ($tag->parseTagContent()) {
            $ret = swftag_recompress($tag->tag);
            if ($ret) {
                $tag->content = null;
            }
        }
    }
}

echo $swf->build();

exit(0);
