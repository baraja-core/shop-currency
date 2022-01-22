<?php

declare(strict_types=1);

namespace Baraja\Shop\Entity\Currency;


use Baraja\EcommerceStandard\DTO\CurrencyInterface;
use Baraja\EcommerceStandard\DTO\ExchangeRateInterface;
use Baraja\Shop\Repository\ExchangeRateRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExchangeRateRepository::class)]
#[ORM\Table(name: 'shop__currency_rate')]
#[ORM\Index(columns: ['pair', 'date'], name: 'shop__currency_rate_pair')]
class ExchangeRate implements ExchangeRateInterface
{
	#[ORM\Id]
	#[ORM\Column(type: 'integer', unique: true, options: ['unsigned' => true])]
	#[ORM\GeneratedValue]
	protected int $id;

	#[ORM\ManyToOne(targetEntity: Currency::class)]
	private CurrencyInterface $currencySource;

	#[ORM\ManyToOne(targetEntity: Currency::class)]
	private CurrencyInterface $currencyTarget;

	#[ORM\Column(type: 'string', length: 6)]
	private string $pair;

	#[ORM\Column(type: 'datetime')]
	private \DateTimeInterface $date;

	#[ORM\Column(type: 'float', nullable: true)]
	private ?float $buy = null;

	#[ORM\Column(type: 'float', nullable: true)]
	private ?float $sell = null;

	#[ORM\Column(type: 'float', nullable: true)]
	private ?float $middle = null;


	public function __construct(CurrencyInterface $currencySource, CurrencyInterface $currencyTarget)
	{
		$this->currencySource = $currencySource;
		$this->currencyTarget = $currencyTarget;
		$this->pair = self::formatPair($currencySource, $currencyTarget);
		$this->date = new \DateTimeImmutable;
	}


	public static function formatPair(CurrencyInterface|string $source, CurrencyInterface|string $target): string
	{
		if ($source instanceof CurrencyInterface) {
			$source = $source->getCode();
		}
		if ($target instanceof CurrencyInterface) {
			$target = $target->getCode();
		}
		$source = Currency::normalizeCode($source);
		$target = Currency::normalizeCode($target);

		return $source . $target;
	}


	public function getId(): int
	{
		assert($this->id > 0);

		return $this->id;
	}


	public function getCurrencySource(): CurrencyInterface
	{
		return $this->currencySource;
	}


	public function getCurrencyTarget(): CurrencyInterface
	{
		return $this->currencyTarget;
	}


	public function getPair(): string
	{
		return $this->pair;
	}


	public function getDate(): \DateTimeInterface
	{
		return $this->date;
	}


	public function getValue(): float
	{
		$value = $this->getMiddle() ?? ((($this->getBuy() ?? 0.0) + ($this->getSell() ?? 0.0)) / 2);
		if (abs($value) < 1e-10) { // is zero?
			throw new \LogicException(sprintf('Exchange rate can not be resolved for "%s" and date "%s".',
				$this->getPair(),
				$this->getDate()->format('Y-m-d'),
			));
		}

		return $value;
	}


	public function getBuy(): ?float
	{
		return $this->buy;
	}


	public function setBuy(?float $value): void
	{
		if ($value !== null && $value < 0) {
			throw new \InvalidArgumentException(sprintf('Exchange rate: Buy value "%s" for "%s" can not be negative.',
				$value,
				$this->getPair(),
			));
		}
		$this->buy = $value;
	}


	public function getSell(): ?float
	{
		return $this->sell;
	}


	public function setSell(?float $value): void
	{
		if ($value !== null && $value < 0) {
			throw new \InvalidArgumentException(sprintf('Exchange rate: Sell value "%s" for "%s" can not be negative.',
				$value,
				$this->getPair(),
			));
		}
		$this->sell = $value;
	}


	public function getMiddle(): ?float
	{
		return $this->middle;
	}


	public function setMiddle(?float $value): void
	{
		if ($value !== null && $value < 0) {
			throw new \InvalidArgumentException(sprintf('Exchange rate: Middle value "%s" for "%s" can not be negative.',
				$value,
				$this->getPair(),
			));
		}
		$this->middle = $value;
	}
}
