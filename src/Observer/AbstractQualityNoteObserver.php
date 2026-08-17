<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Observer;

use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\Element\Note;
use Portadesign\DataQualityBundle\Contract\QualityObserverInterface;
use Portadesign\DataQualityBundle\Event\QualityThresholdCrossedEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Shared write-a-Note-on-threshold-crossed logic for ChannelNoteObserver/CategoryNoteObserver, so
 * the two axes can't drift out of sync: subclasses only supply which element to target and the
 * scope label used in the note wording.
 */
abstract class AbstractQualityNoteObserver implements QualityObserverInterface
{
    public function __construct(
        #[Autowire('%portadesign_data_quality.note_author_user_id%')]
        private readonly int $noteAuthorUserId,
    ) {
    }

    public function onThresholdReached(QualityThresholdCrossedEvent $event): void
    {
        $this->writeNote($event, reached: true);
    }

    public function onThresholdLost(QualityThresholdCrossedEvent $event): void
    {
        $this->writeNote($event, reached: false);
    }

    abstract protected function getScope(QualityThresholdCrossedEvent $event): ?Concrete;

    /**
     * Lowercase noun used in the note wording, e.g. "channel" or "category".
     */
    abstract protected function getScopeLabel(): string;

    private function writeNote(QualityThresholdCrossedEvent $event, bool $reached): void
    {
        $scope = $this->getScope($event);

        if ($scope === null) {
            return;
        }

        $product = $event->getObject();
        $score = $event->getScore();
        $label = $this->getScopeLabel();

        $note = new Note();
        $note->setElement($scope);
        $note->setType('Quality threshold');
        $note->setTitle($reached
            ? \sprintf('%s — quality complete for this %s', $product->getKey(), $label)
            : \sprintf('%s — no longer meets mandatory requirements for this %s', $product->getKey(), $label));
        $note->setDescription($reached
            ? \sprintf('Product "%s" now satisfies all mandatory quality rules for this %s (score: %.2f).', $product->getKey(), $label, $score)
            : \sprintf('Product "%s" no longer satisfies all mandatory quality rules for this %s (score: %.2f).', $product->getKey(), $label, $score));
        $note->setDate(\time());
        $note->setUser($this->noteAuthorUserId);
        $note->save();
    }
}
