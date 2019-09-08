<?php
namespace Internationalisation\View\Helper;

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Zend\View\Helper\AbstractHelper;

/**
 * @todo Use a filter (rep.value.html or rep.resource.display_values) to display the good locale anywhere, even in admin.
 * @todo Override the method value() with a new option for language fallback.
 * @todo Override the method value() to display the title and the description in the language of the user.
 * Warning: the privacy of each property should be checked.
 */
 class LocaleValue extends AbstractHelper
{
    /**
     * Get the values in the site language, or a fallback.
     *
     * @see \Omeka\Api\Representation\AbstractResourceEntityRepresentation::value()
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @param string $term The prefix:local_part
     * @param array $options
     * - type: (null) Get values of this type only. Valid types are "literal",
     *   "uri", and "resource". Returns all types by default.
     * - all: (false) If true, returns all values that match criteria. If false,
     *   returns the first matching value.
     * - default: (null) Default value if no values match criteria. Returns null
     *   by default.
     * - lang: (null) Get values of this language only. Returns values of all
     *   languages by default.
     * - fallbacks: (array) Ordered list of fallbacks for the language. An empty
     *   string is a fallback for values without language.
     * @return \Omeka\Api\Representation\ValueRepresentation|\Omeka\Api\Representation\ValueRepresentation[]|mixed
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource, $term, array $options = [])
    {
        // Set defaults.
        $defaultOptions = [
            'type' => null,
            'all' => false,
            'default' => null,
            'lang' => null,
            'fallbacks' => [],
        ];
        $options += $defaultOptions;

        if (!$this->isTerm($term)) {
            return $options['default'];
        }

        $values = $resource->values();
        if (!isset($values[$term])) {
            return $options['default'];
        }

        $values = $values[$term]['values'];

        // Match only the representations that fit all the criteria.
        $optionType = !is_null($options['type']);
        $optionLang = !is_null($options['lang']);

        // Order values by language.
        if ($optionLang && $options['fallbacks']) {
            $valuesByLang = [];
            foreach ($values as $value) {
                if ($optionType && $value->type() !== $options['type']) {
                    continue;
                }
                $valuesByLang[$value->lang()][] = $value;
            }

            if (isset($valuesByLang[$options['lang']])) {
                $matchingValues = $valuesByLang[$options['lang']];
            } else {
                // Keep only values with fallbacks and order them by fallbacks,
                // and take only the first not empty.
                $fallbacks = array_fill_keys($options['fallbacks'], null);
                $matchingValues = array_filter(
                    array_replace(
                        $fallbacks,
                        array_intersect_key($valuesByLang, $fallbacks)
                    )
                );
                $matchingValues = $matchingValues ? reset($matchingValues) : [];
            }
        } elseif ($optionType || $optionLang) {
            $matchingValues = [];
            foreach ($values as $value) {
                if ($optionType && $value->type() !== $options['type']) {
                    continue;
                }
                if ($optionLang && $value->lang() !== $options['lang']) {
                    continue;
                }
                $matchingValues[] = $value;
            }
        } else {
            $matchingValues = $values;
        }

        if (!count($matchingValues)) {
            return $options['default'];
        }

        return $options['all'] ? $matchingValues : $matchingValues[0];
    }

    /**
     * Determine whether a string is a valid JSON-LD term.
     *
     * @param string $term
     * @return bool
     */
    protected function isTerm($term)
    {
        return (bool) preg_match('/^[a-z0-9-_]+:[a-z0-9-_]+$/i', $term);
    }
}
