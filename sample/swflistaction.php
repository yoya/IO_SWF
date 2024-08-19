<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require 'IO/SWF/Editor.php';
}

if ($argc != 2) {
    echo "Usage: php swflistaction.php <swf_file>\n";
    echo "ex) php swflistaction.php test.swf\n";
    exit(1);
}

$filename = $argv[1];

if ($filename === "-") {
    $filename = "php://stdin";
}

$swfdata = file_get_contents($filename);

$swf = new IO_SWF_Editor();

$opts = [
    'abcparse' => true
];
$swf->parse($swfdata, $opts);

swflistaction($swf->_tags, null);

function swflistaction($swftags, $spriteId) {
    $opts = [ 'abcparse' => true ];
    $abc = null;
    $currentFrame = 1;
    foreach ($swftags as $idx => $tag) {
        $tag_code = $tag->code;
        if ($tag_code == 1) { // ShowFrame
            $currentFrame++;
            continue;
        }
        if (($tag_code == 12) || ($tag_code == 59)) { // DoAction, DoInitAction
            try {
                if ($tag->parseTagContent($opts) === false) {
                    echo "Unknown Action Tag\n";
                    exit(1);
                }
                $action_num = count($tag->tag->_actions);
            } catch (IO_Bit_Exception $e) {
                if (isset($tag->tag->_actions)) {
                    $action_num = count($tag->tag->_actions);
                    $action_num .= "(at least)";
                } else {
                    $action_num = "(parse error)";
                }
            }
            $length = strlen($tag->content);
            if ($spriteId) {
                echo "spriteId:$spriteId";
            } else {
                echo "spriteId:(root)";
            }
            echo "  frame:$currentFrame\t=> instruction:$action_num  length=$length\n";
        }
        if ($tag_code == 82) { // DoABC
            try {
                if ($tag->parseTagContent($opts) === false) {
                    echo "Unknown ABC Tag\n";
                    exit(1);
                }
                $method_num = count($tag->tag->_ABC->method);
            } catch (IO_Bit_Exception $e) {
                if (isset($tag->tag->_ABC->method)) {
                    $method_num = count($tag->tag->_ABC->method);
                    $method_num .= "(at least)";
                } else {
                    $action_num = "(parse error)";
                }
            }
            $length = strlen($tag->content);
            if ($spriteId) {
                echo "spriteId:$spriteId";
            } else {
                echo "spriteId:(root)";
            }
            echo "  frame:$currentFrame\t=> method:$method_num  length=$length\n";
            $abc = $tag->tag->_ABC;
        }
        if ($tag_code == 76) { // SymbolClass
            try {
                if ($tag->parseTagContent($opts) === false) {
                    echo "Unknown ABC Tag\n";
                    exit(1);
                }
                $symbol_num = count($tag->tag->_Symbols);
            } catch (IO_Bit_Exception $e) {
                echo "SynbolClass: parse error occurred";
            }
            foreach ($tag->tag->_Symbols as $symbol) {
                $id = $symbol["Tag"];
                $symbolName = $symbol["Name"];
                echo "  symbol: spriteId:$id name:$symbolName\n";
                list($ns, $name) = explode(".", $symbolName);
                $inst = $abc->getInstanceByName($ns, $name);
                if (is_null($inst)) {
                    throw new Exception("inst is null, symbolName:$symbolName ns:$ns name:$name");
                }
                $frameMethodArray = $abc->getFrameAndCodeByInstance($inst);
                foreach ($frameMethodArray as $methodArray) {
                    list($frame, $methodId) = $methodArray;
                    $code = $abc->getCodeByMethodId($methodId);
                    $method_lines = count($code->codeArray);
                    $method_size = strlen($code->codeData);
                    echo "    frame:$frame => method_lines:$method_lines  method_size=$method_size\n";
                }
            }
            $abc = null;
        }
        if ($tag_code == 39) { // DefineSprite
            if ($tag->parseTagContent($opts) === false) {
                echo "Unknown DefineSprite!!!\n";
                exit(1);
            }
            $spriteId = $tag->tag->_spriteId;
            swflistaction($tag->tag->_controlTags, $spriteId);
        }
    }
}

exit(0);
