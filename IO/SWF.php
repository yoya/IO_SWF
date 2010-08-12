<?php

/*
 * 2010/8/11- (c) yoya@awm.jp
 */

require_once 'IO/Bit.php';

class IO_SWF {
    // instance variable
    var $_headers = array(); // protected
    var $_tags = array();    // protected

    function parse($swfdata) {
        $reader = new IO_Bit();
        $reader->input($swfdata);

        /* SWF Header */
        $this->_headers['Signature'] = $reader->getData(3);
        $this->_headers['Version'] = $reader->getUI8();
        $this->_headers['FileLength'] = $reader->getUI32LE();
        $this->_headers['FrameSize'] = array();
        $frameSize = array();
        $nBits = $reader->getUIBits(5);
        $frameSize['NBits'] = $nBits;
        $frameSize['Xmin'] = $reader->getUIBits($nBits);
        $frameSize['Xmax'] = $reader->getUIBits($nBits);
        $frameSize['Ymin'] = $reader->getUIBits($nBits);
        $frameSize['Ymax'] = $reader->getUIBits($nBits) ;
        $this->_headers['FrameSize'] = $frameSize;
        $reader->byteAlign();
        $this->_headers['FrameRate'] = $reader->getUI16LE();
        $this->_headers['FrameCount'] = $reader->getUI16LE();
        
        /* SWF Tags */
        while (true) {
            $tag = Array();
            $tagAndLength = $reader->getUI16LE();
            $code = $tagAndLength >> 6;
            $length = $tagAndLength & 0x3f;
            if ($length == 0x3f) { // long format
                $length = $reader->getUI32LE();
                $tag['LongFormat'] = true;
            }
            $tag['Code']  = $code;
            $tag['Length'] = $length;
            $tag['Content'] = $reader->getData($length);
            $this->_tags[] = $tag;
            if ($code == 0) { // END Tag
                break;
            }
        }
    }
    // function dump() => IO_SWF_Dumper
    
    function build() {
        $writer = new IO_Bit();

        /* SWF Header */
        $writer->putData($this->_headers['Signature']);
        $writer->putUI8($this->_headers['Version']);
        $writer->putUI32LE($this->_headers['FileLength']);

        $nBits = $this->_headers['FrameSize']['NBits'];
        // nBits check
        $writer->putUIBits($nBits, 5);
        $writer->putUIBits($this->_headers['FrameSize']['Xmin'], $nBits);
        $writer->putUIBits($this->_headers['FrameSize']['Xmax'], $nBits);
        $writer->putUIBits($this->_headers['FrameSize']['Ymin'], $nBits);
        $writer->putUIBits($this->_headers['FrameSize']['Ymax'], $nBits);
        $writer->byteAlign();
        $writer->putUI16LE($this->_headers['FrameRate']);
        $writer->putUI16LE($this->_headers['FrameCount']);
        
        /* SWF Tags */
        foreach ($this->_tags as $tag) {
            $code = $tag['Code'];
            $length = $tag['Length'];
            if (empty($tag['LongFormat']) && ($length < 0x3f)) {
                $tagAndLength = ($code << 6) | $length;
                $writer->putUI16LE($tagAndLength);
            } else {
                $tagAndLength = ($code << 6) | 0x3f;
                $writer->putUI16LE($tagAndLength);
                $writer->putUI32LE($length);
            }
            $writer->putData($tag['Content']);
        }
        list($fileLength, $bit_offset_dummy) = $writer->getOffset();
        $this->_headers['FileLength'] = $fileLength;
        $writer->setUI32LE($fileLength, 4);
        return $writer->output();
    }
}
