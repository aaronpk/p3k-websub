<?php
namespace p3k\WebSub\Tests;

use PHPUnit\Framework\TestCase;
use p3k\WebSub\Client;

class SubscribeTest extends TestCase {

  public function testSubscribePostsFormEncodedRequest() {
    $http = new FakeHTTP(['POST' => ['code' => 202]]);
    $client = new Client($http);

    $response = $client->subscribe('https://hub.example/', 'https://example.com/feed', 'https://reader.example/callback');

    $this->assertSame(202, $response['code']);
    $this->assertCount(1, $http->requests);
    $this->assertSame('https://hub.example/', $http->requests[0]['url']);
    parse_str($http->requests[0]['body'], $params);
    $this->assertSame([
      'hub_mode' => 'subscribe',
      'hub_topic' => 'https://example.com/feed',
      'hub_callback' => 'https://reader.example/callback',
    ], $params);
    $this->assertStringContainsString('hub.mode=subscribe', $http->requests[0]['body']);
  }

  public function testSubscribeIncludesLeaseSecondsAndSecret() {
    $http = new FakeHTTP();
    $client = new Client($http);

    $client->subscribe('https://hub.example/', 'https://example.com/feed', 'https://reader.example/callback', [
      'lease_seconds' => 86400,
      'secret' => 's3cret',
    ]);

    $body = $http->requests[0]['body'];
    $this->assertStringContainsString('hub.lease_seconds=86400', $body);
    $this->assertStringContainsString('hub.secret=s3cret', $body);
  }

  public function testUnsubscribePostsFormEncodedRequest() {
    $http = new FakeHTTP();
    $client = new Client($http);

    $client->unsubscribe('https://hub.example/', 'https://example.com/feed', 'https://reader.example/callback');

    $this->assertSame('https://hub.example/', $http->requests[0]['url']);
    $this->assertSame(
      'hub.mode=unsubscribe&hub.topic=https%3A%2F%2Fexample.com%2Ffeed&hub.callback=https%3A%2F%2Freader.example%2Fcallback',
      $http->requests[0]['body']
    );
  }

}
