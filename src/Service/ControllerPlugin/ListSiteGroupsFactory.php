<?php declare(strict_types=1);
namespace Internationalisation\Service\ControllerPlugin;

use Internationalisation\Mvc\Controller\Plugin\ListSiteGroups;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Omeka\Api\Exception\NotFoundException;

class ListSiteGroupsFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $list = $this->listGroups($services);
        return new ListSiteGroups($list);
    }

    protected function listGroups(ContainerInterface $services)
    {
        $api = $services->get('Omeka\ApiManager');
        $settings = $services->get('Omeka\Settings');

        $siteGroups = $settings->get('internationalisation_site_groups') ?: [];

        $sites = $api
            ->search('sites', ['sort_by' => 'slug', 'sort_order' => 'asc'], ['returnScalar' => 'slug'])
            ->getContent();
        $sites = array_combine($sites, $sites);

        // Clean sites.
        ksort($siteGroups, SORT_NATURAL);
        $siteGroups = array_filter(array_map(function ($group) use ($sites) {
            $v = array_intersect($group, $sites);
            if (count($v) <= 1) {
                return [];
            }
            sort($v, SORT_NATURAL);
            return $v;
        }, array_intersect_key($siteGroups, $sites)));

        // Remove sites that belongs to a group and append them.
        $remaining = array_map(function ($site) {
            return [$site];
        }, $sites);
        $result = $siteGroups;
        foreach ($result as $site => $group) {
            unset($remaining[$site]);
            $remaining = array_diff_key($remaining, array_flip($group));
            if (isset($siteGroups[$site])) {
                foreach ($group as $siteInGroup) {
                    if ($siteInGroup !== $site) {
                        unset($siteGroups[$siteInGroup]);
                    }
                }
            }
        }

        // If the main site is in a group, put the group as first for a better
        // display.
        $mainSite = $settings->get('default_site');
        if ($mainSite) {
            try {
                $mainSite = $api->read('sites', $mainSite)->getContent()->slug();
                if (!isset($remaining[$mainSite])) {
                    foreach ($siteGroups as $site => $group) {
                        if (in_array($mainSite, $group)) {
                            $siteGroups = [$site => $group] + $siteGroups;
                            break;
                        }
                    }
                }
            } catch (NotFoundException $e) {
            }
        }

        return $siteGroups + $remaining;
    }
}
