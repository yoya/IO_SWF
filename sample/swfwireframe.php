<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require 'IO/SWF/Editor.php';
}

if ($argc != 2) {
    echo "Usage: php swfwireframe.php <swf_file>\n";
    echo "ex) php swfwireframe.php test.swf\n";
    exit(1);
}

assert(is_readable($argv[1]));

$swfdata = file_get_contents($argv[1]);

$swf = new IO_SWF_Editor();

$swf->parse($swfdata);

$opts = array('Version' => $swf->_headers['Version']); // for parser
foreach ($swf->_tags as $idx => &$tag) {
    $tag_code = $tag->code;
    if (($tag_code == 2) || ($tag_code == 22) || ($tag_code == 32)) { // Shape
        $opts['tagCode'] = $tag_code;
        if ($tag->parseTagContent($opts) === false) {
            throw new IO_SWF_Exception("Can't parseTagContent tag_code=$tag_code");
        }
        $transTable = swfwireframe($tag->tag->_fillStyles, $tag->tag->_lineStyles);
        foreach ($tag->tag->_shapeRecords as &$record) {
            if ($record['TypeFlag'] == 0 && (isset($record['EndOfShape']) === false)) {
                if (isset($record['FillStyles'])) {
                    $transTable = swfwireframe($record['FillStyles'], $record['LineStyles']);
                }
                if ($record['LineStyle'] == 0) {
                    if ($record['FillStyle0']) {
                        $record['LineStyle'] = $transTable[$record['FillStyle0']];
                    } else if ($record['FillStyle1']) {
                        $record['LineStyle'] = $transTable[$record['FillStyle1']];
                    } else {
                        $record['LineStyle'] = 1; // XXX
                    }
                    $record['FillStyle0'] = 0;
                    $record['FillStyle1'] = 0;
                }
            }
        }
        $tag->content = null;
    }
}

function swfwireframe(&$fillStyles, &$lineStyles) {
    $transTable = array();
    $lineStylesIdx = count($lineStyles);
    foreach ($fillStyles as $idx => $fillStyle) {
        switch ($fillStyle['FillStyleType']) {
          case 0: //fill
            $color = $fillStyle['Color'];
            break;
          default:
            $color = array("Red"   => 128,
                           "Green" => 128,
                           "Blue"  => 128,
                           "Alpha" => 255);
            break;
        }
        $lineStyle = array("Width" => 2,
                           "Color" => $color);
        $lineStyles []= $lineStyle;
        $transTable[$idx+1] = $lineStylesIdx+1; // 1 origin
        $lineStylesIdx++;
    }
    $fillStyles = array();
    return $transTable;
}

echo $swf->build();

exit(0);
