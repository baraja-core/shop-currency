<?php

declare(strict_types=1);

namespace Baraja\Shop\Currency;


use Baraja\Doctrine\EntityManager;
use Baraja\Shop\Entity\Currency\Currency;
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


	public function getCurrency(string $code): Currency
	{
		Currency::validateCode($code);

		return $this->entityManager->getRepository(Currency::class)
			->createQueryBuilder('currency')
			->where('currency.code = :code')
			->setParameter('code', $code)
			->setMaxResults(1)
			->getQuery()
			->getSingleResult();
	}


	public function createCurrency(string $code, string $symbol, int $unit): Currency
	{
		$currency = new Currency($code, $symbol, $unit);
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
			$return = $this->createCurrency('USD', '$', 1);
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
