<?php

declare(strict_types=1);

namespace Baraja\Shop\Entity\Currency;


final class CurrencyNumber
{
	public static function formatPrice(
		float $value,
		Currency $currency,
		?int $decimalPrecision = null,
		?string $schema = null,
		?string $thousandSeparator = null,
		?string $decimalSeparator = null,
		?string $symbol = null,
		bool $htmlCompatible = true,
	): string {
		$decimalPrecision ??= $currency->getDecimalPrecision();
		$decimalSeparator ??= $currency->getDecimalSeparator();
		$thousandSeparator ??= $currency->getThousandSeparator();
		$schema ??= $currency->getDefaultSchema();
		$symbol ??= $currency->getSymbol();

		if ($value < 0) {
			$value = -$value;
			$value = round($value, $decimalPrecision);
			$value = -$value;
		}

		$price = number_format($value, $decimalPrecision, $decimalSeparator, $thousandSeparator);
		$return = str_replace(['%NUM%', '%SYMBOL%'], [$price, $symbol], $schema);
		if ($htmlCompatible) {
			$return = str_replace(' ', '&nbsp;', $return);
		}

		return $return;
	}


	public static function format(
		float $value,
		int $decimalPrecision,
		?string $decimalSeparator = ',',
		?string $thousandSeparator = ' ',
	): string {
		return number_format($value, $decimalPrecision, $decimalSeparator, $thousandSeparator);
	}
}
