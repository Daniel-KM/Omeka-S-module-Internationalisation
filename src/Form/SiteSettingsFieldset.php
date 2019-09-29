<?php
namespace Internationalisation\Form;

use Omeka\View\Helper\Setting;
use Zend\Form\Element;
use Zend\Form\Fieldset;

class SiteSettingsFieldset extends Fieldset
{
    /**
     * @var Setting
     */
    protected $siteSetting;

    protected $label = 'Internationalisation'; // @translate

    public function init()
    {
        $siteSetting = $this->getSiteSetting();
        $locale = $siteSetting('locale');
        if ($locale) {
            $valueOptions = [
                'all' => 'All values', // @translate
                'all_ordered' => 'All values, with language of the site first', // @translate
                'site_lang' => 'Only values with the language of the site', // @translate
                'site_fallback' => 'Only values with the language of the site, with fallback', // @translate
                // 'user_defined' => 'User choice in the public front-end (if theme allows it)', // @translate
            ];
            $info = 'Display only the values in the language of the site. It applies only for properties that contains at least one value with a language. The option can be overridden in the theme'; // @translate
        } else {
            $valueOptions = [];
            $info = 'Display only the values in the language of the site. This option is available only when the site has a language.'; // @translate
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
            ]);

        $this
            ->add([
                'name' => 'internationalisation_fallbacks',
                'type' => Element\Textarea::class,
                'options' => [
                    'label' => 'Language fallbacks', // @translate
                    'info' => 'Specify values to display when a property has no value with the language of the site. Set one language code by line.', // @translate
                ],
                'attributes' => [
                    'id' => 'internationalisation_fallbacks',
                    'placeholder' => 'way
fra
fre
fr'
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
