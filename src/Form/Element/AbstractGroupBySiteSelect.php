<?php
namespace Internationalisation\Form\Element;

use Omeka\Api\Manager as ApiManager;
use Omeka\Api\Representation\SiteRepresentation;
use Zend\Form\Element\Select;

/**
 * @see \Omeka\Form\Element\AbstractGroupByOwnerSelect
 */
abstract class AbstractGroupBySiteSelect extends Select
{
    /**
     * @var ApiManager
     */
    protected $apiManager;

    /**
     * @param ApiManager $apiManager
     */
    public function setApiManager(ApiManager $apiManager)
    {
        $this->apiManager = $apiManager;
    }

    /**
     * @return ApiManager
     */
    public function getApiManager()
    {
        return $this->apiManager;
    }

    /**
     * Get the resource name.
     *
     * @return string
     */
    abstract public function getResourceName();

    /**
     * Get the value label from a resource.
     *
     * @param $resource
     * @return string
     */
    abstract public function getValueLabel($resource);

    public function getValueOptions()
    {
        $query = $this->getOption('query');
        if (!is_array($query)) {
            $query = [];
        }

        $response = $this->getApiManager()->search($this->getResourceName(), $query);

        if ($this->getOption('disable_group_by_site')) {
            // Group alphabetically by resource label without grouping by site.
            $resources = [];
            foreach ($response->getContent() as $resource) {
                $resources[$this->getValueLabel($resource)][] = $resource->id();
            }
            ksort($resources);
            $valueOptions = [];
            foreach ($resources as $label => $ids) {
                foreach ($ids as $id) {
                    $valueOptions[$id] = $label;
                }
            }
        } else {
            // Group alphabetically by site title (but use slugs as keys).
            $resourceSites = [];
            $resourceSiteTitles = [];
            foreach ($response->getContent() as $resource) {
                $site = $resource->site();
                $index = $site ? $site->slug() : null;
                $resourceSites[$index]['site'] = $site;
                $resourceSites[$index]['resources'][] = $resource;
                $resourceSiteTitles[$index] = $site->title();
            }
            natcasesort($resourceSiteTitles);
            $resourceSites = array_replace($resourceSiteTitles, $resourceSites);

            $valueOptions = [];
            foreach ($resourceSites as $resourceSite) {
                $options = [];
                foreach ($resourceSite['resources'] as $resource) {
                    $options[$resource->id()] = $this->getValueLabel($resource);
                    if (!$options) {
                        continue;
                    }
                }
                $site = $resourceSite['site'];
                if ($site instanceof SiteRepresentation) {
                    $label = $site->isPublic() ? $site->title() : ($site->title() . ' *');
                }
                // Is it really possible? Not important anyway.
                else {
                    $label = '[No site]'; // @translate
                }
                $valueOptions[] = ['label' => $label, 'options' => $options];
            }
        }

        $prependValueOptions = $this->getOption('prepend_value_options');
        if (is_array($prependValueOptions)) {
            $valueOptions = $prependValueOptions + $valueOptions;
        }
        return $valueOptions;
    }
}
