<?php

namespace SocketLync;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use JsonException;
use Throwable;

class SocketClient
{
	private Client $client;

	public function __construct(protected array $settings)
	{
		$this->client = new Client([
			'base_uri' => sprintf("%s://%s:%s/apps/%s", $this->settings['options']['scheme'], $this->settings['options']['host'], $this->settings['options']['port'], $this->settings['key']),
		]);
	}

	public function authorizeChannel(string $socketId, string $channel, string $customData = null) : array
	{
		$parameters = [
			$socketId,
			$channel,
		];
		if ( $customData ) {
			$parameters[] = $customData;
		}

		$signature = hash_hmac('sha256', implode(':', $parameters), $this->settings['secret'], false);
		$signature = [
			'auth' => $this->settings['key'].':'.$signature,
		];

		// add the custom data if it has been supplied
		if ( $customData ) {
			$signature['channel_data'] = $customData;
		}

		return $signature;
	}

	public function authorizePresenceChannel(string $channel, string $socketId, string $userId, $userInfo = null) : array
	{
		$userData = [
			'user_id' => $userId,
		];
		if ( $userInfo ) {
			$userData['user_info'] = $userInfo;
		}

		try {
			return $this->authorizeChannel($socketId, $channel, json_encode($userData, JSON_THROW_ON_ERROR));
		} catch ( JsonException $exception ) {
			throw new Exception('Data encoding error.');
		}
	}

	public function emit(array|string $channels, string $event, array $data, array $parameters) : bool
	{
		if ( is_string($channels) ) {
			$channels = [$channels];
		}

		$postData = array_merge($parameters, [
			'name' => $event,
			'data' => json_encode($data),
			'channels' => array_values($channels),
		]);

		$options = [
			RequestOptions::JSON => $postData,
			RequestOptions::HEADERS => [
				'X-EVENT-TIMESTAMP' => $timestamp = time(),
				'X-EVENT-SIGNATURE' => hash_hmac('sha256', $timestamp.(json_encode($postData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), $this->settings['secret'], false),
			],
		];

		try {
			$response = $this->getClient()->post('events', $options);
		} catch ( Throwable $exception ) {
			return false;
		}

		return in_array($response->getStatusCode(), [200, 204]);
	}

	public function getClient() : Client
	{
		return $this->client;
	}
}