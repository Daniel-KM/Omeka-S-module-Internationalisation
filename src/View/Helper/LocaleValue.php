<?php declare(strict_types=1);
namespace Internationalisation\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

/**
 * @todo Use a filter (rep.value.html or rep.resource.display_values, entity level) to display the good locale anywhere, even in admin.
 * @todo Override the method value() with a new option for language fallback.
 * @todo Override the method value() to display the title and the description in the language of the user.
 * @todo Override the method title() to display the title in the selected language.
 *
 * Warning: the privacy of each property should be checked.
 */
class LocaleValue extends AbstractHelper
{
    /**
     * @var array
     */
    protected $defaultLocales;

    /**
     * @var array
     */
    protected $defaultFallbacks;

    /**
     * @param string $defaultLocale
     * @param array $defaultFallbacks
     */
    public function __construct($defaultLocale, array $defaultFallbacks)
    {
        $this->defaultLocale = $defaultLocale ? [$defaultLocale] : [];
        $this->defaultFallbacks = $defaultFallbacks;
    }

    /**
     * Get the values in the site or specified languages, or a fallback.
     *
     * @see \Omeka\Api\Representation\AbstractResourceEntityRepresentation::value()
     *
     * @todo Manage internationalisation settings of the site.
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @param string $term The prefix:local_part
     * @param array $options
     * - type: (null) Get values of this type or these types only. Default valid
     *   types are "literal", "uri", "resource", "resource:item",
     *   "resource:media", and "resource:itemset. Returns all types by default.
     * - all: (false) If true, returns all values that match criteria. If false,
     *   returns the first matching value.
     * - default: (null) Default value if no values match criteria. Returns null
     *   by default.
     * - lang: (null) Get values of this language or these languages only.
     *   Returns values of all languages by default. If true, use the locale of
     *   the site and its fallbacks.
     * - fallbacks: (array) Ordered list of fallbacks for the language. An empty
     *   string is a fallback for values without language. When lang is true,
     *   this option is skipped in order to use the site settings.
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
        $optionType = !empty($options['type']);
        $optionLang = !empty($options['lang']);
        if ($optionType || $optionLang) {
            $matchingValues = [];
            // Prepare options for quicker match.
            // Note: isset() is quicker than in_array(), even with small arrays.
            if ($optionType) {
                $types = is_array($options['type']) ? $options['type'] : [$options['type']];
                $types = array_fill_keys($types, true);
            }
            if ($optionLang) {
                if ($options['lang'] === true) {
                    $langs = $this->defaultLocale;
                    $options['fallbacks'] = $this->defaultFallbacks;
                } else {
                    $langs = is_array($options['lang']) ? $options['lang'] : [$options['lang']];
                }
                $langs = array_fill_keys($langs, true);
            }

            // Order values by language.
            if ($optionLang && $options['fallbacks']) {
                $fallbacks = is_array($options['fallbacks']) ? $options['fallbacks'] : [$options['fallbacks']];
                $fallbacks = array_fill_keys(array_filter($fallbacks), true);
                $fallbacks[''] = true;

                // Keep only values with lang and fallbacks and order them by langs
                // and fallbacks directly.
                $valuesByLang = array_fill_keys(array_keys($langs + $fallbacks), []);
                foreach ($values as $value) {
                    if ($optionType && !isset($types[$value->type()])) {
                        continue;
                    }
                    $valuesByLang[$value->lang()][] = $value;
                }
                $valuesByLang = array_filter($valuesByLang);

                $matchingValues = array_intersect_key($valuesByLang, $langs);
                if (count($matchingValues)) {
                    $matchingValues = array_merge(...array_values($matchingValues));
                } else {
                    $matchingValues = array_intersect_key($valuesByLang, $fallbacks);
                    $matchingValues = $matchingValues ? reset($matchingValues) : [];
                }
            } else {
                foreach ($values as $value) {
                    if ($optionType && !isset($types[$value->type()])) {
                        continue;
                    }
                    if ($optionLang && !isset($langs[$value->lang()])) {
                        continue;
                    }
                    $matchingValues[] = $value;
                }
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
