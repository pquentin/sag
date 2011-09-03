<?php
/**
 * Provides a common interface for Sag to connect to CouchDB over HTTP,
 * allowing for different drivers to be used thereby controling your project's
 * dependencies.
 *
 * @version 0.6.0
 * @package HTTP
 */
abstract class SagHTTPAdapter {
  public $decodeResp = true;

  protected $host;
  protected $port;

  public function __construct($host = "127.0.0.1", $port = "5984") {
    $this->host = $host;
    $this->port = $port;
  }

  /**
   * Processes the packet, returning the server's response.
   */
  abstract public function procPacket($method, $url, $data = null, $headers = array());

  //TODO put more thought into response object creation
  protected function makeResult($method, $response) {
    /*
     * HEAD requests can return an HTTP response code >=400, meaning that there
     * was a CouchDB error, but we don't get a $response->body->error because
     * HEAD responses don't have bodies.
     *
     * And we do this before the json_decode() because even running
     * json_decode() on undefined can take longer than calling it on a JSON
     * string. So no need to run any of the $json code.
     */
    if($method == 'HEAD') {
      if($response->headers->_HTTP->status >= 400) {
        throw new SagCouchException('HTTP/CouchDB error without message body', $response->headers->_HTTP->status);
      }

      //no else needed - just going to return below
    }
    else {
      /*
       * $json won't be set if invalid JSON is sent back to us. This will most
       * likely happen if we're GET'ing an attachment that isn't JSON (ex., a
       * picture or plain text). Don't be fooled by storing a PHP string in an
       * attachment as text/plain and then expecting it to be parsed by
       * json_decode().
       */
      $json = json_decode($response->body);

      if(isset($json)) {
        /*
         * Check for an error from CouchDB regardless of whether they want JSON
         * returned.
         */
        if(!empty($json->error)) {
          throw new SagCouchException("{$json->error} ({$json->reason})", $response->headers->_HTTP->status);
        }

        $response->body = ($this->decodeResp) ? $json : $response->body;
      }
    }

    return $response;
  }
}
?>
