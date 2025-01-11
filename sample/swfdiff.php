<?php

/*
 * $ brew install libxdiff
 * $ pecl install xdiff
 * libxdiff library installation dir? [autodetect] : /opt/homebrew/Cellar/libxdiff/0.23/
 */

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require 'IO/SWF.php';
}

if ($argc !== 3)  {
    echo "Usage: php swfdiff.php <swf_file1> <swf_file2>\n";
    echo "ex) php swfdiff.php test1.swf test2.swf\n";
    exit(1);
}

$opts = [ 'hexdump'  => false,
          'addlabel' => true,
          'abcdump'  => true];

$a = get_swfdumpdata($argv[1], $opts);
$b = get_swfdumpdata($argv[2], $opts);

echo xdiff_string_diff($a, $b);

exit(0);

function get_swfdumpdata($filename, $opts) {
    if ($filename === "-") {
        $filename = "php://stdin";
    }
    $swfdata = file_get_contents($filename);
    $swf = new IO_SWF();
    $swf->parse($swfdata);
    ob_start();
    $swf->dump($opts);
    $data = ob_get_contents(); 
    ob_end_clean();
    return $data;
}
