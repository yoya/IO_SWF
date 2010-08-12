<?php

/*
 * 2010/8/11- (c) yoya@awm.jp
 */

class UI_SWF {
    var $_headers = array();
    var $_tags = array();

    function parse($swfdata) {
        $reader = new BitIO();
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
    
    function dump() {
        /* SWF Header */
        echo 'Signature: '.$this->_headers['Signature'].PHP_EOL;
        echo 'Version: '.$this->_headers['Version'].PHP_EOL;
        echo 'FileLength: '.$this->_headers['FileLength'].PHP_EOL;
        echo 'FrameSize: '.PHP_EOL;
        echo "\tXmin: ".($this->_headers['FrameSize']['Xmin'] / 20).PHP_EOL;
        echo "\tXmax: ".($this->_headers['FrameSize']['Xmax'] / 20).PHP_EOL;
        echo "\tYmin: ".($this->_headers['FrameSize']['Ymin'] / 20).PHP_EOL;
        echo "\tYmax: ".($this->_headers['FrameSize']['Ymax'] / 20).PHP_EOL;
        echo 'FrameRate: '.($this->_headers['FrameRate'] / 0x100).PHP_EOL;
        echo 'FrameCount: '.$this->_headers['FrameCount'].PHP_EOL;

        /* SWF Tags */
        
        echo 'Tags:'.PHP_EOL;
        foreach ($this->_tags as $tag) {
            $code = $tag['Code'];
            $length = $tag['Length'];
            echo "\tCode: $code  Length: $length".PHP_EOL;
        }
    }
    function build() {
        $writer = new BitIO();

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
        return $writer->output();
    }
}
