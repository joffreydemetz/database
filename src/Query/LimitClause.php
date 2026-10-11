<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Database\Query;

/**
 * LIMIT/OFFSET clause trait
 * 
 * Provides LIMIT and OFFSET functionality for queries
 */
trait LimitClause
{
    protected int $limit = 0;
    protected int $offset = 0;

    /**
     * Set query limit and offset
     * 
     * @param int $limit Maximum number of rows to return
     * @param int $offset Number of rows to skip
     * @return static
     */
    public function setLimit(int $limit = 0, int $offset = 0): static
    {
        $this->limit = $limit;
        $this->offset = $offset;
        return $this;
    }

    /**
     * Build the LIMIT and OFFSET clause
     * 
     * @return string The LIMIT/OFFSET SQL clause
     */
    protected function buildLimitClause(): string
    {
        $limit = max(0, $this->limit);
        $offset = max(0, $this->offset);

        if (0 === $limit && 0 === $offset) {
            return '';
        }

        // on its own line like every other clause (2.3.0); an offset without a
        // limit means "every row after it", MySQL's idiom for it (it was dropped)
        $clause = PHP_EOL . 'LIMIT ' . (0 === $limit ? '18446744073709551615' : $limit);

        if ($offset > 0) {
            $clause .= ' OFFSET ' . $offset;
        }

        return $clause;
    }
}
