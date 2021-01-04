<?php declare(strict_types=1);
namespace Internationalisation\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Omeka\Form\Element\ArrayTextarea;
use Omeka\View\Helper\Setting;

class SiteSettingsFieldset extends Fieldset
{
    /**
     * @var Setting
     */
    protected $siteSetting;

    protected $label = 'Internationalisation'; // @translate

    public function init(): void
    {
        $siteSetting = $this->getSiteSetting();
        $locale = $siteSetting('locale');
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
            ->add([
                'name' => 'internationalisation_display_values',
                'type' => Element\Select::class,
                'options' => [
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
                'type' => ArrayTextarea::class,
                'options' => [
                    'label' => 'Custom language fallbacks', // @translate
                    'info' => 'Specify values to display when a property has no value with the language of the site. Set one language code by line.', // @translate
                ],
                'attributes' => [
                    'id' => 'internationalisation_fallbacks',
                    'placeholder' => 'way
fra
fre
fr',
                ],
            ])

            ->add([
                'name' => 'internationalisation_required_languages',
                'type' => ArrayTextarea::class,
                'options' => [
                    'label' => 'Required languages', // @translate
                    'info' => 'Specify values to display in all cases. Values without language are displayed in all cases. Set one language code by line.', // @translate
                ],
                'attributes' => [
                    'id' => 'internationalisation_required_languages',
                    'placeholder' => 'apy
way
fra',
                ],
            ]);
    }

    /**
     * @param Setting $siteSetting
     */
    public function setSiteSetting(Setting $siteSetting)
    {
        $this->siteSetting = $siteSetting;
        return $this;
    }

    /**
     * @return \Omeka\View\Helper\Setting
     */
    public function getSiteSetting()
    {
        return $this->siteSetting;
    }
}
