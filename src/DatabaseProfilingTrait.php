<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\Database;

/**
 * Database Profiling
 * 
 * @author  Joffrey Demetz <joffrey.demetz@gmail.com>
 */
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
