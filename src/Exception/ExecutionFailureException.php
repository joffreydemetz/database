<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\Database\Exception;

use JDZ\Database\Exception\DatabaseException;

/**
 * Exception class defining an error executing a statement
 * 
 * @author  Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class ExecutionFailureException extends DatabaseException {}
