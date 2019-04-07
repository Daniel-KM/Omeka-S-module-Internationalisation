<?php
namespace LanguageSwitcher\Form;

use LanguageSwitcher\Form\Element\SitePageSelect;

class SitePageForm extends \Omeka\Form\SitePageForm
{
    public function init()
    {
        parent::init();

        if (!$this->getOption('addPage')) {
            $this->add([
                'name' => 'o-module-language-switcher:related_page',
                'type' => SitePageSelect::class,
                'options' => [
                    'label' => 'Translations', // @translate
                    'info' => 'The selected pages are translations of the current page. The language switcher displays only one related page by site.', // @translate
                ],
                'attributes' => [
                    'id' => 'o-module-language-switcher:related_page',
                    'required' => false,
                    'multiple' => true,
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select translations of this page…' // @translate
                ],
            ]);
        }
    }

    public function setData($data)
    {
        if (isset($data['o-module-language-switcher:related_page'])
            && is_array($data['o-module-language-switcher:related_page'])
        ) {
            $data['o-module-language-switcher:related_page'] = array_map(function($relatedPage) {
                return is_numeric($relatedPage)
                    ? $relatedPage
                    : (is_array($relatedPage)
                        ? $relatedPage['o:id']
                        : (is_object($relatedPage)
                            ? $relatedPage->id()
                            : null));
            }, $data['o-module-language-switcher:related_page']);
        }

        return parent::setData($data);
    }
}
