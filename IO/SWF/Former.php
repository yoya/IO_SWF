<?php

/*
 * 2010/8/12- (c) yoya@awm.jp
 */

require_once 'IO/SWF.php';

class IO_SWF_Former extends IO_SWF {
    // var $_headers = array(); // protected
    // var $_tags = array();    // protected

    function form() {
        foreach ($this->_tags as $idx => $tag) {
            switch ($tag['Code']) {
                case 26: // PlaceObject2
                    $this->_form_26($tag);
                    break;
            }
        }
    }
    function _form_26($tag) { // PlaceObject2
        $reader = new IO_Bit();
        $reader->input($tab['Content']);
        $placeFlag = $reader->getUI8();
        $depth = $reader->getUI16LE();
        if ($placeFlag & 0x02) {
            $characterId = $reader->getUI16LE();
        }
        // 
    }
}
