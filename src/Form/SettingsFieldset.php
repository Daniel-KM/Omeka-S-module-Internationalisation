<?php declare(strict_types=1);

namespace Internationalisation\Form;

use Common\Form\Element as CommonElement;
use Laminas\Form\Fieldset;
use Omeka\Form\Element as OmekaElement;

class SettingsFieldset extends Fieldset
{
    protected $label = 'Internationalisation'; // @translate

    protected $elementGroups = [
        'internationalisation' => 'Internationalisation', // @translate
    ];

    public function init(): void
    {
        // See \Internationalisation\Module::handleMainSettings().
        $this
            ->setAttribute('id', 'internationalisation')
            ->setOption('element_groups', $this->elementGroups)

            ->add([
                'name' => 'internationaliation_translation_tables',
                'type' => CommonElement\ArrayText::class,
                'options' => [
                    'element_group' => 'internationalisation',
                    'label' => 'Tables to use for translation', // @translate
                    'info' => 'The module Table allows to translate strings in admin board. Separate table slugs with a space. The table should be associative and should have a language.', // @translate
                    'documentation' => 'https://gitlab.com/Daniel-KM/Omeka-S-module-Internationalisation#tables-of-translations',
                    'value_separator' => ' ',
                ],
                'attributes' => [
                    'id' => 'internationaliation_translation_tables',
                    'placeholder' => 'translation-fr translation-el-gr',
                ],
            ])
            ->add([
                    'name' => 'internationalisation_site_groups',
                    'type' => OmekaElement\RestoreTextarea::class,
                    'options' => [
                        'element_group' => 'internationalisation',
                        'label' => 'Site groups', // @translate
                        'info' => 'Group some sites with a different language so they can be managed together as a whole. Set all site slugs by group, one by line, with or without comma separator.', // @translate
                        'restoreButtonText' => 'Remove all groups', // @translate
                    ],
                    'restoreButtonText' => 'Remove all groups', // @translate
                    'attributes' => [
                        'id' => 'internationalisation_site_groups',
                        'placeholder' => <<<'TXT'
                            my-site-fra my-site-rus my-site-way
                            my-exhibit-fra my-exhibit-rus
                            other-exhibit-fra other-exhibit-rus
                            TXT,
                        'rows' => 10,
                    ],
            ])
        ;
    }
}
