<?php
include 'CSeqHeader.php';
include 'CallIdHeader.php';
include 'ContactHeader.php';
include 'ContactValue.php';
include 'FromHeader.php';
include 'MaxForwardsHeader.php';
include 'MultiValueHeader.php';
include 'MultiValueWithParamsHeader.php';
include 'NameAddrHeader.php';
include 'RAckHeader.php';
include 'ScalarHeader.php';
include 'SingleValueWithParamsHeader.php';
include 'ValueWithParams.php';
include 'ViaHeader.php';
include 'ViaValue.php';
/**
* Header Class
*/
class Header
{
    /** @var list<string> Generic header field values */
    public array $values = [];

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
