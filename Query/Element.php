<?php
/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JDZ\Database\Query;

/**
 * Query Element Class
 * 
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class Element
{
	/**
   * The name of the element
   * 
	 * @var    string  
	 */
	protected $name;

	/**
   * An array of elements
   * 
	 * @var    array
	 */
	protected $elements;

	/**
   * Glue piece
   * 
	 * @var    string
	 */
	protected $glue;

	/**
	 * Constructor
	 *
	 * @param 	string  $name      The name of the element
	 * @param 	mixed   $elements  String or array
	 * @param 	string  $glue      The glue for elements
	 */
	public function __construct($name, $elements, $glue=',')
	{
		$this->name     = $name;
		$this->elements = [];
		$this->glue     = $glue;

		$this->append($elements);
	}

	/**
	 * Magic method to get the string representation of this object
   * 
	 * @return 	string
	 */
	public function __toString()
	{
		if ( substr($this->name, -2) == '()' ){
			return PHP_EOL . substr($this->name, 0, -2).'('.implode($this->glue, $this->elements).')';
		}

    return PHP_EOL . $this->name.' '.implode($this->glue, $this->elements);
	}

	/**
	 * Provide deep copy support to nested objects and arrays when cloning
	 * 
	 * @return 	void
	 */
	public function __clone()
	{
		foreach($this as $k => $v){
			if ( is_object($v) || is_array($v) ){
				$this->{$k} = unserialize(serialize($v));
			}
		}
	}

	/**
	 * Appends element parts to the internal list.
	 *
	 * @param 	mixed  $elements  String or array.
	 * @return 	void
	 */
	public function append($elements)
	{
		if ( is_array($elements) ){
			$this->elements = array_merge($this->elements, $elements);
		}
		else {
			$this->elements = array_merge($this->elements, [$elements]);
		}
	}

	/**
	 * Gets the elements of this element.
   * 
	 * @return 	array
	 */
	public function getElements()
	{
		return $this->elements;
	}
}

