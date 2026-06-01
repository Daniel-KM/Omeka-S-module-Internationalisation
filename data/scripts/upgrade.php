<?php declare(strict_types=1);

namespace Internationalisation;

use Common\Stdlib\PsrMessage;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Omeka\Api\Manager $api
 * @var \Omeka\View\Helper\Url $url
 * @var \Omeka\Settings\Settings $settings
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger
 */
$plugins = $services->get('ControllerPluginManager');
$url = $services->get('ViewHelperManager')->get('url');
$api = $plugins->get('api');
$config = $services->get('Config');
$settings = $services->get('Omeka\Settings');
$translate = $plugins->get('translate');
$translator = $services->get('MvcTranslator');
$connection = $services->get('Omeka\Connection');
$messenger = $plugins->get('messenger');
$entityManager = $services->get('Omeka\EntityManager');

$localConfig = require dirname(__DIR__, 2) . '/config/module.config.php';

$this->checkExtensionIntl();

if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.86')) {
    $message = new \Omeka\Stdlib\Message(
        $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
        'Common', '3.4.86'
    );
    $messenger->addError($message);
    throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $translate('Missing requirement. Unable to upgrade.')); // @translate
}

if (version_compare($oldVersion, '3.2.0', '<')) {
    $settings = $services->get('Omeka\Settings\Site');
    $api = $services->get('Omeka\ApiManager');
    $siteIds = $api->search('sites', [], ['returnScalar' => 'id'])->getContent();
    foreach ($siteIds as $siteId) {
        $settings->setTargetId($siteId);
        $settings->set('internationalisation_fallbacks',
            $localConfig['internationalisation']['site_settings']['internationalisation_fallbacks']);
        $settings->set('internationalisation_required_languages',
            $localConfig['internationalisation']['site_settings']['internationalisation_required_languages']);
    }
}

if (version_compare($oldVersion, '3.2.4', '<')) {
    $sql = <<<'SQL'
         ALTER TABLE site_page_relation DROP PRIMARY KEY;
         ALTER TABLE site_page_relation ADD id INT AUTO_INCREMENT NOT NULL UNIQUE FIRST;
         CREATE UNIQUE INDEX site_page_relation_idx ON site_page_relation (page_id, related_page_id);
         ALTER TABLE site_page_relation ADD PRIMARY KEY (id);
        SQL;
    // Use single statements for execution.
    // See core commit #2689ce92f.
    $sqls = array_filter(array_map('trim', explode(";\n", $sql)));
    foreach ($sqls as $sql) {
        $connection->executeStatement($sql);
    }
}

if (version_compare($oldVersion, '3.2.7', '<')) {
    $sql = <<<'SQL'
        UPDATE `site_setting` SET `value` = '"all_site"'
        WHERE `id` = "internationalisation_display_values"
            AND `value` = '"all_ordered"';
        UPDATE site_setting SET `value` = '"site"'
        WHERE `id` = "internationalisation_display_values"
            AND `value` = '"site_lang"';
        UPDATE site_setting SET `value` = '"site_iso"'
        WHERE `id` = "internationalisation_display_values"
            AND `value` = '"site_lang_iso"';
        SQL;
    // Use single statements for execution.
    // See core commit #2689ce92f.
    $sqls = array_filter(array_map('trim', explode(";\n", $sql)));
    foreach ($sqls as $sql) {
        $connection->executeStatement($sql);
    }

    $settings = $services->get('Omeka\Settings\Site');
    $api = $services->get('Omeka\ApiManager');
    $siteIds = $api->search('sites', [], ['returnScalar' => 'id'])->getContent();
    foreach ($siteIds as $siteId) {
        $settings->setTargetId($siteId);
        $this->prepareSiteLocales($settings);
    }
}

if (version_compare($oldVersion, '3.3.10', '<')) {
    $sql = <<<'SQL'
        UPDATE `site_page_block` SET `layout` = "mirrorPage"
        WHERE `layout` = "simplePage";
        SQL;
    $connection->executeStatement($sql);
}

if (version_compare($oldVersion, '3.4.14', '<')) {
    if ($this->isModuleActive('BlockPlus')
        && !$this->isModuleVersionAtLeast('BlockPlus', '3.4.29')
    ) {
        $message = new PsrMessage(
            'The module {module} should be upgraded to version {version} or later.', // @translate
            ['module' => 'BlockPlus', 'version' => '3.4.29']
        );
        throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message->setTranslator($translator));
    }

    $message = new PsrMessage(
        'The language switcher is now available as a page block and as a resource block.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.17', '<')) {
    $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
    if (!$this->checkDestinationDir($basePath . '/language')) {
        $message = new PsrMessage(
            'The directory "{path}" is not writeable.', // @translate
            ['path' => $basePath . '/language']
        );
        $messenger->addError($message);
    throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $translate('Missing requirement. Unable to upgrade.')); // @translate
    }

    $sql = <<<'SQL'
        ALTER TABLE `site_page_relation` DROP INDEX `site_page_relation_idx`;
         CREATE UNIQUE INDEX `idx_site_page_relation` ON `site_page_relation` (`page_id`, `related_page_id`);
        SQL;
    // Use single statements for execution.
    // See core commit #2689ce92f.
    $sqls = array_filter(array_map('trim', explode(";\n", $sql)));
    foreach ($sqls as $sql) {
        try {
            $connection->executeStatement($sql);
        } catch (\Throwable $e) {
            // Skip.
        }
    }

    $sql = <<<'SQL'
        CREATE TABLE `translating` (
            `id` INT AUTO_INCREMENT NOT NULL,
            `lang` VARCHAR(8) NOT NULL,
            `string` LONGTEXT NOT NULL,
            `translation` LONGTEXT NOT NULL,
            INDEX `idx_translating_lang_string` (`lang`, `string`(190)),
            PRIMARY KEY(`id`)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
        SQL;
    $connection->executeStatement($sql);

    // Fix a name in the config.
    $sql = <<<'SQL'
        UPDATE `setting` SET `id` = "internationalisation_translation_tables" WHERE `id` = "internationaliation_translation_tables";
        UPDATE `site_setting` SET `id` = "internationalisation_translation_tables" WHERE `id` = "internationaliation_translation_tables";
        SQL;
    // Use single statements for execution. See core commit #2689ce92f.
    $sqls = array_filter(array_map('trim', explode(";\n", $sql)));
    foreach ($sqls as $sql) {
        $connection->executeStatement($sql);
    }

    $message = new PsrMessage(
        'It is now possible to translate strings in admin via the {link}page of translations{link_end}.', // @translate
        ['link' => '<a href="' . $url('admin') . '/translation">', 'link_end' => '</a>']
    );
    $message->setEscapeHtml(false);
    $messenger->addSuccess($message);

    if ($oldVersion === '3.4.16') {
        $message = new PsrMessage(
            'If you used the module {link}Table{link_end} to manage translations, just copy them manually in the new form, remove tables and update settings. The settings are kept to manage specific translations.', // @translate
            ['link' => '<a href="' . $url('admin') . '/translation">', 'link_end' => '</a>']
        );
    }
    $message->setEscapeHtml(false);
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.20', '<')) {
    // Rename the table "translating" to the clearer "translated".
    $hasOld = (bool) $connection->executeQuery(
        "SHOW TABLES LIKE 'translating'"
    )->fetchOne();
    $hasNew = (bool) $connection->executeQuery(
        "SHOW TABLES LIKE 'translated'"
    )->fetchOne();
    if ($hasOld && !$hasNew) {
        $connection->executeStatement('RENAME TABLE `translating` TO `translated`');
        // Rename the index too, when supported.
        try {
            $connection->executeStatement(
                'ALTER TABLE `translated` RENAME INDEX `idx_translating_lang_string` TO `idx_translated_lang_string`'
            );
        } catch (\Throwable $e) {
            // Older MariaDB/MySQL: drop and recreate the index.
            try {
                $connection->executeStatement('ALTER TABLE `translated` DROP INDEX `idx_translating_lang_string`');
            } catch (\Throwable $e) {
            }
            try {
                $connection->executeStatement(
                    'ALTER TABLE `translated` ADD INDEX `idx_translated_lang_string` (`lang`, `string`(190))'
                );
            } catch (\Throwable $e) {
            }
        }
    }

    // Drop the support of module Table for translations: migrate any
    // translation into the dedicated "translated" table, then remove the
    // generated table-*.php files and the related settings.

    $localFilesPath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
    $languageDir = $localFilesPath . '/language';

    $migrated = 0;
    $langsMigrated = [];

    $insertTranslated = function (string $lang, $string, $translation) use ($connection, &$migrated, &$langsMigrated): void {
        $lang = trim($lang);
        $string = (string) $string;
        $translation = (string) $translation;
        if ($lang === '' || $string === '' || $translation === '') {
            return;
        }
        $connection->executeStatement(
            'INSERT IGNORE INTO `translated` (`lang`, `string`, `translation`) VALUES (:lang, :string, :translation)',
            ['lang' => $lang, 'string' => $string, 'translation' => $translation]
        );
        $migrated++;
        $langsMigrated[$lang] = true;
    };

    // 1. Migrate from the generated table-*.php files, that are the strings
    // actually loaded at runtime. This works even when module Table was already
    // uninstalled, and covers every language present in the files, whether or
    // not it exists in the "translated" table. Each file returns an array [lang
    // => [string => translation]].
    $migratedFiles = [];
    if (is_dir($languageDir)) {
        $tableFiles = preg_grep('~[/\\\\]table-\d+\.php$~', glob($languageDir . '/table-*.php') ?: []);
        foreach ($tableFiles as $file) {
            if (!is_file($file) || !is_readable($file)) {
                continue;
            }
            $locales = include $file;
            if (!is_array($locales)) {
                // Keep the unreadable file to avoid any loss.
                continue;
            }
            foreach ($locales as $lang => $strings) {
                if (!is_array($strings)) {
                    continue;
                }
                foreach ($strings as $string => $translation) {
                    $insertTranslated((string) $lang, $string, $translation);
                }
            }
            $migratedFiles[] = $file;
        }
    }

    // 2. Also migrate directly from module Table when still installed, to cover
    // tables that were never materialized to a file.
    $tablesNoLang = [];
    if (class_exists('Table\Module', false)) {
        $tableSlugs = $api
            ->search('tables', ['sort_by' => 'slug', 'sort_order' => 'ASC'], ['returnScalar' => 'slug'])
            ->getContent();
        $translationSlugs = preg_grep(
            '~^(?:translation|translation-([a-zA-Z]{2,3})((-|_)[a-zA-Z0-9]{2,4})?)$~',
            $tableSlugs
        );
        foreach ($translationSlugs as $slug) {
            /** @var \Table\Api\Representation\TableRepresentation $table */
            $table = $api->searchOne('tables', ['slug' => $slug])->getContent();
            if (!$table) {
                continue;
            }
            $lang = $table->lang() ?: null;
            if (!$lang) {
                $tablesNoLang[] = $slug;
                continue;
            }
            foreach ($table->codesAssociative() as $string => $translation) {
                $insertTranslated((string) $lang, $string, $translation);
            }
        }
    }

    // 3. Remove the generated table files now that their content is copied.
    // Only the files that were successfully read are removed.
    foreach ($migratedFiles as $file) {
        if (is_file($file) && is_writeable($file)) {
            @unlink($file);
        }
    }

    // 4. Regenerate the "{lang}.php" files from the "translated" table so the
    // migrated strings are actually loaded at runtime, without waiting for a
    // manual save in the translations page.
    try {
        $services->get('ControllerPluginManager')->get('updateTranslationFiles')();
    } catch (\Throwable $e) {
        $messenger->addWarning(new PsrMessage(
            'Migrated translations are stored but the language files could not be regenerated automatically: open and save any language in the translations page.' // @translate
        ));
    }

    // 5. Remove the now unused settings.
    $connection->executeStatement(
        'DELETE FROM `setting` WHERE `id` = "internationalisation_translation_tables"'
    );
    $connection->executeStatement(
        'DELETE FROM `site_setting` WHERE `id` = "internationalisation_translation_tables"'
    );

    if ($migrated) {
        $message = new PsrMessage(
            'Module Table support for translations was removed: {count} strings ({langs} languages) were copied into the translations page.', // @translate
            ['count' => $migrated, 'langs' => count($langsMigrated)]
        );
        $messenger->addSuccess($message);
    } else {
        $message = new PsrMessage(
            'Module Table support for translations was removed. No translation to migrate.' // @translate
        );
        $messenger->addSuccess($message);
    }
    if ($tablesNoLang) {
        $message = new PsrMessage(
            'These translation tables have no language and were not migrated: {tables}. Set a language and copy them manually in the translations page.', // @translate
            ['tables' => implode(', ', $tablesNoLang)]
        );
        $messenger->addWarning($message);
    }

    if (!class_exists('SiteHub\Module', false)) {
        $message = new PsrMessage(
            'To manage the site settings and theme settings of grouped sites more easily, it is recommended to install the module {link}Site Hub{link_end}, that propagates settings between the sites of a group.', // @translate
            ['link' => '<a href="https://gitlab.com/Daniel-KM/Omeka-S-module-SiteHub">', 'link_end' => '</a>']
        );
        $message->setEscapeHtml(false);
        $messenger->addWarning($message);
    }
}
