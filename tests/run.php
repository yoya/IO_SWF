<?php
/*
 * regression tests (no dependency)
 * usage: php tests/run.php
 *   IO_Bit is loaded from vendor/ (composer install) or include_path.
 */

$root = dirname(dirname(__FILE__));
if (is_readable($root.'/vendor/autoload.php')) {
    require_once $root.'/vendor/autoload.php';
}
set_include_path($root.PATH_SEPARATOR.get_include_path());
require_once 'IO/SWF/Editor.php';
require_once 'IO/SWF/ActionEditor.php';
require_once 'IO/SWF/Tag/Text.php';
require_once 'IO/SWF/Tag/Font.php';
require_once 'IO/SWF/ABC.php';
require_once 'IO/SWF/JPEG.php';

$failed = 0;
$passed = 0;

function check($name, $expect, $actual) {
    global $failed, $passed;
    if ($expect === $actual) {
        $passed++;
        return;
    }
    $failed++;
    echo "FAIL: $name".PHP_EOL;
    echo "  expect: ".var_export($expect, true).PHP_EOL;
    echo "  actual: ".var_export($actual, true).PHP_EOL;
}

// tag header + content
function tag($code, $content) {
    $len = strlen($content);
    if ($len < 0x3f) {
        return pack('v', ($code << 6) | $len).$content;
    }
    return pack('v', ($code << 6) | 0x3f).pack('V', $len).$content;
}

// uncompressed SWF: RECT(nbits=0), FrameRate 12, FrameCount 1, tags, End
function swf($tags, $version = 8) {
    $body = chr(0).pack('v', 12 << 8).pack('v', 1).$tags.tag(0, '');
    return 'FWS'.chr($version).pack('V', 8 + strlen($body)).$body;
}

// DefineShape(code) with no styles and only an EndShapeRecord
function minimalShape($code, $id) {
    return tag($code, pack('v', $id).chr(0).chr(0).chr(0).chr(0).chr(0));
}

function place2($cid, $depth, $name = null) {
    $flags = 0x02 | (is_null($name) ? 0 : 0x20);
    return tag(26, chr($flags).pack('v', $depth).pack('v', $cid).(is_null($name) ? '' : $name."\0"));
}

function place3($cid, $depth) {
    return tag(70, chr(0x02).chr(0x00).pack('v', $depth).pack('v', $cid));
}

function sprite($id, $tags, $frames = 1) {
    return tag(39, pack('vv', $id, $frames).$tags.tag(0, ''));
}

function tagCodes($swfdata) {
    $swf = new IO_SWF();
    $swf->parse($swfdata);
    $codes = array();
    foreach ($swf->_tags as $tag) {
        $codes[] = $tag->code;
    }
    return implode(',', $codes);
}

/*
 * SHAPE build: a StyleChangeRecord that is skipped (MoveTo to the current
 * position) must not leave its TypeFlag bit in the stream.
 */
// DefineShape id=1, no styles, records: MoveTo(0,0), StraightEdge(+1,+1), End
$content = pack('v', 1).chr(0).chr(0).chr(0).chr(0)."\x04\x18\x54\x00";
$shape = new IO_SWF_Tag_Shape();
$shape->parseContent(2, $content);
$built = $shape->buildContent(2);
$shape2 = new IO_SWF_Tag_Shape();
try {
    $shape2->parseContent(2, $built);
    $records = $shape2->_shapeRecords;
} catch (Exception $e) {
    $records = $e->getMessage();
}
check('shape skip no-op StyleChange: record count', 2, is_array($records) ? count($records) : $records);
check('shape skip no-op StyleChange: edge', array(1, 1, 1),
      is_array($records) ? array($records[0]['TypeFlag'], $records[0]['X'], $records[0]['Y']) : null);

/*
 * DoAction: Jump offsets follow the real length of edited actions
 */
// Jump over Push "cd", Stop, End
$push = chr(0x96).pack('v', 4).chr(0)."cd\0";
$content = chr(0x99).pack('v', 2).pack('v', strlen($push)).$push.chr(0x07).chr(0x00);
$action = new IO_SWF_Tag_Action();
$action->parseContent(12, $content);
$action->replaceActionStrings(array('cd' => 'longer-string'));
$action2 = new IO_SWF_Tag_Action();
$action2->parseContent(12, $action->buildContent(12));
check('action jump after replaceActionStrings: offset', $action2->_byteSizeTable[1],
      $action2->_actions[0]['BranchOffset']);
check('action jump after replaceActionStrings: target', 2,
      isset($action2->_branches[0]) ? $action2->_branches[0] : null);

/*
 * Editor::getSoundData() finds a DefineSound
 */
$editor = new IO_SWF_Editor();
$editor->parse(swf(tag(14, pack('v', 5).chr(0x2E).pack('V', 1)."ABC")));
check('getSoundData', 'ABC', $editor->getSoundData(5));
check('getSoundData unknown id', false, $editor->getSoundData(6));

/*
 * ActionPush Double: high 32-bit word first
 */
// 1.5 = 0x3FF8000000000000
$push = chr(0x96).pack('v', 9).chr(6)."\x00\x00\xF8\x3F\x00\x00\x00\x00";
$action = new IO_SWF_Tag_Action();
$action->parseContent(12, $push."\x00");
check('ActionPush Double parse', 1.5, $action->_actions[0]['Values'][0]['Double']);
check('ActionPush Double build', $push."\x00", $action->buildContent(12));

/*
 * getMovieClip keeps characters referenced by PlaceObject3 and by
 * MorphShape bitmap fills
 */
$data = swf(minimalShape(2, 1).sprite(2, place3(1, 1).tag(1, '')).place2(2, 1, 'mc').tag(1, ''));
$editor = new IO_SWF_Editor();
$editor->parse($data);
check('getMovieClip PlaceObject3 reference', '2,70,1,0', tagCodes($editor->getMovieClip('mc')));

$bitmap = tag(21, pack('v', 1)."JPEGDATA"); // DefineBitsJPEG2 id=1
foreach (array(46, 84) as $code) {
    // DefineMorphShape(2) id=2 with a clipped bitmap fill of bitmap 1
    $morph = pack('v', 2).chr(0).chr(0);
    if ($code == 84) {
        $morph .= chr(0).chr(0).chr(0); // StartEdgeBounds, EndEdgeBounds, flags
    }
    $morph .= pack('V', 9).chr(1).chr(0x41).pack('v', 1).chr(0).chr(0) // Offset, MorphFillStyles
            .chr(0).chr(0x10).chr(0).chr(0x00).chr(0); // MorphLineStyles, StartEdges, EndEdges
    $data = swf($bitmap.tag($code, $morph).sprite(9, place2(2, 1).tag(1, '')).place2(9, 1, 'mc').tag(1, ''));
    $editor = new IO_SWF_Editor();
    $editor->parse($data);
    check("getMovieClip MorphShape($code) bitmap reference", "21,$code,26,1,0", tagCodes($editor->getMovieClip('mc')));
}

/*
 * replaceMovieClip: references that are not renumbered stay as they are,
 * and DefineButton2 records are renumbered
 */
function replacedMovieClip($mc) {
    $base = swf(minimalShape(2, 1).sprite(2, tag(1, '')).place2(2, 1, 'mc').tag(1, ''));
    $editor = new IO_SWF_Editor();
    $editor->parse($base);
    $editor->replaceMovieClip('mc', $mc);
    $swf = new IO_SWF();
    $swf->parse($editor->build());
    $result = array();
    foreach ($swf->_tags as $tag) {
        $tag->parseTagContent(array('Version' => 8));
        switch ($tag->code) {
          case 2:
            $result[] = "shape:".$tag->tag->_shapeId;
            break;
          case 34:
            $result[] = "button:".$tag->tag->_buttonId."/".$tag->tag->_characters[0]['CharacterID'];
            break;
          case 39:
            foreach ($tag->tag->_controlTags as $t) {
                if ($t->code == 26) {
                    $t->parseTagContent();
                    $result[] = "place:".var_export($t->tag->_characterId, true);
                }
            }
            break;
        }
    }
    return implode(' ', $result);
}
check('replaceMovieClip without id collision', 'shape:1 shape:5 place:5',
      replacedMovieClip(swf(minimalShape(2, 5).place2(5, 1).tag(1, ''))));
// DefineButton2 id=5 whose record uses shape 1 (renumbered to 3)
$button2 = tag(34, pack('v', 5).chr(0).pack('v', 0)
               .chr(0x0F).pack('vv', 1, 1).chr(0).chr(0).chr(0));
check('replaceMovieClip DefineButton2', 'shape:1 shape:3 button:5/3 place:5',
      replacedMovieClip(swf(minimalShape(2, 1).$button2.place2(5, 1).tag(1, ''))));

/*
 * grepMovieClip keeps the movie clip and its placement
 */
$data = swf(minimalShape(2, 1).sprite(2, place2(1, 1).tag(1, '')).place2(2, 1, 'mc').tag(1, ''), 6);
$editor = new IO_SWF_Editor();
$editor->parse($data);
try {
    $codes = tagCodes($editor->grepMovieClip('mc'));
} catch (Exception $e) {
    $codes = $e->getMessage();
}
check('grepMovieClip', '2,39,26,0', $codes);

/*
 * ActionEditor::rebuild()
 */
$editor = new IO_SWF_ActionEditor();
$editor->parse(swf(tag(12, "\x07\x00").tag(1, ''), 6)); // DoAction(Stop)
$editor->parseAllTagContent(array());
$editor->insertSimpleTrace(0, 1, 1, "hi");
try {
    $editor->rebuild();
    $swf = new IO_SWF();
    $swf->parse($editor->build());
    $swf->_tags[0]->parseTagContent();
    $codes = array();
    foreach ($swf->_tags[0]->tag->_actions as $action) {
        $codes[] = $action['Code'];
    }
    sort($codes); // the insert position is not checked here
    $codes = implode(',', $codes);
} catch (Throwable $e) {
    $codes = get_class($e);
}
check('ActionEditor rebuild', '0,7,38,150', $codes);

/*
 * setActionVariables() without DoAction in frame 1
 */
$editor = new IO_SWF_Editor();
$editor->parse(swf(tag(9, "\xff\xff\xff").tag(1, ''), 6)); // SetBackgroundColor, ShowFrame
try {
    $editor->setActionVariables(array('foo' => 'bar', 'baz' => 'qux'));
    $swf = new IO_SWF();
    $swf->parse($editor->build());
    $result = tagCodes($editor->build());
    $swf->_tags[1]->parseTagContent();
    foreach ($swf->_tags[1]->tag->_actions as $action) {
        $result .= ' '.(isset($action['Values']) ? $action['Values'][0]['String'] : $action['Code']);
    }
} catch (Throwable $e) {
    $result = get_class($e);
}
check('setActionVariables new DoAction', '9,12,1,0 foo bar 29 baz qux 29 0', $result);

/*
 * VideoFrame: dump shows the codec specific data, no dynamic properties
 */
$errors = array();
set_error_handler(function ($errno, $errstr) use (&$errors) {
    $errors[] = $errstr;
    return true;
});
// DefineVideoStream id=3 CodecID=4 (VP6), VideoFrame StreamID=3
$data = swf(tag(60, pack('vvvv', 3, 1, 16, 16).chr(0).chr(4)).tag(61, pack('vv', 3, 0)."XYZW"));
$swf = new IO_SWF();
$swf->parse($data);
ob_start();
$swf->dump();
$dump = ob_get_clean();
check('VideoFrame dump Data', true, strpos($dump, "\n    Data: (len=4)") !== false);
$editor = new IO_SWF_Editor();
$editor->parse($data);
$frames = $editor->getVideoFrames(3);
check('getVideoFrames', 'XYZW', $frames[0]['Data']);
restore_error_handler();
check('VideoFrame no warnings', array(), $errors);

/*
 * Lossless2PNG: PIX15, and no float to int deprecations
 */
if (function_exists('imagecreatefromstring')) {
    $errors = array();
    set_error_handler(function ($errno, $errstr) use (&$errors) {
        $errors[] = $errstr;
        return true;
    });
    // DefineBitsLossless format 4 (PIX15), 1x1 red pixel, row padded to 4 bytes
    $pix15 = tag(20, pack('v', 1).chr(4).pack('vv', 1, 1).gzcompress(pack('n', 0x7C00)."\0\0"));
    // DefineBitsLossless2 format 5 (ARGB), 1x1 opaque pixel
    $argb = tag(36, pack('v', 2).chr(5).pack('vv', 1, 1).gzcompress("\xff\xfd\x00\x00"));
    $editor = new IO_SWF_Editor();
    $editor->parse(swf($pix15.$argb));
    $result = array();
    foreach (array(1, 2) as $id) {
        try {
            $im = imagecreatefromstring($editor->getPNGData($id));
            $result[] = sprintf('%08x', imagecolorat($im, 0, 0));
        } catch (Throwable $e) {
            $result[] = get_class($e);
        }
    }
    restore_error_handler();
    check('Lossless2PNG pixels', array('00f80000', '00fd0000'), $result);
    check('Lossless2PNG no warnings', array(), $errors);
}

/*
 * replace(Bitmap)TagByCharacterId: no dynamic property
 */
$errors = array();
set_error_handler(function ($errno, $errstr) use (&$errors) {
    $errors[] = $errstr;
    return true;
});
$editor = new IO_SWF_Editor();
$editor->parse(swf(tag(21, pack('v', 1)."\xff\xd8\xff\xd9")));
$editor->setCharacterId();
$jpeg2 = array('Code' => 21, 'Content' => pack('v', 1)."\xff\xd8\xff\xd9\x00");
$result = array($editor->replaceTagByCharacterId(21, 1, $jpeg2),
                $editor->replaceBitmapTagByCharacterId(21, 1, $jpeg2));
restore_error_handler();
check('replaceTagByCharacterId', array(1, 1), $result);
check('replaceTagByCharacterId no warnings', array(), $errors);

/*
 * SHAPE_BITMAP_TYPE_TILED mode
 */
$editor = new IO_SWF_Editor();
$editor->setShapeAdjustMode(IO_SWF_Editor::SHAPE_BITMAP_TYPE_TILED);
try {
    $result = $editor->applyShapeAdjustModeByRefId(1, 10, 10);
} catch (Throwable $e) {
    $result = get_class($e);
}
check('applyShapeAdjustModeByRefId TILED', true, $result);

/*
 * IO_SWF_Bitmap::get_bitmapsize() with short data
 */
$result = array();
foreach (array("\x89PNG\r\n", "GIF89a") as $data) {
    try {
        $result[] = IO_SWF_Bitmap::get_bitmapsize($data);
    } catch (Throwable $e) {
        $result[] = get_class($e);
    }
}
check('get_bitmapsize short data', array(false, false), $result);

/*
 * listMovieClip with a named instance of a shape
 */
$errors = array();
set_error_handler(function ($errno, $errstr) use (&$errors) {
    $errors[] = $errstr;
    return true;
});
$editor = new IO_SWF_Editor();
$editor->parse(swf(minimalShape(2, 1).place2(1, 1, 'shp').tag(1, '')));
$list = $editor->listMovieClip();
restore_error_handler();
check('listMovieClip shape instance', 'shp', $list[1]['path_list'][0]['path']);
check('listMovieClip no warnings', array(), $errors);

/*
 * LINESTYLE2 dump with a fill
 */
$errors = array();
set_error_handler(function ($errno, $errstr) use (&$errors) {
    $errors[] = $errstr;
    return true;
});
// Width 20, HasFillFlag, solid fill RGBA #112233(44)
$reader = new IO_Bit();
$reader->input(pack('v', 20)."\x08\x00"."\x00\x11\x22\x33\x44");
$opts = array('tagCode' => 83);
$lineStyle = IO_SWF_Type_LINESTYLE::parse($reader, $opts);
$text = IO_SWF_Type_LINESTYLE::string($lineStyle, $opts);
restore_error_handler();
check('LINESTYLE2 fill dump', true, strpos($text, 'solid fill: #112233(44)') !== false);
check('LINESTYLE2 fill dump no warnings', array(), $errors);

/*
 * FILLSTYLE dump: focal gradient, morph gradient start matrix
 */
// focal gradient (DefineShape4): identity matrix, 1 record, FocalPoint 0.5
$reader = new IO_Bit();
$reader->input(chr(0x13).chr(0).chr(0x01).chr(0)."\xff\x00\x00\xff".pack('v', 0x0080));
$opts = array('tagCode' => 83);
$text = IO_SWF_Type_FILLSTYLE::string(IO_SWF_Type_FILLSTYLE::parse($reader, $opts), $opts);
check('FILLSTYLE focal gradient dump', true,
      strpos($text, "focal gradient fill\n") === 0 && strpos($text, "FocalPoint: 0.5\n") !== false);
// morph linear gradient: start matrix translates X by 20 twips, end matrix is identity
$reader = new IO_Bit();
$reader->input(chr(0x10)."\x10\x28\x00\x00".chr(1)."\x00\x11\x22\x33\x44\xff\x55\x66\x77\x88");
$opts = array('tagCode' => 46);
$text = IO_SWF_Type_FILLSTYLE::string(IO_SWF_Type_FILLSTYLE::parse($reader, $opts), $opts);
check('FILLSTYLE morph gradient dump', "linear gradient fill\n".
      "    StartGradientMatrix:\n".
      "        | 1.000 0.000 |  1.00\n".
      "        | 0.000 1.000 |  0.00\n".
      "    EndGradientMatrix:\n".
      "        | 1.000 0.000 |  0.00\n".
      "        | 0.000 1.000 |  0.00\n".
      "        Start: Ratio:0 Color:#112233(44) => End: Ratio:255 Color:#556677(88)\n", $text);

/*
 * TEXTRECORD XOffset/YOffset are SI16
 */
// TextRecordType=1, HasYOffset, HasXOffset, XOffset=-20, YOffset=30, GlyphCount=0
$data = chr(0x83).pack('v', 0xFFEC).pack('v', 30).chr(0);
$reader = new IO_Bit();
$reader->input($data);
$opts = array('tagCode' => 11, 'GlyphBits' => 0, 'AdvanceBits' => 0);
$record = IO_SWF_Type_TEXTRECORD::parse($reader, $opts);
check('TEXTRECORD offsets', array(-20, 30), array($record['XOffset'], $record['YOffset']));
$writer = new IO_Bit();
IO_SWF_Type_TEXTRECORD::build($writer, $record, $opts);
check('TEXTRECORD offsets build', $data, $writer->output());

/*
 * DefineFont2 with no glyphs and FontFlagsHasLayout: CodeTableOffset and the layout block
 */
// FontID=1, HasLayout, no name, NumGlyphs=0, CodeTableOffset=2, Ascent=900 Descent=200 Leading=10, KerningCount=0
$content = pack('v', 1).chr(0x80).chr(0).chr(0).pack('v', 0).pack('v', 2).pack('vvv', 900, 200, 10).pack('v', 0);
$font = new IO_SWF_Tag_Font();
$font->parseContent(48, $content, array('tagCode' => 48));
check('DefineFont2 no glyphs layout parse', array(900, 200, 10),
      array($font->FontAscent, $font->FontDescent, $font->FontLeading));
check('DefineFont2 no glyphs layout build', bin2hex($content), bin2hex($font->buildContent(48)));
// without layout: NumGlyphs=0, CodeTableOffset=2 (as Flash writes it)
$content = pack('v', 1).chr(0).chr(0).chr(0).pack('v', 0).pack('v', 2);
$font = new IO_SWF_Tag_Font();
$font->parseContent(48, $content, array('tagCode' => 48));
check('DefineFont2 no glyphs no layout build', bin2hex($content), bin2hex($font->buildContent(48)));
// FontFlagsWideOffsets: CodeTableOffset is UI32 (4)
$content = pack('v', 1).chr(0x08).chr(0).chr(0).pack('v', 0).pack('V', 4);
$font = new IO_SWF_Tag_Font();
$font->parseContent(75, $content, array('tagCode' => 75));
check('DefineFont3 no glyphs wide offsets build', bin2hex($content), bin2hex($font->buildContent(75)));

/*
 * selectByCIDs: placements of unselected characters inside sprites are dropped,
 * and the source Editor is not changed.
 */
function spritePlaces($swfdata) {
    $swf = new IO_SWF();
    $swf->parse($swfdata);
    $result = array();
    foreach ($swf->_tags as $tag) {
        if ($tag->code == 39) {
            $tag->parseTagContent();
            foreach ($tag->tag->_controlTags as $tag_in_sprite) {
                if ($tag_in_sprite->code == 26) {
                    $tag_in_sprite->parseTagContent();
                    $result[] = $tag_in_sprite->tag->_characterId;
                }
            }
        }
    }
    return implode(',', $result);
}
// shapes 1 and 2, sprite 9 places both
$data = swf(minimalShape(2, 1).minimalShape(2, 2).
            sprite(9, place2(1, 1).place2(2, 2).tag(1, '')).place2(9, 1, 'mc').tag(1, ''));
$editor = new IO_SWF_Editor();
$editor->parse($data);
$selected = $editor->selectByCIDs(array(1, 9));
check('selectByCIDs tags', '2,39,26,1,0', tagCodes($selected));
check('selectByCIDs sprite places', '1', spritePlaces($selected));
// sprite 9: place shape 1, ShowFrame, RemoveObject2(depth 1), ShowFrame
$data = swf(minimalShape(2, 1).sprite(9, place2(1, 1).tag(1, '').tag(28, pack('v', 1)).tag(1, ''), 2).
            place2(9, 1, 'mc').tag(1, ''));
$editor = new IO_SWF_Editor();
$editor->parse($data);
$before = $editor->build();
$editor->selectByCIDs(array(1, 9));
check('selectByCIDs keeps source', bin2hex($before), bin2hex($editor->build()));

/*
 * reference tracking: DefineEditText -> its font
 */
// DefineFont2 id=1 (content after the id is not parsed), DefineEditText id=3 HasFont FontID=1
$font = tag(48, pack('v', 1).'FONTDATA');
$edit = tag(37, pack('v', 3).chr(0).chr(0x01).chr(0x00).pack('vv', 1, 240).chr(0));
$data = swf($font.$edit.sprite(9, place2(3, 1).tag(1, '')).place2(9, 1, 'mc').tag(1, ''));
$editor = new IO_SWF_Editor();
$editor->parse($data);
check('getMovieClip DefineEditText font reference', '48,37,26,1,0', tagCodes($editor->getMovieClip('mc')));
$editor = new IO_SWF_Editor();
$editor->parse($data);
check('selectByCIDs DefineEditText font reference', '48,37,39,26,1,0', tagCodes($editor->selectByCIDs(array(1, 9))));

/*
 * reference tracking: DefineButton/DefineButton2 -> the characters in its button records
 */
// DefineButton id=4, one BUTTONRECORD (all states, CharacterID=1, depth 1, identity MATRIX), no actions
$button = tag(7, pack('v', 4).chr(0x0F).pack('vv', 1, 1).chr(0).chr(0).chr(0));
$data = swf(minimalShape(2, 1).$button.sprite(9, place2(4, 1).tag(1, '')).place2(9, 1, 'mc').tag(1, ''));
$editor = new IO_SWF_Editor();
$editor->parse($data);
check('getMovieClip DefineButton reference', '2,7,26,1,0', tagCodes($editor->getMovieClip('mc')));
// DefineButton2 id=4, TrackAsMenu=0, ActionOffset=0, one BUTTONRECORD with CXFORMWITHALPHA, no actions
$button = tag(34, pack('v', 4).chr(0).pack('v', 0).chr(0x0F).pack('vv', 1, 1).chr(0).chr(0).chr(0));
$data = swf(minimalShape(2, 1).$button.sprite(9, place2(4, 1).tag(1, '')).place2(9, 1, 'mc').tag(1, ''));
$editor = new IO_SWF_Editor();
$editor->parse($data);
check('getMovieClip DefineButton2 reference', '2,34,26,1,0', tagCodes($editor->getMovieClip('mc')));

/*
 * ABC cpool dump: the NaN double entry is printed without a warning
 */
$errors = array();
set_error_handler(function ($errno, $errstr) use (&$errors) {
    $errors[] = $errstr;
    return true;
});
$info = array('integer' => array(), 'uinteger' => array(), 'double' => array(NAN, 1.5),
              'string' => array(), 'namespace' => array(), 'ns_set' => array(), 'multiname' => array());
$abc = new IO_SWF_ABC();
ob_start();
$abc->dump_cpool_info($info);
$text = ob_get_clean();
restore_error_handler();
check('ABC cpool double dump', true, strpos($text, "        double(count:2)\n    [0] NAN\n    [1] 1.5\n") !== false);
check('ABC cpool double dump no warnings', array(), $errors);

/*
 * imagedestroy() is not called on PHP >= 8.0 (deprecated since PHP 8.5)
 */
if (function_exists('imagecreatefromstring')) {
    $errors = array();
    set_error_handler(function ($errno, $errstr) use (&$errors) {
        if (strpos($errstr, 'imagedestroy') !== false) {
            $errors[] = $errstr;
        }
        return true;
    });
    $im = imagecreatetruecolor(1, 1);
    ob_start();
    imagepng($im);
    $png = ob_get_clean();
    ob_start();
    imagejpeg($im);
    $jpeg = ob_get_clean();
    $lossless = new IO_SWF_Lossless();
    $lossless->BitmapData2Lossless(1, $png);
    IO_SWF_JPEG::bitmapAlpha2PNG($jpeg, "\0");
    restore_error_handler();
    check('no imagedestroy deprecation', array(), $errors);
}

echo "passed: $passed, failed: $failed".PHP_EOL;
exit($failed ? 1 : 0);
