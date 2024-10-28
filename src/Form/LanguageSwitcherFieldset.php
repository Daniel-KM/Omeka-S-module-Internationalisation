<?php declare(strict_types=1);

namespace Internationalisation\Form;

use Common\Form\Element as CommonElement;
use Laminas\Form\Fieldset;

class LanguageSwitcherFieldset extends Fieldset
{
    public function init(): void
    {
        $this
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][display_locale]',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Type of display for language', // @translate
                    'value_options' => [
                        'code' => 'Language code', // @translate
                        'flag' => 'Language flag', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'display_locale',
                ],
            ]);
    }
}
