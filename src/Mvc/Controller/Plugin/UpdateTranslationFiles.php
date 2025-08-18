<?php declare(strict_types=1);

namespace Internationalisation\Mvc\Controller\Plugin;

use Doctrine\DBAL\Connection;
use Laminas\Log\Logger;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Omeka\Api\Manager as ApiManager;
use Omeka\Settings\Settings;
use Omeka\Settings\SiteSettings;

class UpdateTranslationFiles extends AbstractPlugin
{
    /**
     * @var \Omeka\Api\Manager
     */
    protected $api;

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    /**
     * @var \Laminas\Log\Logger
     */
    protected $logger;

    /**
     * @var \Omeka\Settings\Settings
     */
    protected $settings;

    /**
     * @var \Omeka\Settings\SiteSettings
     */
    protected $siteSettings;

    /**
     * @var string
     */
    protected $localFilesPath;

    public function __construct(
        ApiManager $api,
        Connection $connection,
        Logger $logger,
        Settings $settings,
        SiteSettings $siteSettings,
        string $localFilesPath
    ) {
        $this->api = $api;
        $this->connection = $connection;
        $this->logger = $logger;
        $this->settings = $settings;
        $this->siteSettings = $siteSettings;
        $this->localFilesPath = $localFilesPath;
    }

    /**
     * Update all translation files (formatted like xx-yy.php).
     *
     * The existing files (xx-yy.php) are removed or overridden.
     *
     * @return bool Success or not.
     */
    public function __invoke(): bool
    {
        $dir = $this->checkDestinationDir($this->localFilesPath . '/language');
        if (!$dir) {
            return false;
        }

        $existingFilenames = glob($dir . '/*.php');
        $existingFilenames = preg_grep('~^[a-zA-Z]{2,3}((-|_)[a-zA-Z0-9]{2,4})?\.php$~', $existingFilenames);

        // Remove all language files.
        foreach ($existingFilenames as $filename) {
            $file = "$dir/$filename";
            if (file_exists($file) && is_file($file) && is_writeable($file)) {
                @unlink($file);
            }
        }

        $translations = $this->connection
            ->executeQuery('SELECT `lang`, `lang`, `string`, `translated` FROM `translation` ORDER BY `lang` ASC, `string` ASC')
            ->fetchAllAssociative();

        $translationsByLanguage = [];
        foreach ($translations as $translation) {
            $translationsByLanguage[$translation['lang']][$translation['string']] = $translation['translated'];
        }

        $content = <<<'PHP'
            <?php
            /**
             * This file is automatically replaced when any language is created, updated or deleted.
             */
            return
            PHP;

        $hasError = false;
        foreach ($translationsByLanguage as $lang => $strings) {
            // In laminas, the language code should be "xx" or "xx_YY".
            $lang = strtr(mb_strtolower($lang), '-', '_');
            $positionSeparator = mb_strpos($lang, '_');
            if ($positionSeparator) {
                $lang = mb_substr($lang, 0, $positionSeparator) . '_' . mb_strtoupper(mb_substr($lang, $positionSeparator + 1));
            }
            $file = "$dir/$lang.php";
            if (file_exists($file) && is_file($file)) {
                if (!is_writeable($file)) {
                    $this->logger->err(
                        'The file "{file}" is not writeable.', // @translate
                        ['file' => $file]
                    );
                    unset($translationsByLanguage[$lang]);
                    $hasError = true;
                    continue;
                }
                @unlink($file);
            }
            $result = file_put_contents($file, $content . ' ' . var_export($strings, true) . ";\n");
            $hasError = $hasError || $result === false;
        }

        // Prepare files for module Table.
        if (class_exists('Table\Module', false)) {
            $result = $this->prepareTableFiles();
            $hasError = $hasError || !$result;
        }

        return $hasError;
    }

    /**
     * Prepare tables for each site.
     */
    protected function prepareTableFiles(): bool
    {
        /**
         * @var \Omeka\Api\Manager $api
         * @var \Omeka\Mvc\Status $status
         * @var \Laminas\Log\Logger $logger
         * @var \Omeka\Settings\Settings $settings
         * @var \Omeka\Settings\SiteSettings $siteSettings
         * @var \Laminas\I18n\Translator\TranslatorInterface $translator
         * @var \Table\Api\Representation\TableRepresentation $table
         *
         * The current locale is set in:
         * @see \Omeka\Mvc\MvcListeners::bootstrapLocale()
         * @see \Omeka\Mvc\MvcListeners::preparePublicSite()
         *
         * @fixme Load only the current domain/language and load other ones on request. So don't preload other locales.
         * Else the translation files in another language are not loaded on demand (even if never asked).
         * It may make an issue with fallback.
         */

        $dir = $this->checkDestinationDir($this->localFilesPath . '/language');
        $existingFilenames = glob($dir . '/table-*.php');
        $existingFilenames = preg_grep('~^table-\d+\.php$~', $existingFilenames);

        $hasError = false;

        // Remove all language files for tables.
        foreach ($existingFilenames as $filename) {
            $file = "$dir/$filename";
            if (file_exists($file) && is_file($file) && is_writeable($file)) {
                @unlink($file);
            }
        }

        // Include automatic translations, from generic to specific.
        $tableSlugs = $this->api->search('tables', ['sort_by' => 'slug', 'sort_order' => 'ASC'], ['returnScalar' => 'slug'])->getContent();
        $tableTranslationSlugs = preg_grep('~^(?:translation|translation-([a-zA-Z]{2,3})((-|_)[a-zA-Z0-9]{2,4})?)$~', $tableSlugs);
        usort($tableTranslationSlugs, fn($a, $b) => strlen($a) <=> strlen($b));

        $tableSlugsNoLang = [];

        $prepareFile = function (int $siteId, array $tableSlugs) use ($dir, $tableTranslationSlugs, &$tableSlugsNoLang, &$hasError): bool
        {
            $locales = [];
            $tableSlugs = array_unique(array_merge($tableTranslationSlugs, $tableSlugs));
            if (!$tableSlugs) {
                return true;
            }
            $tables = $this->api->search('tables', ['slug' => $tableSlugs])->getContent();
            if (!$tables) {
                $hasError = true;
                $this->logger->err(
                    'The tables "{tables}" does not exist.', // @translate
                    ['tables' => implode(', ', $tableSlugs)]
                );
                return false;
            } elseif (count($tables) !== count($tableSlugs)) {
                $hasError = true;
                $missingSlugs = array_diff($tableSlugs, array_map(fn ($v) => $v->slug(), $tables));
                $this->logger->err(
                    'The tables "{tables}" does not exist.', // @translate
                    ['tables' => implode(', ', $missingSlugs)]
                );
            }
            foreach ($tables as $table) {
                $lang = $table->lang() ?: null;
                if ($lang) {
                    $locales[$lang] = array_replace($locales[$lang] ?? [], $table->codesAssociative());
                } else {
                    $tableSlugsNoLang[$table->slug()] = $table->slug();
                }
            }
            $content = <<<'PHP'
                <?php
                /**
                 * This file is automatically replaced when any language is created, updated or deleted.
                 */
                return
                PHP;
            $file = "$dir/table-$siteId.php";
            if (file_exists($file) && is_file($file)) {
                if (!is_writeable($file)) {
                    $hasError = true;
                    $this->logger->err(
                        'The file "{file}" is not writeable.', // @translate
                        ['file' => $file]
                    );
                    return false;
                }
                @unlink($file);
            }

            $result = file_put_contents($file, $content . ' ' . var_export($locales, true) . ";\n");
            if ($result === false) {
                $hasError = true;
                $this->logger->err(
                    'The file "{file}" is not writeable.', // @translate
                    ['file' => $file]
                );
            }

            return $hasError;
        };

        // Manage admin.
        $tablesAdmin = $this->settings->get('internationalisation_translation_tables', []);
        $prepareFile(0, $tablesAdmin);

        /** @var \Omeka\Api\Representation\SiteRepresentation $site */
        $sites = $this->api->search('sites')->getContent();
        foreach ($sites as $site) {
            // TODO Maybe a direct fetch via an entity manager query?
            $siteId = $site->id();
            $tablesSite = $this->siteSettings->get('internationalisation_translation_tables', [], $siteId);
            $prepareFile($site->id(), $tablesSite);
        }

        if ($tableSlugsNoLang) {
            $this->logger->warn(
                'The following tables are included for internationalisation in site settings, but have no defined language: {table_slugs}.', // @translate
                ['table_slugs' => implode(', ', array_values($tableSlugsNoLang))]
            );
        }

        return $hasError;
    }

    /**
     * Check or create the destination folder.
     *
     * @param string $dirPath Absolute path of the directory to check.
     * @return string|null The dirpath if valid, else null.
     */
    protected function checkDestinationDir(string $dirPath): ?string
    {
        if (file_exists($dirPath)) {
            if (!is_dir($dirPath) || !is_readable($dirPath) || !is_writeable($dirPath)) {
                $this->logger->err(
                    'The directory "{path}" is not writeable.', // @translate
                    ['path' => $dirPath]
                );
                return null;
            }
            return $dirPath;
        }

        $result = @mkdir($dirPath, 0775, true);
        if (!$result) {
            $this->logger->err(
                'The directory "{path}" is not writeable: {error}.', // @translate
                ['path' => $dirPath, 'error' => error_get_last()['message']]
            );
            return null;
        }
        return $dirPath;
    }
}
