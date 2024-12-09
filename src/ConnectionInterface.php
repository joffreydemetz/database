<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\Database;

/**
 * Connection Interface
 * 
 * @author  Joffrey Demetz <joffrey.demetz@gmail.com>
 */
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
