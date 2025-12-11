<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Database;

interface ConnectionInterface
{
  /*
   * @throws \Callisto\Database\Exception\DatabaseException
   */
  public function connect(array $attrs = []);

  /*
   * @throws \Callisto\Database\Exception\DatabaseException
   */
  public function checkIfDriverAvailable();
}
