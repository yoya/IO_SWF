<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require 'IO/SWF/Editor.php';
}

if ( ($argc < 4) || (($argc % 2) == 0) ||
      (($argv[1] !== '-') && (is_readable($argv[1]) === false)) ) {
    fprintf(STDERR, "Usage: php swfreplaceactionstrings.php <swf_file> <from_str> <to_str> [<from_str2> <to_str2> [...]]\n");
    fprintf(STDERR, "ex) php swfreplaceactionstrings.php test.swf foo baa\n");
    exit(1);
}

if ($argv[1] === '-') {
  $swfdata = "php://stdin";
} else {
  $swfdata = file_get_contents($argv[1]);
}

$trans_table = array();
for ($i = 2 ; $i < $argc ; $i+=2) {
    $trans_table[$argv[$i]] = $argv[$i+1];
}

$swf = new IO_SWF_Editor();

$swf->parse($swfdata);

$swf->replaceActionStrings($trans_table);

echo $swf->build();

exit(0);
