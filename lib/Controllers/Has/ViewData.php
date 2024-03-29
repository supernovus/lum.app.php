<?php

namespace Lum\Controllers\Has;

/**
 * Adds an array-like interface to your controller for easier
 * handling of View template data. 
 *
 * You MUST declare your class to implement \ArrayAccess for this to work.
 *
 * It also adds a view variable called $data which contains all of the
 * view variables (useful for passing to components.)
 */
trait ViewData
{
  protected function __construct_viewdata_controller ($opts=[])
  {
    if (!isset($this->data['__data_alias']))
    {
      $this->data['__data_alias'] = 'data';
    }
  }

  public function offsetExists ($offset): bool
  {
    return isset($this->data[$offset]);
  }

  public function offsetGet ($offset): mixed
  {
    return $this->data[$offset];
  }

  public function offsetSet ($offset, $value): void
  {
    $this->data[$offset] = $value;
  }

  public function offsetUnset ($offset): void
  {
    unset($this->data[$offset]);
  }
}

