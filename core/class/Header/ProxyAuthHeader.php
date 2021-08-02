<?php
/**
* ProxyAuth Header Class
*/
class ProxyAuthHeader
{
    /** @var list<ProxyAuthValue> ProxyAuth value(s) */
    public $values = [];

    final public function __construct() {}

    /**
     * Single value header field parser, with parameters
     *
     * @param list<string> $hbody Header body
     * @throws InvalidHeaderLineException
     * @throws InvalidHeaderParameter
     * @return ProxyAuthHeader
     */
    public static function parse(array $hbody): ProxyAuthHeader
    {
        $ret = new static;

        foreach ($hbody as $hline) {
            $hvalues = explode(',', $hline);

            foreach ($hvalues as $hvalue) {
                $ProxyAuth = trim($hvalue);
               
                $vsplit = explode(' ', trim($psplit[2]), 2);

                if (count($vsplit) !== 2) {
                    throw new InvalidHeaderLineException('Invalid ProxyAuthHeader header', Response::BAD_REQUEST);
                }

                $val->digest = $vsplit[0];
                $vparams = explode(',', $vsplit[1]);
                foreach ($vparams as $param) {
                    $p = explode('=', $param);
                    $p[0] = trim($p[0]);

                    if (!isset($p[0][0])) {
                        throw new InvalidHeaderParameter('Empty header parameters', Response::BAD_REQUEST);
                    }

                    $pv = isset($p[1]) ? trim($p[1]) : '';
                    $val->params[$p[0]] = $pv;
                }

                $ret->values[] = $val;
            }
        }

        return $ret;
    }
   /**
     * ProxyAuthHeader header field value reponse
     *
     * @param string $hname Header field name
     * @throws InvalidHeaderValue
     * @return string
     */
    public function reponse(): string
    {
       foreach ($this->values as $key => $value) {
            foreach ($value->params as $pk => $pv) {
                switch($pk){
                    case 'realm';
                      $realm = $pv;
                    break;
                    case 'nonce';
                      $nonce = $pv;
                    break;
                    case 'username';
                      $nusername = $pv;
                    break;
                    case 'password';
                      $password = $pv;
                    break;
                    case 'uri';
                      $uri = $pv;
                    break;
                    case 'method';
                      $method = $pv;
                    break;
                }
              
                $ret .= ';' . $pk . (!isset($pv[0]) ? '' : "={$pv}");
            }
            $ha1 = md5($username.':'.$realm.':'.$password);
            $ha2 = md5($method.':'.$uri);
            $res = md5($ha1.':'.$nonce.':'.$ha2);
        }
        return $res;
    }
    /**
     * ProxyAuthHeader header field value renderer
     *
     * @param string $hname Header field name
     * @throws InvalidHeaderValue
     * @return string
     */
    public function render(string $hname): string
    {
        $ret = "{$hname}: ";
        $delim = '';

        foreach ($this->values as $key => $value) {
            if (!isset($value->digest, $value->reponse)) {
                throw new InvalidHeaderValue('Malformed ProxyAuthHeader header');
            }

            $ret .= "{$delim}{$value->digest}";

            foreach ($value->params as $pk => $pv) {
                $ret .= ',' . $pk . (!isset($pv[0]) ? '' : "={$pv}");
            }
        }
        return $ret . "\r\n";
    }
}
