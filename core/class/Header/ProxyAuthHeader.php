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
           $hvalues = explode(' ', trim($hline));
            $val = new ProxyAuthValue;
            if (count($hvalues) != 2) {
                throw new InvalidHeaderLineException('Invalid ProxyAuthHeader header', Response::BAD_REQUEST);
            }
            $val->digest = $hvalues[0];
            $vparams = explode(',', $hvalues[1]);
            foreach ($vparams as $param) {
                $p = explode('=', $param);
                $p[0] = trim($p[0]);
                if (!isset($p[0][0])) {
                    throw new InvalidHeaderParameter('Empty header parameters', Response::BAD_REQUEST);
                }
                $pv = isset($p[1]) ? trim($p[1]) : '';
                $val->params[$p[0]] = str_replace('"','',$pv);
            }
            $ret->values[] = $val;
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
    public function reponse($params): string
    {
        $ha1 = md5($params['username'].':'.$params['realm'].':'.$params['password']);
        $ha2 = md5($params['method'].':'.$params['uri']);
        $res = md5($ha1.':'.$params['nonce'].':'.$ha2);
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
        $delim = ' ';
        foreach ($this->values as $key => $value) {
          	$value->reponse = $this->reponse($value->params);
            if (!isset($value->digest, $value->reponse)) {
                throw new InvalidHeaderValue('Malformed ProxyAuthHeader header');
            }
            $ret .= "{$value->digest}{$delim}";
            foreach ($value->params as $pk => $pv) {
              	if($pk == 'password' || $pk == 'method')
                  continue;
             	if($pk == 'algorithm')
                	$ret .= $pk . (!isset($pv[0]) ? '' : "={$pv}");
              	else
                	$ret .= $pk . (!isset($pv[0]) ? '' : "=\"{$pv}\"");
                $ret .= ', ';
            }
           $ret .='response="'.$value->reponse.'"';
        }
        return $ret . "\r\n";
    }
}
