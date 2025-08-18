<?php declare(strict_types=1);

namespace Internationalisation\Form;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Omeka\Form\Element as OmekaElement;
use Omeka\View\Helper\Setting;

class SiteSettingsFieldset extends Fieldset
{
    /**
     * @var Setting
     */
    protected $siteSetting;

    protected $label = 'Internationalisation'; // @translate

    protected $elementGroups = [
        'internationalisation' => 'Internationalisation', // @translate
        'internationalisation_resources' => 'Internationalisation: Resources', // @translate
    ];

    public function init(): void
    {
        $hasModuleTable = class_exists('Table\Module', false);

        /**
         * The same options for internationalisation resources are used in
         * module Internationalisation and Translator.
         * When the two modules are present, the settings of the module
         * Translator are skipped.
         *
         * @see \Internationalisation\Form\SiteSettingsFieldset
         * @see \Translator\Form\SiteSettingsFieldset
         */

        $valueOptions = [
            'all' => [
                'value' => 'all',
                'label' => 'All values', // @translate
            ],
            'all_site' => [
                'value' => 'all_site',
                'label' => 'All values, with language of the site first', // @translate
            ],
            'all_site_iso' => [
                'value' => 'all_site_iso',
                'label' => 'All values, with language of the site or iso fallback first', // @translate
            ],
            'all_site_fallback' => [
                'value' => 'all_site_fallback',
                'label' => 'All values, with language of the site or custom fallback first', // @translate
            ],
            'site' => [
                'value' => 'site',
                'label' => 'Only values with the language of the site', // @translate
            ],
            'site_iso' => [
                'value' => 'site_iso',
                'label' => 'Only values with the language of the site, with iso fallback', // @translate
            ],
            'site_fallback' => [
                'value' => 'site_fallback',
                'label' => 'Only values with the language of the site, with custom fallback', // @translate
            ],
            /*
            'user_defined' => [
                'value' => 'user_defined',
                'label' => 'User choice in the public front-end (if theme allows it)', // @translate
            ],
            */
        ];

        $locale = $this->siteSetting->__invoke('locale');
        if (!$locale) {
            $valueOptions['all']['label'] = 'All values (set a locale for more options)'; // @translate
            foreach ($valueOptions as &$valueOption) {
                if ($valueOption['value'] !== 'all') {
                    $valueOption['attributes']['disabled'] = true;
                }
            }
            unset($valueOption);
        }

        $this
            ->setAttribute('id', 'internationalisation')
            ->setOption('element_groups', $this->elementGroups)

            ->add([
                'name' => 'internationalisation_translation_tables',
                'type' => $hasModuleTable
                    ? \Table\Form\Element\TablesSelect::class
                    : CommonElement\ArrayText::class,
                'options' => [
                    'element_group' => 'internationalisation',
                    'label' => 'Tables to use for special translations', // @translate
                    'info' => $hasModuleTable
                        ? 'The module Table allows to translate strings in admin board. The tables should have a language.' // @translate
                        : 'The module Table allows to translate strings in admin board. Separate tables slugs with a space. The tables should have a language.', // @translate
                    'documentation' => 'https://gitlab.com/Daniel-KM/Omeka-S-module-Internationalisation#tables-of-translations',
                    'disabled' => !$hasModuleTable,
                    // When Table is available.
                    'disable_group_by_owner' => true,
                    'slug_as_value' => true,
                    'empty_option' => '',
                    // When Table is not available.
                    'value_separator' => ' ',
                ],
                'attributes' => [
                    'id' => 'internationalisation_translation_tables',
                    'multiple' => $hasModuleTable,
                    'class' => $hasModuleTable ? 'chosen-select' : '',
                    'placeholder' => 'translation-fr translation-el-gr',
                    'data-placeholder' => 'Select tables…' // @translate,
                ],
            ])

            ->add([
                'name' => 'internationalisation_display_values',
                'type' => Element\Select::class,
                'options' => [
                    'element_group' => 'internationalisation_resources',
                    'label' => 'Language of values', // @translate
                    'info' => 'Display only the values in the specified language. It applies only for properties that contains at least one value with a language. The option can be overridden in the theme.', // @translate
                    'value_options' => $valueOptions,
                ],
                'attributes' => [
                    'id' => 'internationalisation_display_values',
                    'class' => 'chosen-select',
                ],
            ])

            ->add([
                'name' => 'internationalisation_fallbacks',
                'type' => OmekaElement\ArrayTextarea::class,
                'options' => [
                    'element_group' => 'internationalisation_resources',
                    'label' => 'Custom language fallbacks', // @translate
                    'info' => 'Specify values to display when a property has no value with the language of the site. Set one language code by line.', // @translate
                ],
                'attributes' => [
                    'id' => 'internationalisation_fallbacks',
                    'rows' => 5,
                    'placeholder' => <<<'TXT'
                        way
                        fra
                        fre
                        fr
                        TXT,
                ],
            ])

            ->add([
                'name' => 'internationalisation_required_languages',
                'type' => OmekaElement\ArrayTextarea::class,
                'options' => [
                    'element_group' => 'internationalisation_resources',
                    'label' => 'Required languages', // @translate
                    'info' => 'Specify values to display in all cases. Values without language are displayed in all cases. Set one language code by line.', // @translate
                ],
                'attributes' => [
                    'id' => 'internationalisation_required_languages',
                    'rows' => 5,
                    'placeholder' => <<<'TXT'
                        apy
                        way
                        fra
                        TXT,
                ],
            ]);
    }

    public function setSiteSetting(Setting $siteSetting): self
    {
        $this->siteSetting = $siteSetting;
        return $this;
    }

    public function getSiteSetting(): Setting
    {
        return $this->siteSetting;
    }
}
