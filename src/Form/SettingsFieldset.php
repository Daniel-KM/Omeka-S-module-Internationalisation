<?php declare(strict_types=1);
namespace Internationalisation\Form;

use Laminas\Form\Fieldset;
use Omeka\Form\Element\RestoreTextarea;

class SettingsFieldset extends Fieldset
{
    protected $label = 'Internationalisation'; // @translate

    public function init(): void
    {
        // See \Internationalisation\Module::handleMainSettings().
        $this
            ->setAttribute('id', 'internationalisation')
            ->add([
                    'name' => 'internationalisation_site_groups',
                    'type' => RestoreTextarea::class,
                    'options' => [
                        'label' => 'Site groups', // @translate
                        'info' => 'Group some sites with a different language so they can be managed together as a whole. Set all site slugs by group, one by line, with or without comma separator.', // @translate
                        'restoreButtonText' => 'Remove all groups', // @translate
                    ],
                    'restoreButtonText' => 'Remove all groups', // @translate
                    'attributes' => [
                        'id' => 'internationalisation_site_groups',
                        'placeholder' => 'my-site-fra my-site-rus my-site-way
my-exhibit-fra my-exhibit-rus
other-exhibit-fra other-exhibit-rus',
                        'rows' => 10,
                    ],
            ])
        ;
    }
}
