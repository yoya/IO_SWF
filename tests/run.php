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
          case 7:
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
// DefineButton id=2 (renumbered to 4) whose record uses shape 1 (renumbered to 3)
$button = tag(7, pack('v', 2).chr(0x0F).pack('vv', 1, 1).chr(0).chr(0).chr(0));
check('replaceMovieClip DefineButton', 'shape:1 shape:3 button:4/3 place:4',
      replacedMovieClip(swf(minimalShape(2, 1).$button.place2(2, 1).tag(1, ''))));

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

/*
 * DefineButton (7) is a character definition like DefineButton2
 */
// DefineButton id=4 -> shape 1; the button is not placed
$button = tag(7, pack('v', 4).chr(0x0F).pack('vv', 1, 1).chr(0).chr(0).chr(0));
$editor = new IO_SWF_Editor();
$editor->parse(swf(minimalShape(2, 1).$button.minimalShape(2, 2).place2(2, 1).tag(1, '')));
$editor->purgeUselessContents();
check('purgeUselessContents unused DefineButton', '2,26,1,0', tagCodes($editor->build()));
// selecting the button id keeps the button, as for DefineButton2
$button2 = tag(34, pack('v', 4).chr(0).pack('v', 0).chr(0x0F).pack('vv', 1, 1).chr(0).chr(0).chr(0));
$result = array();
foreach (array($button, $button2) as $b) {
    $editor = new IO_SWF_Editor();
    $editor->parse(swf(minimalShape(2, 1).$b.tag(1, '')));
    $result[] = tagCodes($editor->selectByCIDs(array(1, 4)));
    $editor = new IO_SWF_Editor();
    $editor->parse(swf(minimalShape(2, 1).$b.minimalShape(2, 2).tag(1, '')));
    $result[] = tagCodes($editor->selectByCIDs(array(2)));
}
check('selectByCIDs DefineButton', array('2,7,1,0', '2,1,0', '2,34,1,0', '2,1,0'), $result);

/*
 * BitmapData2Lossless / bitmapAlpha2PNG: no float to int deprecations (values truncated as before)
 */
if (function_exists('imagecreatefromstring')) {
    $errors = array();
    set_error_handler(function ($errno, $errstr) use (&$errors) {
        if (strpos($errstr, 'imagedestroy') === false) {
            $errors[] = $errstr;
        }
        return true;
    });
    // truecolor with alpha: GD alpha 64 -> SWF alpha 2 * (127 - 64) = 126, premultiplied
    $im = imagecreatetruecolor(1, 1);
    imagealphablending($im, false);
    imagesavealpha($im, true);
    imagesetpixel($im, 0, 0, imagecolorallocatealpha($im, 200, 100, 50, 64));
    ob_start();
    imagepng($im);
    $png = ob_get_clean();
    $lossless = new IO_SWF_Lossless();
    $tag = $lossless->BitmapData2Lossless(1, $png);
    check('BitmapData2Lossless alpha pixel', '7e623118', bin2hex(gzuncompress(substr($tag['Content'], 7))));
    // palette with a transparent color
    $im = imagecreate(1, 1);
    imagecolorallocatealpha($im, 200, 100, 50, 64);
    ob_start();
    imagepng($im);
    $png = ob_get_clean();
    $lossless->BitmapData2Lossless(1, $png);
    // JPEG + alpha 128
    $im = imagecreatetruecolor(1, 1);
    imagefill($im, 0, 0, imagecolorallocate($im, 100, 100, 100));
    ob_start();
    imagejpeg($im);
    $jpeg = ob_get_clean();
    IO_SWF_JPEG::bitmapAlpha2PNG($jpeg, chr(128));
    restore_error_handler();
    check('BitmapData2Lossless / bitmapAlpha2PNG no warnings', array(), $errors);
}

/*
 * selectByCIDs: move-only PlaceObject2 and RemoveObject2 in sprites are kept
 * for a depth whose character is kept
 */
function spriteControls($swfdata) {
    $swf = new IO_SWF();
    $swf->parse($swfdata);
    $result = array();
    foreach ($swf->_tags as $tag) {
        if ($tag->code == 39) {
            $tag->parseTagContent();
            foreach ($tag->tag->_controlTags as $t) {
                $t->parseTagContent();
                $result[] = $t->code.(isset($t->tag->_depth) ? '@'.$t->tag->_depth : '');
            }
        }
    }
    return implode(',', $result);
}
$move = function ($depth) { return tag(26, chr(0x01).pack('v', $depth)); };
$sprite = sprite(9, place2(1, 1).place2(2, 2).tag(1, '').
                    $move(1).$move(2).tag(1, '').
                    tag(28, pack('v', 1)).tag(28, pack('v', 2)).tag(1, ''), 3);
$editor = new IO_SWF_Editor();
$editor->parse(swf(minimalShape(2, 1).minimalShape(2, 2).$sprite.place2(9, 1, 'mc').tag(1, '')));
check('selectByCIDs sprite move/remove by depth', '26@1,1,26@1,1,28@1,1,0',
      spriteControls($editor->selectByCIDs(array(1, 9))));

/*
 * DefineButtonCxform / DefineButtonSound go with their button
 */
// DefineButton id=4 -> shape 1, DefineButtonCxform for button 4 (empty CXFORM)
$button = tag(7, pack('v', 4).chr(0x0F).pack('vv', 1, 1).chr(0).chr(0).chr(0));
$cxform = tag(23, pack('v', 4).chr(0));
$result = array();
foreach (array('', place2(4, 2)) as $place) {
    $editor = new IO_SWF_Editor();
    $editor->parse(swf(minimalShape(2, 1).$button.$cxform.minimalShape(2, 2).place2(2, 1).$place.tag(1, '')));
    $editor->purgeUselessContents();
    $result[] = tagCodes($editor->build());
}
// DefineButton2 id=4 and DefineButtonSound for button 4 (no sounds)
$button2 = tag(34, pack('v', 4).chr(0).pack('v', 0).chr(0x0F).pack('vv', 1, 1).chr(0).chr(0).chr(0));
$editor = new IO_SWF_Editor();
$editor->parse(swf(minimalShape(2, 1).$button2.tag(17, pack('v', 4).str_repeat(pack('v', 0), 4)).
                   minimalShape(2, 2).place2(2, 1).tag(1, '')));
$editor->purgeUselessContents();
$result[] = tagCodes($editor->build());
check('purgeUselessContents button cxform/sound', array('2,26,1,0', '2,7,23,2,26,26,1,0', '2,26,1,0'), $result);

// move-only PlaceObject3 is treated as move-only PlaceObject2
$move3 = function ($depth) { return tag(70, chr(0x01).chr(0x00).pack('v', $depth)); };
$sprite = sprite(9, place2(1, 1).place2(2, 2).tag(1, '').$move3(1).$move3(2).tag(1, ''), 2);
$editor = new IO_SWF_Editor();
$editor->parse(swf(minimalShape(2, 1).minimalShape(2, 2).$sprite.place2(9, 1, 'mc').tag(1, '')));
check('selectByCIDs sprite PlaceObject3 move by depth', '26@1,1,70@1,1,0',
      spriteControls($editor->selectByCIDs(array(1, 9))));

/*
 * ABC d64 is a little-endian double
 */
require_once 'IO/SWF/ABC/Bit.php';
$abcBit = new IO_SWF_ABC_Bit();
$abcBit->input("\x00\x00\x00\x00\x00\x00\xf8\x3f"."\x61\x71\x38\xf3\xab\x39\xfb\x3f");
check('ABC d64 1.5', 1.5, $abcBit->get_d64());
check('ABC d64 1.70158', 1.70158, $abcBit->get_d64());

/*
 * synthetic test data (tests/data/make.php): parse, dump, build round trips
 */
function dataFile($name) {
    global $root;
    return file_get_contents("$root/tests/data/$name");
}

function checkThrows($name, $class, $func) {
    try {
        $func();
        $actual = 'no exception';
    } catch (Throwable $e) {
        $actual = ($e instanceof $class) ? $class : get_class($e).': '.$e->getMessage();
    }
    check($name, $class, $actual);
}

// array(return value of $func, PHP errors raised in it)
function withErrors($func) {
    $errors = array();
    set_error_handler(function ($errno, $errstr, $errfile, $errline) use (&$errors) {
        $errors[] = "$errstr (".basename($errfile).":$errline)";
        return true;
    });
    try {
        $ret = $func();
    } finally {
        restore_error_handler();
    }
    return array($ret, $errors);
}

function parseSWF($swfdata, $opts = array()) {
    $swf = new IO_SWF();
    $swf->parse($swfdata, $opts);
    return $swf;
}

function parseEditor($swfdata) {
    $editor = new IO_SWF_Editor();
    $editor->parse($swfdata);
    return $editor;
}

// parsed tag objects with the code, the ones in sprites included
function tagObjects($tags, $code, $opts = array()) {
    $objects = array();
    foreach ($tags as $tag) {
        if ($tag->code == $code) {
            $tag->parseTagContent($opts);
            $objects[] = $tag->tag;
        }
        if ($tag->code == 39) {
            $tag->parseTagContent($opts);
            $objects = array_merge($objects, tagObjects($tag->tag->_controlTags, $code, $opts));
        }
    }
    return $objects;
}

// parse every tag content (in sprites too) and build it again
function rebuildAll($tags) {
    foreach ($tags as $tag) {
        if ($tag->parseTagContent()) {
            if ($tag->code == 39) {
                rebuildAll($tag->tag->_controlTags);
            }
            $tag->content = null;
        }
    }
}

// uncompressed SWF data
function fws($swfdata) {
    if (substr($swfdata, 0, 3) === 'CWS') {
        return 'FWS'.substr($swfdata, 3, 5).gzuncompress(substr($swfdata, 8));
    }
    return $swfdata;
}

function dumpText($swfdata, $opts = array()) {
    return withErrors(function () use ($swfdata, $opts) {
        $swf = parseSWF($swfdata, $opts);
        $level = ob_get_level();
        ob_start();
        try {
            $swf->dump($opts);
        } finally {
            // dumpSWF() leaves its SJIS conversion buffer open for SWF < 6
            while (ob_get_level() > $level + 1) {
                ob_end_flush();
            }
            $text = ob_get_clean();
        }
        return $text;
    });
}

$dataFiles = array(
    // Signature, Version, FrameSize (twips), FrameRate (8.8), FrameCount, tag codes
    'shapes.swf' => array('FWS', 8, array(0, 4000, 0, 3000), 24 << 8, 1, '9,20,20,20,36,36,2,22,32,83,26,26,26,26,1,0'),
    'text.swf'   => array('FWS', 8, array(0, 2000, 0, 1000), 0x0C80, 1, '48,75,75,37,37,26,26,1,0'),
    'sprite.swf' => array('CWS', 8, array(0, 4000, 0, 3000), 30 << 8, 2, '2,2,39,39,26,26,1,26,28,1,0'),
    'action.swf' => array('FWS', 5, array(0, 2000, 0, 1500), 12 << 8, 2, '2,12,12,7,34,26,26,1,12,1,0'),
    'abc.swf'    => array('CWS', 10, array(0, 11000, 0, 8000), 24 << 8, 1, '69,9,82,76,1,0'),
);
foreach ($dataFiles as $name => $expect) {
    list($signature, $version, $frameSize, $frameRate, $frameCount, $codes) = $expect;
    $data = dataFile($name);
    $swf = parseSWF($data);
    $h = $swf->_headers;
    check("$name header", array($signature, $version, strlen(fws($data)), $frameSize, $frameRate, $frameCount),
          array($h['Signature'], $h['Version'], $h['FileLength'],
                array_values($h['FrameSize']), $h['FrameRate'], $h['FrameCount']));
    check("$name tags", $codes, tagCodes($data));
    check("$name size < 4KB", true, strlen($data) < 4096);
    // round trips: as parsed, Editor::rebuild(), every tag (in sprites too) rebuilt
    check("$name build", bin2hex($data), bin2hex($swf->build()));
    $editor = parseEditor($data);
    $editor->rebuild();
    check("$name Editor rebuild", bin2hex($data), bin2hex($editor->build()));
    $swf = parseSWF($data);
    list($built, $errors) = withErrors(function () use ($swf) {
        rebuildAll($swf->_tags);
        return $swf->build();
    });
    check("$name rebuild all tags", bin2hex($data), bin2hex($built));
    check("$name rebuild all tags no warnings", array(), $errors);
    // FWS <=> CWS
    $swf = parseSWF($data);
    $swf->_headers['Signature'] = ($signature === 'FWS') ? 'CWS' : 'FWS';
    $other = $swf->build();
    check("$name to ".$swf->_headers['Signature'], bin2hex(fws($data)), bin2hex(fws($other)));
    check("$name to ".$swf->_headers['Signature'].' header', $swf->_headers['Signature'].substr($data, 3, 5),
          substr($other, 0, 8));
    // dump
    list($text, $errors) = dumpText($data, array('abcdump' => true));
    check("$name dump no warnings", array(), $errors);
    check("$name dump header", "Signature: $signature\nVersion: $version\nFileLength: ".strlen(fws($data))."\n",
          substr($text, 0, strlen("Signature: $signature\nVersion: $version\nFileLength: ".strlen(fws($data))."\n")));
    preg_match_all('/^ *Code: (\d+)\(/m', $text, $m);
    check("$name dump tags", true, count($m[1]) >= count(explode(',', $codes)));
}
// dump with hexdump
list($text, $errors) = dumpText(dataFile('shapes.swf'), array('hexdump' => true));
check('dump hexdump no warnings', array(), $errors);
check('dump hexdump header bytes', true, strpos($text, "0000  46 57 53 08 e4 01 00 00") !== false);

/*
 * shapes.swf: SetBackgroundColor, DefineBitsLossless(2), DefineShape1-4
 */
$swf = parseSWF(dataFile('shapes.swf'));
$bg = tagObjects($swf->_tags, 9);
check('SetBackgroundColor', array('Red' => 0x33, 'Green' => 0x66, 'Blue' => 0x99), $bg[0]->_color);
$bitmaps = array();
foreach (array_merge(tagObjects($swf->_tags, 20), tagObjects($swf->_tags, 36)) as $b) {
    $bitmaps[] = array($b->_CharacterID, $b->_BitmapFormat, $b->_BitmapWidth, $b->_BitmapHeight, $b->_BitmapColorTableSize);
}
check('DefineBitsLossless(2) fields', array(array(10, 3, 3, 2, 2), array(11, 5, 2, 1, null), array(14, 4, 1, 1, null),
                                             array(12, 5, 2, 2, null), array(13, 3, 1, 1, 2)), $bitmaps);
$shapes = tagObjects($swf->_tags, 2);
$shape = $shapes[0];
check('DefineShape bounds', array('Xmin' => 0, 'Xmax' => 400, 'Ymin' => 0, 'Ymax' => 400), $shape->_shapeBounds);
check('DefineShape styles', array(array(array('FillStyleType' => 0, 'Color' => array('Red' => 255, 'Green' => 0, 'Blue' => 0))),
                                  array(array('Width' => 20, 'Color' => array('Red' => 0, 'Green' => 0, 'Blue' => 0)))),
      array($shape->_fillStyles, $shape->_lineStyles));
$r = $shape->_shapeRecords;
check('DefineShape records', array(5, 20, 20, 0, 1, 1, array(380, 20), array(380, 380), array(20, 20), 0),
      array(count($r), $r[0]['MoveX'], $r[0]['MoveY'], $r[0]['FillStyle0'], $r[0]['FillStyle1'], $r[0]['LineStyle'],
            array($r[1]['X'], $r[1]['Y']), array($r[2]['X'], $r[2]['Y']), array($r[3]['X'], $r[3]['Y']), $r[4]['EndOfShape']));
$shapes = tagObjects($swf->_tags, 22);
$r = $shapes[0]->_shapeRecords;
check('DefineShape2 curved edge', array(0, 200, 0, 200, 100), array($r[1]['StraightFlag'], $r[1]['ControlX'], $r[1]['ControlY'],
                                                                      $r[1]['AnchorX'], $r[1]['AnchorY']));
check('DefineShape2 new styles', array(1, 2, 40, 1, 0, 0),
      array($r[3]['StateNewStyles'], count($r[3]['FillStyles']), $r[3]['LineStyles'][0]['Width'],
            $r[3]['FillStyle0'], $r[3]['FillStyle1'], $r[3]['LineStyle']));
check('DefineShape2 styles after new styles', array(2, 1, array(200, 200)),
      array($r[4]['FillStyle0'], $r[4]['LineStyle'], array($r[6]['X'], $r[6]['Y'])));
$shapes = tagObjects($swf->_tags, 32);
$fills = $shapes[0]->_fillStyles;
check('DefineShape3 linear gradient', array(0x10, 0x4000, 0x4000, 400, 300, 0, 0, 2,
                                            array('Red' => 255, 'Green' => 0, 'Blue' => 0, 'Alpha' => 0x80), 255),
      array($fills[0]['FillStyleType'], $fills[0]['GradientMatrix']['ScaleX'], $fills[0]['GradientMatrix']['ScaleY'],
            $fills[0]['GradientMatrix']['TranslateX'], $fills[0]['GradientMatrix']['TranslateY'],
            $fills[0]['SpreadMode'], $fills[0]['InterpolationMode'], count($fills[0]['GradientRecords']),
            $fills[0]['GradientRecords'][0]['Color'], $fills[0]['GradientRecords'][1]['Ratio']));
check('DefineShape3 radial gradient', array(0x12, 0x10000, 0, 1, 1, array(0, 128, 255)),
      array($fills[1]['FillStyleType'], $fills[1]['GradientMatrix']['ScaleX'], $fills[1]['GradientMatrix']['TranslateX'],
            $fills[1]['SpreadMode'], $fills[1]['InterpolationMode'],
            array_column($fills[1]['GradientRecords'], 'Ratio')));
check('DefineShape3 bitmap fill', array(0x41, 10, 20 << 16, 20 << 16, 0x100, -0x100),
      array($fills[2]['FillStyleType'], $fills[2]['BitmapId'], $fills[2]['BitmapMatrix']['ScaleX'],
            $fills[2]['BitmapMatrix']['ScaleY'], $fills[2]['BitmapMatrix']['RotateSkew0'],
            $fills[2]['BitmapMatrix']['RotateSkew1']));
$r = $shapes[0]->_shapeRecords;
check('DefineShape3 style changes', array(array(1, 1), array(2, 1, 100, 100), array(3, 0, 300, 300), 14),
      array(array($r[0]['FillStyle0'], $r[0]['LineStyle']), array($r[5]['FillStyle0'], $r[5]['LineStyle'], $r[5]['MoveX'], $r[5]['MoveY']),
            array($r[9]['FillStyle0'], $r[9]['LineStyle'], $r[9]['MoveX'], $r[9]['MoveY']), count($r)));
$shapes = tagObjects($swf->_tags, 83);
$shape = $shapes[0];
check('DefineShape4 bounds and flags', array(array(-20, 420, -20, 420), array(0, 400, 0, 400), 0, 0, 0),
      array(array_values($shape->_shapeBounds), array_values($shape->_edgeBounds),
            $shape->_usesFillWindingRule, $shape->_usesNonScalingStrokes, $shape->_usesScalingStrokes));
check('DefineShape4 focal gradient', array(0x13, -128, 2), array($shape->_fillStyles[0]['FillStyleType'],
      $shape->_fillStyles[0]['FocalPoint'], count($shape->_fillStyles[0]['GradientRecords'])));
$l = $shape->_lineStyles;
check('DefineShape4 LINESTYLE2 miter', array(30, 1, 2, 0, 1, 0, 1, 1, 2, 0x0300, array('Red' => 0x11, 'Green' => 0x22, 'Blue' => 0x33, 'Alpha' => 0xff)),
      array($l[0]['Width'], $l[0]['StartCapStyle'], $l[0]['JoinStyle'], $l[0]['HasFillFlag'], $l[0]['NoHScaleFlag'],
            $l[0]['NoVScaleFlag'], $l[0]['PixelHintingFlag'], $l[0]['NoClose'], $l[0]['EndCapStyle'],
            $l[0]['MiterLimitFactor'], $l[0]['Color']));
check('DefineShape4 LINESTYLE2 fill', array(40, 1, 1, 1, false, array('Red' => 0x44, 'Green' => 0x55, 'Blue' => 0x66, 'Alpha' => 0x77)),
      array($l[1]['Width'], $l[1]['JoinStyle'], $l[1]['HasFillFlag'], $l[1]['NoVScaleFlag'],
            isset($l[1]['MiterLimitFactor']), $l[1]['FillType']['Color']));
$r = $shape->_shapeRecords;
check('DefineShape4 records', array(2, 200, 0, 0, 400), array($r[3]['LineStyle'], $r[4]['ControlX'], $r[4]['ControlY'],
                                                                  $r[4]['AnchorX'], $r[4]['AnchorY']));
$places = array();
foreach (tagObjects($swf->_tags, 26) as $p) {
    $places[] = $p->_depth.':'.$p->_characterId.(is_null($p->_matrix) ? '' : ':'.$p->_matrix['TranslateX'].','.$p->_matrix['TranslateY']);
}
check('shapes.swf PlaceObject2', array('1:1', '2:2', '3:3:200,100', '4:4'), $places);

/*
 * text.swf: DefineFont2/3, DefineEditText
 */
$swf = parseSWF(dataFile('text.swf'));
$fonts = array_merge(tagObjects($swf->_tags, 48), tagObjects($swf->_tags, 75));
$font = $fonts[0];
check('DefineFont2 flags', array(1, 0, 0, 0, 0, 1, 0, 1, 1, 'Test'),
      array($font->FontID, $font->FontFlagsShiftJIS, $font->FontFlagsSmallText, $font->FontFlagsANSI,
            $font->FontFlagsWideOffsets, $font->FontFlagsWideCodes, $font->FontFlagsItalic, $font->FontFlagsBold,
            $font->LanguageCode['LanguageCode'], $font->FontName));
// OffsetTable: 2 UI16 offsets + CodeTableOffset, glyph 0 is 124 bits (16 bytes)
check('DefineFont2 OffsetTable', array(6, 22), $font->OffsetTable);
$g = $font->GlyphShapeTable;
check('DefineFont2 glyphs', array(6, 100, -700, 1, array(100, -700), 5, array(512, 100, 0, 0)),
      array(count($g[0]), $g[0][0]['MoveX'], $g[0][0]['MoveY'], $g[0][0]['FillStyle1'], array($g[0][4]['X'], $g[0][4]['Y']),
            count($g[1]), array($g[1][3]['ControlX'], $g[1][3]['ControlY'], $g[1][3]['AnchorX'], $g[1][3]['AnchorY'])));
check('DefineFont2 layout', array(array(0x41, 0x3042), 800, 200, 40, array(800, 1024),
                                  array('Xmin' => 0, 'Xmax' => 1024, 'Ymin' => -800, 'Ymax' => 100),
                                  array(array('FontKerningCode1' => 0x41, 'FontKerningCode2' => 0x3042, 'FontKerningAdjustment' => -50))),
      array($font->CodeTable, $font->FontAscent, $font->FontDescent, $font->FontLeading, $font->FontAdvanceTable,
            $font->FontBoundsTable[1], $font->FontKerningTable));
check('DefineFont3 no glyphs', array(2, 'Empty', array(), array(), null),
      array($fonts[1]->FontID, $fonts[1]->FontName, $fonts[1]->OffsetTable, $fonts[1]->CodeTable, $fonts[1]->FontAscent));
check('DefineFont3 no glyphs wide offsets layout', array(3, 1, 1, '', 1024, 256, 0, array(), null),
      array($fonts[2]->FontID, $fonts[2]->FontFlagsWideOffsets, $fonts[2]->FontFlagsWideCodes, $fonts[2]->FontName,
            $fonts[2]->FontAscent, $fonts[2]->FontDescent, $fonts[2]->FontLeading, $fonts[2]->FontAdvanceTable,
            $fonts[2]->FontKerningTable));
$edits = tagObjects($swf->_tags, 37);
$e = $edits[0];
check('DefineEditText', array(4, array(-40, 2000, -40, 400), 1, 1, 0, 0, 1, 240, array(0x10, 0x20, 0x30, 0xff), 32,
                              1, 20, 40, 0, -20, 0, 1, 0, 0, 1, 'msg', 'Hello', null),
      array($e->CharacterID, array_values($e->Bounds), $e->WordWrap, $e->Multiline, $e->Password, $e->ReadOnly,
            $e->FontID, $e->FontHeight, array_values($e->TextColor), $e->MaxLength,
            $e->Align, $e->LeftMargin, $e->RightMargin, $e->Indent, $e->Leading,
            $e->AutoSize, $e->Border, $e->WasStatic, $e->HTML, $e->UseOutlines, $e->VariableName, $e->InitialText, $e->FontClass));
$e = $edits[1];
check('DefineEditText FontClass, no text', array(5, 1, 1, 1, 1, 1, 0, 'MyFont', null, null, null, null, null, 'v2', null),
      array($e->CharacterID, $e->ReadOnly, $e->AutoSize, $e->NoSelect, $e->WasStatic, $e->HTML, $e->Border,
            $e->FontClass, $e->FontID, $e->FontHeight, $e->TextColor, $e->MaxLength, $e->Align, $e->VariableName, $e->InitialText));

/*
 * sprite.swf: DefineSprite, PlaceObject/2/3, RemoveObject/2, FrameLabel
 */
$swf = parseSWF(dataFile('sprite.swf'));
$sprites = tagObjects($swf->_tags, 39);
check('DefineSprite', array(array(3, 1, '26,1,0'), array(4, 3, '26,4,12,1,26,70,43,1,5,28,1,0')),
      array_map(function ($s) {
          return array($s->_spriteId, $s->_frameCount, implode(',', array_map(function ($t) { return $t->code; }, $s->_controlTags)));
      }, $sprites));
$controls = $sprites[1]->_controlTags;
foreach ($controls as $t) {
    $t->parseTagContent();
}
$p = $controls[0]->tag;
check('PlaceObject2 in sprite', array(1, 3, 'inner', 100, 50, 0x10000, 0), array($p->_depth, $p->_characterId, $p->_name,
      $p->_matrix['TranslateX'], $p->_matrix['TranslateY'], $p->_matrix['ScaleX'], $p->_placeFlagMove));
$p = $controls[1]->tag;
check('PlaceObject', array(2, 2, 0x20000, 0x8000, array(1, 1, 256, 128, 64, -10, 0, 10)),
      array($p->_characterId, $p->_depth, $p->_matrix['ScaleX'], $p->_matrix['ScaleY'], array_values($p->_colorTransform)));
$p = $controls[4]->tag;
check('PlaceObject2 move', array(1, 1, null, 5, -20, 300), array($p->_placeFlagMove, $p->_depth, $p->_characterId,
      $p->_ratio, $p->_matrix['TranslateX'], $p->_matrix['TranslateY']));
$p = $controls[5]->tag;
check('PlaceObject3', array(3, 1, 3, 1, null, null, 0), array($p->_depth, $p->_characterId, $p->_blendMode, $p->_bitmapCache,
      $p->_className, $p->_matrix, $p->_placeFlagMove));
check('FrameLabel', array('second', null), array($controls[6]->tag->_label, $controls[6]->tag->_namedAnchor));
check('RemoveObject / RemoveObject2', array(2, 2, null, 3), array($controls[8]->tag->_characterId, $controls[8]->tag->_depth,
      $controls[9]->tag->_characterId, $controls[9]->tag->_depth));
$places = tagObjects(array($swf->_tags[4], $swf->_tags[5], $swf->_tags[7]), 26);
check('PlaceObject2 CXFORMWITHALPHA, name', array(4, 'mc', array(1, 0, 0, 0, 0, -128)),
      array($places[0]->_characterId, $places[0]->_name, array_values($places[0]->_colorTransform)));
check('PlaceObject2 ClipDepth', array(2, 3), array($places[1]->_characterId, $places[1]->_clipDepth));
check('PlaceObject2 move only', array(1, null, 10, 10), array($places[2]->_placeFlagMove, $places[2]->_characterId,
      $places[2]->_matrix['TranslateX'], $places[2]->_matrix['TranslateY']));

/*
 * action.swf: DoAction (SWF 4 and 5 actions), DefineButton, DefineButton2
 */
$swf = parseSWF(dataFile('action.swf'));
$doActions = tagObjects($swf->_tags, 12);
$actions = $doActions[0]->_actions;
check('DoAction SWF4 codes', '81,83,8b,8c,8b,8a,6,96,1d,96,9d,96,26,9a,9f,7,99,0',
      implode(',', array_map(function ($a) { return dechex($a['Code']); }, $actions)));
check('DoAction SWF4 fields', array(1, 'http://example.com/', '_blank', '/mc', 'lbl', '', 1, 1),
      array($actions[0]['Frame'], $actions[1]['UrlString'], $actions[1]['TargetString'], $actions[2]['TargetName'],
            $actions[3]['Label'], $actions[4]['TargetName'], $actions[5]['Frame'], $actions[5]['SkipCount']));
check('ActionPush values', array(array('Type' => 0, 'String' => 'v'), array('Type' => 1, 'Float' => 1.5),
                                 array('Type' => 2, 'null' => null), array('Type' => 3, 'undefined' => null),
                                 array('Type' => 4, 'RegisterNumber' => 1), array('Type' => 5, 'Boolean' => 1),
                                 array('Type' => 6, 'Double' => 3.25), array('Type' => 7, 'Integer' => 7)),
      $actions[7]['Values']);
// If skips Push "skipped" (12 bytes) and Trace (1 byte), Jump goes back to [9]
check('ActionIf / ActionJump', array(13, -39, array(10 => 13, 16 => 9)),
      array($actions[10]['Offset'], $actions[16]['BranchOffset'], $doActions[0]->_branches));
check('ActionGetURL2 / ActionGotoFrame2', array(0, 1, 2, 1, 1, 2),
      array($actions[13]['LoadVariablesFlag'], $actions[13]['LoadTargetFlag'], $actions[13]['SendVarsMethod'],
            $actions[14]['SceneBiasFlag'], $actions[14]['PlayFlag'], $actions[14]['SceneBias']));
$actions = $doActions[1]->_actions;
check('DoAction SWF5 codes', '88,9b,96,1c,3e,96,3d,87,17,96,47,17,8e,3e,0',
      implode(',', array_map(function ($a) { return dechex($a['Code']); }, $actions)));
check('ActionConstantPool', array(3, array('a', 'b', 'f')), array($actions[0]['Count'], $actions[0]['ConstantPool']));
check('ActionDefineFunction / StoreRegister data', array("f\0\x01\x00x\0\x08\x00", "\0"),
      array($actions[1]['Data'], $actions[7]['Data']));
check('ActionPush constants', array(array('Type' => 7, 'Integer' => 1), array('Type' => 7, 'Integer' => 1),
                                    array('Type' => 8, 'Constant8' => 2), array('Type' => 9, 'Constant16' => 0),
                                    array('Type' => 8, 'Constant8' => 1)),
      array_merge($actions[5]['Values'], $actions[9]['Values']));
$a = $actions[12];
check('ActionDefineFunction2', array('g', 1, 2, 1, 1, 0, 0, array(array('Register' => 1, 'ParamName' => 'y')), 1),
      array($a['FunctionName'], $a['NumParams'], $a['RegisterCount'], $a['PreloadThisFlag'], $a['PreloadGlobalFlag'],
            $a['PreloadParentFlag'], $a['SuppressThisFlag'], $a['Parameters'], $a['codeSize']));
$buttons = tagObjects($swf->_tags, 7);
$c = $buttons[0]->_characters[0];
check('DefineButton', array(2, 1, array(1, 1, 1, 1), 1, 1, 20, 20, array(0x83), 'next.html'),
      array($buttons[0]->_buttonId, count($buttons[0]->_characters),
            array($c['ButtonStateHitTest'], $c['ButtonStateDown'], $c['ButtonStateOver'], $c['ButtonStateUp']),
            $c['CharacterID'], $c['PlaceDepth'], $c['PlaceMatrix']['TranslateX'], $c['PlaceMatrix']['TranslateY'],
            array_column($buttons[0]->_actions, 'Code'), $buttons[0]->_actions[0]['UrlString']));
$buttons = tagObjects($swf->_tags, 34);
$b = $buttons[0];
// ActionOffset: the field (2) + records (12 + 7 bytes) + the end flag (1)
check('DefineButton2', array(3, 1, 22, 2), array($b->_buttonId, $b->_trackAsMenu, $b->_actionOffset, count($b->_characters)));
$c = $b->_characters;
check('DefineButton2 records', array(array(0, 0, 1, 1), 1, 256, 128, 256, array(1, 0, 0, 0), 2, 0, 0),
      array(array($c[0]['ButtonStateHitTest'], $c[0]['ButtonStateDown'], $c[0]['ButtonStateOver'], $c[0]['ButtonStateUp']),
            $c[0]['ColorTransform']['HasMultiTerms'], $c[0]['ColorTransform']['RedMultiTerm'],
            $c[0]['ColorTransform']['GreenMultiTerm'], $c[0]['ColorTransform']['AlphaMultiTerm'],
            array($c[1]['ButtonStateHitTest'], $c[1]['ButtonStateDown'], $c[1]['ButtonStateOver'], $c[1]['ButtonStateUp']),
            $c[1]['PlaceDepth'], $c[1]['ColorTransform']['HasMultiTerms'], $c[1]['ColorTransform']['HasAddTerms']));
$conds = $b->_actions;
check('DefineButton2 BUTTONCONDACTION', array(array(12, 1, 0, 0, array(0x96, 0x26)), array(0, 0, 0, 13, array(0x07))),
      array_map(function ($c) {
          return array($c['CondActionSize'], $c['CondOverUpToOverDown'], $c['CondIdleToOverUp'], $c['CondKeyPress'],
                       array_column($c['Actions'], 'Code'));
      }, $conds));

/*
 * abc.swf: FileAttributes, DoABC (minimal ABC), SymbolClass
 */
$swf = parseSWF(dataFile('abc.swf'));
$attrs = tagObjects($swf->_tags, 69);
check('FileAttributes', array(1, 1, 0, 0, 0), array($attrs[0]->ActionScript3, $attrs[0]->UseNetwork, $attrs[0]->HasMetadata,
                                                     $attrs[0]->UseGPU, $attrs[0]->UseDirectBlit));
$symbols = tagObjects($swf->_tags, 76);
check('SymbolClass', array(array('Tag' => 0, 'Name' => 'pkg.Main')), $symbols[0]->_Symbols);
$doABCs = tagObjects($swf->_tags, 82, array('abcparse' => true));
$abc = $doABCs[0]->_ABC;
check('DoABC', array(1, 'frame1', 16, 46), array($doABCs[0]->_Flags, $doABCs[0]->_Name, $abc->_minor_version, $abc->_major_version));
$cpool = $abc->_constant_pool;
check('ABC cpool numbers', array(array(0, -5, 1000), array(0, 4294967295), true, 1.5, -0.25),
      array($cpool['integer'], $cpool['uinteger'], is_nan($cpool['double'][0]), $cpool['double'][1], $cpool['double'][2]));
check('ABC cpool strings, namespaces', array(array('*', 'pkg', 'Main', '', 'Object', 'frame1', 'pkg:Main'),
                                             array(array(), array('kind' => 0x16, 'name' => 1), array('kind' => 0x16, 'name' => 3),
                                                   array('kind' => 0x18, 'name' => 6)),
                                             array(array(), array(1, 2))),
      array($cpool['string'], $cpool['namespace'], $cpool['ns_set']));
check('ABC cpool multinames', array(array(), array('kind' => 7, 'ns' => 1, 'name' => 2), array('kind' => 7, 'ns' => 2, 'name' => 4),
                                    array('kind' => 7, 'ns' => 2, 'name' => 5), array('kind' => 9, 'name' => 4, 'ns_set' => 1)),
      $cpool['multiname']);
$inst = $abc->instance[0];
check('ABC instance', array(4, 1, 2, 0x09, 3, 1, array('name' => 3, 'kind' => 1, 'disp_id' => 1, 'method' => 3, 'metadata' => null)),
      array(count($abc->method), $inst['name'], $inst['super_name'], $inst['flags'], $inst['protectedNs'], $inst['iinit'],
            $inst['trait'][0]));
check('ABC class, script', array(2, 0, 0, 4, 0), array($abc->klass[0]['cinit'], $abc->klass[0]['trait_count'],
      $abc->script[0]['init'], $abc->script[0]['trait'][0]['kind'], $abc->script[0]['trait'][0]['classi']));
$body = $abc->method_body[3];
// getlocal_0 pushscope (pushdouble pop) x2 (pushint pop) (pushuint pop) (pushbyte pop) (pushstring pop) pushtrue iffalse nop returnvoid
check('ABC method body', array(4, 3, 27, 18, array(15 => 17), array(17 => 17)),
      array(count($abc->method_body), $body['method'], strlen($body['code']->codeData), count($body['code']->codeArray),
            $body['code']->branches, $body['code']->labels));
$main = $abc->getInstanceByName('Main', 'pkg');
check('ABC getInstanceByName', array(1, null), array($main['name'], $abc->getInstanceByName('Main', 'other')));
check('ABC frame methods', array(array(array(1, 3)), 3, 18),
      array($abc->getFrameAndCodeByInstance($main), $abc->getMethodIdByTraitName($main, 'frame1'),
            count($abc->getCodeByMethodId(3)->codeArray)));
$editor = new IO_SWF_Editor();
$editor->parse(dataFile('abc.swf'));
$tags = $editor->_tags;
$tags[2]->parseTagContent(array('abcparse' => true));
$tags[3]->parseTagContent();
check('listABCmethodIdToActionFrame', array(0 => array(1 => 3)), $editor->listABCmethodIdToActionFrame($tags[2], $tags[3]));
list($text, $errors) = dumpText(dataFile('abc.swf'), array('abcdump' => true));
check('ABC dump', array(true, true, true), array(strpos($text, "    [1] -5\n    [2] 1000\n") !== false,
      strpos($text, "    [1] 1.5\n    [2] -0.25\n") !== false, strpos($text, "[15] iffalse 1 (Branchs:[17])") !== false));

/*
 * truncated input
 */
checkThrows('truncated.swf', 'IO_Bit_Exception', function () { parseSWF(dataFile('truncated.swf')); });
foreach (array('', 'FWS', "FWS\x08", "FWS\x08\x10\x00\x00\x00", "FWS\x08\x10\x00\x00\x00\x00\x00\x0c\x01\x00") as $data) {
    checkThrows('truncated header '.bin2hex($data), 'IO_Bit_Exception', function () use ($data) { parseSWF($data); });
}
checkThrows('ZWS', 'IO_SWF_Exception', function () { parseSWF("ZWS\x0d".pack('V', 100)."\x00\x00\x00\x00"); });

echo "passed: $passed, failed: $failed".PHP_EOL;
exit($failed ? 1 : 0);
