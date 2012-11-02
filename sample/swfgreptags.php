<?php

require 'IO/SWF/Editor.php';

if ($argc < 2) {
    echo "Usage: php swfgreptags.php <keyword> <swf_file> [<swf_file2> [...]]]\n";
    echo "ex) php swfgreptags.php test.swf\n";
    echo "ex) php swfgreptags.php DefineMorph test.swf\n";
    echo "ex) php swfgreptags.php DefineShape,DefineBits test.swf test2.swf\n";
    exit(1);
}

$keyword = $argv[1];
$tagNo_list = array();

$tagMap = IO_SWF_Tag::$tagMap;

foreach (split(',', $keyword) as $key) {
    if (is_numeric($keyword)) {
        $tagNo_list[(int) $key] = true;
    } else {
        foreach ($tagMap as $tagNo => $tag) {
            if (stripos($tag['name'], $key) !== false) {
                $tagNo_list[(int) $tagNo] = true;
            }
        }
    }
}


//var_dump($tagNo_list);

foreach (array_slice($argv, 2) as $swffile) {
    if (is_readable($swffile) === false) {
        echo "ERROR: $swffile is not readable\n";
        continue;
    }
    $swfdata = file_get_contents($swffile);
    $swf = new IO_SWF_Editor();
    try {
        $swf->parse($swfdata);
    } catch (IO_Bit_Exception $e) {
        echo "ERROR: $swffile parse failed\n";
        continue;
    }
    foreach ($swf->_tags as $tag) {
        $code = $tag->code;
        $length = strlen($tag->content);
        if (isset($tagNo_list[$code])) {
            $tagName = $tagMap[$code]['name'];
            echo "$swffile: $tagName(code:$code, length:$length)\n";
        }
    }
}


