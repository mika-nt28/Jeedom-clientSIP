<?php
class Request extends SIPMessage
{
    /** @var string Request method */
    public $method;

    /** @var string Request URI */
    public $uri;

    /**
     * SIP Request constructor
     *
     * @param ?string $startLine Raw message start line
     * @throws InvalidMessageStartLineException
     * @throws InvalidProtocolVersionException
     * @throws InvalidRequestURI
     */
    public function __construct($startLine = null)
    {
        if (is_null($startLine)) {
            return;
        }

        $rqstLine = explode(' ', $startLine);

        if (count($rqstLine) !== 3) {
            throw new InvalidMessageStartLineException('Malformed Request-Line: ' . $startLine, Response::BAD_REQUEST);
        }

        if ($rqstLine[1][0] === '<') {
            throw new InvalidRequestURI('Cannot enclose <> request URIs', Response::BAD_REQUEST);
        }

        if ($rqstLine[2] !== SIPMessage::SIP_VERSION) {
            throw new InvalidProtocolVersionException('Unsupported SIP version: ' . $rqstLine[2], Response::VERSION_NOT_SUPPORTED);
        }

        $this->version = $rqstLine[2];
        $this->method = $rqstLine[0];
        $this->uri = $rqstLine[1];
    }

    /**
     * SIP Response Renderer
     *
     * @param bool $compact Whether to output compact headers or not
     * @throws InvalidRequestMethod
     * @throws InvalidRequestURI
     * @return string SIP response as text
     */
    public function render(bool $compact = false): string
    {
        if (!isset($this->method[0])) {
            throw new InvalidRequestMethod('Missing request method');
        }

        if (!isset($this->uri[0])) {
            throw new InvalidRequestURI('Missing request URI');
        }

        $this->version = SIPMessage::SIP_VERSION;
        $headers = $this->renderHeaders($compact);

        return "{$this->method} {$this->uri} {$this->version}\r\n{$headers}\r\n{$this->body}\r\n";
    }
}
