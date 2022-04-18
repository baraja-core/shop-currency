<?php

declare(strict_types=1);

namespace Baraja\Shop\Currency;


use Baraja\EcommerceStandard\DTO\CurrencyInterface;
use Baraja\Localization\Localization;

final class CurrencyResolver
{
	public function __construct(
		private Localization $localization,
		private CurrencyManager $currencyManager,
	) {
	}


	public function getCurrency(?CurrencyInterface $expected = null, ?string $locale = null): CurrencyInterface
	{
		if ($expected !== null) {
			return $expected;
		}
		$session = $this->getSessionValue();
		if ($session !== null) { // resolve by session
			return $this->currencyManager->getCurrency($session);
		}
		$locale ??= $this->localization->getLocale();
		try { // use default by locale
			return $this->currencyManager->getByLocale($locale);
		} catch (\Throwable) {
			// Silence is golden.
		}

		throw new \InvalidArgumentException('Expected currency does not exist.');
	}


	public function setCurrency(?CurrencyInterface $currency = null): void
	{
		$this->setSessionValue($currency?->getCode());
	}


	private function getSessionKey(): string
	{
		return 'baraja_shop__currency';
	}


	private function getSessionValue(): ?string
	{
		$currency = (string) ($_SESSION[$this->getSessionKey()] ?? '');

		return $currency === '' ? null : $currency;
	}


	private function setSessionValue(?string $value): void
	{
		$sessionKey = $this->getSessionKey();
		if ($value === null) {
			unset($_SESSION[$sessionKey]);
		} else {
			$_SESSION[$sessionKey] = $value;
		}
	}
}
