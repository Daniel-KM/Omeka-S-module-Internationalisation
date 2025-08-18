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

if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.72')) {
    $message = new \Omeka\Stdlib\Message(
        $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
        'Common', '3.4.72'
    );
    throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
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
        throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
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
        } catch (\Exception $e) {
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
    $connection->executeStatement($sql);

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
