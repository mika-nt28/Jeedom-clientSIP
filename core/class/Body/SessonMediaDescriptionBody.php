<?php
/**
* Sesson Media Description Body Class
*/
class SessonMediaDescriptionBody
{
/** @var string Body field value */
    public $type;
    public $port;
    public $protocol;
    public $codec;

    final public function __construct() {}

    /**
     * Sesson Connexion body field parser
     *
     * @param list<string> $hbody body
     * @throws InvalidDuplicateHeader
     * @throws InvalidHeaderLineException
     * @return SessionConnexionBody
     */
    public static function parse(array $bbody): SessonMediaDescriptionBody
    {
        if (isset($bbody[1])) {
            throw new InvalidDuplicateHeader('Cannot have single value body', Response::BAD_REQUEST);
        }
        $tok = explode(' ',trim($bbody[0]));
        if ($tok === false) {
            throw new InvalidHeaderLineException('Empty body value', Response::BAD_REQUEST);
        }
        $ret = new static;
        $ret->type = $tok[0];
        $ret->port = $tok[1];
        $ret->protocol = $tok[2];
        $ret->codec = $tok[3];
        return $ret;
    }

    /**
     * Sesson Connexion body renderer, with optional parameters
     *
     * @param string $hname body field name
     * @throws InvalidHeaderValue
     * @return string
     */
    public function render(string $bname): string
    {
        if (!isset($this->networkType, $this->adresseType, $this->adresse)) {
            throw new InvalidHeaderValue('Missing body field value for body: ' . $bname);
        }
        $ret = "{$hname}: {$this->type} {$this->port} {$this->protocol} {$this->codec}";
        $ret .= "\r\n";
        return $ret;
    }
}
