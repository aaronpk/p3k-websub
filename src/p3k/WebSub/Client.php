<?php
namespace p3k\WebSub;

use p3k\HTTP;
use p3k;
use DOMXPath;

class Client {

  private $http;

  public function __construct($http=false) {
    if($http) {
      $this->http = $http;
    } else {
      $this->http = new HTTP('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_1) p3k-websub/0.1.0');
    }
  }

  public function discover($url, $headfirst=true, $verbose=false) {
    $hub = false;
    $self = false;
    $type = 'unknown';

    $http = [
      'hub' => [],
      'self' => [],
      'type' => $type,
    ];
    $body = [
      'hub' => [],
      'self' => [],
      'type' => $type,
    ];

    if($headfirst) {
      // First make a HEAD request, and if the links are found there, stop.
      $topic = $this->http->head($url);

      // Get the values from the Link headers
      if(array_key_exists('hub', $topic['rels'])) {
        $http['hub'] = $topic['rels']['hub'];
        $hub = $http['hub'][0];
      }
      if(array_key_exists('self', $topic['rels'])) {
        $http['self'] = $topic['rels']['self'];
        $self = $http['self'][0];
      }

      $content_type = '';
      if(array_key_exists('Content-Type', $topic['headers'])) {
        $content_type = $topic['headers']['Content-Type'];
        if(is_array($content_type))
          $content_type = $content_type[count($content_type)-1];

        if(strpos($content_type, 'text/html') !== false) {
          $type = $http['type'] = 'html';
        } else if(strpos($content_type, 'xml') !== false) {
          if(strpos('rss', $content_type) !== false) {
            $type = $http['type'] = 'rss';
          } else if(strpos($content_type, 'atom') !== false) {
            $type = $http['type'] = 'atom';
          }
        }
      }
    }

    if(!$hub || !$self) {
      // If we're missing hub or self, now make a GET request
      $topic = $this->http->get($url);

      $content_type = '';
      if(array_key_exists('Content-Type', $topic['headers'])) {
        $content_type = $topic['headers']['Content-Type'];
        if(is_array($content_type))
          $content_type = $content_type[count($content_type)-1];

        // Get the values from the Link headers
        if(array_key_exists('hub', $topic['rels'])) {
          $http['hub'] = $topic['rels']['hub'];
        }
        if(array_key_exists('self', $topic['rels'])) {
          $http['self'] = $topic['rels']['self'];
        }

        if(preg_match('|text/html|', $content_type)) {
          $type = $body['type'] = 'html';

          $dom = p3k\html_to_dom_document($topic['body']);
          $xpath = new DOMXPath($dom);

          foreach($xpath->query('*/link[@href]') as $link) {
            $rel = $link->getAttribute('rel');
            $url = $link->getAttribute('href');
            if($rel == 'hub') {
              $body['hub'][] = $url;
            } else if($rel == 'self') {
              $body['self'][] = $url;
            }
          }

        } else if(preg_match('|xml|', $content_type)) {
          $dom = p3k\xml_to_dom_document($topic['body']);
          $xpath = new DOMXPath($dom);
          $xpath->registerNamespace('atom', 'http://www.w3.org/2005/Atom');

          if($xpath->query('/rss')->length) {
            $type = $body['type'] = 'rss';
          } elseif($xpath->query('/atom:feed')->length) {
            $type = $body['type'] = 'atom';
          }

          // Look for atom link elements in the feed        
          foreach($xpath->query('/atom:feed/atom:link[@href]') as $link) {
            $rel = $link->getAttribute('rel');
            $url = $link->getAttribute('href');
            if($rel == 'hub') {
              $body['hub'][] = $url;
            } else if($rel == 'self') {
              $body['self'][] = $url;
            }
          }

          // Some RSS feeds include the link element as an atom attribute
          foreach($xpath->query('/rss/channel/atom:link[@href]') as $link) {
            $rel = $link->getAttribute('rel');
            $url = $link->getAttribute('href');
            if($rel == 'hub') {
              $body['hub'][] = $url;
            } else if($rel == 'self') {
              $body['self'][] = $url;
            }
          }
        }
      }
    }

    // Prioritize the HTTP headers
    if($http['hub']) {
      $hub = $http['hub'][0];
      $hub_source = 'http';
    }
    elseif($body['hub']) {
      $hub = $body['hub'][0];
      $hub_source = 'body';
    } else {
      $hub_source = false;
    }

    if($http['self']) {
      $self = $http['self'][0];
      $self_source = 'http';
    }
    elseif($body['self']) {
      $self = $body['self'][0];
      $self_source = 'body';
    } else {
      $self_source = false;
    }

    if(!($hub && $self)) {
      return false;
    }

    $response = [
      'hub' => $hub,
      'hub_source' => $hub_source,
      'self' => $self,
      'self_source' => $self_source,
      'type' => $type,
    ];

    if($verbose) {
      $response['details'] = [
        'http' => $http,
        'body' => $body
      ];
    }

    return $response;
  }

  public function subscribe($hub, $topic, $callback, $options=[]) {
    $params = [
      'hub.mode' => 'subscribe',
      'hub.topic' => $topic,
      'hub.callback' => $callback,
    ];
    if(isset($options['lease_seconds'])) {
      $params['hub.lease_seconds'] = $options['lease_seconds'];
    }
    if(isset($options['secret'])) {
      $params['hub.secret'] = $options['secret'];
    }
    $response = $this->http->post($hub, http_build_query($params));

    // TODO: Check for HTTP 307/308 and subscribe at the new location

    return $response;
  }

  public function unsubscribe($hub, $topic, $callback) {
    $params = [
      'hub.mode' => 'unsubscribe',
      'hub.topic' => $topic,
      'hub.callback' => $callback,
    ];
    $response = $this->http->post($hub, http_build_query($params));

    // TODO: Check for HTTP 307/308 and unsubscribe at the new location

    return $response;
  }

  public static function verify_signature($body, $signature_header, $secret) {
    if($signature_header && is_string($signature_header) 
      && preg_match('/(sha(?:1|256|384|512))=(.+)/', $signature_header, $match)) {
      $alg = $match[1];
      $sig = $match[2];
      $expected_signature = hash_hmac($alg, $body, $secret);
      return $sig == $expected_signature;
    } else {
      return false;
    }
  }

}

