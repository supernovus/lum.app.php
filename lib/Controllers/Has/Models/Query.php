<?php

namespace Lum\Controllers\Models;

/**
 * A couple extensions for the Has\Models trait.
 */
trait Query
{
  use \Lum\Controllers\Has\Models;

  /**
   * Return a list of models with a specific ".type" definition,
   * along with the flat model options.
   *
   * [
   *   'modelname' => $model_opts,
   *   // more here
   * ]
   */
  public function get_models_of_type ($type)
  {
#    error_log("In get_models_of_type('$type')");
    $models = [];
    foreach ($this->model_opts as $name => $opts)
    {
      $firstchar = substr($name, 0, 1);
      if ($firstchar == '.') continue; // Skip groups.
      if 
      (
        is_string($opts)
        ||
        (is_array($opts) && isset($opts['.type']))
      )
      {
        if (is_string($opts))
          $modeltype = $opts;
        else
          $modeltype = $opts['.type'];

        if (is_array($modeltype) && in_array($type, $modeltype))
        {
          $models[$name] = $opts;
        }
        elseif (is_string($modeltype) && $modeltype == $type)
        {
          $models[$name] = $opts;
        }
      }
    }
    return $models;
  }

  /**
   * Return a list of models that are decended from a type, no matter how
   * deep in the group hierarchy they are. Also returns the expanded model
   * options (as returned by get_model_opts() with @types enabled.)
   *
   * [
   *   'modelname' => $extended_model_opts,
   *   // more here
   * ]
   */
  public function get_models_with_type ($type, $opts=[])
  {
    $models = [];
    foreach ($this->model_opts as $name => $def)
    {
      $firstchar = substr($name, 0, 1);
      if ($firstchar == '.') continue; // skip groups.
      $modelopts = $this->get_model_opts($name, $opts, ['types'=>true]);
      if (!isset($modelopts['@types'])) continue; // no groups? skip it.
      if (in_array($type, $modelopts['@types']))
      {
        $models[$name] = $modelopts;
      }
    }
    return $models;
  }

}

