<?php

declare(strict_types=1);

namespace Baraja\Shop\Currency;


use Nette\DI\CompilerExtension;

final class ShopCurrencyExtension extends CompilerExtension
{
	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();

		$builder->addDefinition($this->prefix('currencyManager'))
			->setFactory(CurrencyManager::class);

		$builder->addAccessorDefinition($this->prefix('currencyManagerAccessor'))
			->setImplement(CurrencyManagerAccessor::class);
	}
}
