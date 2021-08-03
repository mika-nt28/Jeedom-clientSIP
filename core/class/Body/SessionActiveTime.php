<?php
/**
* Session Active Time Class
*/
class SessionActiveTime
{
    /** @var string Body field value */
    public $start;
    /** @var string Body field value */
    public $stop;

    final public function __construct() {}

    /**
     * Session Active Time body field parser
     *
     * @param list<string> $hbody body
     * @throws InvalidDuplicateHeader
     * @throws InvalidHeaderLineException
     * @return SessionActiveTime
     */
    public static function parse(array $hbody): SessionActiveTime
    {
        if (isset($hbody[1])) {
            throw new InvalidDuplicateHeader('Cannot have single value body', Response::BAD_REQUEST);
        }

        $tok = explode(' ',trim($hbody[0]));

        if ($tok === false) {
            throw new InvalidHeaderLineException('Empty body value', Response::BAD_REQUEST);
        }

        $ret = new static;
        $ret->start = $tok[0];
        $ret->stop = $tok[1];

        return $ret;
    }

    /**
     * Session Active Time body renderer
     *
     * @param string $hname body field name
     * @throws InvalidHeaderValue
     * @return string
     */
    public function render(string $hname): string
    {
        if (!isset($this->start $this->stop)) {
            throw new InvalidHeaderValue('Missing Active Time field value for body: ' . $hname);
        }
        $ret = "{$hname}: {$this->start} {$this->stop}";
        $ret .= "\r\n";
        return $ret;
    }
}
