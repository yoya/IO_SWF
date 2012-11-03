<?php

require_once 'IO/Bit.php';
require_once dirname(__FILE__).'/Base.php';

class IO_SWF_Tag_Sound extends IO_SWF_Tag_Base {
   var $SoundId;
   var $SoundFormat, $SoundRate, $SoundSize;
   var $SoundSampleCount;
   var $SoundData;
   function parseContent($tagCode, $content, $opts = array()) {
        $reader = new IO_Bit();
    	$reader->input($content);
        $this->SoundId = $reader->getUI16LE();
        // ---
        $this->SoundFormat  = $reader->getUIBits(4);
        $this->SoundRate = $reader->getUIBits(2);
        $this->SoundSize  = $reader->getUIBit();
        // ---
        $this->SoundSampleCount  = $reader->getUI32LE();
        $this->SoundData  = $reader->getDataUntil(false);
    }

   static $SoundFormatNoteList = array(0 => 'Uncompressed, native-endian',
                                       1 => 'ADPCM',
                                       2 => 'MP3',
                                       3 => 'Uncompressed, little-endian',
                                       4 => 'Nellymoser 16 kHz',
                                       5 => 'Nellymoser 8kHz',
                                       6 => 'Nellymoser',
                                       11 => 'Speex',
                                       15 => 'Melo', // Maybe
       );
   static $SoundRateNoteList = array('5.5kHz', '11kHz', '22kHz', '44kHz');
   static $SoundSizeNoteList = array('sndMono', 'sndStereo');
    function dumpContent($tagCode, $opts = array()) {

        echo "\tSoundId:{$this->SoundId}\n";
        $SoundFormatNote = self::$SoundFormatNoteList[$this->SoundFormat];
        echo "\tSoundFormat:{$this->SoundFormat}($SoundFormatNote)\n";
        if ($this->SoundFormat < 15) {
            $SoundRateNote = self::$SoundRateNoteList[$this->SoundRate];
            echo "\tSoundRate:{$this->SoundRate}($SoundRateNote)\n";
            $SoundSizeNote =  self::$SoundSizeNoteList[$this->SoundSize];
            echo "\tSoundSize:{$this->SoundSize}($SoundSizeNote)\n";
            echo "\tSoundSampleCount:{$this->SoundSampleCount}\n";
        }
    }

    function buildContent($tagCode, $opts = array()) {
        $writer = new IO_Bit();
        $writer->putUI16LE($this->SoundId);
        // ----
        $writer->putUIBits($this->SoundFormat, 4);
        $writer->putUIBits($this->SoundRate, 2);
        $writer->putUIBit($this->SoundSize);
        // ---
        $writer->putUI32LE($this->SoundSampleCount);
        $writer->putData($this->SoundData);
    	return $writer->output();
    }
}
