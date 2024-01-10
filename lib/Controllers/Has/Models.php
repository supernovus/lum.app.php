<?php

namespace Lum\Controllers\Has;

/**
 * A trait that adds model configuration to your controllers.
 *
 * Expects a $core->conf->models configuration structure to exist.
 * It adds a protected method to the controller which is used when calling
 * the model() method to add extra options to the model constructors.
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
   * First looks for options specific to the model.
   * Then looks for options with the name '.common' which will be added
   * to all models.
   *
   * @param string $model  The name of the model we are getting options for.
   * @param array  $opts  (Optional) The options we are adding to.
   * @return array  The options after populating with the model configuration.
   */
  protected function populate_model_opts (string $model, $opts)
  { // Load any specific options.
    $opts = $this->get_model_opts($model, $opts, ['defaults'=>true]);

    // Load any common options.
    $opts = $this->get_model_opts('.common', $opts);

    return $opts;
  }

  /**
   * Get model options from the model configuration.
   *
   * @param String $name              The model/group to look up options for.
   * @param Array  $opts              Current/overridden options.
   * @param Array  $behavior          See below
   *
   * If the $behavior['defaults'] is True, and we cannot find a set of options 
   * for the specified model, then we will look for a set of options called 
   * '.default' and use that instead.
   *
   * A special option called '.type' allows for nesting option defintions.
   * If set in any level, an option group with the name of the '.type' will be 
   * looked up, and any options in its definition will be added (if they don't
   * already exist.) Groups may have their own '.type' option, allowing for
   * multiple levels of nesting.
   *
   * If $behavior['types'] is True, we build a list of all nested groups.
   *
   * The '.type' rule MUST NOT start with a dot. The group definition MUST
   * start with a dot. The dot will be assumed on all groups.
   *
   * So if a '.type' option is set to 'common', then a group called '.common'
   * will be inherited from.
   */
  protected function get_model_opts ($model, $opts=[], $behavior=[])
  {
    if (isset($behavior['defaults']))
      $use_defaults = $behavior['defaults'];
    else
      $use_defaults = false;
    
    if (isset($behavior['types']))
      $build_types = $behavior['types'];
    else
      $build_types = false;

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
              if (!isset($opts['@types']))
              {
                $opts['@types'] = [$modeltype];
              }
              else
              {
                $opts['@types'][] = $modeltype;
              }
            }
            // Groups start with a dot.
            $opts = $this->get_model_opts('.'.$modeltype, $opts);
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
        $opts = $this->get_model_opts('.default', $opts);
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
