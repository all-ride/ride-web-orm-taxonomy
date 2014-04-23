<?php

namespace ride\web\orm\taxonomy;

use ride\library\orm\model\GenericModel;

/**
 * Model for the taxonomy terms
 */
class TaxonomyTermModel extends GenericModel {

    /**
     * Gets a term by it's name
     * @param string $name Name of the term
     * @param integer|string|TaxonomyVocabulary $vocabulary Vocabulary where the
     * tag should reside in
     * @return TaxonomyTerm Instance of a term
     */
    public function getByName($name, $vocabulary = null) {
        $query = $this->createQuery();
        $query->setRecursiveDepth(0);
        $query->addCondition('{name} = %1%', $name);

        if ($vocabulary) {
            if (is_object($vocabulary)) {
                $query->addCondition('{vocabulary} = %1%', $vocabulary->id);
            } elseif (is_numeric($vocabulary)) {
                $query->addCondition('{vocabulary} = %1%', $vocabulary);
            } else {
                $query->addCondition('{vocabulary.slug} = %1%', $vocabulary);
            }
        }

        $term = $query->queryFirst();
        if (!$term) {
            $term = $this->createData();
            $term->name = $name;

            if ($vocabulary) {
                if (is_object($vocabulary)) {
                    $vocabulary = $vocabulary->id;
                } elseif (!is_numeric($vocabulary)) {
                    $vocabularyModel = $this->orm->getTaxonomyVocabularyModel();
                    $vocabulary = $vocabularyModel->getBy('slug', $vocabulary, 0);
                    if ($vocabulary) {
                        $vocabulary = $vocabulary->id;
                    }
                }

                $term->vocabulary = $vocabulary;
            }
        }

        return $term;
    }

}
