<?php

/* pdf.php
 *
 * Top level PDF functionality.
 *
 * pdf.php is called like:
 *
 * http://www.example.com/pdf.php[/name][/params][.pdf][?p=1&p=2]
 *
 * (items in square brackets are optional)
 *
 * The ".pdf" after the params is optional and will, if it exists, be stripped
 * out of the parameters.
 *
 * Upon being called like so, page.php will attempt to load the file "name.php"
 * in the pdf directory. If found, it will include "name.php" and instansiate
 * the class named "name".
 *
 * Should no suitable file or class be found, or the name is omitted, the file
 * "fallback.php" will be loaded (this *must* exist) instead. This class will
 * be used as if it were the class for the named pdf. In this case, the params
 * as passed to "display()" will be all path components specified in the
 * URL.
 *
 * This class must be of a suitable base class.
 *
 * The "display()" method of the selected class will be called with the
 * params as specified in the URL as an array as it's only parameter.
 *
 * If the PDF is successfully generated, this method will return true. If there
 * is no data, it will return null, and if there is an error, it will return
 * FALSE.
 * 
 * The "getName()" method of the selected class will then be called with no
 * parameters.
 *
 * This method will either return a string naming the PDF. (e.g. for a PDF
 * called "example.pdf", this method will return "example") FALSE if there
 * is an error or null if there is no data.
 *
 * If "display()" or "getName()" returns false, then a 500 class error page
 * will be generated with the string returned from "getMessage()". If either of
 * them return null, then a 404 page will be generated with the string from
 * "getMessage()". Otherwise, the "Output()" method will be called on the class
 * to get the PDF data.
 */

session_cache_limiter("private_no_expire");

require_once("pdf/lib/pdfBase.php");
require_once("lib/util.php");

$items = array();
$extension = null;

if (isset($_SERVER["PATH_INFO"]) && $_SERVER["PATH_INFO"] != "") {
    $path = preg_replace("/^\//", "", $_SERVER["PATH_INFO"]);

    if (preg_match("/^(.*)\.(.*?)$/", $path, $regs)) {
        $extension = $regs[2];

        $path = $regs[1];
    }

    $items = explode("/", $path);
}

switch (strtolower($extension)) {
    case "pdf":
    default:
        require_once("lib/libPDF.php");
        $docclass = new libPDF();
}

// FIXME: HACK: This is to support the hacked up RTF code in DPCCapTool
/*if ($_SERVER["HTTP_USER_AGENT"] == "contype") {
        header("Content-Type: ".$docclass->getMimeType());

        exit;
}*/

$args = array();
for ($i = 1; $i < count($items); $i++) {
    $args[] = $items[$i];
}

$class = null;
$cls = null;

if (count($items) > 0) {
    $class = current($items);
}

if ($class != null && file_which("pdf/".$class.".php")) {
    require_once("pdf/".$class.".php");

    if (class_exists($class)) {
        $cls = new $class($docclass);

        if (!is_subclass_of($cls, "pdfBase")) {
            $cls = null;
        }
    }
}

if ($cls == null) {
    require_once("pdf/fallback.php");

    $cls = new fallback($class, $docclass);

    if (!is_subclass_of($cls, "pdfBase")) {
        $cls = null;
    }

    $args = $items;
}

$etag = $cls->getETag($args);

if ($etag != null) {
    header("ETag: \"".$etag."\"");
}

// FIXME: HACK: This is to support the hacked up RTF code in DPCCapTool
if ($_SERVER["HTTP_USER_AGENT"] == "contype") {
    header("Content-Type: ".$cls->getMimeType());
    exit;
}

$ret = $cls->display($args);

if ($ret != null && $ret != false) {
    $name = $cls->getName();

    if (!$name) {
        $ret = $name;
    }
}

if ($ret === null) {
    $content = "<html><head><title>PDF Page</title></head><body><h1>PDF Not Found</h1><h2>Error Message:</h2><p>".$cls->getMessage()."</p></body></html>";

    header("HTTP/1.1 404 Page Not Found");
} elseif ($ret === FALSE) {
    $content = "<html><head><title>PDF Generation Failed</title></head><body><h1>PDF Generation Failed</h1><h2>Error Message:</h2><p>".$cls->getMessage()."</p></body></html>";

    header("HTTP/1.1 500 Server Error");
} else {
    // FIXME: HACK: This is to support the hacked up RTF code in DPCCapTool
    header("Content-Type: ".$cls->getMimeType());
    //header("Content-Type: ".$docclass->getMimeType());

    $content = $cls->getContent();
    //$content = $docclass->getContent();

    header("Content-Disposition: inline; filename=\"".$name.".".$cls->getExtension()."\";");
    //header("Content-Disposition: inline; filename=".$name.".".$docclass->getExtension().";");
    header("Content-Length: ".strlen($content));
}

print $content;
