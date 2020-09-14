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
        $isNew = (bool) $this->getOption('is_new');
        $this
            ->setName('internationalisation')
            ->setAttribute('id', 'internationalisation')
            ->add([
                'name' => 'is_new',
                'type' => Element\Hidden::class,
                'attributes' => [
                    'id' => 'is_new',
                    'value' => (int) $isNew,
                ],
            ])
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
                'name' => 'remove_pages',
                'type' => $isNew ? Element\Hidden::class : Element\Checkbox::class,
                'options' => [
                    'label' => 'Remove existing pages', // @translate
                ],
                'attributes' => [
                    'id' => 'remove_pages',
                    'value' => (int) $isNew,
                ],
            ])
            ->add([
                'name' => 'data',
                'type' => Element\MultiCheckbox::class,
                'options' => [
                    'label' => 'Data to copy', // @translate
                    'value_options' => [
                        'pages' => 'Pages', // @translate
                        'navigation' => 'Site navigation', // @translate
                        'settings' => 'Site settings', // @translate
                        'theme' => 'Theme', // @translate
                        'item_pool' => 'Item pool', // @translate
                        'item_sets' => 'Item sets', // @translate
                        'permissions' => 'Permissions', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'data',
                    'required' => false,
                    'value' => $isNew ? ['pages', 'navigation', 'settings', 'theme', 'item_pool', 'item_sets', 'permissions'] : [],
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
            ]);
        if ($isNew) {
            $this
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
