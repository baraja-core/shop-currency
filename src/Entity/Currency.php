<?php

declare(strict_types=1);

namespace Baraja\Shop\Entity\Currency;


use Baraja\EcommerceStandard\DTO\CurrencyInterface;
use Baraja\Localization\Localization;
use Baraja\Shop\Price\Price;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'shop__currency')]
class Currency implements CurrencyInterface
{
	#[ORM\Id]
	#[ORM\Column(type: 'integer', unique: true, options: ['unsigned' => true])]
	#[ORM\GeneratedValue]
	protected int $id;

	#[ORM\Column(type: 'string', length: 3, unique: true)]
	private string $code;

	#[ORM\Column(type: 'string', length: 3)]
	private string $symbol;

	#[ORM\Column(type: 'boolean')]
	private bool $main = false;

	#[ORM\Column(type: 'boolean')]
	private bool $active = true;

	#[ORM\Column(type: 'boolean')]
	private bool $rateLock = false;

	#[ORM\Column(type: 'string', length: 2, nullable: true)]
	private ?string $locale = null;

	#[ORM\Column(type: 'string', length: 2)]
	private string $thousandSeparator = ' ';

	#[ORM\Column(type: 'string', length: 2)]
	private string $decimalSeparator = ',';

	#[ORM\Column(type: 'integer')]
	private int $decimalPrecision = 2;

	#[ORM\Column(type: 'string', length: 48)]
	private string $defaultSchema = '%NUM% %SYMBOL%';

	#[ORM\Column(type: 'datetime')]
	private \DateTimeInterface $insertedDate;

	#[ORM\Column(type: 'datetime')]
	private \DateTimeInterface $updatedDate;


	public function __construct(string $code, string $symbol)
	{
		$this->setCode($code);
		$this->setSymbol($symbol);
		$this->insertedDate = new \DateTimeImmutable;
		$this->updatedDate = new \DateTimeImmutable;
	}


	public static function normalizeCode(string $code): string
	{
		$code = strtoupper(trim($code));
		if ($code === '') {
			throw new \InvalidArgumentException('Currency code is required.');
		}
		if (preg_match('/^[A-Z]{3}/', $code) !== 1) {
			throw new \InvalidArgumentException(sprintf(
				'Currency code must be 3 exactly chars long, for example "USD", but "%s" given.',
				$code,
			));
		}

		return $code;
	}


	public function getId(): int
	{
		return $this->id;
	}


	/**
	 * @param numeric-string $price
	 */
	public function renderPrice(string $price, bool $html = false): string
	{
		$price = $price === '' ? '0' : $price;
		$value = Price::normalize($price, $this->decimalPrecision);
		if ($this->decimalSeparator !== '.') {
			$value = str_replace('.', $this->decimalSeparator, $value);
		}
		// TODO: $this->getThousandSeparator()

		$return = str_replace(
			['%NUM%', '%SYMBOL%'],
			[$value, $this->getSymbol()],
			$this->getDefaultSchema(),
		);
		if ($html) {
			$return = str_replace(' ', '&nbsp;', $return);
		}

		return $return;
	}


	public function getCode(): string
	{
		return $this->code;
	}


	public function setCode(string $code): void
	{
		$this->code = self::normalizeCode($code);
	}


	public function getSymbol(): string
	{
		return $this->symbol;
	}


	public function setSymbol(string $symbol): void
	{
		$this->symbol = $symbol;
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


	public function isActive(): bool
	{
		return $this->active;
	}


	public function setActive(bool $active): void
	{
		$this->active = $active;
	}


	public function isRateLock(): bool
	{
		return $this->rateLock;
	}


	public function setRateLock(bool $rateLock): void
	{
		$this->rateLock = $rateLock;
	}


	public function getThousandSeparator(): string
	{
		return $this->thousandSeparator;
	}


	public function setThousandSeparator(string $thousandSeparator): void
	{
		$this->thousandSeparator = $thousandSeparator;
	}


	public function getDecimalSeparator(): string
	{
		return $this->decimalSeparator;
	}


	public function setDecimalSeparator(string $decimalSeparator): void
	{
		$this->decimalSeparator = $decimalSeparator;
	}


	public function getDecimalPrecision(): int
	{
		return $this->decimalPrecision;
	}


	public function setDecimalPrecision(int $decimalPrecision): void
	{
		$this->decimalPrecision = $decimalPrecision;
	}


	public function getDefaultSchema(): string
	{
		return $this->defaultSchema;
	}


	public function setDefaultSchema(string $defaultSchema): void
	{
		$this->defaultSchema = $defaultSchema;
	}


	public function getInsertedDate(): \DateTimeInterface
	{
		return $this->insertedDate;
	}


	public function getUpdatedDate(): \DateTimeInterface
	{
		return $this->updatedDate;
	}
}
