<?php

namespace ride\web\orm\taxonomy;

use ride\library\orm\OrmManager;

use ride\web\taxonomy\TagHandler;

/**
 * Tag handler implementation to process tags for the ORM taxonomy backend
 */
class OrmTagHandler implements TagHandler {

    /**
     * Instance of the ORM manager
     * @var \ride\library\orm\OrmManager
     */
    protected $orm;

    /**
     * Id of the vocabulary
     * @var string|integer
     */
    protected $vocabulary;

    /**
     * Code of the locale to process the tags in
     * @var string
     */
    protected $locale;

    /**
     * Constructs a new ORM tag handler
     * @param \ride\library\orm\OrmManager $orm Instance of the ORM manager
     * @param string|integer $vocabulary Id or slug of a vocabulary
     * @return null
     */
    public function __construct(OrmManager $orm, $vocabulary, $locale = null) {
        $this->orm = $orm;
        $this->vocabulary = $vocabulary;
        $this->locale = null;
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
                $taxonomyTerms[$tag] = $taxonomyTermModel->getByName($tag, $this->vocabulary, null, $this->locale);
            }
        }

        return $taxonomyTerms;
    }

}
