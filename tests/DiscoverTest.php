<?php
namespace p3k\WebSub\Tests;

use PHPUnit\Framework\TestCase;
use p3k\WebSub\Client;

class DiscoverTest extends TestCase {

  public function testFindsHubAndSelfInHeadRequestLinkHeaders() {
    $http = new FakeHTTP([
      'HEAD' => [
        'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
        'rels' => [
          'hub' => ['https://hub.example/'],
          'self' => ['https://example.com/'],
        ],
      ],
    ]);
    $client = new Client($http);

    $result = $client->discover('https://example.com/');

    $this->assertSame([
      'hub' => 'https://hub.example/',
      'hub_source' => 'http',
      'self' => 'https://example.com/',
      'self_source' => 'http',
      'type' => 'html',
    ], $result);
    // Everything was in the HEAD response, so no GET is needed
    $this->assertCount(1, $http->requests);
    $this->assertSame('HEAD', $http->requests[0]['method']);
  }

  public function testFallsBackToGetWhenHeadHasNoLinks() {
    $http = new FakeHTTP([
      'GET' => [
        'headers' => ['Content-Type' => 'text/html'],
        'rels' => [
          'hub' => ['https://hub.example/'],
          'self' => ['https://example.com/'],
        ],
      ],
    ]);
    $client = new Client($http);

    $result = $client->discover('https://example.com/');

    $this->assertSame('https://hub.example/', $result['hub']);
    $this->assertSame('http', $result['hub_source']);
    $this->assertSame(['HEAD', 'GET'], array_column($http->requests, 'method'));
  }

  public function testSkipsHeadRequestWhenHeadfirstIsFalse() {
    $http = new FakeHTTP([
      'GET' => [
        'headers' => ['Content-Type' => 'text/html'],
        'rels' => [
          'hub' => ['https://hub.example/'],
          'self' => ['https://example.com/'],
        ],
      ],
    ]);
    $client = new Client($http);

    $client->discover('https://example.com/', false);

    $this->assertSame(['GET'], array_column($http->requests, 'method'));
  }

  public function testDetectsRssFromHeadContentType() {
    $http = new FakeHTTP([
      'HEAD' => [
        'headers' => ['Content-Type' => 'application/rss+xml; charset=utf-8'],
        'rels' => [
          'hub' => ['https://hub.example/'],
          'self' => ['https://example.com/feed.rss'],
        ],
      ],
    ]);
    $client = new Client($http);

    $result = $client->discover('https://example.com/feed.rss');

    $this->assertSame('rss', $result['type']);
    $this->assertCount(1, $http->requests);
  }

  public function testFindsLinksInAtomFeedBody() {
    $http = new FakeHTTP([
      'GET' => [
        'headers' => ['Content-Type' => 'application/atom+xml'],
        'body' => '<?xml version="1.0" encoding="utf-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Example</title>
  <link rel="hub" href="https://hub.example/"/>
  <link rel="self" href="https://example.com/feed.atom"/>
</feed>',
      ],
    ]);
    $client = new Client($http);

    $result = $client->discover('https://example.com/feed.atom');

    $this->assertSame([
      'hub' => 'https://hub.example/',
      'hub_source' => 'body',
      'self' => 'https://example.com/feed.atom',
      'self_source' => 'body',
      'type' => 'atom',
    ], $result);
  }

  public function testFindsAtomLinksInRssFeedBody() {
    $http = new FakeHTTP([
      'GET' => [
        'headers' => ['Content-Type' => 'application/rss+xml'],
        'body' => '<?xml version="1.0" encoding="utf-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
    <title>Example</title>
    <atom:link rel="hub" href="https://hub.example/"/>
    <atom:link rel="self" href="https://example.com/feed.rss"/>
  </channel>
</rss>',
      ],
    ]);
    $client = new Client($http);

    $result = $client->discover('https://example.com/feed.rss');

    $this->assertSame('https://hub.example/', $result['hub']);
    $this->assertSame('https://example.com/feed.rss', $result['self']);
    $this->assertSame('body', $result['self_source']);
    $this->assertSame('rss', $result['type']);
  }

  public function testFindsLinkElementsInHtmlBody() {
    $http = new FakeHTTP([
      'GET' => [
        'headers' => ['Content-Type' => 'text/html'],
        'body' => '<!doctype html>
<html>
<head>
  <title>Example</title>
  <link rel="hub" href="https://hub.example/">
  <link rel="self" href="https://example.com/">
</head>
<body></body>
</html>',
      ],
    ]);
    $client = new Client($http);

    $result = $client->discover('https://example.com/');

    $this->assertSame('https://hub.example/', $result['hub']);
    $this->assertSame('body', $result['hub_source']);
    $this->assertSame('https://example.com/', $result['self']);
    $this->assertSame('html', $result['type']);
  }

  public function testLinkHeadersTakePriorityOverBody() {
    $http = new FakeHTTP([
      'GET' => [
        'headers' => ['Content-Type' => 'application/atom+xml'],
        'rels' => ['hub' => ['https://header-hub.example/']],
        'body' => '<?xml version="1.0" encoding="utf-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <link rel="hub" href="https://body-hub.example/"/>
  <link rel="self" href="https://example.com/feed.atom"/>
</feed>',
      ],
    ]);
    $client = new Client($http);

    $result = $client->discover('https://example.com/feed.atom');

    $this->assertSame('https://header-hub.example/', $result['hub']);
    $this->assertSame('http', $result['hub_source']);
    $this->assertSame('https://example.com/feed.atom', $result['self']);
    $this->assertSame('body', $result['self_source']);
  }

  public function testReturnsFalseWithoutHub() {
    $http = new FakeHTTP([
      'GET' => [
        'headers' => ['Content-Type' => 'text/html'],
        'rels' => ['self' => ['https://example.com/']],
      ],
    ]);
    $client = new Client($http);

    $this->assertFalse($client->discover('https://example.com/'));
  }

  public function testVerboseIncludesDetails() {
    $http = new FakeHTTP([
      'HEAD' => [
        'headers' => ['Content-Type' => 'text/html'],
        'rels' => [
          'hub' => ['https://hub.example/'],
          'self' => ['https://example.com/'],
        ],
      ],
    ]);
    $client = new Client($http);

    $result = $client->discover('https://example.com/', true, true);

    $this->assertSame(['https://hub.example/'], $result['details']['http']['hub']);
    $this->assertSame([], $result['details']['body']['hub']);
  }

}
