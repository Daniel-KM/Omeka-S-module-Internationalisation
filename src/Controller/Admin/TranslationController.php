<?php declare(strict_types=1);

namespace Internationalisation\Controller\Admin;

use Common\Stdlib\PsrMessage;
use Doctrine\DBAL\Connection;
use Internationalisation\Form\TranslationForm;
use Laminas\Form\Form;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Omeka\Form\ConfirmForm;

class TranslationController extends AbstractActionController
{
    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function indexAction()
    {
        $params = $this->params()->fromRoute();
        $params['action'] = 'browse';
        return $this->forward()->dispatch(__CLASS__, $params);
    }

    public function browseAction()
    {
        $formDeleteSelected = $this->getForm(ConfirmForm::class);
        $formDeleteSelected->setAttribute('action', $this->url()->fromRoute('admin/translation/default', ['action' => 'batch-delete'], true));
        $formDeleteSelected->setButtonLabel('Confirm Delete'); // @translate
        $formDeleteSelected->setAttribute('id', 'confirm-delete-selected');

        $formDeleteAll = $this->getForm(ConfirmForm::class);
        $formDeleteAll->setAttribute('action', $this->url()->fromRoute('admin/translation/default', ['action' => 'batch-delete-all'], true));
        $formDeleteAll->setButtonLabel('Confirm Delete'); // @translate
        $formDeleteAll->setAttribute('id', 'confirm-delete-all');
        $formDeleteAll->get('submit')->setAttribute('disabled', true);

        $languages = $this->api()->search('translations', [], ['returnScalar' => 'lang'])->getContent();
        $languages = array_unique($languages);
        sort($languages);

        /** @var \Table\Form\TableForm $form */
        $formLanguage = new Form();
        $formLanguage
            ->setAttribute('id', 'add-language')
            // ->setAttribute('action', $this->url()->fromRoute('admin/translation/default', ['action' => 'add']))
            ->add([
                'name' => 'o:lang',
                'type' => \Laminas\Form\Element\Text::class,
                'options' => [
                    'label' => 'Language with optional locale', // @translate
                ],
                'attributes' => [
                    'id' => 'o-lang',
                    'pattern' => '[a-zA-Z]{2,3}((-|_)[a-zA-Z0-9]{2,4})?',
                    'required' => true,
                    'placeholder' => 'el-GR',
                ],
            ])
            ->add([
                'name' => 'submit',
                'type' => \Laminas\Form\Element\Button::class,
                'options' => [
                    'label' => 'Add language',
                ],
                'attributes' => [
                    'id' => 'add-language-submit',
                    'type' => 'submit',
                ],
            ])
        ;

        if ($this->getRequest()->isPost()) {
            $post = $this->params()->fromPost();
            $formLanguage->setData($post);
            if ($formLanguage->isValid()) {
                $data = $formLanguage->getData();
                $language = $data['o:lang'];
                $language = strtolower(strtr($language, '_', '-'));
                return $this->redirect()->toRoute('admin/translation/id', ['action' => 'edit', 'language' => $language]);
            }
        }

        return new ViewModel([
            'languages' => $languages,
            'formLanguage' => $formLanguage,
            'formDeleteSelected' => $formDeleteSelected,
            'formDeleteAll' => $formDeleteAll,
        ]);
    }

    public function showAction()
    {
        $language = strtolower(strtr($this->params('language'), '_', '-'));
        $translations = $this->getTranslations($language);

        $confirmForm = $this->getForm(ConfirmForm::class);
        $confirmForm->setAttribute('action', 'admin/translation/id', ['language' => $language, 'action' => 'delete']);

        return new ViewModel([
            'language' => $language,
            'translations' => $translations,
            'confirmForm' => $confirmForm,
        ]);
    }

    public function showDetailsAction()
    {
        $language = strtolower(strtr($this->params('language'), '_', '-'));
        $translations = $this->getTranslations($language);

        $linkTitle = (bool) $this->params()->fromQuery('link-title', true);

        $view = new ViewModel([
            'language' => $language,
            'translations' => $translations,
            'linkTitle' => $linkTitle,
        ]);
        return $view
            ->setTerminal(true);
    }

    public function addAction()
    {
        return $this->addEdit(true);
    }

    public function editAction()
    {
        return $this->addEdit(false);
    }

    protected function addEdit(bool $add)
    {
        $language = strtolower(strtr($this->params('language'), '_', '-'));

        $existingTranslations = $this->getTranslations($language);

        /** @var \Internationalisation\\Form\TranslationForm $form */
        $form = $this->getForm(TranslationForm::class);
        $form
            ->setAttribute('action', $this->url()->fromRoute(null, [], true))
            ->setAttribute('enctype', 'multipart/form-data')
            ->setAttribute('id', 'edit-translation');

        $form->get('translations')->setValue($existingTranslations);

        if ($this->getRequest()->isPost()) {
            $post = $this->params()->fromPost();
            $form->setData($post);
            if ($form->isValid()) {
                $message = new PsrMessage('Translations successfully updated.'); // @translate
                $data = $form->getData();
                $translations = $data['translations'];
                if (!$translations) {
                    $this->connection
                        ->executeStatement(
                            'DELETE FROM `translation` WHERE `lang` = :lang',
                            ['lang' => $language]
                        );
                    $this->messenger()->addSuccess($message);
                    return $this->redirect()->toRoute('admin/translation/id', ['language' => $language]);
                }

                asort($translations);

                // Do not update translations that are not updated.
                $kept = array_intersect_assoc($existingTranslations, $translations);
                $translations = array_diff_key($translations, $kept);
                $existingTranslations = array_diff_key($existingTranslations, $kept);
                if (!$translations && !$existingTranslations) {
                    $this->messenger()->addSuccess($message);
                    return $this->redirect()->toRoute('admin/translation/id', ['language' => $language]);
                }

                // Update translations that are updated.
                $updatedTranslations = array_intersect_key($translations, $existingTranslations);
                if ($updatedTranslations) {
                    $sql = 'UPDATE `translation` SET `translated` = :translated WHERE `lang` = :lang AND `string` = :string';
                    foreach ($updatedTranslations as $string => $translated) {
                        $bind = ['lang' => $language, 'string' => $string, 'translated' => $translated];
                        $this->connection->executeStatement($sql, $bind);
                    }
                    $translations = array_diff_key($translations, $updatedTranslations);
                    $existingTranslations = array_diff_key($existingTranslations, $updatedTranslations);
                }

                // Delete translations that are deleted.
                $deletedTranslations = array_diff_key($existingTranslations, $translations);
                if ($deletedTranslations) {
                    $this->connection
                        ->executeStatement(
                            'DELETE FROM `translation` WHERE `lang` = :lang AND `string` IN (:strings)',
                            ['lang' => $language, 'strings' => array_values(array_map('strval', array_keys($deletedTranslations)))],
                            ['lang' => \Doctrine\DBAL\ParameterType::STRING, 'strings' => \Doctrine\DBAL\Connection::PARAM_STR_ARRAY]
                        );
                    $existingTranslations = array_diff_key($existingTranslations, $deletedTranslations);
                    // Normally, there is no existing translations here.
                }

                // Create new translations.
                if ($translations) {
                    $sql = 'INSERT INTO `translation` (`lang`, `string`, `translated`) VALUES(:lang, :string, :translated)';
                    foreach ($translations as $string => $translated) {
                        $bind = ['lang' => $language, 'string' => $string, 'translated' => $translated];
                        $this->connection->executeStatement($sql, $bind);
                    }
                }
                $this->messenger()->addSuccess($message);
                return $this->redirect()->toRoute('admin/translation/id', ['language' => $language]);
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $confirmForm = $this->getForm(ConfirmForm::class);
        $confirmForm->setAttribute('action', 'admin/translation/id', ['language' => $language, 'action' => 'delete']);

        return new ViewModel([
            'language' => $language,
            'translations' => $existingTranslations,
            'form' => $form,
            'confirmForm' => $confirmForm,
        ]);
    }

    public function deleteConfirmAction()
    {
        $language = strtolower(strtr($this->params('language'), '_', '-'));

        $translations = $this->getTranslations($language);

        $formDeleteSelected = $this->getForm(ConfirmForm::class);
        $formDeleteSelected->setAttribute('action', $this->url()->fromRoute('admin/translation/id', ['action' => 'delete', 'language' => $language], true));
        $formDeleteSelected->setButtonLabel('Confirm Delete'); // @translate
        $formDeleteSelected->setAttribute('id', 'confirm-delete-selected');

        $linkTitle = (bool) $this->params()->fromQuery('link-title', true);

        $view = new ViewModel([
            'form' => $formDeleteSelected,
            'language' => $language,
            'resource' => 'translations',
            'translations' => $translations,
            'linkTitle' => $linkTitle,
            'resourceLabel' => 'language', // @translate
            'wrapSidebar' => true,
            'partialPath' => 'internationalisation/admin/translation/show-details',
            'isActiveSidebar' => true,
        ]);
        return $view
            ->setTemplate('internationalisation/admin/translation/delete-confirm-details')
            ->setTerminal(true);
    }

    public function deleteAction()
    {
        if (!$this->userIsAllowed(\Internationalisation\Api\Adapter\TranslationAdapter::class, 'delete')) {
            $this->messenger()->addError('You are not allowed to delete translations.'); // @translate
        } elseif ($this->getRequest()->isPost()) {
            $form = $this->getForm(ConfirmForm::class);
            $form->setData($this->getRequest()->getPost());
            if ($form->isValid()) {
                $language = strtolower(strtr($this->params('language'), '_', '-'));
                //  TODO Use api to delete translations?
                // Don't use api, this is a simple two columns table and there
                // are no event.
                $this->connection
                    ->executeStatement(
                        'DELETE FROM `translation` WHERE `lang` = :lang',
                        ['lang' => $language]
                    );
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }
        return $this->redirect()->toRoute('admin/translation');
    }

    public function batchDeleteAction()
    {
        if (!$this->userIsAllowed(\Internationalisation\Api\Adapter\TranslationAdapter::class, 'batch_delete')) {
            $this->messenger()->addError('You are not allowed to delete translations.'); // @translate
            return $this->redirect()->toRoute('admin/translation');
        }

        $languages = $this->params()->fromPost('languages', []);
        $languages = is_array($languages)
            ? array_filter(array_unique(array_map(fn ($v) => strtolower(strtr($v, '_', '-')), $languages)))
            : [];
        if (!$languages) {
            $this->messenger()->addError('You must select at least one language to delete.'); // @translate
            return $this->redirect()->toRoute('admin/translation');
        }

        $form = $this->getForm(ConfirmForm::class);
        $form->setData($this->getRequest()->getPost());
        if ($form->isValid()) {
            $this->connection
                ->executeStatement(
                    'DELETE FROM `translation` WHERE `lang` IN (:langs)',
                    ['langs' => array_values($languages)],
                    ['langs' => \Doctrine\DBAL\Connection::PARAM_STR_ARRAY]
                );
        } else {
            $this->messenger()->addFormErrors($form);
        }
        return $this->redirect()->toRoute('admin/translation');
    }

    public function batchDeleteAllAction()
    {
        if (!$this->userIsAllowed(\Internationalisation\Api\Adapter\TranslationAdapter::class, 'batch_delete_all')) {
            $this->messenger()->addError('You are not allowed to delete all translations.'); // @translate
            return $this->redirect()->toRoute('admin/translation');
        }

        $form = $this->getForm(ConfirmForm::class);
        $form->setData($this->getRequest()->getPost());
        if ($form->isValid()) {
            $this->connection
                ->executeStatement('DELETE FROM `translation`');
        } else {
            $this->messenger()->addFormErrors($form);
        }
        return $this->redirect()->toRoute('admin/translation');
    }

    protected function getTranslations(string $language): array
    {
        // Use a direct query to avoid to load representations for a simple
        // two-column table.
        return $this->connection
            ->executeQuery('SELECT `string`, `translated` FROM `translation` WHERE `lang` = :lang', ['lang' => $language])
            ->fetchAllKeyValue();
    }
}
