<?php

require 'IO/SWF/Editor.php';

$tagMap = IO_SWF_Tag::$tagMap;

if ($argc !== 3) {
    echo "Usage: php swfextract.php <swf_file> <outfile_prefix>\n";
    echo "ex) php swfextract.php test.swf test-\n";
    exit(1);
}

$swffile = $argv[1];
$outfile_prefix  = $argv[2];
$swfdata = file_get_contents($swffile);

$swf = new IO_SWF_Editor();

try {
    $swf->parse($swfdata);
} catch (Exception $e) {
    echo "ERROR: $swffile parse failed\n";
    exit (1);
}

$jpegTables = false;
foreach ($swf->_tags as $tag) {
    $code = $tag->code;
    $length = strlen($tag->content);
    $tagName = $tagMap[$code]['name'];
    $cid = false;
    $ext = false;
    switch ($code) {
    case 8:// JPEGTables;
        $jpegTables = $tag->content;
        break;
    case 6: // DefineBits
    case 21: // DefineBitsJPEG2
    case 35: // DefineBitsJEPG3
        $tag->parseTagContent();
        $cid = $tag->tag->_CharacterID;
        $data = $tag->getJpegData($jpegTables);
        $ext = "jpg";
        break;
    case 20: // DefineLossless
    case 36: // DefineLossless2
        $tag->parseTagContent();
        $cid = $tag->tag->_CharacterID;
        $data = $tag->getPNGData();
        $ext = "png";
        break;
    case 14: // DefineSound
        $tag->parseTagContent();
        $cid = $tag->tag->SoundId;
        $data = $tag->getSoundData();
        $ext = "sound"; // sound default
        switch ($tag->tag->SoundFormat) {
        case 1: // ADPCM
            $ext = "adpcm";
            break;
        case 2: // MP3
            $ext = "mp3";
            break;
        case 11: // Speex
            $ext = "spx";
            break;
        case 15: // Melody (maybe)
            if (strncmp($data, 'melo', 4) === 0) {
                $ext = "mld";
            } else if (strncmp($data, 'MMMD', 4) === 0) {
                $ext = "mmf";
            } else {
                $ext = "melo";
            }
        }
        break;
    case 39: // Sprite
        $tag->parseTagContent();
        $cid = $tag->tag->_spriteId;
        $data = $swf->getMovieClip($cid);
        $ext = "swf";
        //case 14: // DefineSound
        //break;
    }
    if ($cid !== false) {
        $outfile = "$outfile_prefix$cid.$ext";
        echo $outfile.PHP_EOL;
        file_put_contents($outfile, $data);
    }
}


exit(0);
