<?php

abstract class parser {

    /**
     * actual parsing of the data
     * @return array
     */
    abstract function parse($string, $static_data = array(), $debug=false);
    
}