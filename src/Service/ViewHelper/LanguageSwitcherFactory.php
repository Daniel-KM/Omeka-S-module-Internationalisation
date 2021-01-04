<?php declare(strict_types=1);
namespace Internationalisation\Service\ViewHelper;

use Internationalisation\View\Helper\LanguageSwitcher;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

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
        /** @var \Laminas\Authentication\AuthenticationService $auth */
        $auth = $services->get('Omeka\AuthenticationService');
        if ($auth->hasIdentity()) {
            $role = $auth->getIdentity()->getRole();
            $isPublic = $role === 'guest';
        } else {
            $isPublic = true;
        }

        // Filter empty locale directly? Not here, in order to manage complex cases.
        $sql = <<<SQL
SELECT site.slug AS site_slug, REPLACE(site_setting.value, '"', "") AS localeId
FROM site_setting
JOIN site ON site.id = site_setting.site_id
WHERE site_setting.id = :setting_id
SQL;
        $bind = ['setting_id' => 'locale'];
        if ($isPublic) {
            $sql .= ' AND site.is_public = :is_public';
            $bind['is_public'] = 1;
        }

        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $services->get('Omeka\Connection');
        $connection->setFetchMode(\PDO::FETCH_KEY_PAIR);
        $localeSites = $connection->fetchAll($sql, $bind);
        $localeSites = array_filter($localeSites);

        if (extension_loaded('intl')) {
            $localeLabels = [];
            foreach ($localeSites as $localeId) {
                $localeLabels[$localeId] = \Locale::getDisplayName($localeId, $localeId);
            }
        } else {
            $localeLabels = array_combine($localeSites, $localeSites);
        }

        return new LanguageSwitcher(
            $localeSites,
            $localeLabels,
            $services->get('Omeka\Settings')->get('internationalisation_site_groups', [])
        );
    }
}
