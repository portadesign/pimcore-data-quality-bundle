<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Tests\Fixture;

use Pimcore\Model\DataObject\Concrete;
use Portadesign\DataQualityBundle\Contract\QualityConfigurationInterface;

final class FakeQualityRule implements QualityConfigurationInterface
{
    public function __construct(
        private readonly ?int $id = 1,
        private readonly ?string $name = 'Fake rule',
        private readonly ?Concrete $channel = null,
        private readonly ?Concrete $category = null,
        private readonly ?string $targetType = 'coreField',
        private readonly ?string $targetKey = 'ean',
        private readonly ?string $requirementLevel = 'mandatory',
        private readonly ?float $weight = 1.0,
        private readonly ?string $message = null,
        private readonly ?string $ruleType = 'presence',
        private readonly ?bool $active = true,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getChannel(): ?Concrete
    {
        return $this->channel;
    }

    public function getCategory(): ?Concrete
    {
        return $this->category;
    }

    public function getTargetType(): ?string
    {
        return $this->targetType;
    }

    public function getTargetKey(): ?string
    {
        return $this->targetKey;
    }

    public function getRequirementLevel(): ?string
    {
        return $this->requirementLevel;
    }

    public function getWeight(): ?float
    {
        return $this->weight;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getRuleType(): ?string
    {
        return $this->ruleType;
    }

    public function getActive(): ?bool
    {
        return $this->active;
    }
}
