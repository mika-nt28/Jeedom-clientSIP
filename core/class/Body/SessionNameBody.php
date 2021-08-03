<?php
/**
* SessionNameBody Class
*/
class SessionNameBody
{
    /** @var string Body field value */
    public $value;

    final public function __construct() {}

    /**
     * Single value body field parser, with parameters
     *
     * @param list<string> $hbody body
     * @throws InvalidDuplicateHeader
     * @throws InvalidHeaderLineException
     * @throws InvalidBodyParameter
     * @return SingleValueWithParamsBody
     */
    public static function parse(array $hbody): SessionNameBody
    {
        if (isset($hbody[1])) {
            throw new InvalidDuplicateHeader('Cannot have single value body', Response::BAD_REQUEST);
        }

        $tok = trim($hbody[0]);

        if ($tok === false) {
            throw new InvalidHeaderLineException('Empty body value', Response::BAD_REQUEST);
        }

        $ret = new static;
        $ret->value = $tok;

        return $ret;
    }

    /**
     * Single value body renderer, with optional parameters
     *
     * @param string $hname body field name
     * @throws InvalidHeaderValue
     * @return string
     */
    public function render(string $hname): string
    {
        if (!isset($this->value[0])) {
            throw new InvalidHeaderValue('Missing body field value for body: ' . $hname);
        }

        $ret = "{$hname}: {$this->value}";

        $ret .= "\r\n";

        return $ret;
    }
}
