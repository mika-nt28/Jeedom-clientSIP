<?php
require_once  dirname(__FILE__) .'/CSeqHeader.php';
require_once  dirname(__FILE__) .'/CallIdHeader.php';
require_once  dirname(__FILE__) .'/ContactHeader.php';
require_once  dirname(__FILE__) .'/ContactValue.php';
require_once  dirname(__FILE__) .'/MultiValueHeader.php';
require_once  dirname(__FILE__) .'/MultiValueWithParamsHeader.php';
require_once  dirname(__FILE__) .'/NameAddrHeader.php';
require_once  dirname(__FILE__) .'/FromHeader.php';
require_once  dirname(__FILE__) .'/RAckHeader.php';
require_once  dirname(__FILE__) .'/ScalarHeader.php';
require_once  dirname(__FILE__) .'/MaxForwardsHeader.php';
require_once  dirname(__FILE__) .'/SingleValueWithParamsHeader.php';
require_once  dirname(__FILE__) .'/ValueWithParams.php';
require_once  dirname(__FILE__) .'/ViaHeader.php';
require_once  dirname(__FILE__) .'/ViaValue.php';
require_once  dirname(__FILE__) .'/wwwAuthHeader.php';
require_once  dirname(__FILE__) .'/ProxyAuthHeader.php';
require_once  dirname(__FILE__) .'/AuthValue.php';
/**
* Header Class
*/
class Header
{
    /** @var list<string> Generic header field values */
    public $values = [];

    final public function __construct() {}

    /**
     * Generic header value parser
     *
     * @param list<string> $hbody Header body
     * @return Header
     */
    public static function parse(array $hbody): Header
    {
        $ret = new static;

        foreach ($hbody as $hline) {
            $ret->values[] = trim($hline);
        }

        return $ret;
    }

    /**
     * Generic header value renderer
     *
     * @param string $hname Header field name
     * @return string
     */
    public function render(string $hname): string
    {
        $ret = '';

        foreach ($this->values as $value) {
            $ret .= "{$hname}: {$value}\r\n";
        }

        return $ret;
    }
}
