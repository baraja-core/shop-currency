<?php

declare(strict_types=1);

namespace Baraja\Shop\Currency;


use Baraja\EcommerceStandard\DTO\CurrencyInterface;
use Baraja\Shop\Entity\Currency\ExchangeRate;

final class ExchangeRateFetcher
{
	private string $baseUrl = 'https://brj.cz/exchange-rate-api';

	/** @var array<string, array<string, mixed>> */
	private array $streamContext = [
		'ssl' => [
			'verify_peer' => false,
			'verify_peer_name' => false,
		],
	];


	/**
	 * Foreign exchange market rates are announced for commonly traded currencies every business day after 14.30,
	 * valid for the current business day and for the following Saturday, Sunday or public holiday
	 * (e.g. the rate announced on Tuesday 23 December is valid for Tuesday 23 December,
	 * public holidays 24-26 December and Saturday 27 December and Sunday 28 December).
	 */
	public static function resolveDate(\DateTimeInterface $date): \DateTimeImmutable
	{
		if ($date >= new \DateTimeImmutable('tomorrow')) {
			throw new \InvalidArgumentException(sprintf(
				'Currency exchange rate date can not be in future, but "%s" given.',
				$date->format('Y-m-d'),
			));
		}
		$now = new \DateTimeImmutable('now');
		if ($date->format('Y-m-d') === $now->format('Y-m-d')) { // today
			$nowHour = (int) $now->format('H');
			$nowMinute = (int) $now->format('i');
			if ($nowHour < 14 || ($nowHour === 14 && $nowMinute < 45)) {
				return new \DateTimeImmutable('yesterday');
			}
		}

		return new \DateTimeImmutable($date->format('Y-m-d'));
	}


	public function fetch(CurrencyInterface $source, CurrencyInterface $target, \DateTimeInterface $date): ExchangeRate
	{
		$url = sprintf(
			'%s?%s',
			$this->baseUrl,
			http_build_query(
				[
					'source' => $source->getCode(),
					'target' => $target->getCode(),
					'date' => self::resolveDate($date)->format('Y-m-d'),
				],
			),
		);

		$payload = (string) file_get_contents($url, false, stream_context_create($this->streamContext));
		/** @var array{error?: bool, day: string, buy: float, sell: float, middle: float} $response */
		$response = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

		$rate = new ExchangeRate($source, $target);
		if (($response['error'] ?? true) === false) {
			$rate->setMiddle($response['middle']);
			$rate->setBuy($response['buy']);
			$rate->setSell($response['sell']);
		} else {
			throw new \RuntimeException('Can not fetch currency data.' . "\n" . $payload);
		}

		return $rate;
	}


	public function getBaseUrl(): string
	{
		return $this->baseUrl;
	}


	/**
	 * @param array<string, mixed> $value
	 */
	public function setStreamContext(string $key, array $value): void
	{
		$this->streamContext[$key] = $value;
	}
}
