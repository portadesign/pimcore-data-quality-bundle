<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle;

use Pimcore\Extension\Bundle\AbstractPimcoreBundle;
use Pimcore\Extension\Bundle\Installer\InstallerInterface;

class PortadesignDataQualityBundle extends AbstractPimcoreBundle
{
    /**
     * Must override: the default Bundle::getPath() resolves to src/ (this file's directory), not
     * the bundle root, so `assets:install` would look for src/public instead of public/ and
     * silently skip this bundle.
     */
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function getInstaller(): ?InstallerInterface
    {
        /** @var InstallerInterface|null $installer */
        $installer = $this->container?->get(Installer::class);

        return $installer;
    }
}
