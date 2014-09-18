<?php

namespace ride\web\orm\taxonomy;

use ride\library\orm\OrmManager;

use ride\web\taxonomy\TagHandler;

/**
 * Tag handler implementation to process tags for the ORM taxonomy backend
 */
class OrmTagHandler implements TagHandler {

    /**
     * Constructs a new ORM tag handler
     * @param \ride\library\orm\OrmManager $orm Instance of the ORM manager
     * @param string|integer $vocabulary Id or slug of a vocabulary
     * @return null
     */
    public function __construct(OrmManager $orm, $vocabulary) {
        $this->orm = $orm;
        $this->vocabulary = $vocabulary;
    }

    /**
     * Converts an array of string tags to tag entries
     * @param array $tags
     * @return array Processed tags
     */
    public function processTags(array $tags) {
        $taxonomyTermModel = $this->orm->getTaxonomyTermModel();
        $taxonomyTerms = array();

        foreach ($tags as $tag) {
            $tag = trim($tag);
            if (!$tag) {
                continue;
            }

            if (!isset($taxonomyTerms[$tag])) {
                $taxonomyTerms[$tag] = $taxonomyTermModel->getByName($tag, $this->vocabulary);
            }
        }

        return $taxonomyTerms;
    }

}
