<?php

/**
 * Copyright (c) 2011-2018 Michael Dowling, https://github.com/mtdowling <mtdowling@gmail.com>

 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:

 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.

 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace Obs\Internal\Common;

class Model implements \ArrayAccess, \IteratorAggregate, \Countable, ToArrayInterface
{
	protected $data;
	
	public function __construct(array $data = [])
	{
		$this->data = $data;
	}
	
	public function count()
	{
		return count($this->data);
	}
	
	public function getIterator()
	{
		return new \ArrayIterator($this->data);
	}
	
	public function toArray()
	{
		return $this->data;
	}
	
	public function clear()
	{
		$this->data = [];
		
		return $this;
	}
	
	public function getAll(array $keys = null)
	{
		return $keys ? array_intersect_key($this->data, array_flip($keys)) : $this->data;
	}
	
	public function get($key)
	{
		return isset($this->data[$key]) ? $this->data[$key] : null;
	}
	
	public function set($key, $value)
	{
		$this->data[$key] = $value;
		
		return $this;
	}
	
	public function add($key, $value)
	{
		if (!array_key_exists($key, $this->data)) {
			$this->data[$key] = $value;
		} elseif (is_array($this->data[$key])) {
			$this->data[$key][] = $value;
		} else {
			$this->data[$key] = [$this->data[$key], $value];
		}
		
		return $this;
	}
	
	public function remove($key)
	{
		unset($this->data[$key]);
		
		return $this;
	}
	
	public function getKeys()
	{
		return array_keys($this->data);
	}
	
	public function hasKey($key)
	{
		return array_key_exists($key, $this->data);
	}
	
	public function keySearch($key)
	{
		foreach (array_keys($this->data) as $k) {
			if (!strcasecmp($k, $key)) {
				return $k;
			}
		}
		
		return false;
	}
	
	
	public function hasValue($value)
	{
		return array_search($value, $this->data);
	}
	
	public function replace(array $data)
	{
		$this->data = $data;
		
		return $this;
	}
	
	public function merge($data)
	{
		foreach ($data as $key => $value) {
			$this->add($key, $value);
		}
		
		return $this;
	}
	
	public function overwriteWith($data)
	{
		if (is_array($data)) {
			$this->data = $data + $this->data;
		} else {
			foreach ($data as $key => $value) {
				$this->data[$key] = $value;
			}
		}
		
		return $this;
	}
	
	public function map(\Closure $closure, array $context = [], $static = true)
	{
		$collection = $static ? new static() : new self();
		foreach ($this as $key => $value) {
			$collection->add($key, $closure($key, $value, $context));
		}
		
		return $collection;
	}
	
	public function filter(\Closure $closure, $static = true)
	{
		$collection = ($static) ? new static() : new self();
		foreach ($this->data as $key => $value) {
			if ($closure($key, $value)) {
				$collection->add($key, $value);
			}
		}
		
		return $collection;
	}
	
	public function offsetExists($offset)
	{
		return isset($this->data[$offset]);
	}
	
	public function offsetGet($offset)
	{
		return isset($this->data[$offset]) ? $this->data[$offset] : null;
	}
	
	public function offsetSet($offset, $value)
	{
		$this->data[$offset] = $value;
	}
	
	public function offsetUnset($offset)
	{
		unset($this->data[$offset]);
	}
	
	public function setPath($path, $value)
	{
		$current =& $this->data;
		$queue = explode('/', $path);
		while (null !== ($key = array_shift($queue))) {
			if (!is_array($current)) {
				throw new \RuntimeException("Trying to setPath {$path}, but {$key} is set and is not an array");
			} elseif (!$queue) {
				$current[$key] = $value;
			} elseif (isset($current[$key])) {
				$current =& $current[$key];
			} else {
				$current[$key] = [];
				$current =& $current[$key];
			}
		}
		
		return $this;
	}
	
	public function getPath($path, $separator = '/', $data = null)
	{
		if ($data === null) {
			$data =& $this->data;
		}
		
		$path = is_array($path) ? $path : explode($separator, $path);
		while (null !== ($part = array_shift($path))) {
			if (!is_array($data)) {
				return null;
			} elseif (isset($data[$part])) {
				$data =& $data[$part];
			} elseif ($part != '*') {
				return null;
			} else {
				// Perform a wildcard search by diverging and merging paths
				$result = [];
				foreach ($data as $value) {
					if (!$path) {
						$result = array_merge_recursive($result, (array) $value);
					} elseif (null !== ($test = $this->getPath($path, $separator, $value))) {
						$result = array_merge_recursive($result, (array) $test);
					}
				}
				return $result;
			}
		}
		
		return $data;
	}
	
	public function __toString()
	{
		$output = 'Debug output of ';
		$output .= 'model';
		$output = str_repeat('=', strlen($output)) . "\n" . $output . "\n" . str_repeat('=', strlen($output)) . "\n\n";
		$output .= "Model data\n-----------\n\n";
		$output .= "This data can be retrieved from the model object using the get() method of the model "
				. "(e.g. \$model->get(\$key)) or accessing the model like an associative array (e.g. \$model['key']).\n\n";
		$lines = array_slice(explode("\n", trim(print_r($this->toArray(), true))), 2, -1);
		$output .=  implode("\n", $lines);
		
		return $output . "\n";
	}
}
