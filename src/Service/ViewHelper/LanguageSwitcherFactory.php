<?php
namespace LanguageSwitcher\Service\ViewHelper;

use Interop\Container\ContainerInterface;
use LanguageSwitcher\View\Helper\LanguageSwitcher;
use Zend\ServiceManager\Factory\FactoryInterface;

/**
 * Service factory to get the language switcher view helper.
 */
class LanguageSwitcherFactory implements FactoryInterface
{
    /**
     * Create and return the LanguageSwitcher view helper.
     *
     * @return LanguageSwitcher
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        // TODO The visibility may be a little more complex with some modules.
        $auth = $services->get('Omeka\AuthenticationService');
        $isPublic = !$auth->hasIdentity();

        // TODO Filter empty locale directly? Not currently, in order to manage complex cases.
        $sql = <<<SQL
SELECT site.slug AS site_slug, REPLACE(site_setting.value, '"', "") AS localeId
FROM site_setting
JOIN site ON site.id = site_setting.site_id
WHERE site_setting.id = ?
SQL;
        $bind = ['locale'];
        if ($isPublic) {
            $sql .= ' AND site.is_public = ?';
            $bind[] = [1];
        }

        /* @var \Doctrine\DBAL\Connection $connection */
        $connection = $services->get('Omeka\Connection');
        $connection->setFetchMode(\PDO::FETCH_KEY_PAIR);
        $locales = $connection->fetchAll($sql, $bind);

        if (extension_loaded('intl')) {
            $localeLabels = [];
            foreach (array_filter($locales) as $localeId) {
                $localeLabels[$localeId] = \Locale::getDisplayName($localeId, $localeId);
            }
        } else {
            $localeLabels = array_filter($localeId);
            $localeLabels = array_combine($localeLabels, $localeLabels);
        }

        return new LanguageSwitcher(
            $locales,
            $localeLabels
        );
    }
}
