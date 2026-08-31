<?php declare(strict_types=1);

namespace Internationalisation\Form;

use Common\Form\Element as CommonElement;
use Laminas\Form\Form;

class TranslationForm extends Form
{
    public function init(): void
    {
        $this
            ->setAttribute('id', 'table-form')
            ->add([
                'name' => 'translations',
                'type' => CommonElement\ArrayTextarea::class,
                'options' => [
                    'label' => 'List of strings and translations separated by " = " (space, equal, space)', // @translate
                    'as_key_value' => true,
                    // Use " = " (not "=") so source strings may contain "=".
                    'key_value_separator' => ' = ',
                    'pairs_editor' => [
                        'key_label' => 'String', // @translate
                        'value_label' => 'Translation', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'translations',
                    'rows' => '20',
                ],
            ])
        ;
    }
}
