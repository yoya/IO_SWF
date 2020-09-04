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
        $this->_VideoData = $reader->getDataUntil(false);
        if (isset($opts['_CodecID'][$this->_StreamID])) {
            $this->_CodecID = $codecID = $opts['_CodecID'][$this->_StreamID];
            $readerVideo = new IO_Bit();
            $readerVideo->input($this->_VideoData);
            switch ($codecID) {
            case 4:  // VP6SWFVIDEOPACKET
                $this->_Data = $readerVideo->getDataUntil(false);
                break;
            case 5:  // VP6SWFALPHAVIDEOPACKET
                $this->_OffsetToAlpha = $readerVideo->getUIBits(24);
                $this->_Data = $readerVideo->getData($this->_OffsetToAlpha);
                $this->_AlphaData = $readerVideo->getDataUntil(false);
                break;
            case 2:  // H263VIDEOPACKET
            case 3:  // SCREENVIDEOPACKET
            case 6:  // SCREENV2VIDEOPACKET
            default:
                break;
            }
        }
    }

    function dumpContent($tagCode, $opts = array()) {
        $vdata_len = strlen($this->_VideoData);
        echo "    StreamID: {$this->_StreamID}";
        echo "  FrameNum: {$this->_FrameNum}";
        echo "  VideoData: (len=$vdata_len)\n";
        if (isset($this->_CodecID) &&
            isset($this->_CodecID[$this->_StreamID])) {
            $codecID = $this->_CodecID[$this->_StreamID];
            switch ($codecID) {
            case 4:  // VP6SWFVIDEOPACKET
                $data_len = strlen($this->_Data);
                echo "    Data: (len=$data_len)\n";
                break;
            case 5:  // VP6SWFALPHAVIDEOPACKET
                $data_len = strlen($this->_Data);
                $alphadata_len = strlen($this->_AlphaData);
                echo "    OffsetToAlpha: {$this->_OffsetToAlpha}";
                echo "  Data: (len=$data_len)";
                echo "  AlphaData: (len=$alphadata_len)\n";
                break;
            case 2:  // H263VIDEOPACKET
            case 3:  // SCREENVIDEOPACKET
            case 6:  // SCREENV2VIDEOPACKET
            default:
                break;
            }
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
