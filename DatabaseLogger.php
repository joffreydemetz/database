<?php
/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JDZ\Database;

use Monolog\Logger as MonologLogger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;

/**
 * Monolog Database Logger
 * 
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class DatabaseLogger
{
	/**
   * Logger name
   * 
   * @var    string
	 */
  protected $name;
  
	/**
   * Logger file
   * 
   * @var    string
	 */
  protected $logFile;
  
	/**
   * Monolog log importance
   * 
   * @var    int
	 */
  protected $importance;
  
	/**
   * Monolog logger
   * 
   * @var    LineFormatter
	 */
  protected $formatter;
  
	/**
   * Monolog logger
   * 
   * @var    MonologLogger
	 */
  protected $logger;
  
	/**
	 * Constructor
	 *
	 * @param 	string  $name     The logger name
	 * @param 	string  $logFile  The logger filepath
	 * @param 	string  $type     The logget type (error,info) Defaults to info
	 */
  public function __construct($name, $logFile, $type='info')
  {
    $this->name    = $name;
    $this->logFile = $logFile;
    
    $this->setImportance($type);
    $this->setFormatter();
    $this->setLogger();
  }
  
	/**
	 * Set logger importance
	 *
	 * @param 	string  $type     The logget type (error,info) Defaults to info
	 * @return 	void
	 */
  protected function setImportance($type)
  {
    switch($type){
      case 'error':
        $this->importance = MonologLogger::ERROR;
        break;
      
      case 'info':
      default:
        $this->importance = MonologLogger::INFO;
        break;
    }
  }
  
	/**
	 * Set logger formatter
	 *
	 * @return 	void
	 */
  protected function setFormatter()
  {
    $dateFormat = "d/m/Y H:i:s";
    // the default output format is "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n"
    $output = "[%datetime%] %level_name% > %message% %context% %extra%\n";
    // create a formatter
    $this->formatter = new LineFormatter($output, $dateFormat);
  }
  
	/**
	 * Set logger
	 *
	 * @return 	void
	 */
  protected function setLogger()
  {
    $stream = new RotatingFileHandler($this->logFile, $this->importance);
    $stream->setFormatter($this->formatter);
    
    $this->logger = new MonologLogger($this->name);
    $this->logger->pushHandler($stream);
  }
  
	/**
	 * Add a entry
	 *
	 * @return 	void
	 */
  public function add($method, $str)
  {
    $str = $this->cleanLine($str);
    
    $method = 'add'.ucfirst($method);
    
    $this->logger->$method($str);
  }
  
	/**
	 * Clean an entry
	 *
	 * @param 	string  $line   The raw entry
	 * @return 	string  The cleaned entry
	 */
  protected static function cleanLine($line)
  {
    $line = trim(preg_replace("/\s+/", " ", $line));
    return $line;
  }
}
