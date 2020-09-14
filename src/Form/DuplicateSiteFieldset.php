<?php

namespace Internationalisation\Form;

use Omeka\Form\Element\SiteSelect;
use Zend\Form\Element;
use Zend\Form\Fieldset;
use Zend\InputFilter\InputFilter;

class DuplicateSiteFieldset extends Fieldset
{
    protected $label = 'Duplicate site'; // @translate

    public function init()
    {
        $this
            ->setName('internationalisation')
            ->setAttribute('id', 'internationalisation')
            ->add([
                'name' => 'source',
                'type' => SiteSelect::class,
                'options' => [
                    'label' => 'Site to copy', // @translate
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'source',
                    'class' => 'chosen-select',
                    'required' => false,
                    'multiple' => false,
                    'data-placeholder' => 'Select site…', // @translate
                ],
            ])
            ->add([
                'name' => 'data',
                'type' => Element\MultiCheckbox::class,
                'options' => [
                    'label' => 'Data to copy', // @translate
                    'value_options' => [
                        'metadata' => 'Site metadata', // @translate
                        'settings' => 'Site settings', // @translate
                        'pages' => 'Pages', // @translate
                        'item_pool' => 'Item pool', // @translate
                        'item_sets' => 'Item sets', // @translate
                        'permissions' => 'Permissions', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'data',
                    'required' => false,
                    'value' => ['metadata', 'settings', 'pages', 'item_pool', 'item_sets', 'permissions'],
                ],
            ])
            ->add([
                'name' => 'pages_mode',
                'type' => Element\Radio::class,
                'options' => [
                    'label' => 'Duplication mode for pages', // @translate
                    'value_options' => [
                        'block' => 'Copy each page and block individually', // @translate
                        'mirror' => 'Create linked mirror pages', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'pages_mode',
                    'required' => false,
                    'value' => 'block',
                ],
            ])
            ->add([
                'name' => 'locale',
                'type' => 'Omeka\Form\Element\LocaleSelect',
                'options' => [
                    'label' => 'Locale', // @translate
                    'info' => 'Locale/language code for this site. Leave blank to use the global locale setting.', // @translate
                ],
                'attributes' => [
                    'id' => 'locale',
                    'class' => 'chosen-select',
                ],
            ]);
    }

    public function updateInputFilter(InputFilter $inputFilter)
    {
        $inputFilter
            ->add([
                'name' => 'source',
                'required' => false,
            ])
            ->add([
                'name' => 'data',
                'required' => false,
            ])
            ->add([
                'name' => 'pages_mode',
                'required' => false,
            ])
            ->add([
                'name' => 'locale',
                'required' => false,
            ])
        ;
    }
}
