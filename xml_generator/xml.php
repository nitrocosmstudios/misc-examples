<?php

/**
* This class provides functionality for importing and exporting XML feeds.
*/

class xml{

  var $data = Array();  // Array to hold parsed data
  var $tag_counts = Array(); // Array to keep track of the number of each tag on each level.
  var $parent_tags = Array(); // Array to keep track of the current list of parent tags.
  var $depth = 0; // Number to determine the depth in the XML structure (to apply to each tag)
  var $pointer; // Pointer to refer to positions in the main parsed data array
  var $parser;  // Parser object
  var $cdata; // Holder for detected character data.

  /**
  * Reads an XML string and parses it into a useable multi-dimensional array.
  * @param $xml = the string of XML to parse.
  * @return $data = the parsed XML data.
  */
  function parse($xml){     
    // Set up the XML parser
    $this->parser = xml_parser_create();
    xml_parser_set_option($this->parser,XML_OPTION_SKIP_WHITE,1);    
    xml_set_object($this->parser,$this);
    xml_set_element_handler($this->parser,'xmlStartTag','xmlEndTag');
    xml_set_character_data_handler($this->parser,'xmlCharacterData');
    // Parse.
    xml_parse($this->parser,$xml,true);   
  }
  
  // Function that handles xml start tags
  function xmlStartTag($parser, $name, $attributes){
    $name = strtolower($name);
    // Increase tag depth
    $this->depth++;
    // Add tag to list of parent tags
    $tag_count = (isset($this->tag_counts[$this->depth][$name])) ? ($this->tag_counts[$this->depth][$name] + 1) : 0;
    $this->tag_counts[$this->depth][$name] = $tag_count;
    // Add current tag name to list of parent tags
    $this->parent_tags[$this->depth] = $name;
    // Set the pointer in the parsed data structure
    $this->setPointer();
    // Store the tag's attributes
    if(count($attributes) > 0){
      foreach($attributes as $n => $v){
        $this->pointer['attributes'][strtolower($n)] = $v;    
      }
    }
  }
  
  // Function that handles xml end tags
  function xmlEndTag($parser, $name){
    $name = strtolower($name);
    // Record the character data for the current tag.
    if(!empty($this->cdata)){
      $this->pointer['contents'] = $this->cdata;
    }
    // Clear the character data holder
    $this->cdata = '';    
    // Remove tag from list of parent tags
    unset($this->parent_tags[$this->depth]);
    // Decrease tag depth
    $this->depth--;
  }
  
  
  // Function that handles xml character data
  function xmlCharacterData($parser, $data){
    $this->cdata = trim($data);
  }
    
  // Function that dynamically sets the data pointer
  function setPointer(){
    $path = '';
    $eval_string = '';
    foreach($this->parent_tags as $depth => $tag_name){
      $path .= '[\''.$tag_name.'\']['.$this->tag_counts[$depth][$tag_name].']';    
    }
    $eval_string .= 'if(!isset($this->data'.$path.')){$this->data'.$path.' = Array();}';
    $eval_string .= '$this->pointer = &$this->data'.$path.';';
    eval($eval_string); 
  }
  
  // Returns an array of all tag counts for each depth in the xml.
  function getTagCounts(){
    return $this->tag_counts;
  }
  
  // Returns the parsed data
  function getData(){
    return $this->data;
  }

}

?>
