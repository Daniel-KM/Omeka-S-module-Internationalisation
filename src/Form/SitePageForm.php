<?php declare(strict_types=1);

namespace Internationalisation\Form;

use Laminas\EventManager\Event;
use Laminas\EventManager\EventManagerAwareTrait;

class SitePageForm extends \Omeka\Form\SitePageForm
{
    use EventManagerAwareTrait;

    public function init(): void
    {
        parent::init();

        $event = new Event('form.add_elements', $this);
        $this->getEventManager()->triggerEvent($event);

        $inputFilter = $this->getInputFilter();
        $event = new Event('form.add_input_filters', $this, ['inputFilter' => $inputFilter]);
        $this->getEventManager()->triggerEvent($event);
    }
}
