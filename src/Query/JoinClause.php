<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Database\Query;

/**
 * JOIN clause trait
 * 
 * Provides JOIN functionality for queries
 */
trait JoinClause
{
    protected array $join = [];

    /**
     * Add a JOIN clause
     */
    public function join(string $type, string $condition): static
    {
        $this->join[] = [
            'type' => strtoupper($type),
            'condition' => $condition
        ];
        return $this;
    }

    /**
     * Add a LEFT JOIN clause
     */
    public function leftJoin(string $condition): static
    {
        return $this->join('LEFT', $condition);
    }

    /**
     * Add a RIGHT JOIN clause
     */
    public function rightJoin(string $condition): static
    {
        return $this->join('RIGHT', $condition);
    }

    /**
     * Add an INNER JOIN clause
     */
    public function innerJoin(string $condition): static
    {
        return $this->join('INNER', $condition);
    }

    /**
     * Add an OUTER JOIN clause
     */
    public function outerJoin(string $condition): static
    {
        return $this->join('OUTER', $condition);
    }

    /**
     * Build JOIN clauses
     * 
     * @return string The JOIN SQL clauses
     */
    protected function buildJoinClause(): string
    {
        if (empty($this->join)) {
            return '';
        }

        $clauses = [];
        foreach ($this->join as $join) {
            $clauses[] = $join['type'] . ' JOIN ' . $join['condition'];
        }

        return PHP_EOL . implode(PHP_EOL, $clauses);
    }
}
