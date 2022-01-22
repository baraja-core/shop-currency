<?php

declare(strict_types=1);

namespace Baraja\Shop\Repository;


use Baraja\Shop\Entity\Currency\Currency;
use Doctrine\ORM\EntityRepository;

final class CurrencyRepository extends EntityRepository
{
	/**
	 * @return array<int, Currency>
	 */
	public function getAllCurrencies(): array
	{
		/** @var array<int, Currency> $list */
		$list = $this->createQueryBuilder('currency')
			->orderBy('currency.main', 'DESC')
			->getQuery()
			->getResult();

		return $list;
	}
}
