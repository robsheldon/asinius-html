<?php

/*******************************************************************************
*                                                                              *
*   Asinius\HTML\Elements                                                      *
*                                                                              *
*   Workhorse class for the Asinius html component. Wraps DOMNodes and         *
*   DOMNodeLists and makes it possible to work with them in a jQuery-like      *
*   fashion.                                                                   *
*                                                                              *
*   Elements are always operated on as a set, so there's no individual         *
*   "element" class.                                                           *
*                                                                              *
*   LICENSE                                                                    *
*                                                                              *
*   Copyright (c) 2020 Rob Sheldon <rob@rescue.dev>                            *
*                                                                              *
*   Permission is hereby granted, free of charge, to any person obtaining a    *
*   copy of this software and associated documentation files (the "Software"), *
*   to deal in the Software without restriction, including without limitation  *
*   the rights to use, copy, modify, merge, publish, distribute, sublicense,   *
*   and/or sell copies of the Software, and to permit persons to whom the      *
*   Software is furnished to do so, subject to the following conditions:       *
*                                                                              *
*   The above copyright notice and this permission notice shall be included    *
*   in all copies or substantial portions of the Software.                     *
*                                                                              *
*   THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS    *
*   OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF                 *
*   MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.     *
*   IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY       *
*   CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,       *
*   TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE          *
*   SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.                     *
*                                                                              *
*   https://opensource.org/licenses/MIT                                        *
*                                                                              *
*******************************************************************************/

namespace Asinius\HTML;


/*******************************************************************************
*                                                                              *
*   Constants                                                                  *
*                                                                              *
*******************************************************************************/

defined('UNSAFE_HTML')              or define('UNSAFE_HTML', 128);
defined('HTML_INCLUDE_TEXT_NODES')  or define('HTML_INCLUDE_TEXT_NODES', 1);
defined('HTML_NO_TIDY')             or define('HTML_NO_TIDY', 1);


/*******************************************************************************
*                                                                              *
*   \Asinius\HTML\Elements                                                     *
*                                                                              *
*******************************************************************************/

class Elements extends \ArrayIterator
{

    private $_parent_document = null;
    private $_options = 0;


    /**
     * Convert a DOMNodeList object into an array.
     *
     * @param   DOMNodeList $node_list
     * 
     * @internal
     *
     * @return  array
     */
    private function _domnodelist_to_array ($node_list)
    {
        $nodes = [];
        foreach ($node_list as $node) {
            $nodes[] = $node;
        }
        return $nodes;
    }


    /**
     * Apply a function to each element in the current set and discard the output.
     *
     * @internal
     *
     * @return  \Asinius\HTML\Elements
     */
    private function _for_all_do ()
    {
        $arguments = func_get_args();
        $function = $arguments[0];
        $n = parent::count();
        for ( $i = 0; $i < $n; $i++ ) {
            $arguments[0] = parent::offsetGet($i);
            call_user_func_array($function, $arguments);
        }
        return $this;
    }


    /**
     * Apply a function to each element in the current set and return the output
     * from each element as an array.
     *
     * @internal
     *
     * @return  mixed
     */
    private function _for_all_get ()
    {
        $n = parent::count();
        if ( $n == 0 ) {
            return null;
        }
        $return_value = [];
        $arguments = func_get_args();
        $function = $arguments[0];
        for ( $i = 0; $i < $n; $i++ ) {
            $arguments[0] = parent::offsetGet($i);
            $return_value[] = call_user_func_array($function, $arguments);
        }
        return count($return_value) == 1 ? $return_value[0] : $return_value;
    }


    /**
     * Polyfill for Javascript's substring() function. Can you tell where some
     * of this code originated? :-)
     *
     * @param   string      $string
     * @param   int         $begin
     * @param   int         $end
     * 
     * @internal
     * @deprecated
     *
     * @return  string
     */
    private function _substring ($string, $begin, $end = -1)
    {
        if ( $end == -1 ) {
            $end = strlen($string);
        }
        return substr($string, $begin, $end - $begin);
    }


    /**
     * Return the value of an attribute for a DOMNode.
     *
     * @param   DOMNode     $element
     * @param   string      $attribute_name
     * 
     * @internal
     *
     * @return  mixed
     */
    private function _get_element_attribute ($element, $attribute_name)
    {
        if ( ! is_null($attribute = $element->attributes->getNamedItem($attribute_name)) ) {
            return $attribute->value;
        }
        return null;
    }


    /**
     * Return true if an element has a particular class name, false otherwise.
     *
     * @param   DOMNode     $element
     * @param   string      $search
     * 
     * @internal
     *
     * @return  boolean
     */
    private function _element_has_class ($element, $search)
    {
        //  Match elements with multiple classes.
        if ( is_null($classes = $element->attributes->getNamedItem('class')) ) {
            return false;
        }
        $element_classes = array_unique(explode(' ', $classes->value));
        $search_classes = array_unique(explode('.', $search));
        return count(array_intersect($element_classes, $search_classes)) == count($search_classes);
    }


    /**
     * Create a new collection of HTML elements.
     *
     * @param   mixed       $elements
     * @param   DOMDocument $parent_document
     * @param   int         $options
     *
     * @throws  \RuntimeException
     *
     * @return  \Asnius\HTML\Elements
     */
    public function __construct ($elements, $parent_document = null, $options = 0)
    {
        $this->_parent_document = $parent_document;
        $this->_options = $options;
        if ( is_a($elements, 'DOMNodeList') ) {
            $elements = $this->_domnodelist_to_array($elements);
        }
        else if ( is_a($elements, 'DOMNode') ) {
            $elements = [$elements];
        }
        else if ( empty($elements) ) {
            //  Allow empty sets to be created.
            $elements = [];
        }
        else if ( ! is_array($elements) ) {
            throw new \RuntimeException('Not a DOMNode or DOMNodeList', \Asinius\EINVAL);
        }
        parent::__construct($elements);
    }


    /**
     * Return the current element in the collection.
     *
     * @return  \Asinius\HTML\Elements
     */
    public function current ()
    {
        if ( ($element = parent::current()) === false ) {
            return false;
        }
        return new Elements($element, $this->_parent_document, $this->_options);
    }


    /**
     * Get a copy of the current element collection.
     *
     * @return  \Asinius\HTML\Elements
     */
    public function getArrayCopy ()
    {
        return new Elements(parent::getArrayCopy(), $this->_parent_document, $this->_options);
    }


    /**
     * Get an element at an index in the collection.
     *
     * @param   integer     $index
     * 
     * @return  mixed
     */
    public function offsetGet ($index)
    {
        $element = parent::offsetGet($index);
        if ( is_a($element, 'DOMNode') ) {
            return new Elements($element, $this->_parent_document, $this->_options);
        }
        return null;
    }


    /**
     * Set an element at an index in the collection.
     *
     * @param   integer     $index
     * @param   DOMNode     $element
     * 
     * @return  null
     */
    public function offsetSet ($index, $element)
    {
        if ( ! is_a($element, 'DOMNode') ) {
            throw new RuntimeException('Not a DOMNode', \Asinius\EINVAL);
        }
        //  TODO. Need to figure out how to inject this element into the DOM
        //  (if it isn't already).
        throw new RuntimeException('Not implemented yet', \Asinius\ENOSYS);
    }


    /**
     * Delete an element from an index in the collection.
     *
     * @param   integer     $index
     * 
     * @return  null
     */
    public function offsetUnset ($index)
    {
        $element = parent::offsetGet($index);
        if ( is_a($element, 'DOMNOde') ) {
            $element->parentNode->removeChild($element);
        }
        parent::offsetUnset($index);
    }


    /**
     * Return the HTML for the current element collection.
     *
     * @return  string
     */
    public function __toString ()
    {
        return $this->getHTML();
    }


    /**
     * Get an element at an index in the collection.
     *
     * @param   integer     $index
     * 
     * @return  Elements
     */
    public function element ($index)
    {
        if ( $index < parent::count() && $index >= 0 ) {
            return new Elements(parent::offsetGet($index), $this->_parent_document, $this->_options);
        }
        return null;
    }


    /**
     * Return the current element collection as a basic array.
     *
     * @return  array
     */
    public function elements ()
    {
        return parent::getArrayCopy();
    }


    /**
     * Append some new content to each element in the collection.
     *
     * @param   mixed       $content
     * @param   integer     $flags
     * 
     * @return  Elements
     */
    public function append ($content, $flags = 0)
    {
        if ( is_a($content, '\Asinius\HTML\Elements') ) {
            $new_elements = $content->elements();
            $i = count($new_elements);
            while ( $i-- ) {
                $new_elements[$i] = $this->_parent_document->importNode($new_elements[$i], true);
            }
        }
        else if ( is_string($content) ) {
            if ( $flags & UNSAFE_HTML ) {
                //  Treat as html.
                $new_code = new DOMDocument();
                $new_code->loadHTML($content);
                if ( is_null($new_code->documentElement) ) {
                    throw new RuntimeException('Failed to load HTML content', EINVAL);
                }
                $new_elements = $this->_domnodelist_to_array($new_code->documentElement->firstChild->childNodes);
                $i = count($new_elements);
                while ( $i-- ) {
                    //  Each node (and its children) needs to be imported into
                    //  the current document before it can be cloned below.
                    $new_elements[$i] = $this->_parent_document->importNode($new_elements[$i], true);
                }
            }
            else {
                $new_elements = array($this->_parent_document->createTextNode($content));
            }
        }
        else {
            throw new RuntimeException('Unhandled parameter type: ' . gettype($content), \Asinius\EINVAL);
        }
        foreach ($new_elements as $new_element) {
            $this->_for_all_do(function($element, $new_element){
                $element->appendChild($new_element->cloneNode(true));
            }, $new_element);
        }
        return $this;
    }


    /**
     * Set or return the content of each element in the collection.
     *
     * @return  mixed
     */
    public function value ()
    {
        if ( func_num_args() == 0 ) {
            return $this->_for_all_get(function($element){return $element->nodeValue;});
        }
        return $this->_for_all_do(function($element, $value){$element->nodeValue = $value;}, func_get_arg(0));
    }


    /**
     * Set or return the content of each element in the collection.
     *
     * @return  mixed
     */
    public function content ()
    {
        //  Alias for value().
        //  IMPORTANT NOTE: Setting the content here automatically converts
        //  the content using htmlspecialchars().
        if ( func_num_args() == 0 ) {
            return $this->_for_all_get(function($element){return $element->nodeValue;});
        }
        return $this->_for_all_do(function($element, $value){$element->nodeValue = $value;}, func_get_arg(0));
    }


    /**
     * Return the HTML for the current element collection.
     *
     * @return  string
     */
    public function getHTML ()
    {
        $document = new DOMDocument();
        $document->formatOutput = true;
        $document->preserveWhiteSpace = false;
        $document->strictErrorChecking = false;
        $elements = parent::getArrayCopy();
        foreach ($elements as $element) {
            $document->appendChild($document->importNode($element, true));
        }
        $html_out = $document->saveHTML();
        if ( ! ($this->_options & HTML_NO_TIDY) && class_exists('tidy') ) {
            $tidy = new tidy;
            $tidy->parseString($html_out, array('indent' => true, 'output-xhtml' => false, 'wrap' => 0), 'utf8');
            $html_out = tidy_get_output($tidy);
        }
        return $html_out;
    }


    /**
     * Delete every element from the current collection (and from their document).
     *
     * @return  Elements
     */
    public function delete ()
    {
        $this->_for_all_do(function($element){
            $element->parentNode->removeChild($element);
        });
        $i = parent::count();
        while ( $i-- ) {
            parent::offsetUnset($i);
        }
        return $this;
    }


    /**
     * Return the ID attribute for each element in the collection.
     *
     * @return  mixed
     */
    public function id ()
    {
        return $this->_for_all_get(function($element){
            return $element->hasAttribute('id') ? $element->getAttribute('id') : '';
        });
    }


    /**
     * Return the tag name for each element in the collection.
     *
     * @return  mixed
     */
    public function tag ()
    {
        return $this->_for_all_get(function($element){
            return $element->nodeType == XML_ELEMENT_NODE ? strtolower($element->tagName) : '';
        });
    }


    /**
     * Return the text content for each element in the collection.
     *
     * @return  mixed
     */
    public function text ()
    {
        return $this->_for_all_get(function($element){
            return $element->textContent;
        });
    }


    /**
     * Return a new collection of the child elements of every element in the
     * current collection.
     *
     * @param   integer     $flags
     * 
     * @return  Elements
     */
    public function children ($flags = 0)
    {
        //  Return a list of the children associated with this element.
        $children = array_merge($this->_for_all_get(function($element, $flags){
            $child_nodes = [];
            $elements = $this->_domnodelist_to_array($element->childNodes);
            $i = count($elements);
            while ( $i-- ) {
                //  For now, only add DOMElement nodes to the heap.
                //  See also http://php.net/manual/en/class.domnode.php#domnode.props.nodetype
                if ( ($elements[$i]->nodeType == XML_ELEMENT_NODE) || (($flags & HTML_INCLUDE_TEXT_NODES) && $elements[$i]->nodeType == XML_TEXT_NODE) ) {
                    $child_nodes[] = $elements[$i];
                }
            }
            return array_reverse($child_nodes);
        }, $flags));
        return new Elements($children, $this->_parent_document, $this->_options);
    }


    /**
     * Return a collection of the immediate parent element for each element in
     * the current collection.
     *
     * @param   integer     $flags
     * 
     * @return  Elements
     */
    public function parent ($flags = 0)
    {
        //  Return a list of the immediate parent element(s) for these elements.
        $parent_nodes = $this->_for_all_get(function($element, $flags){
            $element = $_element->parentNode;
            while ( ! is_null($element) && $element->nodeType != XML_ELEMENT_NODE ) {
                $element = $element->parentNode;
            }
            return $element;
        }, $flags);
        //  The parent nodes need to be deduplicated into a list of common ancestors.
        $parents = array();
        foreach ($parent_nodes as $parent_node) {
            if ( is_null($parent_node) ) {
                continue;
            }
            foreach ($parents as $parent) {
                if ( $parent_node->isSameNode($parent) ) {
                    continue 2;
                }
            }
            $parents[] = $parent_node;
        }
        return new Elements($parents, $this->_parent_document, $this->_options);
    }


    /**
     * Set or return the value of an attribute for each element in the collection.
     *
     * @return  mixed
     */
    public function attribute ()
    {
        if ( func_num_args() == 1 ) {
            return $this->_for_all_get(function($element, $attribute_name){
                $value = $this->_get_element_attribute($element, $attribute_name);
                if ( is_null($value) ) {
                    return false;
                }
                return $value;
            }, func_get_arg(0));
        }
        return $this->_for_all_do(function($element, $attribute_name, $value){
            $element->setAttribute($attribute_name, $value);
        }, func_get_arg(0), func_get_arg(1));
    }


    /**
     * Delete an attribute from each element in the collection.
     *
     * @param   string      $attribute_name
     * 
     * @return  Elements
     */
    public function delete_attribute ($attribute_name)
    {
        return $this->_for_all_do(function($element, $attribute_name){
            $element->removeAttribute($attribute_name);
        }, $attribute_name);
    }


    /**
     * Set or return the "class" attribute for each element in the collection.
     *
     * @return  mixed
     */
    public function classname ()
    {
        if ( func_num_args() == 0 ) {
            return $this->_for_all_get(function($element){
                $classes = $this->_get_element_attribute($element, 'class');
                return is_null($classes) ? '' : $classes;
            });
        }
        return $this->_for_all_do(function($element, $classname){
            $element->setAttribute('class', $classname);
        }, func_get_arg(0));
    }


    /**
     * Set or return the "class" attribute for each element in the collection,
     * parsed as an array of classes.
     *
     * @return  mixed
     */
    public function classnames ()
    {
        //  Returns this element's class names as an array.
        if ( func_num_args() == 0 ) {
            return $this->_for_all_get(function($element){
                $classes = $this->_get_element_attribute($element, 'class');
                return empty($classes) ? [] : explode(' ', $classes);
            });
        }
        return $this->_for_all_do(function($element, $classes){
            $element->setAttribute('class', implode(' ', $classes));
        }, func_get_arg(0));
    }


    /**
     * Add a class name to the "class" attribute for each element in the collection.
     *
     * @param   string      $class
     * 
     * @return  Elements
     */
    public function add_class ($class)
    {
        if ( is_string($class) ) {
            $class = explode(' ', $class);
        }
        return $this->_for_all_do(function($element, $class){
            $classes = $this->_get_element_attribute($element, 'class');
            if ( empty($classes) ) {
                $classes = '';
            }
            $classes = implode(' ', array_unique(array_merge(explode(' ', $classes), $class)));
            $element->setAttribute('class', $classes);
        }, func_get_arg(0));
    }


    /**
     * Return a subset of the current elements or their descendants matching a
     * CSS-style selector.
     *
     * @param   string      $selector
     * @param   boolean     $match_selector
     * 
     * @return  Elements
     */
    public function select ($selector, $match_selector = false)
    {
        //  Start at the top of the document by default, otherwise search for elements that are
        //  children of the second optional parameter.
        $_elements = parent::getArrayCopy();
        $_id = '';
        $_class = '';
        $_tag = '';
        $_matches = 0;
        $_attrs = array();
        $x = 0;
        $i = 0;
        $selector .= ' ';
        while ( $i < strlen($selector) ) {
            switch ($selector[$i]) {
                case '#':
                    $_id = $this->_substring($selector, ++$i, $i = strpos($selector, ' ', $i));
                    $i--;
                    break;
                case '.':
                    $_class = $this->_substring($selector, ++$i, $i = strpos($selector, ' ', $i));
                    $i--;
                    break;
                case '[':
                    //  Attribute selector.
                    $x = strpos($selector, ']', $i);
                    if ( $x === false ) {
                        $i = strpos($selector, ' ', $i) - 1;
                    }
                    else {
                        $attribute = $this->_substring($selector, ++$i, $i = $x);
                        $attribute_value = '';
                        $modifier = '';
                        //  See if the attribute selector specifies a value.
                        $x = strpos($attribute, '=');
                        if ( $x !== false && $x > 0 ) {
                            $attribute_value = $this->_substring($attribute, $x+1);
                            $attribute = $this->_substring($attribute, 0, $x);
                            //  Should now have a key-value pair.
                            //  The last character of xAttr may be "|" or "~" which modify the way that attributes match.
                            switch ($attribute[strlen($attribute)-1]) {
                                case '|':case'~':
                                    $modifier = $attribute[strlen($attribute)-1];
                            }
                        }
                        else if ( $x === 0 ) {
                            //  Selector looks like: "tag[=..". Throw it out.
                            break;
                        }
                        //  Trim quotes.
                        if ( $attribute[0] == '"' && $attribute[strlen($attribute)-1] == '"' ) {
                            $attribute = $this->_substring($attribute, 1, strlen($attribute)-1);
                        }
                        if ( $attribute_value != '' && $attribute_value[0] == '"' && $attribute_value[strlen($attribute_value)-1] == '"' ) {
                            $attribute_value = $this->_substring($attribute_value, 1, strlen($attribute_value)-1);
                        }
                        if ( strlen($attribute) > 0 ) {
                            //  The selection code later on will look for a modifier at the beginning of $attribute_value.
                            if ( $modifier != '' ) {
                                $attribute_value = $modifier . $attribute_value;
                            }
                            $_attrs[$attribute] = $attribute_value;
                        }
                    }
                    break;
                case ' ':
                    //  Build a heap of elements either by the supplied ID (preferred) or by the tag name (or all child elements).
                    $_tag = strtolower($_tag);
                    if ( $match_selector ) {
                        $_heap = $_elements;
                    }
                    else {
                        $_heap = array();
                        foreach ($_elements as $_element) {
                            if ( is_null($_element) ) {
                                continue;
                            }
                            $_heap = array_merge($_heap, $this->_domnodelist_to_array($_element->getElementsByTagName(empty($_tag)?'*':$_tag)));
                        }
                    }
                    //  Now check each element in $_heap against the supplied parameters.
                    //  Each element must have every attribute checked, unfortunately,
                    //  due to the $match_selector optional parameter.
                    //  Checks are ordered from most efficient to least efficient.
                    $_elements = array();
                    foreach ($_heap as $_element) {
                        //  Check tag name.
                        if ( empty($_tag) || (strtolower($_element->tagName) == $_tag) ) {
                            //  Check element id.
                            if ( empty($_id) || $this->_get_element_attribute($_element, 'id') == $_id ) {
                                //  Check element class.
                                if ( empty($_class) || $this->_element_has_class($_element, $_class) ) {
                                    //  Finally, check other element attributes.
                                    $have_match = true;
                                    foreach ($_attrs as $attribute_name => $search_value) {
                                        //  If the element doesn't have this attribute at all, then skip it.
                                        $target_value = $this->_get_element_attribute($_element, $attribute_name);
                                        if ( is_null($target_value) ) {
                                            $have_match = false;
                                            break;
                                        }
                                        //  Now check to see if the attribute specifies a value.
                                        if ( ! empty($search_value) ) {
                                            //  Attribute specifies a value which must be matched to the element's attribute.
                                            if ( $search_value[0] == '~' ) {
                                                //  TODO
                                            }
                                            else if ( $search_value[0] == '|' ) {
                                                //  TODO
                                            }
                                            else if ( $target_value != $search_value ) {
                                                //  Exact match required.
                                                $have_match = false;
                                                break;
                                            }
                                        }
                                    }
                                    if ( $have_match ) {
                                        $_elements[] = $_element;
                                    }
                                }
                            }
                        }
                    }
                    $_id = ''; $_class = ''; $_tag = ''; $_attrs = array();
                    break;
                default:
                    $_tag .= $selector[$i];
                    break;
            }
            $i++;
        }
        //  Replace the elements in the result array with our element class to support some
        //  basic DOM functions.
        return new Elements($_elements, $this->_parent_document, $this->_options);
    }

}
