<?php declare(strict_types=1);

namespace Internationalisation;

use Omeka\Stdlib\Message;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Omeka\Api\Manager $api
 * @var \Omeka\Settings\Settings $settings
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger
 */
$plugins = $services->get('ControllerPluginManager');
$api = $plugins->get('api');
$settings = $services->get('Omeka\Settings');
$connection = $services->get('Omeka\Connection');
$messenger = $plugins->get('messenger');
$entityManager = $services->get('Omeka\EntityManager');

if (version_compare($oldVersion, '3.2.0', '<')) {
    $settings = $services->get('Omeka\Settings\Site');
    $api = $services->get('Omeka\ApiManager');
    $siteIds = $api->search('sites', [], ['returnScalar' => 'id'])->getContent();
    foreach ($siteIds as $siteId) {
        $settings->setTargetId($siteId);
        $settings->set('internationalisation_fallbacks',
            $config['internationalisation']['site_settings']['internationalisation_fallbacks']);
        $settings->set('internationalisation_required_languages',
            $config['internationalisation']['site_settings']['internationalisation_required_languages']);
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
        $connection->executeStatement($sql);
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
    $sql = <<<SQL
UPDATE `site_page_block` SET `layout` = "mirrorPage"
WHERE `layout` = "simplePage";
SQL;
    $connection->executeStatement($sql);
}
