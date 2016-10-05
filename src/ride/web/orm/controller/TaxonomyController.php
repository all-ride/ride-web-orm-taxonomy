<?php

namespace ride\web\orm\controller;

use ride\library\html\table\decorator\DataDecorator;
use ride\library\html\table\decorator\StaticDecorator;
use ride\library\http\Header;
use ride\library\i18n\I18n;
use ride\library\orm\OrmManager;
use ride\library\reflection\ReflectionHelper;
use ride\library\validation\exception\ValidationException;

use ride\service\OrmService;

use ride\web\base\controller\AbstractController;
use ride\web\orm\form\ScaffoldComponent;
use ride\web\orm\table\scaffold\decorator\ActionDecorator;
use ride\web\orm\table\scaffold\decorator\LocalizeDecorator;
use ride\web\orm\table\scaffold\ScaffoldTable;
use ride\web\WebApplication;

/**
 * Controller to manage the taxonomy terms and vocabularies
 */
class TaxonomyController extends AbstractController {

    /**
     * Constructs a new controller
     * @param \ride\library\orm\OrmManager $orm
     */
    public function __construct(OrmManager $orm) {
        $this->orm = $orm;
    }

    /**
     * Action to get an overview of the vocabularies
     * @param \ride\library\reflection\ReflectionHelper $reflectionHelper
     * @return null
     */
    public function vocabulariesAction(ReflectionHelper $reflectionHelper) {
        $vocabularyModel = $this->orm->getTaxonomyVocabularyModel();

        $translator = $this->getTranslator();

        $detailAction = $this->getUrl('taxonomy.vocabulary.edit', array('vocabulary' => '%id%'));
        $detailAction .= '?referer=' . urlencode($this->request->getUrl());

        $detailDecorator = new DataDecorator($reflectionHelper, $detailAction);
        $detailDecorator->mapProperty('title', 'name');

        $termsAction = $this->getUrl('taxonomy.term.list', array('vocabulary' => '%id%'));

        $termsDecorator = new ActionDecorator($translator->translate('title.terms'), $termsAction, null, null, $reflectionHelper);

        $table = new ScaffoldTable($vocabularyModel, $translator, $translator->getLocale(), false, false);
        $table->setPrimaryKeyField('id');
        $table->getModelQuery()->addOrderBy('{name} ASC');
        $table->addDecorator($detailDecorator, new StaticDecorator($translator->translate('label.vocabulary')));
        $table->addDecorator($termsDecorator);
        if ($this->getSecurityManager()->isPermissionGranted('taxonomy.vocabularies.remove')) {
            $table->addAction(
                $translator->translate('button.delete'),
                array($this, 'deleteVocabularies'),
                $translator->translate('label.table.confirm.delete')
            );
        }

        $baseUrl = $this->getUrl('taxonomy.vocabulary.list');
        $rowsPerPage = 10;

        $form = $this->processTable($table, $baseUrl, $rowsPerPage);
        if ($this->response->willRedirect() || $this->response->getView()) {
            return;
        }

        $vocabularyUrl = null;
        if ($this->getSecurityManager()->isPermissionGranted('taxonomy.vocabularies.add')) {
            $vocabularyUrl = $this->getUrl('taxonomy.vocabulary.add') . '?referer=' . urlencode($this->request->getUrl());
        }

        $this->setTemplateView('orm/taxonomy/vocabularies', array(
            'form' => $form->getView(),
            'table' => $table,
            'vocabularyUrl' => $vocabularyUrl
        ));
    }

    /**
     * Action to add or edit a vocabulary
     * @param integer $vocabulary Id of the vocabulary to edit
     * @return null
     */
    public function vocabularyFormAction(WebApplication $web, OrmService $ormService, $vocabulary = null) {
        if (!$this->getSecurityManager()->isPermissionGranted('taxonomy.vocabularies.add')) {
            throw new UnauthorizedException();
        }

        $vocabularyModel = $this->orm->getTaxonomyVocabularyModel();

        if ($vocabulary) {
            $vocabulary = $vocabularyModel->getById($vocabulary);
            if (!$vocabulary) {
                $this->response->setNotFound();

                return;
            }
        } else {
            $vocabulary = $vocabularyModel->createEntry();
        }

        $translator = $this->getTranslator();
        $referer = $this->request->getQueryParameter('referer');

        $vocabulary->extra = $vocabulary;

        $component = new ScaffoldComponent($web, $this->getSecurityManager(), $ormService, $vocabularyModel);
        $component->omitField('name');
        $component->omitField('slug');

        $form = $this->createFormBuilder($vocabulary);
        $form->addRow('name', 'string', array(
            'label' => $translator->translate('label.name'),
            'filters' => array(
                'trim' => array(),
            ),
            'validators' => array(
                'required' => array(),
            ),
        ));
        $form->addRow('extra', 'component', array(
            'component' => $component,
            'embed' => true,
        ));

        if ($vocabulary->id) {
            $form->addRow('slug', 'label', array(
                'label' => $translator->translate('label.slug'),
            ));
        }

        $form = $form->build();
        if ($form->isSubmitted()) {
            try {
                $form->validate();

                $vocabulary = $form->getData();

                $vocabularyModel->save($vocabulary);

                $this->addSuccess('success.data.saved', array('data' => $vocabulary->name));

                if (!$referer) {
                    $referer = $this->getUrl('taxonomy.vocabulary.list');
                }

                $this->response->setRedirect($referer);

                return;
            } catch (ValidationException $exception) {
                $this->setValidationException($exception, $form);
            }
        }

        $this->setTemplateView('orm/taxonomy/vocabulary.form', array(
            'form' => $form->getView(),
            'referer' => $referer,
            'vocabulary' => $vocabulary,
        ));
    }

    /**
     * Action to delete vocabularies from the model
     * @param array $data Array of primary keys
     * @return null
     */
    public function deleteVocabularies($data) {
        if (!$data) {
            return;
        }

        if (!$this->getSecurityManager()->isPermissionGranted('taxonomy.vocabularies.remove')) {
            throw new UnauthorizedException();
        }

        $vocabularyModel = $this->orm->getTaxonomyVocabularyModel();

        foreach ($data as $id) {
            $vocabulary = $vocabularyModel->getById($id);
            if (!$vocabulary) {
                continue;
            }

            $vocabularyModel->delete($vocabulary);

            $this->addSuccess('success.data.deleted', array('data' => $vocabulary->name));
        }

        $referer = $this->request->getHeader(Header::HEADER_REFERER);
        if (!$referer) {
            $referer = $this->request->getUrl();
        }

        $this->response->setRedirect($referer);
    }

    /**
     * Action to get an overview of the vocabulary terms
     * @param \ride\library\reflection\ReflectionHelper $reflectionHelper
     * @param integer $vocabulary Id of the vocabulary
     * @return null
     */
    public function termsAction(I18n $i18n, ReflectionHelper $reflectionHelper, $vocabulary, $locale = null) {
        if (!$locale) {
            $redirect = $this->getUrl('taxonomy.term.list.locale', array(
                'vocabulary' => $vocabulary,
                'locale' => $i18n->getLocale()->getCode(),
            ));

            $this->response->setRedirect($redirect);

            return;
        }

        $vocabularymodel = $this->orm->getTaxonomyVocabularyModel();
        $termModel = $this->orm->getTaxonomyTermModel();

        $vocabulary = $vocabularymodel->getById($vocabulary);
        if (!$vocabulary) {
            $this->response->setNotFound();

            return;
        }

        $locales = $i18n->getLocaleCodeList();
        $translator = $this->getTranslator();

        $detailAction = $this->getUrl('taxonomy.term.edit', array(
            'vocabulary' => $vocabulary->id,
            'locale' => $locale,
            'term' => '%id%',
        ));
        $detailAction .= '?referer=' . urlencode($this->request->getUrl());

        $defaultImage = $this->getTheme() . '/img/data.png';
        $imageUrlGenerator = $this->dependencyInjector->get('ride\\library\\image\\ImageUrlGenerator');

        $detailDecorator = new DataDecorator($reflectionHelper, $detailAction, $imageUrlGenerator, $defaultImage);
        $detailDecorator->mapProperty('title', 'name');
        $detailDecorator->mapProperty('teaser', 'description');

        $localizeDecorator = new LocalizeDecorator($termModel, $detailAction, $locale, $locales);

        $search = array('name' => 'name', 'description' => 'description');
        $order =array($translator->translate('label.name') => 'name', $translator->translate('label.weight') => 'weight');

        $table = new ScaffoldTable($termModel, $translator, $translator->getLocale(), $search, $order);
        $table->setPrimaryKeyField('id');
        $table->addDecorator($detailDecorator, new StaticDecorator($translator->translate('label.term')));
        $table->addDecorator($localizeDecorator);
        $table->setPaginationOptions(array(5, 10, 25, 50, 100, 250, 500));
        $table->addAction(
            $translator->translate('button.delete'),
            array($this, 'deleteTerms'),
            $translator->translate('label.table.confirm.delete')
        );
        $table->addAction(
            $translator->translate('button.delete.locale'),
            array($this, 'deleteLocalizedTerms'),
            $translator->translate('label.table.confirm.delete')
        );

        $query = $table->getModelQuery();
        $query->setLocale($locale);
        $query->addCondition('{vocabulary} = %1%', $vocabulary->id);

        $baseUrl = $this->getUrl('taxonomy.term.list.locale', array(
            'vocabulary' => $vocabulary->id,
            'locale' => $locale,
        ));
        $rowsPerPage = 10;
        $this->locale = $locale;

        $form = $this->processTable($table, $baseUrl, $rowsPerPage);
        if ($this->response->willRedirect() || $this->response->getView()) {
            return;
        }

        $this->setTemplateView('orm/taxonomy/terms', array(
            'form' => $form->getView(),
            'table' => $table,
            'vocabulary' => $vocabulary,
            'locales' => $i18n->getLocaleCodeList(),
            'locale' => $locale,
        ));
    }

    /**
     * Action to add or edit a term
     * @param integer $vocabulary Id of the vocabulary of the term
     * @param integer $term Id of the term to edit
     * @return null
     */
    public function termFormAction(I18n $i18n, WebApplication $web, OrmService $ormService, $vocabulary, $term = null, $locale = null) {
        if (!$locale) {
            if ($term) {
                $redirect = $this->getUrl('taxonomy.term.edit', array(
                    'vocabulary' => $vocabulary,
                    'term' => $term,
                    'locale' => $i18n->getLocale()->getCode(),
                ));
            } else {
                $redirect = $this->getUrl('taxonomy.term.add', array(
                    'vocabulary' => $vocabulary,
                    'locale' => $i18n->getLocale()->getCode(),
                ));
            }

            $this->response->setRedirect($redirect);

            return;
        }

        $vocabularyModel = $this->orm->getTaxonomyVocabularyModel();
        $termModel = $this->orm->getTaxonomyTermModel();

        $vocabulary = $vocabularyModel->getById($vocabulary);
        if (!$vocabulary) {
            $this->response->setNotFound();

            return;
        }

        if ($term) {
            $term = $termModel->getById($term, $locale);
            if (!$term) {
                $this->response->setNotFound();

                return;
            }
        } else {
            $term = $termModel->createEntry();
            $term->vocabulary = $vocabulary;
        }

        $translator = $this->getTranslator();
        $referer = $this->request->getQueryParameter('referer');

        $parent = $term->getParent();
        if ($parent) {
            $term->parentString = $parent->getId();
        }

        // add scaffold component for extra dynamic fields
        $term->extra = $term;

        $component = new ScaffoldComponent($web, $this->getSecurityManager(), $ormService, $termModel);
        $component->setLocale($locale);
        $component->omitField('vocabulary');
        $component->omitField('parent');
        $component->omitField('slug');

        $form = $this->createFormBuilder($term);
        $form->addRow('parentString', 'select', array(
           'label' => $translator->translate('label.parent'),
           'options' => array(null) + $termModel->getTaxonomyTree($vocabulary, null, $locale, 'weight'),
        ));
        $form->addRow('extra', 'component', array(
            'component' => $component,
            'embed' => true,
        ));

        if ($term->getId()) {
            $form->addRow('slug', 'label', array(
                'label' => $translator->translate('label.slug'),
            ));
        }

        $form = $form->build();
        if ($form->isSubmitted()) {
            try {
                $form->validate();

                $term = $form->getData();
                $term->setLocale($locale);

                if ($term->parentString) {
                    $term->setParent($termModel->createProxy($term->parentString));
                } else {
                    $term->setParent(null);
                }

                $termModel->save($term);

                $this->addSuccess('success.data.saved', array('data' => $term->name));

                if (!$referer) {
                    $referer = $this->getUrl('taxonomy.term.list.locale', array(
                        'vocabulary' => $vocabulary->getId(),
                        'locale' => $locale,
                    ));
                }

                $this->response->setRedirect($referer);

                return;
            } catch (ValidationException $exception) {
                $this->setValidationException($exception, $form);
            }
        }

        $this->setTemplateView('orm/taxonomy/term.form', array(
            'form' => $form->getView(),
            'vocabulary' => $vocabulary,
            'term' => $term,
            'locales' => $i18n->getLocaleCodeList(),
            'locale' => $locale,
            'referer' => $referer,
        ));
    }

    /**
     * Action to delete terms from the model
     * @param array $data Array of primary keys
     * @return null
     */
    public function deleteTerms($data) {
        if (!$data) {
            return;
        }

        $termModel = $this->orm->getTaxonomyTermModel();

        foreach ($data as $id) {
            $term = $termModel->getById($id, $this->locale);
            if(!$term) {
                continue;
            }

            $termModel->delete($term);

            $this->addSuccess('success.data.deleted', array('data' => $term->name));
        }

        $referer = $this->request->getHeader(Header::HEADER_REFERER);
        if (!$referer) {
            $referer = $this->request->getUrl();
        }

        $this->response->setRedirect($referer);
    }

    /**
     * Action to delete localized terms from the model
     * @param array $data Array of primary keys
     * @return null
     */
    public function deleteLocalizedTerms($data) {
        if (!$data) {
            return;
        }

        $termModel = $this->orm->getTaxonomyTermModel();

        foreach ($data as $id) {
            $term = $termModel->getById($id, $this->locale);
            if (!$term) {
                continue;
            }

            if ($termModel->deleteLocalized($term, $this->locale)) {
                $this->addSuccess('success.data.deleted', array('data' => $term->getName()));
            }
        }

        $referer = $this->request->getHeader(Header::HEADER_REFERER);
        if (!$referer) {
            $referer = $this->request->getUrl();
        }

        $this->response->setRedirect($referer);
    }

}
