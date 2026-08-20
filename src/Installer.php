<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle;

use Pimcore\Db;
use Pimcore\Extension\Bundle\Installer\AbstractInstaller;
use Pimcore\Extension\Bundle\Installer\Exception\InstallationException;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Service;
use Pimcore\Model\DataObject\Fieldcollection;
use Pimcore\Model\User\Permission\Definition as PermissionDefinition;

class Installer extends AbstractInstaller
{
    private const CLASS_ID = 'data_quality_configuration';
    private const CLASS_NAME = 'DataQualityConfiguration';
    private const FIELD_COLLECTION_KEY = 'DataQualityRule';
    public const SCORES_TABLE = 'portadesign_data_quality_scores';
    public const PERMISSION_KEY = 'portadesign_data_quality_report';
    private const PERMISSION_CATEGORY = 'Data Quality';

    public function install(): void
    {
        // Field collection must exist before the class is (re-)imported: the class' `rules`
        // field references it via allowedTypes, and on an upgrade the class layout is replaced
        // wholesale (see installClassFromJson()'s docblock).
        $this->installFieldCollectionFromJson();
        $this->installClassFromJson();
        $this->installScoresTable();
        $this->installPermission();
    }

    /**
     * Destructive and non-reversible: deletes the DataQualityConfiguration class definition and
     * the DataQualityRule field collection definition outright, which takes every business rule
     * stored in any DataQualityConfiguration object with it (and drops the scores table). Do not
     * uninstall expecting a subsequent install() to bring rules back.
     */
    public function uninstall(): void
    {
        $definition = ClassDefinition::getById(self::CLASS_ID);

        if ($definition !== null) {
            $definition->delete();
        }

        $fieldCollection = Fieldcollection\Definition::getByKey(self::FIELD_COLLECTION_KEY);

        if ($fieldCollection !== null) {
            $fieldCollection->delete();
        }

        Db::get()->executeStatement('DROP TABLE IF EXISTS `' . self::SCORES_TABLE . '`');

        Db::get()->executeStatement(
            'DELETE FROM users_permission_definitions WHERE `key` = ?',
            [self::PERMISSION_KEY],
        );
    }

    /**
     * Must check the class, the field collection, AND the table — any of these could ship in a
     * later version of an already-installed bundle, so checking only the class would make
     * canBeInstalled() stay false and the others would never get (re-)installed for upgrades.
     */
    public function isInstalled(): bool
    {
        return ClassDefinition::getById(self::CLASS_ID) !== null
            && Fieldcollection\Definition::getByKey(self::FIELD_COLLECTION_KEY) !== null
            && $this->scoresTableExists()
            && PermissionDefinition::getByKey(self::PERMISSION_KEY) !== null;
    }

    public function canBeInstalled(): bool
    {
        return ! $this->isInstalled();
    }

    public function canBeUninstalled(): bool
    {
        return ClassDefinition::getById(self::CLASS_ID) !== null
            || Fieldcollection\Definition::getByKey(self::FIELD_COLLECTION_KEY) !== null
            || $this->scoresTableExists()
            || PermissionDefinition::getByKey(self::PERMISSION_KEY) !== null;
    }

    public function needsReloadAfterInstall(): bool
    {
        return true;
    }

    private function getClassDefinitionPath(): string
    {
        $path = \sprintf(
            '%s/Resources/install/class_%s_export.json',
            \dirname(__DIR__),
            self::CLASS_NAME
        );

        $path = \realpath($path);

        if ($path === false || !\is_file($path)) {
            throw new InstallationException(\sprintf(
                'Class export for class "%s" was expected in "%s" but file does not exist',
                self::CLASS_NAME,
                $path
            ));
        }

        return $path;
    }

    /**
     * Creates the class on first install, or upgrades it in place on every subsequent install:
     * Service::importClassDefinitionFromJson() calls $class->setLayoutDefinitions() with the tree
     * built from the JSON, which replaces the entire field layout wholesale rather than merging —
     * so re-running this against a class still on the old flat (name/channel/category/...) schema
     * correctly transforms it into the new (targetClass + rules field collection) schema. Any
     * DataObject rows still holding old-schema column data lose that data as part of the ALTER;
     * see SeedQualityConfigurationRulesCommand in the playground project for cleanup of the
     * resulting orphaned legacy objects.
     *
     * An existing class found by CLASS_ID (unchanged across the QualityConfiguration ->
     * DataQualityConfiguration rename) whose *name* is still the old one is explicitly renamed via
     * ClassDefinition::rename() before the JSON import: importClassDefinitionFromJson()'s
     * importPropertyNames list does not include "name", so plain setName()+save() would leave the
     * old var/classes/definition_QualityConfiguration.php / PHP class file behind and never update
     * existing DataObject rows' className column. rename() handles all of that (deletes the old
     * generated PHP class files, updates existing rows to the new className, then saves).
     */
    private function installClassFromJson(): void
    {
        $path = $this->getClassDefinitionPath();

        $class = ClassDefinition::getById(self::CLASS_ID);

        if ($class === null) {
            $class = new ClassDefinition();
            $class->setId(self::CLASS_ID);
            $class->setName(self::CLASS_NAME);
        } elseif ($class->getName() !== self::CLASS_NAME) {
            $class->rename(self::CLASS_NAME);
        }

        $data = \file_get_contents($path);

        if ($data === false) {
            throw new InstallationException(\sprintf(
                'Failed to read class export file "%s"',
                $path
            ));
        }

        $success = Service::importClassDefinitionFromJson($class, $data, false, true);

        if (!$success) {
            throw new InstallationException(\sprintf(
                'Failed to create class "%s"',
                self::CLASS_NAME
            ));
        }
    }

    private function getFieldCollectionDefinitionPath(): string
    {
        $path = \sprintf(
            '%s/Resources/install/fieldcollection_%s_export.json',
            \dirname(__DIR__),
            self::FIELD_COLLECTION_KEY
        );

        $path = \realpath($path);

        if ($path === false || !\is_file($path)) {
            throw new InstallationException(\sprintf(
                'Field collection export for "%s" was expected in "%s" but file does not exist',
                self::FIELD_COLLECTION_KEY,
                $path
            ));
        }

        return $path;
    }

    /**
     * Creates the DataQualityRule field collection definition on first install, or upgrades it in
     * place (same wholesale-layout-replacement behaviour as installClassFromJson()) on every
     * subsequent install.
     */
    private function installFieldCollectionFromJson(): void
    {
        $path = $this->getFieldCollectionDefinitionPath();

        $fieldCollection = Fieldcollection\Definition::getByKey(self::FIELD_COLLECTION_KEY);

        if ($fieldCollection === null) {
            $fieldCollection = new Fieldcollection\Definition();
            $fieldCollection->setKey(self::FIELD_COLLECTION_KEY);
        }

        $data = \file_get_contents($path);

        if ($data === false) {
            throw new InstallationException(\sprintf(
                'Failed to read field collection export file "%s"',
                $path
            ));
        }

        $success = Service::importFieldCollectionFromJson($fieldCollection, $data);

        if (!$success) {
            throw new InstallationException(\sprintf(
                'Failed to create field collection "%s"',
                self::FIELD_COLLECTION_KEY
            ));
        }
    }

    private function installScoresTable(): void
    {
        Db::get()->executeStatement(
            'CREATE TABLE IF NOT EXISTS `' . self::SCORES_TABLE . '` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `object_id` INT UNSIGNED NOT NULL,
                `scope_type` ENUM(\'channel\', \'category\') NOT NULL,
                `scope_id` INT UNSIGNED NOT NULL,
                `mandatory_complete` TINYINT(1) UNSIGNED NOT NULL,
                `score` DECIMAL(5,2) NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_scope` (`object_id`, `scope_type`, `scope_id`)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        );
    }

    private function scoresTableExists(): bool
    {
        return Db::get()->createSchemaManager()->tablesExist([self::SCORES_TABLE]);
    }

    private function installPermission(): void
    {
        PermissionDefinition::create(self::PERMISSION_KEY)
            ->setCategory(self::PERMISSION_CATEGORY)
            ->save();
    }
}
