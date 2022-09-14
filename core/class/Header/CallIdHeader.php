<?php
/**
* Call-ID Header Class
*/
class CallIdHeader
{
    /** @var string Call-ID value */
    public $value;

    final public function __construct() {}

    /**
     * Call-ID header value parser
     *
     * @param list<string> $hbody Header body
     * @throws InvalidDuplicateHeader
     * @return CallIdHeader
     */
    public static function parse(array $hbody): CallIdHeader
    {
        if (isset($hbody[1])) {
            throw new InvalidDuplicateHeader('Cannot have more than one Call-ID header', Response::BAD_REQUEST);
        }

        $ret = new static;
        $ret->value = trim($hbody[0]);

        return $ret;
    }

    /**
     * Call-ID header value renderer
     *
     * @param string $hname Header field name
     * @throws InvalidHeaderValue
     * @return string
     */
    public function render(string $hname): string
    {
        if (!isset($this->value[0])) {
            throw new InvalidHeaderValue('Missing Call-ID header value');
        }

        return "{$hname}: {$this->value}\r\n";
    }
}
