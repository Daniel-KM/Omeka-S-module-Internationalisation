<?php declare(strict_types=1);
namespace Internationalisation\Form;

use Internationalisation\Form\Element\SitesPageSelect;

class SitePageForm extends \Omeka\Form\SitePageForm
{
    public function init(): void
    {
        parent::init();

        if (!$this->getOption('addPage')) {
            $this->add([
                'name' => 'o-module-internationalisation:related_page',
                'type' => SitesPageSelect::class,
                'options' => [
                    'label' => 'Translations', // @translate
                    'info' => 'The selected pages will be translations of the current page within a site group, that must be defined. The language switcher displays only one related page by site.', // @translate
                    'site_group' => 'internationalisation_site_groups',
                    'exclude_current_site' => true,
                ],
                'attributes' => [
                    'id' => 'o-module-internationalisation:related_page',
                    'required' => false,
                    'multiple' => true,
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select translations of this page…', // @translate
                ],
            ]);
        }

        $inputFilter = $this->getInputFilter();
        $inputFilter->add([
            'name' => 'o-module-internationalisation:related_page',
            'required' => false,
        ]);
    }

    public function setData($data)
    {
        if (isset($data['o-module-internationalisation:related_page'])
            && is_array($data['o-module-internationalisation:related_page'])
        ) {
            $data['o-module-internationalisation:related_page'] = array_map(function ($relatedPage) {
                return is_numeric($relatedPage)
                    ? $relatedPage
                    : (is_array($relatedPage)
                        ? $relatedPage['o:id']
                        : (is_object($relatedPage)
                            ? $relatedPage->id()
                            : null));
            }, $data['o-module-internationalisation:related_page']);
        }

        return parent::setData($data);
    }
}
