<?php

namespace Aventura\Wprss\Core\Model\Set\Synonym;

use Aventura\Wprss\Core\Model\Set\AbstractGenericSet;

/**
 * Common functionality for synonym sets.
 *
 * @since [*next-version*]
 */
class AbstractSynonymSet extends AbstractGenericSet
{
    /**
     * Gets a list of terms in this instance that are synonymous to the specified term.
     *
     * @since [*next-version*]
     *
     * @param string $term The term to get synonyms for.
     *
     * @return string[]|\Traversable A list of terms that are synonymous to the specified term.
     */
    protected function _getSynonyms($term)
    {
        if (!$this->_hasItem($term)) {
            return array();
        }

        $items = $this->_arrayConvert($this->_getItems());
        return array_values(array_diff($items, array($term)));
    }

    /**
     * @inheritdoc
     *
     * @since [*next-version*]
     */
    protected function _validateItem($item)
    {
        if (!is_string($item)) {
            throw new \RuntimeException('The items in this set must be strings');
        }
    }
}
