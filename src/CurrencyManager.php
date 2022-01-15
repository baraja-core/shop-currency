<?php

declare(strict_types=1);

namespace Baraja\Shop\Currency;


use Baraja\Doctrine\EntityManager;
use Baraja\EcommerceStandard\DTO\CurrencyInterface;
use Baraja\EcommerceStandard\DTO\ExchangeRateInterface;
use Baraja\EcommerceStandard\Service\CurrencyManagerInterface;
use Baraja\Localization\Localization;
use Baraja\Shop\Entity\Currency\Currency;
use Baraja\Shop\Entity\Currency\ExchangeRate;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

final class CurrencyManager implements CurrencyManagerInterface
{
	public const LOCALE_TO_CURRENCY = [
		'cs' => 'CZK',
		'sk' => 'EUR',
		'en' => 'EUR',
		'de' => 'EUR',
	];


	public function __construct(
		private EntityManager $entityManager,
	) {
	}


	public function getMainCurrency(): Currency
	{
		static $currency;
		if ($currency === null) {
			$entityMap = $this->entityManager->getUnitOfWork()->getIdentityMap();
			foreach ($entityMap[Currency::class] ?? [] as $entity) {
				if ($entity instanceof Currency && $entity->isMain()) {
					$currency = $entity;
				}
			}
			if ($currency === null) {
				try {
					/** @var Currency $currency */
					$currency = $this->entityManager->getRepository(Currency::class)
						->createQueryBuilder('currency')
						->where('currency.main = TRUE')
						->setMaxResults(1)
						->getQuery()
						->getSingleResult();
				} catch (NoResultException | NonUniqueResultException) {
					$currency = $this->fixCurrenciesAndReturnMain();
				}
			}
		}

		return $currency;
	}


	public function getRateToday(
		CurrencyInterface|string $source,
		CurrencyInterface|string $target,
	): ExchangeRateInterface
	{
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
					$date,
				);
			$this->entityManager->persist($rate);
			$this->entityManager->flush();

			return $rate;
		}
	}


	/**
	 * @return array<int, CurrencyInterface>
	 */
	public function getCurrencies(): array
	{
		return $this->entityManager->getRepository(Currency::class)
			->createQueryBuilder('currency')
			->orderBy('currency.main', 'DESC')
			->getQuery()
			->getResult();
	}


	/**
	 * @throws NoResultException|NonUniqueResultException
	 */
	public function getCurrency(CurrencyInterface|string $code): CurrencyInterface
	{
		if ($code instanceof CurrencyInterface) {
			return $code;
		}

		$return = $this->entityManager->getRepository(Currency::class)
			->createQueryBuilder('currency')
			->where('currency.code = :code')
			->setParameter('code', Currency::normalizeCode($code))
			->setMaxResults(1)
			->getQuery()
			->getSingleResult();
		assert($return instanceof Currency);

		return $return;
	}


	public function getByLocale(string $locale): CurrencyInterface
	{
		$locale = Localization::normalize($locale);
		try {
			/** @var Currency $currency */
			$currency = $this->entityManager->getRepository(Currency::class)
				->createQueryBuilder('currency')
				->where('currency.locale = :locale')
				->setParameter('locale', $locale)
				->setMaxResults(1)
				->getQuery()
				->getSingleResult();
		} catch (NoResultException | NonUniqueResultException) {
			$currencyCode = self::LOCALE_TO_CURRENCY[$locale] ?? null;
			if ($currencyCode !== null) {
				try {
					$currency = $this->getCurrency($currencyCode);
					assert($currency instanceof Currency);
					$currency->setLocale($locale);
					$this->entityManager->flush();
				} catch (NoResultException | NonUniqueResultException) {
					$currency = $this->getMainCurrency();
					assert($currency instanceof Currency);
					if ($currency->getLocale() === null) {
						$currency->setLocale($locale);
						$this->entityManager->flush();
					}
				}
			} else {
				throw new \LogicException(sprintf('Currency for locale "%s" does not exist.', $locale));
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


	private function fixCurrenciesAndReturnMain(): CurrencyInterface
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
