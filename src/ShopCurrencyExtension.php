<?php

declare(strict_types=1);

namespace Baraja\Shop\Currency;


use Baraja\Doctrine\ORM\DI\OrmAnnotationsExtension;
use Baraja\Plugin\PluginComponentExtension;
use Nette\DI\CompilerExtension;

final class ShopCurrencyExtension extends CompilerExtension
{
	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();

		PluginComponentExtension::defineBasicServices($builder);
		OrmAnnotationsExtension::addAnnotationPathToManager(
			$builder,
			'Baraja\Shop\Entity\Currency',
			__DIR__ . '/Entity',
		);

		$builder->addDefinition($this->prefix('currencyManager'))
			->setFactory(CurrencyManager::class);

		$builder->addAccessorDefinition($this->prefix('currencyManagerAccessor'))
			->setImplement(CurrencyManagerAccessor::class);
	}
}
