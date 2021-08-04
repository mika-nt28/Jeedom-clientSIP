<?php
/**
* Body Class
*/

require_once  dirname(__FILE__) .'/SessionNameBody.php';
require_once  dirname(__FILE__) .'/SessionConnexionBody.php';
require_once  dirname(__FILE__) .'/SessionActiveTimeBody.php';
require_once  dirname(__FILE__) .'/SessionMediaDescriptionBody.php';
require_once  dirname(__FILE__) .'/SessionCodecDescriptionBody.php';

class Body
{

    final public function __construct() {}

    /* Compact header definitions */
    public const COMPACT_BODYS = [
        'v' => 'version',
        'o' => 'origine',
        's' => 'session',
        'c' => 'connect',
        't' => 'time',
        'm' => 'media',
        'a' => 'codec',
    ];
    public $SessionName;
    public $SessionConnexion;
    public $SessionActiveTime;
    public $SessionMediaDescription;
    public $SessionCodecDescription = [];
    public $SessionOrigine;
    public $SessionVersion;
    /**
     * Generic header value parser
     *
     * @param list<string> $body Body body
     * @return Body
     */
    public static function parse(string $text): Body
    {
     
        $text = ltrim($text, "\r\n");
        $lines = explode("\r\n", $text);
        $count = count($lines);
        $bodys = [];
        $ret = new static;
        for ($i = 1; $i < $count; $i++) {
            if (($lines[$i][0] === ' ') || ($lines[$i][0] === "\t")) {
                if (!isset($bvalue)) {
                    throw new InvalidHeaderLineException('Malformed Body-Line: ' . $lines[$i], Response::BAD_REQUEST);
                }

                $hvalue .= $lines[$i];
            } else {
                if (isset($bname, $bvalue)) {
                    $bodys[$bname][] = $bvalue;
                }

                $delimPos = strpos($lines[$i], ':');

                /* Use of falsey is intentional, neither 0 nor false are not satisfactory here */
                if (!$delimPos) {
                    throw new InvalidHeaderLineException('Malformed Body-Line: ' . $lines[$i], Response::BAD_REQUEST);
                }

                $bname = strtolower(trim(substr($lines[$i], 0, $delimPos)));

                if (isset(self::COMPACT_BODYS[$bname])) {
                    $bname = self::COMPACT_BODYS[$bname];
                }

                if (!isset($bodys[$bname])) {
                    $bodys[$bname] = [];
                }

                $bvalue = substr($lines[$i], $delimPos + 1);
            }
        }

        if (isset($bname, $bvalue[0])) {
            $bodys[$bname][] = $bvalue;
        }


        foreach ($bodys as $bname => $bbody) {
            switch ($bname) {
                case 'version':
                    continue 2;
                case 'origine':
                    continue 2;
                case 'session':
                    $ret->SessionName = SessionNameBody::parse($bbody);
                    continue 2;
                case 'connect':
                    $ret->SessionConnexion = SessionConnexionBody::parse($bbody);
                    continue 2;
                case 'time':
                    $ret->SessionActiveTime = SessionActiveTimeBody::parse($bbody);
                    continue 2;
                case 'media':
                    $ret->SessionMediaDescription = SessionMediaDescriptionBody::parse($bbody);
                    continue 2;
              case 'codec':
                    $ret->SessionCodecDescription[] = SessionCodecDescriptionBody::parse($bbody);
                    continue 2;
            }
        }
        return $ret;
    }

    /**
     * Generic body value renderer
     *
     * @param string $bname Body field name
     * @return string
     */
    public function render(string $bname): string
    {
        $compact =true;
        $ret = '';
        if (isset($this->SessionVersion)) {
          $ret .= $this->SessionVersion->render($compact ? 'v' : 'version');
        }
        if (isset($this->SessionOrigine)) {
          $ret .= $this->SessionOrigine->render($compact ? 'o' : 'origine');
        }
        if (isset($this->SessionName)) {
          $ret .= $this->SessionName->render($compact ? 's' : 'session');
        }
        if (isset($this->SessionConnexion)) {
          $ret .= $this->SessionConnexion->render($compact ? 'c' : 'connect');
        }
        if (isset($this->SessionActiveTime)) {
          $ret .= $this->SessionActiveTime->render($compact ? 't' : 'time');
        }
        if (isset($this->SessionMediaDescription)) {
          $ret .= $this->SessionMediaDescription->render($compact ? 'm' : 'media');
        }
        if (isset($this->SessionCodec)) {
          $ret .= $this->SessionCodec->render($compact ? 'a' : 'codec');
        }
        return $ret;
    }
}
