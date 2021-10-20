<?php

declare(strict_types=1);

namespace Baraja\Shop\Currency;


use Baraja\Doctrine\EntityManager;
use Baraja\Shop\Entity\Currency\Currency;

final class ExchangeRateUpdater
{
	private const DEFAULT_CURRENCIES = [
		['CZK', 'Kč', '%NUM% %SYMBOL%'],
		['EUR', '€', '%SYMBOL% %NUM%'],
		['USD', '$', '%SYMBOL% %NUM%'],
		['GBP', '£', '%NUM% %SYMBOL%'],
	];

	private ExchangeRateFetcher $fetcher;


	public function __construct(
		private EntityManager $entityManager,
	) {
		$this->fetcher = new ExchangeRateFetcher;
	}


	public function updateAll(?\DateTimeInterface $date = null): void
	{
		if ($date === null) {
			$date = new \DateTimeImmutable('today');
		}
		if ($this->isEmpty()) {
			$this->createDefaultConfiguration();
		}
		$currencies = $this->getAllCurrencies();
		foreach ($currencies as $source) {
			foreach ($currencies as $target) {
				if (
					$target->isRateLock()
					|| $source->getCode() === $target->getCode()
				) {
					continue;
				}
				$this->entityManager->persist(
					$this->fetcher->fetch($source, $target, $date)
				);
			}
		}
		$this->entityManager->flush();
	}


	private function createDefaultConfiguration(): void
	{
		if ($this->isEmpty() === false) {
			return;
		}
		$isMain = true;
		foreach (self::DEFAULT_CURRENCIES as $c) {
			$currency = new Currency($c[0], $c[1]);
			$currency->setDefaultSchema($c[2]);
			$currency->setMain($isMain);
			if ($isMain) {
				$isMain = false;
			}
			$this->entityManager->persist($currency);
		}

		$this->entityManager->flush();
	}


	private function isEmpty(): bool
	{
		/** @var array<int, array{id: int}> $ids */
		$ids = $this->entityManager->getRepository(Currency::class)
			->createQueryBuilder('c')
			->select('PARTIAL c.{id}')
			->getQuery()
			->getArrayResult();

		return $ids === [];
	}


	/**
	 * @return array<int, Currency>
	 */
	private function getAllCurrencies(): array
	{
		return $this->entityManager->getRepository(Currency::class)
			->createQueryBuilder('c')
			->where('c.active = TRUE')
			->getQuery()
			->getResult();
	}
}
