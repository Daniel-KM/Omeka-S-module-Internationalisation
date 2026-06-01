<?php declare(strict_types=1);

namespace Internationalisation\Form;

use Laminas\Form\Form;
use Omeka\Form\Element as OmekaElement;

class TranslationForm extends Form
{
    public function init(): void
    {
        $this
            ->setAttribute('id', 'table-form')
            ->add([
                'name' => 'translations',
                'type' => OmekaElement\ArrayTextarea::class,
                'options' => [
                    'label' => 'List of strings and translations separated by " = " (space, equal, space)', // @translate
                    'as_key_value' => true,
                    // Use " = " (not "=") so source strings may contain "=".
                    'key_value_separator' => ' = ',
                ],
                'attributes' => [
                    'id' => 'translations',
                    'rows' => '20',
                ],
            ])
        ;
    }
}
