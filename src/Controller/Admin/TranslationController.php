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

    /**
     * @var \Omeka\Job\Dispatcher
     */
    protected $jobDispatcher;

    /**
     * @var bool
     */
    protected $hasTranslator;

    public function __construct(
        Connection $connection,
        \Omeka\Job\Dispatcher $jobDispatcher,
        bool $hasTranslator
    ) {
        $this->connection = $connection;
        $this->jobDispatcher = $jobDispatcher;
        $this->hasTranslator = $hasTranslator;
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
        $formDeleteSelected->setAttribute('id', 'confirm-delete-selected');
        $formDeleteSelected->setButtonLabel('Confirm Delete'); // @translate

        $formDeleteAll = $this->getForm(ConfirmForm::class);
        $formDeleteAll->setAttribute('action', $this->url()->fromRoute('admin/translation/default', ['action' => 'batch-delete-all'], true));
        $formDeleteAll->setAttribute('id', 'confirm-delete-all');
        $formDeleteAll->setButtonLabel('Confirm Delete'); // @translate
        $formDeleteAll->get('submit')->setAttribute('disabled', true);

        $languages = $this->api()->search('translateds', [], ['returnScalar' => 'lang'])->getContent();
        $languages = array_unique($languages);
        $languages = array_combine($languages, $languages);

        if (extension_loaded('intl')) {
            foreach ($languages as &$language) {
                $language = $this->getLocaleName($language);
            }
            unset($language);
            $collator = new \Collator("root");
            $collator->asort($languages);
        } else {
            natcasesort($languages);
        }

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
                    'label' => 'Submit',
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
        $confirmForm->setAttribute('action', $this->url()->fromRoute('admin/translation/id', ['language' => $language, 'action' => 'delete']));

        return new ViewModel([
            'language' => $language,
            'localeName' => $this->getLocaleName($language),
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
            'localeName' => $this->getLocaleName($language),
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
                // $removedLanguages = [];
                $data = $form->getData();
                $translations = $data['translations'];
                if (!$translations) {
                    $this->connection
                        ->executeStatement(
                            'DELETE FROM `translated` WHERE `lang` = :lang',
                            ['lang' => $language]
                        );
                    // $removedLanguages = [$language];
                } else {
                    asort($translations);

                    // Do not update translations that are not updated.
                    $kept = array_intersect_assoc($existingTranslations, $translations);
                    $translations = array_diff_key($translations, $kept);
                    $existingTranslations = array_diff_key($existingTranslations, $kept);

                    if ($translations || $existingTranslations) {
                        // Update translations that are updated.
                        $updatedTranslations = array_intersect_key($translations, $existingTranslations);
                        if ($updatedTranslations) {
                            $sql = 'UPDATE `translated` SET `translation` = :translation WHERE `lang` = :lang AND `string` = :string';
                            foreach ($updatedTranslations as $string => $translation) {
                                $bind = ['lang' => $language, 'string' => $string, 'translation' => $translation];
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
                                    'DELETE FROM `translated` WHERE `lang` = :lang AND `string` IN (:strings)',
                                    ['lang' => $language, 'strings' => array_values(array_map('strval', array_keys($deletedTranslations)))],
                                    ['lang' => \Doctrine\DBAL\ParameterType::STRING, 'strings' => \Doctrine\DBAL\Connection::PARAM_STR_ARRAY]
                                );
                            $existingTranslations = array_diff_key($existingTranslations, $deletedTranslations);
                            // Normally, there is no existing translations here.
                            // $removedLanguages = $deletedTranslations;
                        }

                        // Create new translations.
                        if ($translations) {
                            $sql = 'INSERT INTO `translated` (`lang`, `string`, `translation`) VALUES(:lang, :string, :translation)';
                            foreach ($translations as $string => $translation) {
                                $bind = ['lang' => $language, 'string' => $string, 'translation' => $translation];
                                $this->connection->executeStatement($sql, $bind);
                            }
                        }
                    }
                }
                $this->messenger()->addSuccess(new PsrMessage(
                    'Translations successfully updated.' // @translate
                ));
                $result = $this->updateTranslationFiles();
                if (!$result) {
                    $this->messenger()->addError(new PsrMessage(
                        'An error occurred when saving translations as file.' // @translate
                    ));
                }
                return $this->redirect()->toRoute('admin/translation/id', ['language' => $language]);
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $confirmForm = $this->getForm(ConfirmForm::class);
        $confirmForm->setAttribute('action', $this->url()->fromRoute('admin/translation/id', ['language' => $language, 'action' => 'delete']));

        return new ViewModel([
            'language' => $language,
            'localeName' => $this->getLocaleName($language),
            'translations' => $existingTranslations,
            'form' => $form,
            'confirmForm' => $confirmForm,
        ]);
    }

    /**
     * Copy a site page into other sites, from the sidebar of the page browse.
     *
     * The get displays the selector of the sites, the post does the copy.
     */
    public function copyPageAction()
    {
        $pageId = (int) $this->params('page-id');

        try {
            /** @var \Omeka\Api\Representation\SitePageRepresentation $page */
            $page = $this->api()->read('site_pages', $pageId)->getContent();
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            return $this->notFoundAction();
        }

        if (!$page->userIsAllowed('update')) {
            throw new \Omeka\Mvc\Exception\PermissionDeniedException();
        }

        // The route of this action has no site slug, so it is set explicitly.
        $browseUrl = $this->url()->fromRoute('admin/site/slug/page', ['site-slug' => $page->site()->slug()]);

        if (!$this->getRequest()->isPost()) {
            $view = new ViewModel([
                'page' => $page,
                'siteGroups' => $this->listSiteGroupsToCopy($page),
                // The own site of the page is displayed apart and first, so a
                // duplicate is not confused with a translation.
                'ownSite' => $page->site(),
                'sites' => $this->listSitesToCopy($page),
                'hasTranslator' => $this->hasTranslator,
            ]);
            return $view
                ->setTemplate('internationalisation/admin/translation/copy-page')
                ->setTerminal(true);
        }

        $siteIds = $this->siteIdsFromPost((array) $this->params()->fromPost('sites', []));
        if (!$siteIds) {
            $this->messenger()->addWarning('No site was selected, so the page was not copied.'); // @translate
            return $this->redirect()->toUrl($browseUrl);
        }

        $result = $this->copyPageToSites($pageId, $siteIds);

        if ($result['created']) {
            $this->messenger()->addSuccess(new PsrMessage(
                'The page was copied into {count} sites and set as a translation.', // @translate
                ['count' => count($result['created'])]
            ));
        }
        if ($result['skipped']) {
            $this->messenger()->addNotice(new PsrMessage(
                '{count} sites have already a translation of this page, so they were skipped.', // @translate
                ['count' => count($result['skipped'])]
            ));
        }
        // The copies are translated in the background by the module Translator.
        // The job is dispatched explicitly: the source page is not saved here,
        // so the listener of the module on the api is not triggered.
        if ($result['created']
            && $this->hasTranslator
            && $this->params()->fromPost('translate')
        ) {
            $this->dispatchTranslation($pageId);
        }

        if ($result['errors']) {
            $this->messenger()->addError(new PsrMessage(
                'The page could not be copied into {count} sites. See the logs.', // @translate
                ['count' => count($result['errors'])]
            ));
        }

        return $this->redirect()->toUrl($browseUrl);
    }

    /**
     * Translate the copies of a page with the module Translator.
     *
     * The job translates the copies related to the page, following the pairs of
     * languages set in the settings of the module.
     *
     * @see \Translator\Job\TranslatePages
     */
    protected function dispatchTranslation(int $pageId): void
    {
        try {
            $job = $this->jobDispatcher->dispatch(
                \Translator\Job\TranslatePages::class,
                ['page_ids' => $pageId]
            );
        } catch (\Throwable $e) {
            $this->messenger()->addError(new PsrMessage(
                'The translation could not be launched: {message}', // @translate
                ['message' => $e->getMessage()]
            ));
            return;
        }

        $message = new PsrMessage(
            'Translating the copies of the page in background (job {link_job}#{job_id}{link_end}).', // @translate
            [
                'link_job' => sprintf(
                    '<a href="%s">',
                    htmlspecialchars($this->url()->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId()]))
                ),
                'job_id' => $job->getId(),
                'link_end' => '</a>',
            ]
        );
        $this->messenger()->addSuccess($message->setEscapeHtml(false));
    }

    /**
     * Get the site groups where the page may be copied, without the sites that
     * have already a translation.
     */
    protected function listSiteGroupsToCopy($page): array
    {
        $siteGroups = $this->settings()->get('internationalisation_site_groups') ?: [];
        $slug = $page->site()->slug();
        if (empty($siteGroups[$slug])) {
            return [];
        }

        $group = array_values(array_diff($siteGroups[$slug], [$slug]));

        return $group
            ? [$slug => $group]
            : [];
    }

    /**
     * Get the other sites where the page may be copied.
     *
     * The own site of the page is excluded here: a copy inside it is a
     * duplicate and not a translation, so it is displayed apart.
     */
    protected function listSitesToCopy($page): array
    {
        $currentSiteId = $page->site()->id();

        $slugs = $this->api()->search('sites', [], ['returnScalar' => 'slug'])->getContent();
        $titles = $this->api()->search('sites', [], ['returnScalar' => 'title'])->getContent();

        $result = [];
        foreach ($slugs as $siteId => $slug) {
            if ($siteId != $currentSiteId) {
                $result[$siteId] = [
                    'slug' => $slug,
                    'title' => $titles[$siteId] ?? $slug,
                ];
            }
        }
        uasort($result, fn ($a, $b) => strnatcasecmp($a['title'], $b['title']));

        return $result;
    }

    /**
     * Convert the posted values into a list of site ids.
     *
     * A value may be a site id, or a group of sites prefixed with "group:".
     */
    protected function siteIdsFromPost(array $values): array
    {
        $siteGroups = $this->settings()->get('internationalisation_site_groups') ?: [];

        $slugs = [];
        $siteIds = [];
        foreach ($values as $value) {
            if (strpos((string) $value, 'group:') === 0) {
                $groupSlug = substr((string) $value, 6);
                $slugs = array_merge($slugs, $siteGroups[$groupSlug] ?? []);
            } else {
                $siteIds[] = (int) $value;
            }
        }

        if ($slugs) {
            $allSlugs = $this->api()->search('sites', [], ['returnScalar' => 'slug'])->getContent();
            $slugs = array_flip($slugs);
            foreach ($allSlugs as $siteId => $slug) {
                if (isset($slugs[$slug])) {
                    $siteIds[] = (int) $siteId;
                }
            }
        }

        return array_values(array_unique(array_filter($siteIds)));
    }

    public function deleteConfirmAction()
    {
        $language = strtolower(strtr($this->params('language'), '_', '-'));

        $translations = $this->getTranslations($language);

        $formDeleteSelected = $this->getForm(ConfirmForm::class);
        $formDeleteSelected->setAttribute('action', $this->url()->fromRoute('admin/translation/id', ['action' => 'delete', 'language' => $language], true));
        $formDeleteSelected->setAttribute('id', 'confirm-delete-selected');
        $formDeleteSelected->setButtonLabel('Confirm Delete'); // @translate

        $linkTitle = (bool) $this->params()->fromQuery('link-title', true);

        $view = new ViewModel([
            'form' => $formDeleteSelected,
            'language' => $language,
            'localeName' => $this->getLocaleName($language),
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
        if (!$this->userIsAllowed(\Internationalisation\Api\Adapter\TranslatedAdapter::class, 'delete')) {
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
                        'DELETE FROM `translated` WHERE `lang` = :lang',
                        ['lang' => $language]
                    );
                $this->updateTranslationFiles();
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }
        return $this->redirect()->toRoute('admin/translation');
    }

    public function batchDeleteAction()
    {
        if (!$this->userIsAllowed(\Internationalisation\Api\Adapter\TranslatedAdapter::class, 'batch_delete')) {
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
                    'DELETE FROM `translated` WHERE `lang` IN (:langs)',
                    ['langs' => array_values($languages)],
                    ['langs' => \Doctrine\DBAL\Connection::PARAM_STR_ARRAY]
                );
            $this->updateTranslationFiles();
        } else {
            $this->messenger()->addFormErrors($form);
        }
        return $this->redirect()->toRoute('admin/translation');
    }

    public function batchDeleteAllAction()
    {
        if (!$this->userIsAllowed(\Internationalisation\Api\Adapter\TranslatedAdapter::class, 'batch_delete_all')) {
            $this->messenger()->addError('You are not allowed to delete all translations.'); // @translate
            return $this->redirect()->toRoute('admin/translation');
        }

        $form = $this->getForm(ConfirmForm::class);
        $form->setData($this->getRequest()->getPost());
        if ($form->isValid()) {
            $this->connection
                ->executeStatement('DELETE FROM `translated`');
            $this->updateTranslationFiles();
        } else {
            $this->messenger()->addFormErrors($form);
        }
        return $this->redirect()->toRoute('admin/translation');
    }

    public function reindexAction()
    {
        $result = $this->updateTranslationFiles();
        if ($result) {
            $this->messenger()->addSuccess('Translations were reindexed.'); // @translate
        } else {
            $this->messenger()->addError('Translations were reindexed, but an issue occurred. Check logs.'); // @translate
        }
        return $this->redirect()->toRoute('admin/translation');
    }

    /**
     * Adapted from
     * @see \Omeka\Service\Form\Element\LocaleSelectFactory::getValueOption()
     */
    protected function getLocaleName(string $localeId): string
    {
        $localeName = extension_loaded('intl')
            ? \Locale::getDisplayName($localeId, $localeId)
            : $localeId;
        if ($localeId !== $localeName) {
            $localeName = mb_convert_case($localeName, MB_CASE_TITLE, 'UTF-8');
            $localeName = sprintf('%1$s [%2$s]', $localeName, $localeId); // @translate
        }
        return $localeName;
    }

    protected function getTranslations(string $language): array
    {
        // Use a direct query to avoid to load representations for a simple
        // two-column table.
        return $this->connection
            ->executeQuery('SELECT `string`, `translation` FROM `translated` WHERE `lang` = :lang', ['lang' => $language])
            ->fetchAllKeyValue();
    }
}
