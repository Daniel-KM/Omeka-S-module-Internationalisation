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
    ];

    public function init(): void
    {
        $hasModuleTable = class_exists('Table\Module', false);

        $locale = $this->siteSetting->__invoke('locale');
        if ($locale) {
            $valueOptions = [
                'all' => 'All values', // @translate
                'all_site' => 'All values, with language of the site first', // @translate
                'all_site_iso' => 'All values, with language of the site or iso fallback first', // @translate
                'all_site_fallback' => 'All values, with language of the site or custom fallback first', // @translate
                'site' => 'Only values with the language of the site', // @translate
                'site_iso' => 'Only values with the language of the site, with iso fallback', // @translate
                'site_fallback' => 'Only values with the language of the site, with custom fallback', // @translate
                // 'user_defined' => 'User choice in the public front-end (if theme allows it)', // @translate
            ];
            $info = 'Display only the values in the specified language. It applies only for properties that contains at least one value with a language. The option can be overridden in the theme.'; // @translate
        } else {
            $valueOptions = [
                'all' => 'All values', // @translate
            ];
            $info = 'Display only the values in the specified language. This option is available only when the site has a language.'; // @translate
        }

        $this
            ->setAttribute('id', 'internationalisation')
            ->setOption('element_groups', $this->elementGroups)

            ->add([
                'name' => 'internationaliation_translation_tables',
                'type' => $hasModuleTable
                    ? \Table\Form\Element\TablesSelect::class
                    : CommonElement\ArrayText::class,
                'options' => [
                    'element_group' => 'internationalisation',
                    'label' => 'Tables to use for translation', // @translate
                    'info' => 'The module Table allows to translate strings in admin board. Separate table slugs with a space. The table should be associative and should have a language.', // @translate
                    'documentation' => 'https://gitlab.com/Daniel-KM/Omeka-S-module-Internationalisation#tables-of-translations',
                    // When Table is available.
                    'disable_group_by_owner' => true,
                    'slug_as_value' => true,
                    'empty_option' => '',
                    // When Table is not available.
                    'value_separator' => ' ',
                ],
                'attributes' => [
                    'id' => 'internationaliation_translation_tables',
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
                    'element_group' => 'internationalisation',
                    'label' => 'Language of values', // @translate
                    'info' => $info,
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
                    'element_group' => 'internationalisation',
                    'label' => 'Custom language fallbacks', // @translate
                    'info' => 'Specify values to display when a property has no value with the language of the site. Set one language code by line.', // @translate
                ],
                'attributes' => [
                    'id' => 'internationalisation_fallbacks',
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
                    'element_group' => 'internationalisation',
                    'label' => 'Required languages', // @translate
                    'info' => 'Specify values to display in all cases. Values without language are displayed in all cases. Set one language code by line.', // @translate
                ],
                'attributes' => [
                    'id' => 'internationalisation_required_languages',
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
