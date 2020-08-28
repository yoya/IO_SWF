<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require 'IO/SWF/Type/Action.php';
    require 'IO/SWF.php';
}

if ($argc < 2) {
    echo "Usage: php swfhistaction.php <swf_file> [<swf_file2>]\n";
    echo "ex) php swfhistaction.php test.swfn";
    exit (1);
}

$hist = [];
$codeName = [];
$hist3 = [];
$codeName3 = [];
    
foreach (array_slice($argv, 1) as $arg) {
    $swfdata = file_get_contents($arg);
    $swf = new IO_SWF();
    $swf->parse($swfdata);
    swftag_actionhist($swf->_tags, $hist, $codeName, $hist3, $codeName3);
}

if (count($hist) > 0) {
    echo "### AS1 Actions Code:\n";
    foreach ($hist as $code => $count) {
        $name = $codeName[$code];
        echo "$code($name):$count\n";
    }
}

if (count($hist3) > 0) {
    echo "### AS3 ABC Code Inst:\n";
    foreach ($hist3 as $code => $count) {
        $name = $codeName3[$code];
        echo "$code($name):$count\n";
    }
}

exit (0);

function swftag_actionhist($tag, &$hist, &$codeName, &$hist3, &$codeName3) {
    foreach ($tag as $idx => &$tag) {
        switch ($tag->code) {
        case 12: // DoAction
        case 59: // DoInitAction
            if ($tag->parseTagContent()) {
                swftag_actionhistogram($tag->tag, $hist, $codeName);
            }
            break;
        case 82: // DoABC
            if ($tag->parseTagContent()) {
                swftag_abchistogram($tag->tag, $hist3, $codeName3);
            }
            break;
        case 39: // DefineSprite
            if ($tag->parseTagContent()) {
                swftag_actionhist($tag->tag->_controlTags,
                                  $hist, $codeName, $hist3, $codeName3);
            }
            break;
        }
    }
}

# AS1/2 action code
function swftag_actionhistogram($tag, &$hist, &$codeName) {
    foreach ($tag->_actions as $action) {
        $code = $action["Code"];
        if (isset($hist[$code])) {
            $hist[$code] ++;
        } else {
            $hist[$code] = 1;
            $codeName[$code] = IO_SWF_Type_Action::getCodeName($code);
        }
    }
}

# AS3 ABC code inst
function swftag_abchistogram($tag, &$hist, &$codeName) {
    foreach ($tag->_ABC->method_body as $body) {
        foreach ($body["code"]->codeArray as $code) {
            $inst = ord($code["bytes"][0]);
            if (isset($hist[$inst])) {
                $hist[$inst] ++;
            } else {
                $hist[$inst] = 1;
                $codeName[$inst] = $body["code"]->getInstructionName($inst);
            }
        }
    }
}
