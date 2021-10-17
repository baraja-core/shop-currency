<?php

declare(strict_types=1);

namespace Baraja\Shop\Currency;


interface CurrencyManagerAccessor
{
	public function get(): CurrencyManager;
}
