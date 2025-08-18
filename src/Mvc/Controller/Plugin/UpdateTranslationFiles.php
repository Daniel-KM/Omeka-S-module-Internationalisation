<?php declare(strict_types=1);

namespace Internationalisation\Mvc\Controller\Plugin;

use Doctrine\DBAL\Connection;
use Laminas\Log\Logger;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Omeka\Settings\Settings;
use Omeka\Settings\SiteSettings;

class UpdateTranslationFiles extends AbstractPlugin
{
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
        Connection $connection,
        Logger $logger,
        Settings $settings,
        SiteSettings $siteSettings,
        string $localFilesPath
    ) {
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
