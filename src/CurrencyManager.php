<?php

declare(strict_types=1);

namespace Baraja\Shop\Currency;


use Baraja\Doctrine\EntityManager;
use Baraja\Shop\Entity\Currency\Currency;
use Baraja\Shop\Entity\Currency\ExchangeRate;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

final class CurrencyManager
{
	public function __construct(
		private EntityManager $entityManager,
	) {
	}


	public function getMainCurrency(): Currency
	{
		try {
			return $this->entityManager->getRepository(Currency::class)
				->createQueryBuilder('currency')
				->where('currency.main = TRUE')
				->setMaxResults(1)
				->getQuery()
				->getSingleResult();
		} catch (NoResultException | NonUniqueResultException) {
			return $this->fixCurrenciesAndReturnMain();
		}
	}


	public function getRateToday(Currency|string $source, Currency|string $target): ExchangeRate
	{
		return $this->getRate(
			source: $source,
			target: $target,
			date: new \DateTimeImmutable('today')
		);
	}


	public function getRate(
		Currency|string $source,
		Currency|string $target,
		\DateTimeInterface $date
	): ExchangeRate {
		$date = ExchangeRateFetcher::resolveDate($date);
		try {
			return $this->entityManager->getRepository(ExchangeRate::class)
				->createQueryBuilder('rate')
				->where('rate.pair = :pair')
				->andWhere('rate.date >= :date')
				->setParameter('pair', ExchangeRate::formatPair($source, $target))
				->setParameter('date', $date->format('Y-m-d') . ' 00:00:00')
				->orderBy('rate.date', 'ASC')
				->setMaxResults(1)
				->getQuery()
				->getSingleResult();
		} catch (NoResultException | NonUniqueResultException) {
			$rate = (new ExchangeRateFetcher)
				->fetch(
					$this->getCurrency($source),
					$this->getCurrency($target),
					$date
				);
			$this->entityManager->persist($rate);
			$this->entityManager->flush();

			return $rate;
		}
	}


	/**
	 * @return array<int, Currency>
	 */
	public function getCurrencies(): array
	{
		return $this->entityManager->getRepository(Currency::class)
			->createQueryBuilder('currency')
			->orderBy('currency.main', 'DESC')
			->getQuery()
			->getResult();
	}


	public function getCurrency(Currency|string $code): Currency
	{
		if ($code instanceof Currency) {
			return $code;
		}

		return $this->entityManager->getRepository(Currency::class)
			->createQueryBuilder('currency')
			->where('currency.code = :code')
			->setParameter('code', Currency::normalizeCode($code))
			->setMaxResults(1)
			->getQuery()
			->getSingleResult();
	}


	public function createCurrency(string $code, string $symbol): Currency
	{
		$currency = new Currency($code, $symbol);
		$this->entityManager->persist($currency);
		$this->entityManager->flush();

		return $currency;
	}


	public function setMainCurrency(Currency|string $currency): void
	{
		if (is_string($currency) === true) {
			$currency = $this->getCurrency($currency);
		}
		foreach ($this->getCurrencies() as $currencyItem) {
			$currencyItem->setMain(false);
		}
		$currency->setMain(true);
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
				$currency->setMain(false);
				$needFlush = true;
			}
		}

		if ($main !== null) {
			$return = $main;
		} elseif ($first !== null) {
			$return = $first;
			$first->setMain(true);
			$needFlush = true;
		} else {
			$return = $this->createCurrency('USD', '$');
			$return->setMain(true);
			$return->setLocale('en');
			$needFlush = true;
		}
		if ($needFlush === true) {
			$this->entityManager->flush();
		}

		return $return;
	}
}
