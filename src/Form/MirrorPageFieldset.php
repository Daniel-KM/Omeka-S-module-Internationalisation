<?php declare(strict_types=1);
namespace Internationalisation\Form;

use Internationalisation\Form\Element\SitesPageSelect;
use Laminas\Form\Fieldset;

class MirrorPageFieldset extends Fieldset
{
    public function init(): void
    {
        $this->add([
            'name' => 'o:block[__blockIndex__][o:data][page]',
            'type' => SitesPageSelect::class,
            'options' => [
                'label' => 'Page', // @translate
                'info' => 'Private sites are marked with a "*". If a private page is selected, it will be hidden on the public site. The current page and recursive pages are forbidden.', // @translate
            ],
            'attributes' => [
                'id' => 'page',
                'required' => true,
                'class' => 'chosen-select',
            ],
        ]);
    }
}
