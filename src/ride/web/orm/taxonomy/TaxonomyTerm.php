<?php

namespace ride\web\orm\taxonomy;

use ride\library\orm\model\data\Data;

/**
 * Data container for a term entry
 */
class TaxonomyTerm extends Data {

    /**
     * Vocabulary of the term
     * @var null|integer|TaxonomyVocabulary
     */
    public $vocabulary;

    /**
     * Parent term
     * @var null|integer|TaxonomyTerm
     */
    public $parent;

    /**
     * Name of the term
     * @var string
     */
    public $name;

    /**
     * Index for a cloud
     * @var integer
     */
    public $weight;

    /**
     * Gets a string representation of this term
     * @return string
     */
    public function __toString() {
        return $this->name;
    }

}
