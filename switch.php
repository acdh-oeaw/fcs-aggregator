<?php
  /**
   * Switch script that queries upstream resources and transforms their response if requested
   * <br/>
   * Uses the file named in $fcsConfig if it exists to get $configName.<br/>
   * <br/>
   * Parameters for operation "explain"<br/>
   * <pre>
   *      Params - taken from {@link http://www.loc.gov/standards/sru/specs/explain.html}
   *      Name              type        Description
   *      operation         Mandatory   The string: 'explain'.
   *      version           Mandatory   The version of the request, and a statement by the client
   *                                    that it wants the response to be less than, or preferably
   *                                    equal to, that version. See Versions.
   *      recordPacking     Optional    A string to determine how the explain record should be
   *                                    escaped in the response. Defined values are 'string' and
   *                                    'xml'. The default is 'xml'. See Records.
   *      stylesheet        Optional    A URL for a stylesheet. The client requests that the server
   *                                    simply return this URL in the response. See Stylesheets.
   *      extraRequestData  Optional    Provides additional information for the server to process.
   *                                    See Extensions.
   * </pre>
   * Parameters for operation "scan"<br/>
   * <pre>
   *      Params - taken from {@link http://www.loc.gov/standards/sru/specs/scan.html}
   *      Name              type        Description
   *      operation         mandatory   The string: 'scan'.
   *      version           mandatory   The version of the request, and a statement by the client that
   *                                    it wants the response to be less than, or preferably equal to,
   *                                    that version. See Versions.
   *      scanClause        mandatory   The index to be browsed and the start point within it,
   *                                    expressed as a complete index, relation, term  clause in CQL.
   *                                    See CQL.
   *      responsePosition  optional    The position within the list of terms returned where the
   *                                    client would like the start term to occur. If the position
   *                                    given is 0, then the term should be immediately before the
   *                                    first term in the response. If the position given is 1, then
   *                                    the term should be first in the list, and so forth up to the
   *                                    number of terms requested plus 1, meaning that the term should
   *                                    be immediately after the last term in the response, even if
   *                                    the number of terms returned is less than the number requested.
   *                                    The range of values is 0 to the number of terms requested plus
   *                                    1. The default value is 1.
   *      maximumTerms      optional    The number of terms which the client requests be returned. The
   *                                    actual number returned may be less than this, for example if
   *                                    the end of the term list is reached, but may not be more. The
   *                                    explain record for the database may indicate the maximum
   *                                    number of terms which the server will return at once. All
   *                                    positive integers are valid for this parameter. If not
   *                                    specified, the default is server determined.
   *      stylesheet        optional    A URL for a stylesheet. The client requests that the server
   *                                    simply return this URL in the response. See Stylesheets.
   *      extraRequestData  optional    Provides additional information for the server to process. See
   *                                    Extensions.
   * </pre>
   * Parameters for operation "searchRetrieve"<br/>
   * <pre>
   *      Params - taken from {@link http://www.loc.gov/standards/sru/specs/search-retrieve.html}
   *      Name              type        Description
   *      operation         mandatory   The string: 'searchRetrieve'.
   *      version           mandatory   The version of the request, and a statement by the client that
   *                                    it wants the response to be less than, or preferably equal to,
   *                                    that version. See Version.
   *      query             mandatory   Contains a query expressed in CQL to be processed by the
   *                                    server. See CQL.
   *      startRecord       optional    The position within the sequence of matched records of the
   *                                    first record to be returned. The first position in the
   *                                    sequence is 1. The value supplied MUST be greater than 0. The
   *                                    default value if not supplied is 1.
   *      maximumRecords    optional    The number of records requested to be returned. The value must
   *                                    be 0 or greater. Default value if not supplied is determined
   *                                    by the server. The server MAY return less than this number of
   *                                    records, for example if there are fewer matching records than
   *                                    requested, but MUST NOT return more than this number of records.
   *      recordPacking     optional    A string to determine how the record should be escaped in the
   *                                    response. Defined values are 'string' and 'xml'. The default is
   *                                    'xml'. See Records.
   *      recordSchema      optional    The schema in which the records MUST be returned. The value is
   *                                    the URI identifier for the schema or the short name for it
   *                                    published by the server. The default value if not supplied is
   *                                    determined by the server. See Record Schemas.
   *      resultSetTTL      optional    The number of seconds for which the client requests that the
   *                                    result set created should be maintained. The server MAY choose
   *                                    not to fulfil this request, and may respond with a different
   *                                    number of seconds. If resultSetTTL is not supplied then the
   *                                    server will determine the value. See Result Sets.
   *      stylesheet        optional    A URL for a stylesheet. The client requests that the server
   *                                    simply return this URL in the response. See Stylesheets.
   *      extraRequestData  optional    Provides additional information for the server to process. See
   *                                    Extensions.
   * </pre>
   * 
   * @uses HandleXFormatCases()
   * @uses \ACDH\FCSSRU\diagnostics()
   * @uses GetDefaultStyles()
   * @uses $configName
   * @uses $fcsConfig
   * @uses \ACDH\FCSSRU\$sru_fcs_params
   * @package fcs-aggregator
   */

namespace ACDH\FCSSRU\switchAggregator;

\mb_internal_encoding('UTF-8');
\mb_http_output('UTF-8');
\mb_http_input('UTF-8');
\mb_regex_encoding('UTF-8'); 

include_once __DIR__ . '/../utils-php/EpiCurl.php';
include_once __DIR__ . '/../utils-php/IndentDomDocument.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use jmathai\phpMultiCurl\EpiCurl,
    ACDH\FCSSRU\IndentDomDocument,
    ACDH\FCSSRU\ErrorOrWarningException;

  /**
   * Determines how parameters are checked
   * 
   * If $sruMode is set to "strict" all mandatory params get checked and
   * an error message is returned if one is missing (eg. version param
   * not provided). If it's set to anything else eg. "loose" the script
   * just tries to fulfill the request on a best effort bases.
   * @global string $sruMode 
   */
  $sruMode = "strict";

  /**
   * If there is the $fcsConfig file is loaded.
   * 
   * If this file is loaded the $configName cache
   * variable is available.
   * @global bool $fcsConfigFound
   */
  $fcsConfigFound = false;

  /**
   * Load the common config file
   */
  include_once __DIR__ . '/../utils-php/common.php';
  
  use clausvb\vlib\vlibTemplate;

  /**
   * Array (a map) containing default xsl style sheets
   * 
   * The keys for the map are searchRetrieve, scan, explain and default.
   * @global array $globalStyles
   */
  $globalStyles = array ("searchRetrieve" => "", "scan" => "", "explain" => "", "default" => "");

  //import fcs configuration cache
  if (isset($fcsConfig) && file_exists($fcsConfig))
  {
    include $fcsConfig;
    $fcsConfigFound = true;
  }
class FCSSwitch {
  /**
   * Get the node value of the first occurrence of $tagName
   * in the children of $node
   * @param DOMNode $node The Node at which the search is started.
   * @param string $tagName The tag to search for.
   * @return string The value of the tag if found else the empty string "" is returned.
   */
  protected function GetNodeValue($node, $tagName)
  {
     if ($node === null) {
         return "";
     }
     $list = $node->getElementsByTagName($tagName);
     if ($list->length != 0)
     {
       $value = $list->item(0);
       return $value->nodeValue;
     }
     return "";
  }
  
  /**
   * Get the node value of the first occurrence of a $tagName that has an attribute $attr with the value $attrValue
   * 
   * processes the descendants of $node only
   * @param DOMNode $node The Node at which the search is started.
   * @param string $tagName The tag to search for.
   * @param string $attr The attribute to search for.
   * @param string $attrValue The attribure value to search for.
   * @return string Returns the node value of the first fitting tag with $tagName with $attr which has $attrValue
   */
  protected function GetNodeValueWithAttribute($node, $tagName, $attr, $attrValue)
  {
     $list = $node->getElementsByTagName($tagName);
     $idx = 0;

     if ($list->length != 0)
     {
       while ($idx < $list->length)
       {
         $value = $list->item($idx);

         if ($value->hasAttributes())
         {
           $attribute = $value->attributes->getNamedItem($attr)->nodeValue;
           if ($attribute == $attrValue)
             return $value->nodeValue;
         }
         $idx++;
       }
     }
     return "";
  }

  /**
   * Construct format names to be attached to operation to look up a stylesheet
   * @uses $xformat 
   */
  protected function GetFormatId() {
    global $sru_fcs_params;

    if (stripos($sru_fcs_params->xformat, 'html') !== false) {
        return "";
    } else if (stripos($sru_fcs_params->xformat, 'csv-') !== false) {
        return "-csv";
    } else {
        return "-" . $sru_fcs_params->xformat;
    }
  }
  
  /**
   * Get default xsl style sheets
   * 
   * Loads $switchConfig as an XML DOM, uses XPath to fetch styles elements and
   * fills the settings into the $globalStyles array.
   * @uses $switchConfig
   * @uses $globalStyles
   * @uses $xformat
   */
  protected function GetDefaultStyles()
  {
    global $switchConfig;
    global $globalStyles;

    $doc = new \DOMDocument;
    $doc->Load($switchConfig);

    $xpath = new \DOMXPath($doc);
    $query = '//styles';
    $entries = $xpath->query($query);

    $format = $this->GetFormatId();
    
    foreach ($entries as $entry)
    {
      $keys = array_keys($globalStyles);
      foreach ($keys as $key)
      {
        $key_with_format = $key . $format;
        $hstr = $this->GetNodeValueWithAttribute($entry, "style", "operation", $key_with_format);
        if ($hstr != "")
          $globalStyles[$key_with_format] = $hstr;
      }
    }
  }

  /**
   * Opens $switchConfig and searches for an element item with an element name value that
   * equals $context - returns configuration infos for the found node in
   * an array.
   * If context is not found in switch.config $configName is used to look up the information
   * if it exists.
   * @uses $switchConfig
   * @uses $fcsConfigFound
   * @uses $configName
   * @param string $context An internal ID for a resource.
   * @return array|false An array (a map) containing "name" (the internal id), "type" (of the resource), "uri" and "style" (the stylesheet to use with the resource).
   */
  protected function GetConfig($context)
  {
    global $sru_fcs_params;
    global $switchConfig;
    $doc = new \DOMDocument;
    $doc->Load($switchConfig);

    $xpath = new \DOMXPath($doc);
    /* Note that this ignores multiple configurations for the same name ans uses the first occurance! */
    $entry = $xpath->query("//item[name='$context']")->item(0);
    $name = $this->GetNodeValue($entry, "name");
    if ($name == $context) {
        $type = $this->GetNodeValue($entry, "type");
        $uri = $this->GetNodeValue($entry, "uri");
        $ret = array("name" => $name, "type" => $type, "uri" => $uri);
        $styles = $xpath->query("//item[name='$context']/style");
        $key_with_format = $sru_fcs_params->operation . $this->GetFormatId();
        foreach ($styles as $style) {
            $styleSheet = $this->GetNodeValueWithAttribute($entry, "style", "operation", $key_with_format);
            if ($styleSheet !== "") {
                $ret[$key_with_format] = $styleSheet;
            }
            if ($style->hasAttributes() === false and $style->nodeValue !== "") {
                $ret["style"] = $style->nodeValue;
            }
        }
        return $ret;
    }

    global $fcsConfigFound;

    if ($fcsConfigFound)
    {
      global $configName;

      if (array_key_exists($context, $configName))
      {
        $conf = $configName[$context];
        $ret = array("name" => $conf["name"], "type" => $conf["type"], "uri" => $conf["endPoint"]);
        if (array_key_exists("style", $conf)) {
            $ret["style"] = $conf["style"];
        }
        return $ret;
      }
    }

    return false;
  }

  /**
   * Return all known resources
   * 
   * Returns every entry from switch.config and also all
   * entries from fcs.resource.config.php.
   * @uses $switchConfig
   * @uses $fcsConfigFound
   * @uses $configName
   * @return array An array that contains for every resource the "name" (the internal id) and the "label" (the human intelligable name).
   */
  protected function GetCompleteConfig()
  {
    global $switchConfig;
    global $fcsConfigFound;

    $configArray = array();

    //open $switchConfig (switch.config)
    $doc = new \DOMDocument;
    $doc->Load($switchConfig);

    //pick all item tags
    $xpath = new \DOMXPath($doc);
    $query = '//item';
    $entries = $xpath->query($query);

    //iterate through all item tags
    foreach ($entries as $entry)
    {
       //add name and label to $configArray
       $name = $this->GetNodeValue($entry, "name");
       $label = $this->GetNodeValue($entry, "label");
       $type = $this->GetNodeValue($entry, "type");
       array_push($configArray, array("name" => $name, "label" => $label));

       if (($type == "fcs.resource") && ($fcsConfigFound))
       {
         global $configName;
         foreach ($configName as $conf)
         {
           $subName = $conf["name"];

           $pos = strpos($subName, $name);
           if (($pos !== false) && ($pos == 0))
             //add name and displaytext to $configArray
             array_push($configArray, array("name" => $subName, "label" => $conf["displayText"]));
         }
       }
    }

    return $configArray;
  }

  /**
   * Analog to file_exists() this protected function tests if a given $url does exist
   * @param string $url A URL to be testes.
   * @return bool If $url is reachable or not.
   */
  protected function url_exists($url)
  {
      $xContext = array();
      preg_match('/x-context=[^&]+/', $url, $xContext);
      $url = preg_replace('/\?.*$/', '', $url);
      $urlWithXContext = count($xContext) === 0 ? $url : "$url?$xContext[0]";
      $handle = @fopen($urlWithXContext,'r');
      return ($handle !== false);
  }

  /**
   * Fills the $scanCollectionsTemplate with all configured endpoints
   * 
   * Returns the expanded template to the client.
   * @uses $scanCollectionsTemplate
   * @uses $vlibPath
   * @param string $version A version that is inserted into the template.
   */
  protected function GetScan($version)
  {
    global $scanCollectionsTemplate;
    global $vlibPath;

    require_once $vlibPath;

    //instantiate template engine with $scanCollectionsTemplate
    ErrorOrWarningException::$code_has_known_errors = true;
    $tmpl = new vlibTemplate($scanCollectionsTemplate);
    ErrorOrWarningException::$code_has_known_errors = false;

    $tmpl->setvar('version', $version);

    //get all configured endpoints
    $configArray = $this->GetCompleteConfig();

    //fill array for template loop
    $collection = array();
    foreach ($configArray as $item)
    {
      array_push($collection, array(
          'name' => $item['name'],
          'label' => $item['label'],
          'position' => count($collection) + 1));
    }
    
    $tmpl->setVar('count', count($configArray));
    $tmpl->setVar('maximumTerms', count($configArray));
    $tmpl->setVar('responsePosition', 1);
    $tmpl->setloop('collection', $collection);
    //generate xml from template and return it
    ErrorOrWarningException::$code_has_known_errors = true;
    $ret = $tmpl->grab();
    ErrorOrWarningException::$code_has_known_errors = false;
    return $ret;
  }

  /**
   * Reads the $explainSwitchTemplate and returns it
   * 
   * content-type text/xml and charset UTF-8 are set for the answer.
   * @uses $vlibPath
   * @uses $localhost
   * @uses $explainSwitchTemplate
   * @uses $explainSwitchXmlInfoSnippet
   * @return string An XML description of this service.
   */
  protected function GetExplain()
  {
    global $explainSwitchTemplate;
    global $explainSwitchXmlInfoSnippet;
    global $localhost;
    global $vlibPath;

    require_once $vlibPath;

    //instantiate template engine with $scanCollectionsTemplate
    ErrorOrWarningException::$code_has_known_errors = true;
    $tmpl = new vlibTemplate($explainSwitchTemplate);
    ErrorOrWarningException::$code_has_known_errors = false;
    
    $tmpl->setVar('hostid', $localhost);
    $tmpl->setVar('xmlinfosnippet', $explainSwitchXmlInfoSnippet);
    
    ErrorOrWarningException::$code_has_known_errors = true;
    $ret = $tmpl->grab();
    ErrorOrWarningException::$code_has_known_errors = false;
    return $ret;
  }

  /**
   * To speed up local queries the local servername (contained in $localhost) is replaced
   * by the term "127.0.0.1"
   * @uses $localhost
   * @param string $url A URL as input.
   * @return string The input URL with the hostname set to 127.0.0.1 if it matches $localhost.
   */
  protected function ReplaceLocalHost($url)
  {
    global $localhost;
    return str_replace($localhost, "127.0.0.1",  $url);
  }

  /**
   * Get the XML DOM representation of the document at the URL passed as parameter
   * 
   * @uses ReplaceLocalHost()
   * @uses url_exists()
   * @param string $url A URL from which the document should be fetched.
   * @return DOMDocument|false Returns either the XML DOM representation or false.
   */
  protected function GetDomDocument($url)
  {
    $url = $this->ReplaceLocalHost($url);

    if ($this->url_exists($url))
    {
      $xmlDoc = new \DOMDocument();
//      $oldErrorHandler = set_error_handler("\\ACDH\\FCSSRU\\switchAggregator\\exception_error_handler");
      try {
//          $ctx = stream_context_create(array('http' =>
//                array(
//                   'timeout'  => '180'
//                )));
//          $upstream = fopen($url, 'r', false, $ctx);
//          $xmlString = stream_get_contents($upstream);          
          $upstream = EpiCurl::getInstance()->addCurl(curl_init($url));
          $xmlString = $upstream->data;
          $code = $upstream->code;
          if (($code === 200) && ($xmlString !== '')) {
              try {
                $xmlDoc->loadXML($xmlString);
              } catch (\ACDH\FCSSRU\ErrorOrWarningException $e) {
                  $message = $e->getMessage() . "\n" . $xmlString;
                  throw new ErrorOrWarningException($e->getCode(),
                  $message, $e->getFile(), $e->getLine(), $e->getContext());
              }
          } else {
          $xmlDoc->load($url);         
          }
      } catch (DOMProcessingError $exc) {
//          $this->ReturnSomeFile($url, "content-type: text/plain");
          throw $exc;
      }
      restore_error_handler();

      return $xmlDoc;
    }

    return false;
  }

  /**
   * Return the XML Document specified by $url to the client
   * 
   * Sets the response header to $headerStr. If the document can't be fetched upstream
   * a diagnostic message 15 Unsupported context set is returned to the client.
   * @uses $sru_fcs_param
   * @uses url_exists()
   * @uses ReplaceLocalHost()
   * @uses \ACDH\FCSSRU\diagnostics()
   * @param string $url The URL form which the XML should be fetched.
   * @param string $xmlString An XML snippet that is to be used if there is no URL.
   */
  protected function ReturnXmlDocument($url, $xmlString = null)
  {
    global $sru_fcs_params;
    $url = $this->ReplaceLocalHost($url);
    if ($sru_fcs_params->recordPacking !== 'raw') {
        header("content-type: text/xml; charset=UTF-8");
    }
    $xml = new IndentDOMDocument();
    if (($url === "") && ($xmlString !== null)) {
        $xml->loadXML($xmlString);       
    } elseif ($this->url_exists($url)) {
        $upstream = EpiCurl::getInstance()->addCurl(curl_init($url));
        $xmlString = $upstream->data;
        try {
            $xml->loadXML($xmlString);            
        } catch (ErrorOrWarningException $exc) {
            $a = 1;
        }
    } else {
    //"Unsupported context set"
        \ACDH\FCSSRU\diagnostics(15, str_replace("&", "&amp;", $url));
        return;
    }
    if (stripos($sru_fcs_params->xformat, 'tei') !== false) {
        $xpath = new \DOMXPath($xml);
        // forcebly register xmlns:tei
        $xml->createAttributeNS('http://www.tei-c.org/ns/1.0', 'tei:create-ns');
        // forcebly register xmlns:fcs
        $xml->createAttributeNS('http://clarin.eu/fcs/1.0', 'fcs:create-ns');
        // forcebly register xmlns:zr
        $xml->createAttributeNS('http://explain.z3950.org/dtd/2.0/', 'zr:create-ns');
        $tei = $xpath->query('//fcs:DataView[@type="full"]/tei:*|//zr:description/tei:*');
        if ($tei->length === 1) {
            $newRoot = $tei->item(0);
        } else {
            $newRoot = $this->wrapInMinimalTEI($xml, $tei);
        }
        $xml->replaceChild($newRoot, $xml->childNodes->item(0));
    }
    $xml->formatOutput = true;
    $xml->setWhiteSpaceForIndentation("    ")->xmlIndent();
    $output = str_replace('xmlns:default=', 'xmlns:tei=',
              str_replace('</default:', '</tei:',
              str_replace('<default:', '<tei:', $xml->saveXML())));
    echo $output;
}

/**
 * Wrap multiple TEI result nodes in a minimal TEI document.
 * @param DOMDocument $xmlDocument
 * @param DOMNodeList $teiNodeList
 * @return DOMNode A new root Node TEI
 */
protected function wrapInMinimalTEI($xmlDocument, $teiNodeList) {
    $newRoot = $xmlDocument->createElementNs('http://www.tei-c.org/ns/1.0', 'tei:TEI');
    $teiHeader = $xmlDocument->createDocumentFragment();
    $teiHeader->appendXML("  <tei:teiHeader xmlns:tei='http://www.tei-c.org/ns/1.0'>
      <tei:fileDesc>
         <tei:titleStmt>
            <tei:title>Generated search result</tei:title>
         </tei:titleStmt>
         <tei:publicationStmt>
            <tei:p>Same as search resource</tei:p>
         </tei:publicationStmt>
         <tei:sourceDesc>
            <tei:p>Dynamically generated born digital resource</tei:p>
         </tei:sourceDesc>
      </tei:fileDesc>
  </tei:teiHeader>");
    $text = $xmlDocument->createElement('tei:text');
    $group = $xmlDocument->createElement('tei:group');
    $text->appendChild($group);
    $newRoot->appendChild($teiHeader);
    $newRoot->appendChild($text);
    foreach ($teiNodeList as $teiElement) {
        $text = $xmlDocument->createElement('tei:text');
        $body = $xmlDocument->createElement('tei:body');
        $body->appendChild($teiElement);
        $text->appendChild($body);
        $group->appendChild($text);
    }
    return $newRoot;
}

/**
   * Return a file without processing anything.
   * @todo Check if this is needed and for what purpose.
   * @param string $url
   * @param string $headerStr
   */
  protected function ReturnSomeFile($url, $headerStr) {
    global $sru_fcs_params;
    $url = $this->ReplaceLocalHost($url);

    if ($this->url_exists($url))
    {
        if ($sru_fcs_params->recordPacking !== 'raw') {
            if ($headerStr != "")
                header($headerStr);
        }
        readfile($url);
    } else
    //"Unsupported context set"
        \ACDH\FCSSRU\diagnostics(15, str_replace("&", "&amp;", $url));
}

  /**
   * Get the location of the XSL style sheet associated with $operation
   * 
   * The XSL style sheet is fetched from the $globalStyles array or $configItem for "searchRetrieve". If a style for one
   * of the operations is not set in $globalStyles the XSL style sheet for the key "default" is fetched.
   * If operation is not from the described set a diagnostic message 6 Unsupported parameter value is returned to the client.
   * @uses ReplaceLocalHost()
   * @uses $globalStyles
   * @uses $xformat
   * @uses \ACDH\FCSSRU\diagnostics()
   * @param string $operation The operation for which to get the XSL document. One of "explain", "scan" or "searchRetrieve"
   * @param array $configItem A array (a map) that has a "style" key. Used for passing a style for the "searchRetrieve" operation.
   * @return string The URL of the style sheet. If it's located on the local host the URL contains 127.0.0.1 instead of the real domain name.
   */
  protected function GetXslStyle($operation, $configItem)
  {
    global $globalStyles;
    
    $format = $this->GetFormatId();

    switch ($operation)
    {
      case "explain" :
        if (array_key_exists('explain'.$format, $configItem))
          $style = $configItem['explain'.$format];
        elseif (array_key_exists('explain'.$format, $globalStyles))
          $style = $globalStyles['explain'.$format];
        elseif (array_key_exists('default'.$format, $globalStyles))
          $style = $globalStyles['default'.$format];
        else
          $style = "";

        return $this->ReplaceLocalHost($style);
      case "scan" :
        if (array_key_exists('scan'.$format, $configItem))
          $style = $configItem['scan'.$format];
        elseif (array_key_exists('scan'.$format, $globalStyles))
          $style = $globalStyles['scan'.$format];
        elseif (array_key_exists('default'.$format, $globalStyles))
          $style = $globalStyles['default'.$format];
        else
          $style = "";

        return $this->ReplaceLocalHost($style);
      case "searchRetrieve" :
        if (array_key_exists('searchRetrieve'.$format, $configItem))
          $style = $configItem['searchRetrieve'.$format];
        else if (array_key_exists('style', $configItem))
          $style = $configItem['style'];
        else if (array_key_exists('searchRetrieve'.$format, $globalStyles))
          $style = $globalStyles['searchRetrieve'.$format];
        else if (array_key_exists('default'.$format, $globalStyles))
          $style = $globalStyles['default'.$format];
        else
          $style = "";

        return $this->ReplaceLocalHost($style);
    }
    return "FILE_NOT_FOUND";
  }

  /**
   * Get the XSL for an $operation as DOM document
   * 
   * @uses GetXslStyle()
   * @uses GetDomDocument()
   * @param string $operation The operation for which to get the XSL document. One of "explain", "scan" or "searchRetrieve"
   * @param array $configItem A array (a map) that has a "style" key. Used for passing a style for the "searchRetrieve" operation.
   * @return DOMDocument A XSL DOM representation of the XSL style sheet document.  
   */
  protected function GetXslStyleDomDocument($operation, $configItem)
  {
    if ($operation === false) {
        $operation = "explain";
    }
    $xslUrl = $this->GetXslStyle($operation, $configItem);
    $ret = $this->GetDomDocument($xslUrl);
    $ret->createAttributeNS('http://www.w3.org/1999/XSL/Transform', 'xsl:create-ns');
    $query = new \DOMXPath($ret);
    $qres = $query->query('//xsl:stylesheet');
    if (!$qres->item(0)->isSameNode($ret->documentElement)) {      
        $xslText = str_replace(array('&amp;gt;', '&amp;lt;', '&amp;amp;'),
                               array( "&gt;", "&lt;" ,"&amp;"),
                               $qres->item(0)->C14N());
        $ret = new \DOMDocument();
        $ret->loadXML($xslText);
  }
    return $ret;
  }

  /**
   * Return an upstream XML to the client after applying an XSL style sheet transfomation
   * 
   * The type of the returned document is set to text/html the encoding is set to UTF-8.
   * 
   * @uses $operation
   * @uses $xformat
   * @uses $scriptsUrl
   * @uses $xcontext
   * @uses $startRecord
   * @uses $maximumRecords
   * @uses $scanClause
   * @uses $query
   * @uses $switchUrl
   *  
   * @param DOMDocument $xmlDoc The XML input document as XML DOM representation.
   * @param DOMDocument|SimpleXMLElement $xslDoc The XSL style sheet used for the transformation.
   * @param bool $useParams If set $xformat and $scriptsUrl are passed to the XSL processor as parameters "format" and "scripts_url".
   */
  protected function ReturnXslT($xmlDoc, $xslDoc, $useParams, $useCallback = true) {
    global $sru_fcs_params;
    
    $proc = new \XSLTProcessor();
    $importOk = $proc->importStylesheet($xslDoc);
    if (!$importOk) {
        throw new XSLTProcessingError('XSL Error in ' .$xslDoc->documentURI . '!');
    }
    if ($useParams) {
        global $switchUrl;
        global $scriptsUrl;
        global $switchUser;
        global $switchPW;
        global $switchSiteLogo;
        global $switchSiteName;
        
        $sru_fcs_params->passParametersToXSLTProcessor($proc);
        $proc->setParameter('', 'scripts_url', $scriptsUrl);
        // for debugging purpose so switch doesn't call itself.
        if ($useCallback === false) {
            $proc->setParameter('', 'contexts_url', '');
        }
        $proc->setParameter('', 'base_url', $switchUrl);
        $proc->setParameter('', 'scripts_user', $switchUser);
        $proc->setParameter('', 'scripts_pw', $switchPW);
        if (isset($switchSiteLogo) && $switchSiteLogo !== '') {
            $proc->setParameter('', 'site_logo', $switchSiteLogo);
        }
        if (isset($switchSiteName) && $switchSiteName !== '') {
            $proc->setParameter('', 'site_name', $switchSiteName);
        }
        $this->xsltParmeters = $sru_fcs_params->getParameterForXSLTProcessor($proc,
                array('scripts_url', 'contexts_url', 'base_url', 'scripts_user',
                    'scripts_pw', 'site_logo', 'site_name', 'x-context'));
    }
    if ($sru_fcs_params->recordPacking !== 'raw') {
    if (stripos($sru_fcs_params->xformat, "html") !== false) {
        header("content-type: text/html; charset=UTF-8");
        header("Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0");//Dont cache
        header("Pragma: no-cache");//Dont cache
        header("Expires: Thu, 19 Nov 1981 08:52:00 GMT");//Make sure it expired in the past (this can be overkill)
    } else if ($sru_fcs_params->xformat === "json") {
        header("content-type: application/json; charset=UTF-8");
        header("Access-Control-Allow-Origin: *");
    } else if (stripos($sru_fcs_params->xformat, "csv-") !== false) {
        header("content-type: text/csv; charset=UTF-8");
        header("Access-Control-Allow-Origin: *");
        header("Content-disposition: attachment; filename=vocabulary.csv");
    }
    }
    
    ErrorOrWarningException::$code_has_known_errors = true;
        $ret = $proc->transformToXml($xmlDoc);
    ErrorOrWarningException::$code_has_known_errors = false;
    return $ret;
  }

  /**
   * Main method: called after the validity of the request is checked
   * 
   * Iterates over the contents of $context so processes multiple requests if
   * they are specified.
   * <ol>
   * <li>Loads the config for this particular resource.</li>
   * <li>Depending on key strings beeing part of $xformat
   * <ul>
   * <li>on "html": Fetches the XML upstream and applies the XSL document specified
   * for that operation in the $switchConfig</li>
   * <li>on "xsltproc": returns a diagnostic text about the XSLT processor providing EXSLT support (which is mandatory for the XSL style sheets supplied!).</li>
   * <li>on "xsl": returns the stylesheet that would be used for the requested operation.</li>
   * <li>on "img": the response from the upstream endpoint is returnes as if it were an image/jpeg.</li>
   * <li>on an unrecognizable string: the upstream result is returned verbatim and text/xml is assumed.</li>
   * </ul>
   * </li>
   * </ol>
   * @uses sru_fcs_params
   * @uses GetConfig()
   * @uses GetQueryUrl()
   * @uses ReturnXslT()
   * @uses \ACDH\FCSSRU\diagnostics()
   * @uses GetDomDocument()
   * @uses GetXslStyleDomDocument()
   * @uses ReturnXmlDocument()
   * @param string $xmlString Pass in some string that should be treated instead of fetching something.
   */
  protected function HandleXFormatCases($xmlString = null) {
    global $sru_fcs_params;

    foreach ($sru_fcs_params->context as $item) {
        if ($xmlString === null) {
            $configItem = $this->GetConfig($item);
        } else {
            $configItem = array(
                "uri" => "internal",
                "type" => "fcs.resource"
            );
        }
        if ($configItem !== false) {
            $uri = $configItem["uri"];
            $type = $configItem['type'];

            if ($xmlString === null) {
                $fileName = $sru_fcs_params->GetQueryUrl($uri, $type);
            } else {
                $fileName = null;
            }

            if (stripos($sru_fcs_params->xformat, "html") !== false ||
                    $sru_fcs_params->xformat === "json" ||
                    stripos($sru_fcs_params->xformat, "csv-") !== false) {
                if ($xmlString === null) {
                    try {
                      $xmlDoc = $this->GetDomDocument($fileName);
                    } catch (\ACDH\FCSSRU\ErrorOrWarningException $e) {
                      $xmlDoc = false;
                      $fileName .= '\n' . $e->getMessage();
                    }
                } else {
                    $xmlDoc = new \DOMDocument();
                    $xmlDoc->loadXML($xmlString);
                }
                if ($xmlDoc !== false) {
                    $xslDoc = $this->GetXslStyleDomDocument($sru_fcs_params->operation, $configItem);
                    if ($xslDoc !== false)
                        print $this->ReturnXslT($xmlDoc, $xslDoc, true);
                    else
                    //"Unsupported context set"
                        \ACDH\FCSSRU\diagnostics(15, str_replace("&", "&amp;", $this->GetXslStyle($sru_fcs_params->operation, $configItem) . ":  " . $item));
                } else
                //"Unsupported context set"
                    \ACDH\FCSSRU\diagnostics(15, str_replace("&", "&amp;", $fileName));
            }
            elseif (stripos($sru_fcs_params->xformat, "xsltproc") !== false) {
                $proc = new XSLTProcessor();
                if ($sru_fcs_params->recordPacking !== 'raw') {
                header("content-type: text/plain; charset=UTF-8");
                }
                print "XSLTPROC ";
                if ($proc->hasExsltSupport())
                    print "has Exslt support.\n";
                else
                    print "doesn't have Exslt support!\n";
            }
            elseif (stripos($sru_fcs_params->xformat, "xsl") !== false) {
                // this option is more or less only for debugging (to see the xsl used)
                $xslDoc = $this->GetXslStyleDomDocument($sru_fcs_params->operation, $configItem);
                if ($xslDoc === false) {
                    //"Unsupported context set"
                    \ACDH\FCSSRU\diagnostics(15, str_replace("&", "&amp;", $this->GetXslStyle($sru_fcs_params->operation, $configItem) . ":  " . $item));
                }
                $this->ReturnXmlDocument($style->saveXML(), "content-type: text/xml; charset=UTF-8");
            } elseif (stripos($sru_fcs_params->xformat, "img") !== false)
                $this->ReturnSomeFile($fileName, "content-type: image/jpg");
            else
                $this->ReturnXmlDocument($fileName, $xmlString);
        }
        else {
            //"Unsupported context set"
            \ACDH\FCSSRU\diagnostics(15, str_replace("&", "&amp;", $item));
        }
    }
}

public function run() {
        global $sru_fcs_params;

        //load default xsl style sheets from $switchConfig, uses $xformat
        $this->GetDefaultStyles();

        //no operation param provided ==> explain
        if ($sru_fcs_params->operation === false) {
            $this->HandleXFormatCases($this->GetExplain());
        } else {
            switch ($sru_fcs_params->operation) {
                case "explain" :
                    if ($sru_fcs_params->xcontext == "") {
                        $this->HandleXFormatCases($this->GetExplain());
                    } else {
                        $this->HandleXFormatCases();
                    }
                    break;
                case "scan" :
                    if ($sru_fcs_params->scanClause === false) {
                        //"Mandatory parameter not supplied"
                        \ACDH\FCSSRU\diagnostics(7, "scanClause");
                    } elseif ($sru_fcs_params->version === false) {
                        //"Mandatory parameter not supplied"
                        \ACDH\FCSSRU\diagnostics(7, "version");
                    } elseif ($sru_fcs_params->scanClause == "") {
                        //"Unsupported parameter value"
                        \ACDH\FCSSRU\diagnostics(6, "no scanClause specified");
                    } elseif ($sru_fcs_params->version == "") {
                        //"Unsupported parameter value"
                        \ACDH\FCSSRU\diagnostics(6, "no version specified.");
                    } elseif ($sru_fcs_params->version != "1.2") {
                        //"Unsupported version"
                        \ACDH\FCSSRU\diagnostics(5, "version: '$sru_fcs_params->version'");
                    } else {
                        if ($sru_fcs_params->xcontext === "") {
                            if ($sru_fcs_params->scanClause === "fcs.resource") {
                                //return switch scan result ==> overview
                                $this->HandleXFormatCases($this->GetScan($sru_fcs_params->version));
                            } else {
                                //"Unsupported parameter value"
                                \ACDH\FCSSRU\diagnostics(6, "scanClause: '$sru_fcs_params->scanClause'");
                            }
                        } else {
                            $this->HandleXFormatCases();
                        }
                    }

                    break;
                case "searchRetrieve" :
                    if ($sru_fcs_params->query === false) {
                        //"Mandatory parameter not supplied"
                        \ACDH\FCSSRU\diagnostics(7, "query");
                    } elseif ($sru_fcs_params->version === false) {
                        //"Mandatory parameter not supplied"
                        \ACDH\FCSSRU\diagnostics(7, "version");
                    } elseif ($sru_fcs_params->query == "") {
                        //"Unsupported parameter value"
                        \ACDH\FCSSRU\diagnostics(6, "no query specified");
                    } elseif ($sru_fcs_params->version == "") {
                        //"Unsupported parameter value"
                        \ACDH\FCSSRU\diagnostics(6, "no version specified");
                    } elseif ($sru_fcs_params->version != "1.2") {
                        //"Unsupported version"
                        \ACDH\FCSSRU\diagnostics(5, "version: '$sru_fcs_params->version'");
                    } else {
                        $this->HandleXFormatCases();
                    }
                    break;
                default:
                    //"Unsupported parameter value"
                    \ACDH\FCSSRU\diagnostics(6, "operation: '$sru_fcs_params->operation'");
                    break;
            }
        }
    }
}

class DOMProcessingError extends \ErrorException{
}

class XSLTProcessingError extends \ErrorException{
}

function exception_error_handler($errno, $errstr, $errfile, $errline ) {
    throw new DOMProcessingError($errstr, 0, $errno, $errfile, $errline);
}

if (!isset($runner)) {
// Set up the parameter object
    \ACDH\FCSSRU\getParamsAndSetUpHeader("strict");
    $s = new FCSSwitch();
    $s->run();
}