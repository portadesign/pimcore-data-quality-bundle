<?php

declare(strict_types=1);

namespace Basilicom\DataQualityBundle\Provider;

use Basilicom\DataQualityBundle\DefinitionsCollection\Factory\FieldDefinitionFactory;
use Basilicom\DataQualityBundle\DefinitionsCollection\FieldDefinition;
use Basilicom\DataQualityBundle\Exception\DataQualityException;
use Basilicom\DataQualityBundle\Exception\DefinitionException;
use Basilicom\DataQualityBundle\Model\ValidationResultBag;
use Basilicom\DataQualityBundle\View\DataQualityFieldViewModel;
use Basilicom\DataQualityBundle\View\DataQualityGroupViewModel;
use Basilicom\DataQualityBundle\View\DataQualityViewModel;
use Pimcore\Model\DataObject\AbstractObject;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\DataQualityConfig;
use Pimcore\Model\DataObject\Fieldcollection\Data\DataQualityFieldDefinition;
use Pimcore\Model\DataObject\Objectbrick;
use Pimcore\Model\Version as DataObjectVersion;
use Pimcore\Tool;

final class DataQualityProvider
{
    public function __construct(
        private readonly FieldDefinitionFactory $fieldDefinitionFactory
    ) {}

    private function setDataQualityPercent(AbstractObject $dataObject, array $groups, string $fieldName): int
    {
        $countTotal = 0;
        $countComplete = 0;

        /** @var DataQualityGroupViewModel $group */
        foreach ($groups as $group) {
            foreach ($group->getFields() as $field) {
                $countTotal = $countTotal + (1 * $field->getWeight());
                if ($field->isValid()) {
                    $countComplete = $countComplete + (1 * $field->getWeight());
                }
            }
        }

        $value = (int) round(($countComplete / $countTotal) * 100);
        $setter = 'set' . ucfirst($fieldName);

        if (method_exists($dataObject, $setter)) {
            DataObjectVersion::disable();

            $dataObject->$setter((float) $value);
            $dataObject->save();

            DataObjectVersion::enable();
        }

        return $value;
    }

    /**
     * @return DataQualityConfig[]
     */
    public function getDataQualityConfigs(?AbstractObject $dataObject): array
    {
        $dataQualityConfigList = new DataQualityConfig\Listing();

        $dataQualityConfigs = [];
        foreach ($dataQualityConfigList as $dataQualityConfig) {
            $dataQualityClass = $dataQualityConfig->getDataQualityClass();
            if ($dataObject && $dataObject->getClassId() === $dataQualityClass) {
                if ($dataQualityConfig->isPublished()) {
                    $dataQualityConfigs[$dataQualityConfig->getId()] = $dataQualityConfig;
                }
            }
        }

        return $dataQualityConfigs;
    }

    /**
     * @throws DataQualityException|DefinitionException
     */
    public function calculateDataQuality(AbstractObject $dataObject, DataQualityConfig $dataQualityConfig): DataQualityViewModel
    {
        $dataQualityRules = $this->getDataQualityRules($dataQualityConfig);

        $dataQualityGroups = [];

        foreach ($dataQualityRules as $dataQualityRuleGroupName => $dataQualityRuleGroup) {
            $dataQualityFields = [];

            /** @var FieldDefinition $fieldDefinition */
            foreach ($dataQualityRuleGroup as $fieldDefinition) {
                $getter = 'get' . $fieldDefinition->getFieldName();

                if (!method_exists($dataObject, $getter)) {
                    continue;
                }

                $isLocalizedField = false;
                $classFieldDefinition = $this->getClassFieldDefinition(
                    $dataObject,
                    $fieldDefinition->getFieldName(),
                    $isLocalizedField
                );

                $result = match (true) {
                    $this->isObjectBricks($classFieldDefinition) => $this->validateObjectBricks(
                        $dataObject,
                        $getter,
                        $fieldDefinition
                    ),
                    $isLocalizedField => $this->validateLanguages(
                        $dataObject,
                        $getter,
                        $fieldDefinition,
                        $classFieldDefinition
                    ),
                    default => $this->validateField(
                        $dataObject,
                        $getter,
                        $fieldDefinition,
                        $classFieldDefinition
                    ),
                };

                $dataQualityFields[] = new DataQualityFieldViewModel(
                    $fieldDefinition->getTitle(),
                    $fieldDefinition->getWeight(),
                    $result->isValid(),
                    $fieldDefinition->getLanguage(),
                    $result->getData()
                );
            }

            $dataQualityGroups[] = new DataQualityGroupViewModel(
                $dataQualityRuleGroupName,
                $dataQualityFields
            );
        }

        $percent = $this->setDataQualityPercent($dataObject, $dataQualityGroups, $dataQualityConfig->getDataQualityField());

        return new DataQualityViewModel(
            $dataQualityConfig->getDataQualityName(),
            $percent,
            $dataQualityGroups
        );
    }

    private function getDataQualityRules(DataQualityConfig $dataQualityConfig): array
    {
        $fieldCollection = $dataQualityConfig->getDataQualityRules();
        $items = $fieldCollection->getItems();

        $rules = [];

        /** @var DataQualityFieldDefinition $item */
        foreach ($items as $item) {
            $group = empty($item->getGroup()) ? FieldDefinitionFactory::DEFAULT_GROUP : $item->getGroup();
            $rules[$group][] = $this->fieldDefinitionFactory->get($item);
        }

        return $rules;
    }

    /**
     * @throws DataQualityException
     */
    private function getClassFieldDefinition(AbstractObject $dataObject, string $fieldName, bool &$isLocalizedField): Data
    {
        $classFieldDefinition = $dataObject->getClass()->getFieldDefinition($fieldName);
        if (empty($classFieldDefinition)) {
            $localizedFields = $dataObject->getClass()->getFieldDefinition('localizedfields');
            if ($localizedFields) {
                $classFieldDefinition = $localizedFields->getFieldDefinition($fieldName);
                $isLocalizedField = true;
            } else {
                throw new DataQualityException('fieldtype for field ' . $fieldName . ' is not supported.');
            }
        }

        return $classFieldDefinition;
    }

    private function isObjectBricks(Data $fieldDefinition): bool
    {
        return $fieldDefinition->getFieldtype() === 'objectbricks';
    }

    private function validateObjectBricks(
        AbstractObject $dataObject,
        string $getter,
        FieldDefinition $fieldDefinition
    ): ValidationResultBag {
        $valid = true;
        $data = [];
        /** @var Objectbrick $brickContainer */
        $brickContainer = $dataObject->$getter();
        foreach ($brickContainer->getItems() as $brickItem) {
            $brickFieldDefinitions = $brickItem->getDefinition()->getFieldDefinitions();

            foreach ($brickFieldDefinitions as $brickField => $brickFieldValue) {
                $result = $fieldDefinition->getConditionClass()->validate(
                    $brickItem->get($brickField),
                    $brickFieldValue,
                    $fieldDefinition->getParameters()
                );

                $data[$brickField] = $result->isValid();
                $valid = $valid && $result->isValid();
            }
        }

        return new ValidationResultBag($valid, $data);
    }

    private function validateLanguages(
        AbstractObject $dataObject,
        string $getter,
        FieldDefinition $fieldDefinition,
        Data $classFieldDefinition
    ): ValidationResultBag {
        $languages = Tool::getValidLanguages();
        $validLanguages = [];

        $fieldLanguage = $fieldDefinition->getLanguage();
        if (!empty($fieldLanguage) && Tool::isValidLanguage($fieldLanguage)) {
            $value = $dataObject->$getter($fieldLanguage);
            $result = $fieldDefinition->getConditionClass()->validate(
                $value,
                $classFieldDefinition,
                $fieldDefinition->getParameters()
            );
            $valid = $result->isValid();
        } else {
            $valid = true;
            foreach ($languages as $language) {
                $value = $dataObject->$getter($language);
                $result = $fieldDefinition->getConditionClass()->validate(
                    $value,
                    $classFieldDefinition,
                    $fieldDefinition->getParameters()
                );

                $validLanguages[$language] = $result->isValid();

                $valid = $valid && $result->isValid();
            }
        }

        return new ValidationResultBag($valid, $validLanguages);
    }

    private function validateField(
        AbstractObject $dataObject,
        string $getter,
        FieldDefinition $fieldDefinition,
        Data $classFieldDefinition
    ): ValidationResultBag {
        $value = $dataObject->$getter();
        $result = $fieldDefinition->getConditionClass()->validate(
            $value,
            $classFieldDefinition,
            $fieldDefinition->getParameters()
        );

        return $result;
    }
}
