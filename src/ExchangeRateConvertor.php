<?php

declare(strict_types=1);

namespace Baraja\Shop\Currency;


use Baraja\EcommerceStandard\DTO\CurrencyInterface;
use Baraja\EcommerceStandard\DTO\ExchangeRateInterface;
use Baraja\EcommerceStandard\DTO\PriceInterface;
use Baraja\EcommerceStandard\Service\ExchangeRateConvertorInterface;
use Baraja\Shop\Entity\Currency\ExchangeRate;
use Baraja\Shop\Repository\ExchangeRateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

final class ExchangeRateConvertor implements ExchangeRateConvertorInterface
{
	private ExchangeRateRepository $exchangeRateRepository;


	public function __construct(
		private EntityManagerInterface $entityManager,
		private CurrencyManager $currencyManager,
	) {
		$exchangeRateRepository = $entityManager->getRepository(ExchangeRate::class);
		assert($exchangeRateRepository instanceof ExchangeRateRepository);
		$this->exchangeRateRepository = $exchangeRateRepository;
	}


	/**
	 * A very fast and effective method for securely converting currency rates
	 * and specific prices from one currency to another.
	 *
	 * @param PriceInterface|numeric-string $price
	 * @return numeric-string
	 */
	public function convert(
		PriceInterface|string $price,
		CurrencyInterface|string $source,
		CurrencyInterface|string $target,
		?\DateTimeInterface $date = null,
	): string {
		$date ??= new \DateTimeImmutable('today');
		if ($price instanceof PriceInterface) {
			$source = $price->getCurrency();
			$price = $price->getValue();
		}

		$rate = (string) $this->getRate($source, $target, $date)->getValue();
		if ($rate === '0') {
			throw new \LogicException('Rate can not be null.');
		}

		return bcdiv($price, $rate, 2);
	}


	public function getRateToday(
		CurrencyInterface|string $source,
		CurrencyInterface|string $target,
	): ExchangeRateInterface {
		return $this->getRate(
			source: $source,
			target: $target,
			date: new \DateTimeImmutable('today'),
		);
	}


	public function getRate(
		CurrencyInterface|string $source,
		CurrencyInterface|string $target,
		\DateTimeInterface $date,
	): ExchangeRateInterface {
		$sourceEntity = $this->currencyManager->getCurrency($source);
		$targetEntity = $this->currencyManager->getCurrency($target);
		if ($sourceEntity->getCode() === $targetEntity->getCode()) {
			$rate = new ExchangeRate($sourceEntity, $targetEntity);
			$rate->setMiddle(1);

			return $rate;
		}

		$date = ExchangeRateFetcher::resolveDate($date);
		try {
			return $this->exchangeRateRepository->findRatePair($source, $target, $date);
		} catch (NoResultException | NonUniqueResultException) {
			$rate = (new ExchangeRateFetcher)
				->fetch(
					$this->currencyManager->getCurrency($source),
					$this->currencyManager->getCurrency($target),
					$date,
				);
			$this->entityManager->persist($rate);
			$this->entityManager->flush();

			return $rate;
		}
	}
}
