<?php
namespace p3k\WebSub\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use p3k\WebSub\Client;

class VerifySignatureTest extends TestCase {

  public static function signatureAlgorithms() {
    return [['sha1'], ['sha256'], ['sha384'], ['sha512']];
  }

  /**
   * @dataProvider signatureAlgorithms
   */
  #[DataProvider('signatureAlgorithms')]
  public function testAcceptsValidSignature($alg) {
    $body = '{"hello":"world"}';
    $header = $alg.'='.hash_hmac($alg, $body, 's3cret');

    $this->assertTrue(Client::verify_signature($body, $header, 's3cret'));
  }

  public function testRejectsWrongSecret() {
    $body = '{"hello":"world"}';
    $header = 'sha256='.hash_hmac('sha256', $body, 's3cret');

    $this->assertFalse(Client::verify_signature($body, $header, 'wrong'));
  }

  public function testRejectsModifiedBody() {
    $header = 'sha256='.hash_hmac('sha256', 'original', 's3cret');

    $this->assertFalse(Client::verify_signature('modified', $header, 's3cret'));
  }

  public function testRejectsMissingOrMalformedHeader() {
    $this->assertFalse(Client::verify_signature('body', null, 's3cret'));
    $this->assertFalse(Client::verify_signature('body', '', 's3cret'));
    $this->assertFalse(Client::verify_signature('body', 'md5=abc', 's3cret'));
    $this->assertFalse(Client::verify_signature('body', ['sha256=abc'], 's3cret'));
  }

}
