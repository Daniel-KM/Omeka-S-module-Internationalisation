<?php declare(strict_types=1);
namespace Internationalisation;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $serviceLocator
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Api\Manager $api
 */
$services = $serviceLocator;
$settings = $services->get('Omeka\Settings');
$config = require dirname(__DIR__, 2) . '/config/module.config.php';
$connection = $services->get('Omeka\Connection');
$entityManager = $services->get('Omeka\EntityManager');
$plugins = $services->get('ControllerPluginManager');
$api = $plugins->get('api');
$space = strtolower(__NAMESPACE__);

if (version_compare($oldVersion, '3.2.0', '<')) {
    $settings = $services->get('Omeka\Settings\Site');
    $api = $services->get('Omeka\ApiManager');
    $siteIds = $api->search('sites', [], ['returnScalar' => 'id'])->getContent();
    foreach ($siteIds as $siteId) {
        $settings->setTargetId($siteId);
        $settings->set('internationalisation_fallbacks',
            $config[$space]['site_settings']['internationalisation_fallbacks']);
        $settings->set('internationalisation_required_languages',
            $config[$space]['site_settings']['internationalisation_required_languages']);
    }
}

if (version_compare($oldVersion, '3.2.4', '<')) {
    $sql = <<<SQL
 ALTER TABLE site_page_relation DROP PRIMARY KEY;
 ALTER TABLE site_page_relation ADD id INT AUTO_INCREMENT NOT NULL UNIQUE FIRST;
 CREATE UNIQUE INDEX site_page_relation_idx ON site_page_relation (page_id, related_page_id);
 ALTER TABLE site_page_relation ADD PRIMARY KEY (id);
SQL;
    // Use single statements for execution.
    // See core commit #2689ce92f.
    $sqls = array_filter(array_map('trim', explode(";\n", $sql)));
    foreach ($sqls as $sql) {
        $connection->exec($sql);
    }
}

if (version_compare($oldVersion, '3.2.7', '<')) {
    $sql = <<<SQL
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
        $connection->exec($sql);
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
    $sql = <<<SQL
UPDATE `site_page_block` SET `layout` = "mirrorPage"
WHERE `layout` = "simplePage";
SQL;
    $connection->exec($sql);
}
