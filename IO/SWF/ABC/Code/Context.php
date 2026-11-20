<?php

class IO_SWF_ABC_Code_Context {
    var $spriteId = null;
    var $ns = null;
    var $name = null;
    var $propertyMap = [];  // index => name, valuetype
    var $buttonActionMap = [];  // instance name => button name
    // addEventListener の登録: [['spriteId', 'class', 'target' => [...], 'event', 'handler'], ...]
    var $eventListeners = [];
}
