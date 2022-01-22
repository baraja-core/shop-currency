<?php

declare(strict_types=1);

namespace Baraja\Shop\Currency;


use Baraja\Doctrine\EntityManager;
use Baraja\EcommerceStandard\DTO\CurrencyInterface;
use Baraja\EcommerceStandard\DTO\ExchangeRateInterface;
use Baraja\EcommerceStandard\Service\CurrencyManagerInterface;
use Baraja\Localization\Localization;
use Baraja\Shop\Entity\Currency\Currency;

final class CurrencyManager implements CurrencyManagerInterface
{
	public const LOCALE_TO_CURRENCY = [
		'cs' => 'CZK',
		'sk' => 'EUR',
		'en' => 'EUR',
		'de' => 'EUR',
	];

	/** @var array<int, Currency> */
	private array $list = [];


	public function __construct(
		private EntityManager $entityManager,
	) {
	}


	public function getMainCurrency(): Currency
	{
		foreach ($this->getCurrencies() as $currency) {
			if ($currency->isMain()) {
				return $currency;
			}
		}

		return $this->fixCurrenciesAndReturnMain();
	}


	/**
	 * @deprecated since 2022-01-22
	 */
	public function getRateToday(
		CurrencyInterface|string $source,
		CurrencyInterface|string $target,
	): ExchangeRateInterface {
		return (new ExchangeRateConvertor($this->entityManager, $this))
			->getRateToday($source,$target);
	}


	/**
	 * @deprecated since 2022-01-22
	 */
	public function getRate(
		CurrencyInterface|string $source,
		CurrencyInterface|string $target,
		\DateTimeInterface $date,
	): ExchangeRateInterface {
		return (new ExchangeRateConvertor($this->entityManager, $this))
			->getRate($source,$target, $date);
	}


	/**
	 * @return array<int, Currency>
	 */
	public function getCurrencies(): array
	{
		if ($this->list === []) {
			/** @var array<int, Currency> $list */
			$list = $this->entityManager->getRepository(Currency::class)
				->createQueryBuilder('currency')
				->orderBy('currency.main', 'DESC')
				->getQuery()
				->getResult();
			$return = [];
			foreach ($list as $currency) {
				$return[$currency->getId()] = $currency;
			}
			$this->list = $return;
		}

		return array_values($this->list);
	}


	public function getCurrency(CurrencyInterface|string $code): CurrencyInterface
	{
		if ($code instanceof CurrencyInterface) {
			return $code;
		}

		$code = Currency::normalizeCode($code);
		foreach ($this->getCurrencies() as $currency) {
			if ($currency->getCode() === $code) {
				return $currency;
			}
		}

		throw new \InvalidArgumentException(sprintf('Currency "%s" does not exist.', $code));
	}


	public function getByLocale(string $locale): CurrencyInterface
	{
		$locale = Localization::normalize($locale);
		foreach ($this->getCurrencies() as $currency) {
			if ($currency->getLocale() === $locale) {
				return $currency;
			}
		}
		if (isset(self::LOCALE_TO_CURRENCY[$locale]) === false) {
			throw new \LogicException(sprintf('Currency for locale "%s" does not exist.', $locale));
		}
		try {
			$currency = $this->getCurrency(self::LOCALE_TO_CURRENCY[$locale]);
			assert($currency instanceof Currency);
			$currency->setLocale($locale);
			$this->entityManager->flush();
		} catch (\InvalidArgumentException) {
			$currency = $this->getMainCurrency();
			if ($currency->getLocale() === null) {
				$currency->setLocale($locale);
				$this->entityManager->flush();
			}
		}

		return $currency;
	}


	public function createCurrency(string $code, string $symbol): CurrencyInterface
	{
		$currency = new Currency($code, $symbol);
		$this->entityManager->persist($currency);
		$this->entityManager->flush();

		return $currency;
	}


	public function setMainCurrency(CurrencyInterface|string $currency): void
	{
		if (is_string($currency) === true) {
			$currency = $this->getCurrency($currency);
		}
		foreach ($this->getCurrencies() as $currencyItem) {
			$this->markCurrencyAsMain($currencyItem, false);
		}
		$this->markCurrencyAsMain($currency, true);
		$this->entityManager->flush();
	}


	private function fixCurrenciesAndReturnMain(): Currency
	{
		$main = null;
		$first = null;
		$needFlush = false;
		foreach ($this->getCurrencies() as $currency) {
			if ($first === null) {
				$first = $currency;
			}
			if ($main === null && $currency->isMain() === true) {
				$main = $currency;
			} elseif ($currency->isMain()) {
				$this->markCurrencyAsMain($currency, false);
				$needFlush = true;
			}
		}

		if ($main !== null) {
			$return = $main;
		} elseif ($first !== null) {
			$return = $first;
			$this->markCurrencyAsMain($first, true);
			$needFlush = true;
		} else {
			$return = $this->createCurrency('USD', '$');
			$this->markCurrencyAsMain($return, true);
			assert($return instanceof Currency);
			$return->setLocale('en');
			$needFlush = true;
		}
		if ($needFlush === true) {
			$this->entityManager->flush();
		}

		return $return;
	}


	private function markCurrencyAsMain(CurrencyInterface $currency, bool $main): void
	{
		if ($currency instanceof Currency) {
			$currency->setMain($main);
		}
	}
}
