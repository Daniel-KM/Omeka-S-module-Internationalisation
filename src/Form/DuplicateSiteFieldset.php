<?php declare(strict_types=1);

namespace Internationalisation\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Laminas\InputFilter\InputFilter;
use Omeka\Form\Element\SiteSelect;

class DuplicateSiteFieldset extends Fieldset
{
    protected $label = 'Duplicate site'; // @translate

    public function init(): void
    {
        $isNew = (bool) $this->getOption('is_new');

        $data = [
            'pages' => 'Pages', // @translate
            'navigation' => 'Site navigation', // @translate
            'settings' => 'Site settings', // @translate
            'theme' => 'Theme', // @translate
            'item_pool' => 'Item pool', // @translate
            'item_sets' => 'Item sets', // @translate
            'permissions' => 'Permissions', // @translate
        ];
        if ($this->getOptions('collecting')) {
            $data['collecting'] = 'Collecting forms'; // @translate
        }

        $this
            ->setName('duplicate')
            ->setAttribute('id', 'duplicate')
            ->add([
                'name' => 'is_new',
                'type' => Element\Hidden::class,
                'attributes' => [
                    'id' => 'is_new',
                    'value' => (int) $isNew,
                ],
            ])
            ->add([
                'name' => 'remove',
                'type' => Element\MultiCheckbox::class,
                'options' => [
                    'label' => 'Data to remove', // @translate
                    'value_options' => $data,
                ],
                'attributes' => [
                    'id' => 'remove',
                    'required' => false,
                    'value' => $isNew ? array_keys($data) : [],
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
                'name' => 'copy',
                'type' => Element\MultiCheckbox::class,
                'options' => [
                    'label' => 'Data to copy', // @translate
                    'value_options' => $data,
                ],
                'attributes' => [
                    'id' => 'copy',
                    'required' => false,
                    'value' => $isNew ? array_keys($data) : [],
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
                        'label' => 'Locale/language code', // @translate
                        'info' => 'Leave blank to use the global locale setting.', // @translate
                    ],
                    'attributes' => [
                        'id' => 'locale',
                        'class' => 'chosen-select',
                    ],
                ]);
        }
    }

    public function updateInputFilter(InputFilter $inputFilter): void
    {
        $inputFilter
            ->add([
                'name' => 'source',
                'required' => false,
            ])
            ->add([
                'name' => 'remove',
                'required' => false,
            ])
            ->add([
                'name' => 'copy',
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
