<?php

namespace Lum\Controllers\Has;

/**
 * A trait that adds model configuration to your controllers.
 *
 * Expects a $core->conf->models configuration structure to exist.
 * It adds a protected method to the controller which is used when calling
 * the model() method to add extra options to the model constructors.
 *
 * Some of the choices I made when designing this originally bother me now.
 * I can't really change things too much without breaking things a lot, but
 * may eventually find a way to improve the design of this trait.
 */
trait Models
{
  protected $model_opts; // Options to pass when loading models.

  protected function __construct_modelconf_controller ($opts=[])
  {
    $core = \Lum\Core::getInstance();
    if (isset($core->conf->models))
    {
      $this->model_opts = $core->conf->models;
    }
  }

  /**
   * The method used by model() to get the options to pass to the model.
   *
   * Will always look for a configuration section with the name of the model.
   * Any other groups are entirely optional.
   *
   * @param string $model  The name of the model we are getting options for.
   * @param array  $mo     (Optional) The options we are adding to.
   * @param array  $po     (Optional) Options for additional behaviours.
   * 
   *   'common' (bool|string, default: true) → Include '.common' group.
   *     Set to a string to use that instead of '.common' as the group name.
   * 
   *   'defaults' (bool|string, default: true) → See get_model_opts();
   *     This is only used on the primary call with $model as the name.
   *     It is ignored on calls to the 'more' or 'common' groups.
   * 
   *   'more' (?array, default: null) → More config sections to add.
   *     They will be added after the $model config, but before the 
   *     optional 'common' group.
   * 
   * @return array  The options after populating with the model configuration.
   */
  protected function populate_model_opts (string $model, $mo=[], $po=[])
  { // Load any specific options.
    $be = ['defaults'=>($po['defaults'] ?? true)];

    if (isset($po['listTypes']))
    {
      $be['types'] = $po['listTypes'];
    }

    $mo = $this->get_model_opts($model, $mo, $be);

    unset($be['defaults']);

    if (isset($po['more']) && is_array($po['more']))
    {
      foreach($po['more'] as $cname)
      {
        $mo = $this->get_model_opts($cname, $mo, $be);
      }
    }

    // Load any common options.
    $common = $po['common'] ?? false;
    if ($common === true)
    {
      $common = '.common';
    }

    if ($common)
    {
      $mo = $this->get_model_opts($common, $mo);
    }
    
    return $mo;
  }

  /**
   * Get model options from the model configuration.
   * 
   * A special option called '.type' allows for nesting option defintions.
   * If set to a string or array of strings, option group(s) with those names
   * looked up, and any options not already in $opts will be added.
   * The '.type' rule may be set in any config section, including groups, and
   * will be called recursively wherever it is found.
   * 
   * NOTE: Current naming limitations
   * 
   * A '.type' name MUST NOT start with a dot. The group definition MUST
   * start with a dot. The dot will be assumed on all groups.
   *
   * So if a '.type' option is set to 'common', then a group called '.common'
   * will be inherited from.
   * 
   * This limitation is an artefact of the original design of this system,
   * and will be changed in a future release to be more flexible, but without
   * breaking backwards compatibility.
   *
   * @param string $name              The model/group to look up options for.
   * @param array  $opts              Current/overridden options.
   * @param array  $be                Behaviour options (see below)
   *
   * If $be['defaults'] is set to True, and we cannot find a set of options 
   * for the specified model, then we will look for an option group called 
   * '.default' and use that instead. You can also set $be['defaults'] to a
   * string in which case that group will be used instead of '.default'.
   *
   * If $be['types'] is True, we build a list of all nested groups which will
   * be stored in $opts using the '@types' key. You can set $be['types'] to
   * a string in which case that key will be used instead of '@types'.
   */
  protected function get_model_opts ($model, $opts=[], $be=[])
  {
    $use_defaults = $be['defaults'] ?? false;
    if ($use_defaults === true)
    { // Use '.default' for defaults
      $use_defaults = '.default';
    }
    unset($be['defaults']);

    $build_types = $be['types'] ?? false;
    if ($build_types === true)
    {
      $build_types = '@types';
    }

    $model = strtolower($model); // Force lowercase.
#    error_log("Looking for model options for '$model'");
    if (isset($this->model_opts) && is_array($this->model_opts))
    { // We have model options in the controller.
      if (isset($this->model_opts[$model]))
      { // There is model-specific options.
        $modeltypes = null;
        $modelopts = $this->model_opts[$model];
        if (is_array($modelopts))
        { 
          $opts += $modelopts;
          if (isset($modelopts['.type']))
          {
            $modeltypes = $modelopts['.type'];
          }
        }
        elseif (is_string($modelopts))
        {
          $modeltypes = $modelopts;
          if (!isset($opts['.type']))
          {
            $opts['.type'] = $modeltypes;
          }
        }
        if (isset($modeltypes))
        { 
          if (!is_array($modeltypes))
            $modeltypes = [$modeltypes];
          foreach ($modeltypes as $modeltype)
          {
            if ($build_types)
            { // @types keeps track of our nested group hierarchy.
              if (!isset($opts[$build_types]))
              {
                $opts[$build_types] = [$modeltype];
              }
              else
              {
                $opts[$build_types][] = $modeltype;
              }
            }
            // Groups start with a dot.
            $opts = $this->get_model_opts('.'.$modeltype, $opts, $be);
            $func = 'get_'.$modeltype.'_model_opts';
            if (is_callable([$this, $func]))
            {
  #            error_log("  -- Calling $func() to get more options.");
              $addopts = $this->$func($model, $opts);
              if (isset($addopts) && is_array($addopts))
              {
  #              error_log("  -- Options were found, adding to our set.");
                $opts += $addopts;
              }
            }
          }
        }
      }
      elseif ($use_defaults)
      {
        $opts = $this->get_model_opts($use_defaults, $opts, $be);
      }
    }
#    error_log("Returning: ".json_encode($opts));
    return $opts;
  }

  /**
   * Handle a special '.type' called 'conf' which requires an option
   * called '.conf', which can be a string or array of strings, 
   * and will include extra options from $core->conf[$conf];
   */
  protected function get_conf_model_opts ($model, $opts)
  {
    if (isset($opts['.conf']))
    {
      $core = \Lum\Core::getInstance();
      $addopts = [];

      $confs = $opts['.conf'];
      if (!is_array($confs))
        $confs = [$confs];
      
      foreach ($confs as $conf)
      {
        if (isset($core->conf[$conf]))
        {
          $addconf = $core->conf[$conf];
          if (isset($addconf[$model]))
            $addconf = $addconf[$model];
          $addopts += $addconf;
        }
      }

      return $addopts;
    }
  }

}
