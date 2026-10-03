<?php
namespace p3k\WebSub\Tests;

// Stands in for p3k\HTTP: returns canned responses and records each request
class FakeHTTP {

  public $requests = [];
  private $responses;

  public function __construct($responses=[]) {
    $this->responses = $responses;
  }

  public function head($url) {
    return $this->respond('HEAD', $url);
  }

  public function get($url) {
    return $this->respond('GET', $url);
  }

  public function post($url, $body) {
    return $this->respond('POST', $url, $body);
  }

  private function respond($method, $url, $body=null) {
    $this->requests[] = ['method' => $method, 'url' => $url, 'body' => $body];

    $response = isset($this->responses[$method]) ? $this->responses[$method] : [];
    return array_merge([
      'code' => 200,
      'headers' => [],
      'rels' => [],
      'body' => '',
    ], $response);
  }

}
