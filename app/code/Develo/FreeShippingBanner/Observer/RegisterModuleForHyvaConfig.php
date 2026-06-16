<?php
/**
 * Develo_FreeShippingBanner
 */
declare(strict_types=1);

namespace Develo\FreeShippingBanner\Observer;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Registers this module with the Hyvä Tailwind build.
 *
 * `bin/magento hyva:config:generate` dispatches `hyva_config_generate_before`
 * to collect every module whose frontend templates should be scanned for
 * Tailwind classes. We append our own module path so the utility classes used
 * in banner.phtml (e.g. bg-primary, text-on-primary) are emitted into the
 * compiled theme CSS instead of being purged.
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

        $path = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, 'Develo_FreeShippingBanner');
        if ($path) {
            // Paths in hyva-themes.json are stored relative to the Magento root (BP).
            $extensions[] = ['src' => substr($path, strlen(BP) + 1)];
            $config->setData('extensions', $extensions);
        }
    }
}
