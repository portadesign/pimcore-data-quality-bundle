<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Service;

use Pimcore\Model\DataObject\Concrete;
use Portadesign\DataQualityBundle\Contract\QualityConfigurationInterface;
use Portadesign\DataQualityBundle\Dto\ProfileReadiness;
use Portadesign\DataQualityBundle\Dto\QualityProfile;
use Portadesign\DataQualityBundle\Dto\QualityResult;
use Psr\Log\LoggerInterface;

/**
 * Readiness per category × channel combination. A combination is configured by every profile whose
 * scope lies within it: the profile for exactly that category and channel, and profiles for the
 * channel alone (e.g. a channel-wide text length limit). Its readiness counts unscoped rules, rules
 * of that category, of that channel, and of those profiles, but never rules of the object's other
 * categories or channels. A combination no profile covers, or with no mandatory requirement, is
 * reported as not configured rather than ready.
 */
final class ProfileReadinessService
{
    public function __construct(
        private readonly QualityEvaluationService $evaluationService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param list<Concrete>                      $categories
     * @param list<Concrete>                      $channels
     * @param list<QualityConfigurationInterface> $activeRules Pre-fetched via QualityConfigurationResolver::loadActiveRules().
     * @param list<QualityProfile>                $profiles    Pre-fetched via QualityProfileResolver::loadProfiles().
     *
     * @return list<ProfileReadiness>
     */
    public function evaluate(Concrete $object, array $categories, array $channels, array $activeRules, array $profiles): array
    {
        $readiness = [];

        foreach ($channels as $channel) {
            foreach ($categories as $category) {
                $readiness[] = $this->evaluatePair($object, $category, $channel, $activeRules, $profiles);
            }
        }

        return $readiness;
    }

    /**
     * @param list<QualityConfigurationInterface> $activeRules
     * @param list<QualityProfile>                $profiles
     */
    public function evaluatePair(Concrete $object, Concrete $category, Concrete $channel, array $activeRules, array $profiles): ProfileReadiness
    {
        $pairIds = [(int) $category->getId(), (int) $channel->getId()];
        $exact = \array_values(\array_filter($profiles, static fn (QualityProfile $profile): bool => $profile->hasExactScope($pairIds)));
        $covering = \array_values(\array_filter($profiles, static fn (QualityProfile $profile): bool => $profile->appliesTo($pairIds)));
        \usort($covering, static fn (QualityProfile $a, QualityProfile $b): int => \count($b->scopeObjects) <=> \count($a->scopeObjects));
        $profileName = $covering !== [] ? \implode(' + ', \array_map(static fn (QualityProfile $profile): string => $profile->name, $covering)) : null;

        $readiness = static fn (string $status, ?QualityProfile $profile = null, ?QualityResult $result = null): ProfileReadiness => new ProfileReadiness(
            categoryId: (int) $category->getId(),
            categoryName: self::nameOf($category),
            channelId: (int) $channel->getId(),
            channelName: self::nameOf($channel),
            status: $status,
            profileId: $profile?->configurationId,
            profileName: $profileName,
            result: $result,
        );

        if (\count($exact) > 1) {
            return $readiness(ProfileReadiness::STATUS_AMBIGUOUS);
        }

        if ($covering === []) {
            return $readiness(ProfileReadiness::STATUS_NOT_CONFIGURED);
        }

        $profile = $covering[0];

        try {
            $result = $this->evaluationService->evaluate($object, [$category, $channel], $activeRules);
        } catch (\Throwable $exception) {
            $this->logger->error('ProfileReadinessService: failed evaluating profile {profileId} for object {objectId}: {message}', [
                'profileId' => $profile->configurationId,
                'objectId' => $object->getId(),
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            return $readiness(ProfileReadiness::STATUS_ERROR, $profile);
        }

        $status = match (true) {
            ! $result->configured, ! $this->hasProfileRequirement($result, $covering) => ProfileReadiness::STATUS_NOT_CONFIGURED,
            $result->mandatoryComplete => ProfileReadiness::STATUS_READY,
            default => ProfileReadiness::STATUS_INCOMPLETE,
        };

        return $readiness($status, $profile, $result);
    }

    /**
     * @param list<QualityProfile> $profiles
     */
    private function hasProfileRequirement(QualityResult $result, array $profiles): bool
    {
        $profileRuleIds = \array_merge(...\array_map(static fn (QualityProfile $profile): array => $profile->mandatoryRuleIds, $profiles));

        foreach ($result->checks as $check) {
            if ($check->level === 'mandatory' && \in_array($check->ruleId, $profileRuleIds, true)) {
                return true;
            }
        }

        return false;
    }

    private static function nameOf(Concrete $object): string
    {
        if (\method_exists($object, 'getName')) {
            $name = $object->getName();

            if (\is_string($name) && $name !== '') {
                return $name;
            }
        }

        return (string) $object->getKey();
    }
}
