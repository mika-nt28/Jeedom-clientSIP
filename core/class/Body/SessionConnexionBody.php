<?php
/**
* Sesson Connexion Body Class
*/
class SessionConnexionBody
{
/** @var string Body field value */
    public $networkType;
    public $adresseType;
    public $adresse;

    final public function __construct() {}

    /**
     * Single value body field parser, with parameters
     *
     * @param list<string> $hbody body
     * @throws InvalidDuplicateHeader
     * @throws InvalidHeaderLineException
     * @return SessionConnexionBody
     */
    public static function parse(array $hbody): SessionConnexionBody
    {
        if (isset($hbody[1])) {
            throw new InvalidDuplicateHeader('Cannot have single value body', Response::BAD_REQUEST);
        }

        $tok = explode(' ',trim($hbody[0]));

        if ($tok === false) {
            throw new InvalidHeaderLineException('Empty body value', Response::BAD_REQUEST);
        }

        $ret = new static;
        $ret->networkType = $tok[0];
        $ret->adresseType = $tok[0];
        $ret->adresse = $tok[0];

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
        if (!isset($this->networkType $this->adresseType $this->adresse)) {
            throw new InvalidHeaderValue('Missing body field value for body: ' . $hname);
        }

        $ret = "{$hname}: {$this->networkType} {$this->adresseType} {$this->adresse}";

        $ret .= "\r\n";

        return $ret;
    }
}
