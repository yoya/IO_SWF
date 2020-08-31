<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/Bit.php';
}
require_once dirname(__FILE__).'/Base.php';

class IO_SWF_Tag_VideoFrame extends IO_SWF_Tag_Base {
    var $_StreamID  = null;
    var $_FrameNum  = null;
    var $_VideoData = null;

    function parseContent($tagCode, $content, $opts = array()) {
        $reader = new IO_Bit();
    	$reader->input($content);
        $this->_StreamID = $reader->getUI16LE();
        $this->_FrameNum = $reader->getUI16LE();
        if (isset($opts['_CodecID'])) {
            $this->_CodecID = $codecID = $opts['_CodecID'];
            switch ($codecID) {
            case 5:  // VP6SWFALPHAVIDEOPACKET
                $this->_OffsetToAlpha = $reader->getUIBits(24);
                $this->_Data = $reader->getData($this->_OffsetToAlpha);
                $this->_Alphadata = $reader->getDataUntil(false);
                break;
            default:
                $this->_VideoData = $reader->getDataUntil(false);
                break;
            }
        } else {
            $this->_VideoData = $reader->getDataUntil(false);
        }
    }

    function dumpContent($tagCode, $opts = array()) {
        $data_len = strlen($this->_VideoData);
        echo "    StreamID: {$this->_StreamID}";
        echo "  FrameNum: {$this->_FrameNum}";
        if ($this->_CodecID) {
            $codecID = $this->_CodecID;
            switch ($codecID) {
            case 5:  // VP6SWFALPHAVIDEOPACKET
                echo "  OffsetToAlpha: {$this->_OffsetToAlpha}\n";
                $data_len = strlen($this->_Data);
                $alphadata_len = strlen($this->_Alphadata);
                echo "    Data: (len=$data_len)";
                echo "  AlphaData: (len=$alphadata_len)\n";
                break;
            default:
                echo "  VideoData: (len=$data_len)\n";
                break;
            }
        } else {
            echo "  VideoData: (len=$data_len)\n";
        }
    }

    function buildContent($tagCode, $opts = array()) {
        $writer = new IO_Bit();
        $writer->putUI16LE($this->_StreamID);
        $writer->putUI16LE($this->_FrameNum);
        $writer->putData($this->_VideoData);
    	return $writer->output();
    }
}
