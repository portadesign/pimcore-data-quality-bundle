<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Dto;

/**
 * Readiness of one object for one category × channel combination.
 */
final class ProfileReadiness
{
    public const STATUS_READY = 'ready';
    public const STATUS_INCOMPLETE = 'incomplete';
    /** No profile exists for the combination, or the profile has no mandatory requirement. */
    public const STATUS_NOT_CONFIGURED = 'not_configured';
    /** More than one profile has exactly this scope; none is picked silently. */
    public const STATUS_AMBIGUOUS = 'ambiguous';
    public const STATUS_ERROR = 'error';

    public function __construct(
        public readonly int $categoryId,
        public readonly string $categoryName,
        public readonly int $channelId,
        public readonly string $channelName,
        public readonly string $status,
        public readonly ?int $profileId = null,
        public readonly ?string $profileName = null,
        public readonly ?QualityResult $result = null,
    ) {
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'categoryId' => $this->categoryId,
            'categoryName' => $this->categoryName,
            'channelId' => $this->channelId,
            'channelName' => $this->channelName,
            'status' => $this->status,
            'profileId' => $this->profileId,
            'profileName' => $this->profileName,
            ...($this->result?->toArray() ?? ['score' => null, 'mandatoryComplete' => false, 'configured' => false, 'checks' => []]),
        ];
    }
}
