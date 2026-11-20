<?php

/*
 * SoundStreamHead (18) / SoundStreamHead2 (45)
 *   Reserved UB[4], PlaybackSoundRate UB[2], PlaybackSoundSize UB[1], PlaybackSoundType UB[1],
 *   StreamSoundCompression UB[4], StreamSoundRate UB[2], StreamSoundSize UB[1], StreamSoundType UB[1],
 *   StreamSoundSampleCount UI16,
 *   LatencySeek SI16 (StreamSoundCompression == 2 (MP3) のとき。省略されたファイルもある)
 * SoundStreamBlock (19)
 *   StreamSoundData UI8[]  (形式はヘッダ依存なので生データのまま保持)
 */

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/Bit.php';
}
require_once dirname(__FILE__).'/Base.php';
require_once dirname(__FILE__).'/Sound.php';

class IO_SWF_Tag_SoundStream extends IO_SWF_Tag_Base {
    // SoundStreamHead / SoundStreamHead2
    var $_reserved;
    var $_playbackSoundRate, $_playbackSoundSize, $_playbackSoundType;
    var $_streamSoundCompression, $_streamSoundRate, $_streamSoundSize, $_streamSoundType;
    var $_streamSoundSampleCount;
    var $_latencySeek = null;
    var $_rest = null;
    // SoundStreamBlock
    var $_streamSoundData = null;

    function parseContent($tagCode, $content, $opts = array()) {
        if ($tagCode == 19) { // SoundStreamBlock
            $this->_streamSoundData = $content;
            return;
        }
        $reader = new IO_Bit();
        $reader->input($content);
        $this->_reserved = $reader->getUIBits(4);
        $this->_playbackSoundRate = $reader->getUIBits(2);
        $this->_playbackSoundSize = $reader->getUIBit();
        $this->_playbackSoundType = $reader->getUIBit();
        $this->_streamSoundCompression = $reader->getUIBits(4);
        $this->_streamSoundRate = $reader->getUIBits(2);
        $this->_streamSoundSize = $reader->getUIBit();
        $this->_streamSoundType = $reader->getUIBit();
        $this->_streamSoundSampleCount = $reader->getUI16LE();
        if (($this->_streamSoundCompression == 2) && $reader->hasNextData(2)) {
            $this->_latencySeek = $reader->getSI16LE();
        }
        if ($reader->hasNextData(1)) {
            $this->_rest = $reader->getDataUntil(false);
        }
    }

    function dumpContent($tagCode, $opts = array()) {
        if ($tagCode == 19) {
            echo "\tStreamSoundData: ".strlen($this->_streamSoundData)." bytes\n";
            return;
        }
        $rateList = IO_SWF_Tag_Sound::$SoundRateNoteList;
        $sizeList = IO_SWF_Tag_Sound::$SoundSizeNoteList;
        $typeList = IO_SWF_Tag_Sound::$SoundTypeNoteList;
        $formatList = IO_SWF_Tag_Sound::$SoundFormatNoteList;
        $format = isset($formatList[$this->_streamSoundCompression]) ? $formatList[$this->_streamSoundCompression] : 'unknown';
        echo "\tPlayback: Rate:{$this->_playbackSoundRate}({$rateList[$this->_playbackSoundRate]})";
        echo " Size:{$this->_playbackSoundSize}({$sizeList[$this->_playbackSoundSize]})";
        echo " Type:{$this->_playbackSoundType}({$typeList[$this->_playbackSoundType]})\n";
        echo "\tStream: Compression:{$this->_streamSoundCompression}($format)";
        echo " Rate:{$this->_streamSoundRate}({$rateList[$this->_streamSoundRate]})";
        echo " Size:{$this->_streamSoundSize}({$sizeList[$this->_streamSoundSize]})";
        echo " Type:{$this->_streamSoundType}({$typeList[$this->_streamSoundType]})\n";
        echo "\tStreamSoundSampleCount: {$this->_streamSoundSampleCount}";
        if (! is_null($this->_latencySeek)) {
            echo "  LatencySeek: {$this->_latencySeek}";
        }
        echo "\n";
        if (! is_null($this->_rest)) {
            echo "\t(trailing data: ".strlen($this->_rest)." bytes)\n";
        }
    }

    function buildContent($tagCode, $opts = array()) {
        if ($tagCode == 19) { // SoundStreamBlock
            return $this->_streamSoundData;
        }
        $writer = new IO_Bit();
        $writer->putUIBits($this->_reserved, 4);
        $writer->putUIBits($this->_playbackSoundRate, 2);
        $writer->putUIBit($this->_playbackSoundSize);
        $writer->putUIBit($this->_playbackSoundType);
        $writer->putUIBits($this->_streamSoundCompression, 4);
        $writer->putUIBits($this->_streamSoundRate, 2);
        $writer->putUIBit($this->_streamSoundSize);
        $writer->putUIBit($this->_streamSoundType);
        $writer->putUI16LE($this->_streamSoundSampleCount);
        if (! is_null($this->_latencySeek)) {
            $writer->putSI16LE($this->_latencySeek);
        }
        if (! is_null($this->_rest)) {
            $writer->putData($this->_rest);
        }
        return $writer->output();
    }
}
