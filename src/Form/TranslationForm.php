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
                        // The page has a single field, so the column of the
                        // label is a waste: it becomes a note above the text.
                        'label_as_note' => true,
                        // The form mode looks like the page of the language,
                        // so the batch edition opens as a text.
                        'default_display' => 'text',
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
