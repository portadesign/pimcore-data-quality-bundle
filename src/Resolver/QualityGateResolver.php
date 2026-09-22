<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Resolver;

use Pimcore\Model\DataObject\DataQualityConfiguration;
use Pimcore\Model\DataObject\DataQualityConfiguration\Listing as DataQualityConfigurationListing;
use Portadesign\DataQualityBundle\Contract\QualityGateConfigurationInterface;

class QualityGateResolver
{
    /**
     * @return list<QualityGateConfigurationInterface>
     */
    public function loadActiveGates(string $targetClassName): array
    {
        $listing = new DataQualityConfigurationListing();
        $listing->setCondition('targetClass = ?', [$targetClassName]);

        $gates = [];

        foreach ($listing->getObjects() as $configuration) {
            if (! $configuration instanceof DataQualityConfiguration) {
                continue;
            }

            foreach ($configuration->getGates() ?? [] as $gate) {
                if ($gate instanceof QualityGateConfigurationInterface && $gate->getActive() === true) {
                    $gates[] = $gate;
                }
            }
        }

        return $gates;
    }
}
