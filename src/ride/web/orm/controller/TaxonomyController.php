<?php

namespace ride\web\orm\controller;

use ride\library\html\table\decorator\DataDecorator;
use ride\library\http\Header;
use ride\library\http\Response;
use ride\library\i18n\I18n;
use ride\library\orm\OrmManager;
use ride\library\reflection\ReflectionHelper;
use ride\library\validation\exception\ValidationException;

use ride\web\base\controller\AbstractController;
use ride\web\orm\table\scaffold\decorator\ActionDecorator;
use ride\web\orm\table\scaffold\decorator\LocalizeDecorator;
use ride\web\orm\table\scaffold\ScaffoldTable;

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
        $vocabularymodel = $this->orm->getTaxonomyVocabularyModel();

        $translator = $this->getTranslator();

        $detailAction = $this->getUrl('taxonomy.vocabulary.edit', array('vocabulary' => '%id%'));
        $detailAction .= '?referer=' . urlencode($this->request->getUrl());

        $detailDecorator = new DataDecorator($reflectionHelper, $detailAction);
        $detailDecorator->mapProperty('title', 'name');

        $termsAction = $this->getUrl('taxonomy.term.list', array('vocabulary' => '%id%'));

        $termsDecorator = new ActionDecorator($translator->translate('title.terms'), $termsAction, null, null, $reflectionHelper);

        $table = new ScaffoldTable($vocabularymodel, $translator, $translator->getLocale(), false, false);
        $table->setPrimaryKeyField('id');
        $table->getModelQuery()->addOrderBy('{name} ASC');
        $table->addDecorator($detailDecorator);
        $table->addDecorator($termsDecorator);
        $table->addAction(
            $translator->translate('button.delete'),
            array($this, 'deleteVocabularies'),
            $translator->translate('label.table.confirm.delete')
        );

        $baseUrl = $this->getUrl('taxonomy.vocabulary.list');
        $rowsPerPage = 10;

        $form = $this->processTable($table, $baseUrl, $rowsPerPage);
        if ($this->response->willRedirect() || $this->response->getView()) {
            return;
        }

        $this->setTemplateView('orm/taxonomy/vocabularies', array(
            'form' => $form->getView(),
            'table' => $table,
        ));
    }

    /**
     * Action to add or edit a vocabulary
     * @param integer $vocabulary Id of the vocabulary to edit
     * @return null
     */
    public function vocabularyFormAction($vocabulary = null) {
        $vocabularymodel = $this->orm->getTaxonomyVocabularyModel();

        if ($vocabulary) {
            $vocabulary = $vocabularymodel->getById($vocabulary);
            if (!$vocabulary) {
                $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

                return;
            }
        } else {
            $vocabulary = $vocabularymodel->createEntry();
        }

        $translator = $this->getTranslator();
        $referer = $this->request->getQueryParameter('referer');

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

                $vocabularymodel->save($vocabulary);

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

        $vocabularyModel = $this->orm->getTaxonomyVocabularyModel();

        foreach ($data as $id) {
            $vocabulary = $vocabularyModel->getById($id);
            if(!$vocabulary) {
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
            $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

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

        $detailDecorator = new DataDecorator($reflectionHelper, $detailAction);
        $detailDecorator->mapProperty('title', 'name');
        $detailDecorator->mapProperty('teaser', 'description');

        $localizeDecorator = new LocalizeDecorator($termModel, $detailAction, $locale, $locales);

        $search = array('name' => 'name', 'description' => 'description');
        $order =array($translator->translate('label.name') => 'name', $translator->translate('label.weight') => 'weight');

        $table = new ScaffoldTable($termModel, $translator, $translator->getLocale(), $search, $order);
        $table->setPrimaryKeyField('id');
        $table->addDecorator($detailDecorator);
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
    public function termFormAction(I18n $i18n, $vocabulary, $term = null, $locale = null) {
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
            $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

            return;
        }

        if ($term) {
            $term = $termModel->getById($term, $locale);
            if (!$term) {
                $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

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

        $form = $this->createFormBuilder($term);
        $form->addRow('name', 'string', array(
            'label' => $translator->translate('label.name'),
            'filters' => array(
                'trim' => array(),
            ),
            'validators' => array(
                'required' => array(),
            ),
        ));
        $form->addRow('description', 'text', array(
            'label' => $translator->translate('label.description'),
            'filters' => array(
                'trim' => array(),
            ),
        ));
        $form->addRow('parentString', 'select', array(
           'label' => $translator->translate('label.parent'),
           'options' => array(null) + $termModel->getTaxonomyTree($vocabulary, null, $locale, 'weight'),
        ));
        $form->addRow('weight', 'integer', array(
            'label' => $translator->translate('label.weight'),
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
