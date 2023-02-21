## Example Usage

BroadcastServiceProvider:
```php
public function boot(BroadcastManager $manager)
{
	$manager->extend('socket-lync', function($app, $config) {
		return new SocketBroadcaster(new SocketClient($config));
	});

	Broadcast::routes([
		'middleware' => ['api'],
	]);

	require base_path('routes/channels.php');
}
```

Add configuration to `config/broadcasting.php`:
```php
'connections' => [
	'socket-lync' => [
		'driver' => 'socket-lync',
		'key' => env('SOCKET_LYNC_PUBLIC_KEY', null),
		'secret' => env('SOCKET_LYNC_PRIVATE_KEY', null),
		'options' => [
			'host' => env('SOCKET_LYNC_HOST', 'ws.derpierre65.dev'),
			'port' => env('SOCKET_LYNC_PORT', 443),
			'scheme' => env('SOCKET_LYNC_SCHEME', 'https'),
		],
	],
	// ...
]
```

Change `BROADCAST_DRIVER` to `socket-lync`.

Call your event with `event(new MyEvent());`.