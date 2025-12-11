<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Database;

trait DatabaseProfilingTrait
{
  protected bool $profiling = false;

  public function startProfiling()
  {
    $this->profiling = true;

    $this->setQuery('SET profiling = 1;');
    $this->execute();
  }

  public function showProfiles(): array|null
  {
    $profiles = null;

    if (true === $this->profiling) {
      $profiles = [];

      $this->setQuery('SHOW PROFILES;');

      if ($rows = $this->loadAssocList()) {
        foreach ($rows as $row) {
          $this->setQuery('SHOW PROFILE FOR QUERY ' . $row['Query_ID'] . ';');
          $row['infos'] = $this->loadAssocList();

          $profiles[] = $row;
        }
      }
    }

    return $profiles;
  }
}
