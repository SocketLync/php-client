<?php

namespace SocketLync;

use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\Broadcasters\UsePusherChannelConventions;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class SocketBroadcaster extends Broadcaster
{
	use UsePusherChannelConventions;

	protected SocketClient $client;

	public function __construct(SocketClient $client)
	{
		$this->client = $client;
	}

	public function auth($request)
	{
		$channelName = $this->normalizeChannelName($request->channel_name);

		if ( empty($request->channel_name) ||
		     ($this->isGuardedChannel($request->channel_name) &&
		      !$this->retrieveUser($request, $channelName)) ) {
			throw new AccessDeniedHttpException();
		}

		return parent::verifyUserCanAccessChannel(
			$request, $channelName
		);
	}

	public function validAuthenticationResponse($request, $result)
	{
		if ( str_starts_with($request->channel_name, 'private') ) {
			return $this->client->authorizeChannel($request->socket_id, $request->channel_name);
		}

		$channelName = $this->normalizeChannelName($request->channel_name);

		$user = $this->retrieveUser($request, $channelName);

		$broadcastIdentifier = method_exists($user, 'getAuthIdentifierForBroadcasting')
			? $user->getAuthIdentifierForBroadcasting()
			: $user->getAuthIdentifier();

		return $this->client->authorizePresenceChannel($request->channel_name, $request->socket_id, $broadcastIdentifier, $result);
	}

	public function broadcast(array $channels, $event, array $payload = [])
	{
		$socket = Arr::pull($payload, 'socket');
		$parameters = $socket !== null ? ['socket_id' => $socket] : [];
		$channels = Collection::make($this->formatChannels($channels));

		try {
			$channels->chunk(100)->each(function ($channels) use ($event, $payload, $parameters) {
				$this->client->emit($channels->toArray(), $event, $payload, $parameters);
			});
		} catch (\Exception $e) {
			throw new BroadcastException(
				sprintf('Socket broadcaster error: %s.', $e->getMessage())
			);
		}
	}
}