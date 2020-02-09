<?php

/*******************************************************************************
*                                                                              *
*   Asinius\HTML\Document                                                      *
*                                                                              *
*   Static class component that provides some convenience functions for        *
*   loading and working with html documents.                                   *
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
*   Initialization.                                                            *
*                                                                              *
*******************************************************************************/

libxml_use_internal_errors(true);


/*******************************************************************************
*                                                                              *
*   \Asinius\HTML\Document                                                     *
*                                                                              *
*******************************************************************************/

class Document extends \Asinius\HTML\Elements
{

    private $_document = null;

    /**
     * Create a new HTML Document. A blank document is created by default.
     * If $content is a string, the document will attempt to parse the contents
     *   of the string as html.
     * If $content is a URL, the document will attempt to open() the URL and
     *   parse the response.
     * If $content is a Datastream, the document will attempt to read the entire
     *   Datastream and parse the output as html.
     * If $content is a file pointer resource, the document will attempt to
     *   read the entire file descriptor and parse it as html.
     * If it's anything else and not null, it will throw() an error.
     * 
     * @param   mixed       $content
     */
    public function __construct ($content = null, $options = 0)
    {
        $this->_document = new \DOMDocument();
        $this->_document->formatOutput        = true;
        $this->_document->preserveWhiteSpace  = false;
        $this->_document->strictErrorChecking = false;
        if ( ! is_null($content) ) {
            if ( is_object($content) ) {
                if ( is_a($content, '\Asinius\URL') ) {
                    //  This is a little weird, okay, but this allows \Asinius\URL
                    //  to look up the correct handler for this URL and ask it to
                    //  open a Datastream.
                    $content = \Asinius\URL::open("$content");
                }
                if ( is_a($content, '\Asinius\Datastream') ) {
                    $html = '';
                    while ( ! is_null($chunk = $content->read()) ) {
                        $html .= $chunk;
                    }
                }
                else {
                    throw new \RuntimeException("Can't process this input object: " . get_class($content), \Asinius\EINVAL);
                }
            }
            else if ( is_resource($content) ) {
                $html = '';
                while ( ($chunk = fread($content, 8192)) !== false ) {
                    $html .= $chunk;
                }
            }
            else if ( is_string($content) ) {
                $html = $content;
            }
            else {
                throw new \RuntimeException("Can't process this kind of input: " . gettype($content), \Asinius\EINVAL);
            }
            $this->_document->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            parent::__construct($dom_document->documentElement, $this->_document, $options);
        }
    }
}
