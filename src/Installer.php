<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle;

use Pimcore\Db;
use Pimcore\Extension\Bundle\Installer\AbstractInstaller;
use Pimcore\Extension\Bundle\Installer\Exception\InstallationException;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Service;
use Pimcore\Model\User\Permission\Definition as PermissionDefinition;

class Installer extends AbstractInstaller
{
    private const CLASS_ID = 'data_quality_configuration';
    private const CLASS_NAME = 'QualityConfiguration';
    public const SCORES_TABLE = 'portadesign_data_quality_scores';
    public const PERMISSION_KEY = 'portadesign_data_quality_report';
    private const PERMISSION_CATEGORY = 'Data Quality';

    public function install(): void
    {
        $this->installClassFromJson();
        $this->installScoresTable();
        $this->installPermission();
    }

    /**
     * Destructive and non-reversible: deletes the QualityConfiguration class definition outright,
     * which takes every business rule stored as an instance of that class with it (and drops the
     * scores table). Do not uninstall expecting a subsequent install() to bring rules back.
     */
    public function uninstall(): void
    {
        $definition = ClassDefinition::getById(self::CLASS_ID);

        if ($definition !== null) {
            $definition->delete();
        }

        Db::get()->executeStatement('DROP TABLE IF EXISTS `' . self::SCORES_TABLE . '`');

        Db::get()->executeStatement(
            'DELETE FROM users_permission_definitions WHERE `key` = ?',
            [self::PERMISSION_KEY],
        );
    }

    /**
     * Must check both the class AND the table — the table could ship in a later version of an
     * already-installed bundle, so checking only the class would make canBeInstalled() stay
     * false and installScoresTable() would never run for upgrades.
     */
    public function isInstalled(): bool
    {
        return ClassDefinition::getById(self::CLASS_ID) !== null
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

    private function installClassFromJson(): void
    {
        $path = $this->getClassDefinitionPath();

        $class = ClassDefinition::getById(self::CLASS_ID);

        if ($class === null) {
            $class = new ClassDefinition();
            $class->setName(self::CLASS_NAME);
            $class->setId(self::CLASS_ID);
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
