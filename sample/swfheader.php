<?php

require 'IO/SWF/Editor.php';

if ($argc < 2) {
    echo "Usage: php swfheader.php <swf_file> [<key>=<vakue> [<key2>=<value2> [...]]]\n";
    echo "ex) php swfheader.php test.swf\n";
    echo "ex) php swfheader.php test.swf FrameRate=512\n";
    exit(1);
}

assert(is_readable($argv[1]));

$swfdata = file_get_contents($argv[1]);

$swf = new IO_SWF_Editor();

$swf->parse($swfdata);

$headers = $swf->_headers;

if ($argc == 2) {
    echo "SWF Headers:\n";
    foreach ($headers as $key => $value) {
        if (is_array($value)) {
            foreach ($value as $key2 => $value2) {
                echo "   $key.$key2:$value2\n";
            }
        } else {
            echo "   $key:$value\n";
        }
    }
} else {
    $params = array_slice($argv, 2);
    foreach ($params as $param) {
        list ($key, $value) = explode('=', $param);
        $keys = explode('.', $key);
        if (is_array($keys) && (count($keys) > 1)) {
            list ($key1, $key2) = $keys;
            if (array_key_exists($key2, $headers[$key1])) {
                $swf->_headers[$key1][$key2] = $value;
            } else {
                echo "Header key not exists ($key)\n";
                exit (1);
            }
        }
        if (array_key_exists($key, $headers)) {
            $swf->_headers[$key] = $value;
        } else {
            echo "Header key not exists ($key)\n";
            exit (1);
        }
    }
    echo $swf->build();
}

exit(0);
