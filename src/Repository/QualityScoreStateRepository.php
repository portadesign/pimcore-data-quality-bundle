<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Repository;

use Pimcore\Db;
use Portadesign\DataQualityBundle\Contract\QualityScoreStateRepositoryInterface;
use Portadesign\DataQualityBundle\Installer;

final class QualityScoreStateRepository implements QualityScoreStateRepositoryInterface
{
    /**
     * @return array{mandatory_complete: bool, score: float}|null
     */
    public function getPreviousState(int $objectId, string $scopeType, int $scopeId): ?array
    {
        $row = Db::get()->fetchAssociative(
            'SELECT mandatory_complete, score FROM `' . Installer::SCORES_TABLE . '`
             WHERE object_id = ? AND scope_type = ? AND scope_id = ?',
            [$objectId, $scopeType, $scopeId],
        );

        if ($row === false) {
            return null;
        }

        return [
            'mandatory_complete' => (bool) $row['mandatory_complete'],
            'score' => (float) $row['score'],
        ];
    }

    public function upsertState(int $objectId, string $scopeType, int $scopeId, bool $mandatoryComplete, float $score): void
    {
        Db::get()->executeStatement(
            'INSERT INTO `' . Installer::SCORES_TABLE . '`
                (object_id, scope_type, scope_id, mandatory_complete, score, updated_at)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                mandatory_complete = VALUES(mandatory_complete),
                score = VALUES(score),
                updated_at = VALUES(updated_at)',
            [
                $objectId,
                $scopeType,
                $scopeId,
                $mandatoryComplete ? 1 : 0,
                $score,
                (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ],
        );
    }
}
