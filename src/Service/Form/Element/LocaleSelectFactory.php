<?php declare(strict_types=1);

namespace Internationalisation\Service\Form\Element;

use Interop\Container\ContainerInterface;
use Laminas\Form\Element\Select;
use Laminas\ServiceManager\Factory\FactoryInterface;

/**
 * Replacement for Omeka\Service\Form\Element\LocaleSelectFactory.
 *
 * The core factory only lists .mo files under application/language/, so locales
 * translated only in a theme or a module (e.g. Breton "br") never appear. This
 * factory additionally scans themes/{theme}/language/ and
 * modules/{module}/language/ for .mo files and merges the discovered locale ids
 * into the value_options.
 */
class LocaleSelectFactory implements FactoryInterface
{
    protected $intlLoaded;

    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        $this->intlLoaded = extension_loaded('intl');

        $locales = ['en_US' => $this->getValueOption('en_US')];

        $localeIdRegex = '/^[a-zA-Z]{2,3}([_-][a-zA-Z0-9]{2,8})*(@[a-zA-Z]+)?$/';

        foreach ($this->localeDirectories() as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            foreach (new \DirectoryIterator($dir) as $fileinfo) {
                if (!$fileinfo->isFile() || $fileinfo->getExtension() !== 'mo') {
                    continue;
                }
                $localeId = $fileinfo->getBasename('.mo');
                // Skip ill-formed ids like "foo.20210212_124640.bkp".
                if (!preg_match($localeIdRegex, $localeId)) {
                    continue;
                }
                if (!isset($locales[$localeId])) {
                    $locales[$localeId] = $this->getValueOption($localeId);
                }
            }
        }

        // Admin-defined extra locales (main settings, key => label).
        $extra = (array) $services->get('Omeka\Settings')
            ->get('internationalisation_extra_locales', []);
        foreach ($extra as $localeId => $label) {
            $localeId = trim((string) $localeId);
            if ($localeId === '' || !preg_match($localeIdRegex, $localeId)) {
                continue;
            }
            $label = trim((string) $label);
            if (!isset($locales[$localeId])) {
                $locales[$localeId] = $label !== ''
                    ? sprintf('%1$s [%2$s]', $label, $localeId)
                    : $this->getValueOption($localeId);
            }
        }

        if ($this->intlLoaded) {
            $collator = new \Collator('root');
            $collator->asort($locales);
        } else {
            natcasesort($locales);
        }

        $element = new Select();
        $element->setValueOptions($locales);
        $element->setEmptyOption('Default'); // @translate
        return $element;
    }

    protected function localeDirectories(): array
    {
        $dirs = [OMEKA_PATH . '/application/language'];
        foreach (['themes', 'modules'] as $kind) {
            $base = OMEKA_PATH . '/' . $kind;
            if (!is_dir($base)) {
                continue;
            }
            foreach (new \DirectoryIterator($base) as $entry) {
                if (!$entry->isDir() || $entry->isDot()) {
                    continue;
                }
                $candidate = $entry->getPathname() . '/language';
                if (is_dir($candidate)) {
                    $dirs[] = $candidate;
                }
            }
        }
        return $dirs;
    }

    protected function getValueOption(string $localeId): string
    {
        $localeName = $this->intlLoaded
            ? (string) \Locale::getDisplayName($localeId, $localeId)
            : $localeId;
        if ($localeName === '' || $localeName === $localeId) {
            return $localeId;
        }
        $localeName = mb_convert_case($localeName, MB_CASE_TITLE, 'UTF-8');
        return sprintf('%1$s [%2$s]', $localeName, $localeId); // @translate
    }
}
