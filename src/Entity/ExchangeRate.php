<?php

declare(strict_types=1);

namespace Baraja\Shop\Entity\Currency;


use Baraja\Doctrine\Identifier\IdentifierUnsigned;
use Doctrine\ORM\Mapping as ORM;
use Nette\Utils\Floats;

#[ORM\Entity]
#[ORM\Table(name: 'shop__currency')]
class ExchangeRate
{
	use IdentifierUnsigned;

	#[ORM\ManyToOne(targetEntity: Currency::class)]
	private Currency $currencySource;

	#[ORM\ManyToOne(targetEntity: Currency::class)]
	private Currency $currencyTarget;

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


	public function __construct(Currency $currencySource, Currency $currencyTarget)
	{
		$this->currencySource = $currencySource;
		$this->currencyTarget = $currencyTarget;
		$this->pair = self::formatPair($currencySource, $currencyTarget);
		$this->date = new \DateTimeImmutable;
	}


	public static function formatPair(Currency|string $source, Currency|string $target): string
	{
		if ($source instanceof Currency) {
			$source = $source->getCode();
		}
		if ($target instanceof Currency) {
			$target = $target->getCode();
		}
		Currency::validateCode($source);
		Currency::validateCode($target);

		return $source . $target;
	}


	public function getCurrencySource(): Currency
	{
		return $this->currencySource;
	}


	public function getCurrencyTarget(): Currency
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
		$value = (float) ($this->getMiddle() ?? ((($this->getBuy() ?? 0) + ($this->getSell() ?? 0)) / 2));
		if (Floats::isZero($value)) {
			throw new \LogicException(
				'Exchange rate can not be resolved for "' . $this->getPair() . '" '
				. 'and date "' . $this->getDate()->format('Y-m-d') . '".',
			);
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
			throw new \InvalidArgumentException(
				'Exchange rate: Buy value "' . $value . '" for "' . $this->getPair() . '" can not be negative.',
			);
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
			throw new \InvalidArgumentException(
				'Exchange rate: Sell value "' . $value . '" for "' . $this->getPair() . '" can not be negative.',
			);
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
			throw new \InvalidArgumentException(
				'Exchange rate: Middle value "' . $value . '" for "' . $this->getPair() . '" can not be negative.',
			);
		}
		$this->middle = $value;
	}
}
