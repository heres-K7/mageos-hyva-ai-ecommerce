<?php
/**
 * Develo_TopRatedCollage
 */
declare(strict_types=1);

namespace Develo\TopRatedCollage\Observer;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Registers this module with the Hyvä Tailwind build so the utility classes used
 * in collage.phtml are scanned (and therefore not purged from the compiled CSS).
 *
 * @see app/etc/hyva-themes.json — populated by `bin/magento hyva:config:generate`
 */
class RegisterModuleForHyvaConfig implements ObserverInterface
{
    public function __construct(
        private readonly ComponentRegistrar $componentRegistrar
    ) {
    }

    public function execute(Observer $event): void
    {
        $config = $event->getData('config');
        $extensions = $config->hasData('extensions') ? $config->getData('extensions') : [];

        $path = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, 'Develo_TopRatedCollage');
        if ($path) {
            $extensions[] = ['src' => substr($path, strlen(BP) + 1)];
            $config->setData('extensions', $extensions);
        }
    }
}
