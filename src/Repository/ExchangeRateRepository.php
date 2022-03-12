<?php

declare(strict_types=1);

namespace Baraja\Shop\Repository;


use Baraja\EcommerceStandard\DTO\CurrencyInterface;
use Baraja\Shop\Entity\Currency\ExchangeRate;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

final class ExchangeRateRepository extends EntityRepository
{
	/** @var array<string, ExchangeRate> */
	private array $rateCache = [];


	/**
	 * @throws NoResultException|NonUniqueResultException
	 */
	public function findRatePair(
		CurrencyInterface|string $source,
		CurrencyInterface|string $target,
		\DateTimeInterface $date,
	): ExchangeRate {
		$pair = ExchangeRate::formatPair($source, $target);
		$cacheKey = sprintf('%s|%s', $pair, $date->format('Y-m-d'));
		if (isset($this->rateCache[$cacheKey])) {
			return $this->rateCache[$cacheKey];
		}
		$return = $this->createQueryBuilder('rate')
			->where('rate.pair = :pair')
			->andWhere('rate.date >= :date')
			->setParameter('pair', $pair)
			->setParameter('date', $date->format('Y-m-d') . ' 00:00:00')
			->orderBy('rate.date', 'ASC')
			->setMaxResults(1)
			->getQuery()
			->getSingleResult();
		assert($return instanceof ExchangeRate);
		$this->rateCache[$cacheKey] = $return;

		return $return;
	}
}
