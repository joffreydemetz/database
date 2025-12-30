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
        if ($this->limit > 0 && $this->offset > 0) {
            return ' LIMIT ' . $this->offset . ', ' . $this->limit;
        } elseif ($this->limit > 0) {
            return ' LIMIT ' . $this->limit;
        }

        return '';
    }
}
