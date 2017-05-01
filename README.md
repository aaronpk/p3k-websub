# p3k-websub

## Usage

### Initialize the client

```php
$http = new p3k\HTTP('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_1) p3k-websub/0.1.0 example');
$client = new p3k\WebSub\Client($http);
```

### Discover the hub and self URLs for a topic URL

```php
// Returns false unless both hub and self were found
$endpoints = $client->discover($topic);

// $endpoints['hub'] 
// $endpoints['self'] 
```

### Send the subscription request

```php
$secret = p3k\random_string(32);
$id = p3k\random_string(32);
$callback = 'http://localhost:8080/subscriber.php?id='.$id;

$subscription = $client->subscribe($endpoints['hub'], $endpoints['self'], $callback, [
  'lease_seconds' => 300,
  'secret' => $secret
]);
```

### Verify the signature

```php
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE'];
$document = file_get_contents('php://input');
$valid = p3k\WebSub\Client::verify_signature($document, $signature, $secret);
```


## License

Copyright 2017 by Aaron Parecki

Available under the MIT license.

