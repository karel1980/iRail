<?php
/**
 * Description of JSONLiveboardOutput
 *
 * @author pieterc
 */
include_once("LiveboardOutput.php");
class JSONLiveboardOutput extends LiveboardOutput {
    private $liveboard;

    function __construct($l) {
        $this -> liveboard = $l;
    }

    public function printAll() {
        date_default_timezone_set("UTC");
        header("Content-Type: application/json;charset=UTF-8");
        $xml = parent::buildXML($this->liveboard);
        $callback = isset($_GET['callback']) && ctype_alnum($_GET['callback']) ? $_GET['callback'] : false;
        //yes this may cause some overhead, but it's the easiest way to implement this for now.
        echo ($callback ? $callback . '(' : '') . json_encode(new SimpleXMLElement($xml->saveXML(), LIBXML_NOCDATA)) . ($callback ? ')' : '');
    }
}
?>
