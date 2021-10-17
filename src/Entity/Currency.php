<?php

declare(strict_types=1);

namespace Baraja\Shop\Entity\Currency;


use Baraja\Doctrine\Identifier\IdentifierUnsigned;
use Baraja\Localization\Localization;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'shop__currency')]
class Currency
{
	use IdentifierUnsigned;

	#[ORM\Column(type: 'string', length: 3, unique: true)]
	private string $code;

	#[ORM\Column(type: 'string', length: 3)]
	private string $symbol;

	#[ORM\Column(type: 'int')]
	private int $unit = 1;

	#[ORM\Column(type: 'bool')]
	private bool $main = false;

	#[ORM\Column(type: 'string', length: 2, nullable: true)]
	private ?string $locale = null;

	#[ORM\Column(type: 'datetime')]
	private \DateTimeInterface $insertedDate;


	public function __construct(string $code, string $symbol, int $unit = 1)
	{
		$this->setCode($code);
		$this->setSymbol($symbol);
		$this->setUnit($unit);
		$this->insertedDate = new \DateTimeImmutable;
	}


	public static function validateCode(string $code): void
	{
		$code = strtoupper(trim($code));
		if ($code === '') {
			throw new \InvalidArgumentException('Currency code is required.');
		}
		if (preg_match('/^[A-Z]{3}/', $code) !== 1) {
			throw new \InvalidArgumentException(
				'Currency code must be 3 exactly chars long, '
				. 'for example "USD", but "' . $code . '" given.',
			);
		}
	}


	public function getCode(): string
	{
		return $this->code;
	}


	public function setCode(string $code): void
	{
		$code = strtoupper(trim($code));
		self::validateCode($code);
		$this->code = $code;
	}


	public function getSymbol(): string
	{
		return $this->symbol;
	}


	public function setSymbol(string $symbol): void
	{
		$this->symbol = $symbol;
	}


	public function getUnit(): int
	{
		return $this->unit;
	}


	public function setUnit(int $unit): void
	{
		if ($unit <= 0) {
			throw new \InvalidArgumentException('Unit can not be negative or zero.');
		}
		$this->unit = $unit;
	}


	public function isMain(): bool
	{
		return $this->main;
	}


	public function setMain(bool $main): void
	{
		$this->main = $main;
	}


	public function getLocale(): ?string
	{
		return $this->locale;
	}


	public function setLocale(?string $locale): void
	{
		if ($locale !== null) {
			$locale = Localization::normalize($locale);
		}
		$this->locale = $locale;
	}


	public function getInsertedDate(): \DateTimeInterface
	{
		return $this->insertedDate;
	}
}
