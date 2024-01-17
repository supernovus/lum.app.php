<?php

namespace Lum\Controllers\Has;

use Exception;

/**
 * Adds Resource Management for CSS, Stylesheets, etc.
 */
trait Resources
{
  // TypeDef properties to look for when loading resource configs.
  final const RESOURCES_TYPEDEF_PROPS =
  [
    'as'   => ['string'],
    'name' => ['string'],
    'exts' => ['array','string'],
    'path' => ['array','string'],
    'warn' => ['bool','null'],
    'link' => ['bool','null'],
  ];

  // TypeDef Extension fields.
  final const RESOURCES_TYPEDEF_EXTS =
  [
    'groups' => 'resource_groups',
    'urls'   => 'resource_urls',
  ];

  /**
   * If this is true, we print a warning to the error log if a requested
   * resource file cannot be found.
   * 
   * May be overridden on a per-type basis using the `warn` type-def rule.
   */
  public bool $warn_on_missing_resources = true;

  /**
   * If this is set to true, we will add a Link: header for each resource
   * loaded so that in HTTP/2 they will be streamed as a part of the request.
   * 
   * May be overridden on a per-type basis using the `link` type-def rule.
   */
  public bool $use_link_header = false;

  /**
   * If using $use_link_header, this can be defined as a prefix to add
   * to all of the links added. Useful if you have multiple apps on a URL
   * and need to make sure the right subdirectory is used.
   * 
   * See `$link_header_prefix_type` for more information on customizing this.
   * 
   * Default is an empty string.
   */
  protected string $link_header_prefix = '';

  /**
   * Defines how the `$link_header_prefix` property is handled.
   * Ignored if `$link_header_prefix` is an empty string.
   * 
   * - `0` : Use the value of 'link_header_prefix' as the prefix (default).
   * 
   * - `1` : The `link_header_prefix` is the name of a method that will
   *   return the prefix string. It will only be called once per type name,
   *   and will be passed the type name as its sole argument.
   * 
   * - `2` : The `link_header_prefix` is the name of a method that will
   *   return the prefix string. It will be called on every `use_resource()`
   *   call, and will be passed the filename, resource name, and type name.
   * 
   */
  protected int $link_header_prefix_type = 0;

  // Used if `link_header_prefix_type` is `1`.
  protected array $link_header_type_prefixes = [];

  /**
   * Definitions for each type of resource that we can load.
   */
  protected $resource_types =
  [
    'js' =>
    [
      'as'    => 'script',
      'name'  => 'scripts',
      'exts'  => ['.min.js', '.js'],
      'path'  => [],
      'warn'  => null,
      'link'  => null,
    ],
    'css' =>
    [
      'as'    => 'style',
      'name'  => 'stylesheets',
      'exts'  => ['.min.css', '.css'],
      'path'  => [],
      'warn'  => null,
      'link'  => null,
    ],
    'font' =>
    [
      'as'    => 'font',
      'name'  => 'fonts',
      'exts'  => ['.woff2','.woff', '.otf', '.ttf'],
      'path'  => [],
      'warn'  => null,
      'link'  => null,
    ],
  ];

  /**
   * Definitions for resource groups.
   * 
   * A group for one resource type MAY in fact depend on a resource of another
   * type. For instance, you may have a Javascript file that depends on a
   * CSS stylesheet being loaded. You can define a rule that will include it,
   * by using a 'type:name' format, such as 'css:foobar'.
   */
  protected $resource_groups =
  [
    'js'   => [],
    'css'  => [],
    'font' => [],
  ];

  /**
   * Definitions for resource URLs.
   * 
   * Each key within a URL group should be a unique name identifying the 
   * resource, and the value should be the URL string.
   */
  protected $resource_urls =
  [
    'js'   => [],
    'css'  => [],
    'font' => [],    
  ];

  /**
   * A list of loaded resources.
   * 
   * @var array
   */
  protected $resources_added =
  [
    'js'   => [],
    'css'  => [],
    'font' => [],
  ];

  protected function get_resource_def ($type, array $def=[])
  {
    if (isset($this->resource_types[$type]))
    {
      $def += $this->resource_types[$type];
    }

    foreach (self::RESOURCES_TYPEDEF_EXTS as $ekey => $cprop)
    {
      if (isset($this->$cprop[$type]))
      {
        $rdef = $this->$cprop[$type];
        if (isset($def[$ekey]))
        {
          $edef = $def[$ekey];
          $def[$ekey] = array_merge($edef, $rdef);
        }
        else
        {
          $def[$ekey] = $rdef;
        }
      }
    }

    return (count($def) > 0 ? $def : null);
  }

  /**
   * Find a resource file, based on known paths and extensions.
   *
   * @param string $type    The resource type.
   * @param string $name    The resource name without path or extension.
   */
  public function find_resource ($type, $name)
  {
    $resdef = $this->get_resource_def($type);
    if (!isset($resdef)) return null;

    $exts = $resdef['exts'];
    $path = $resdef['path'];

    foreach ($path as $dir)
    {
      foreach ($exts as $ext)
      {
        $filename = $dir . '/' . $name . $ext;
        if (file_exists($filename))
        {
          return $filename;
        }
      }
    }

    return null;
  }

  /**
   * Add a resource file to an array of resources for use in view templates.
   *
   * @param string  $type    The resource type.
   * @param string  $name    The resource or group name.
   * @param boolean $block   Don't actually add it, make it un-addable.
   */
  public function use_resource ($type, $name, $block=false): bool
  {
    if (is_array($name))
    {
      foreach ($name as $res)
      {
        $this->use_resource($type, $res, $block);
      }
      return true; // All done.
    }

    if (isset($this->resources_added[$type][$name]))
    {
      return true;
    }

    $resdef = $this->get_resource_def($type);
    if (!isset($resdef)) return false;

    // If this is a group, we process the group members.
    if (isset($resdef['groups'][$name]))
    {
      /**
       * @var array $group A group definition
       */
      $group = $resdef['groups'][$name];
      foreach ($group as $res)
      {
        $resblock = $block;

        if (substr($res, 0, 1) === '!')
        {
          $res = substr($res, 1);
          $resblock = true;
        }

        if (strpos($res, ':') === false)
        {
          $this->use_resource($type, $res, $resblock);
        }
        else
        {
          $parts = explode(':', $res);
          $etype = $parts[0];
          $ename = $parts[1];
          $this->use_resource($etype, $ename, $resblock);
        }
      }
      $this->resources_added[$type][$name] = $name;
      return true; // We've imported the group, let's leave now.
    }

    if ($block)
    {
      $this->resources_added[$type][$name] = true;
      return true;
    }

    $isURL = false;
    if (isset($resdef['urls'][$name]))
    {
      $isURL = true;
      $file = $resdef['urls'][$name];
    }
    else
    {
      $warn = $resdef['warn'] ?? $this->warn_on_missing_resources;
      $link = $resdef['link'] ?? $this->use_link_header;

      $file = $this->find_resource($type, $name);
      if (!isset($file))
      {
        if ($warn)
          error_log("Could not find $type file for: '$name'.");
        return false;
      }

      if ($link)
      {
        $prefix = trim($this->link_header_prefix);
        if (!empty($prefix) && $this->link_header_prefix_type > 0)
        {
          $meth = [$this, $prefix];
          if (is_callable($meth))
          { // The method exists, let's use it.
            $ptype = $this->link_header_prefix_type;
            if ($ptype === 1)
            { // One-time method.
              if (!isset($this->link_header_type_prefixes[$type]))
              {
                $this->link_header_type_prefixes[$type] = call_user_func($meth, $type);
              }
              $prefix = $this->link_header_type_prefixes[$type] ?? '';
            }
            elseif ($ptype === 2)
            { // Every-method.
              $prefix = call_user_func($meth, $file, $name, $type);
            }
          }
          else
          {
            error_log("Invalid link header prefix method '$prefix'");
          }
        }
        $astype = $resdef['as'];
        header("Link: <{$prefix}{$file}>; rel=preload; as=$astype", false);
      }
    }

    $resname = $resdef['name'];

#    error_log("Adding $type '$name' to $resname as $file");

    if (!isset($this->data[$resname]))
    {
      $this->data[$resname] = [];
    }

    if ($isURL)
      $this->data[$resname][] = ['url'=>$file];
    else
      $this->data[$resname][] = $file;

    $this->resources_added[$type][$name] = $file;
    return true;
  }

  /**
   * Reset a resource group.
   */
  public function reset_resource ($type)
  {
    $resdef = $this->get_resource_def($type);
    if (!isset($resdef)) return;

    $resname = $resdef['name'];
    unset($this->data[$resname]);
    $this->resources_added[$type] = [];
  }

  /**
   * Add a Javascript file or group to our used resources.
   */
  public function add_js ($name)
  {
    return $this->use_resource('js', $name);
  }

  /**
   * Add a CSS stylesheet file or group to our used resources.
   */
  public function add_css ($name)
  {
    return $this->use_resource('css', $name);
  }

  /**
   * Block a Javascript file or group from being loaded.
   * This must be done BEFORE any other calls to add_js() because if the
   * resource is already added, it cannot be blocked.
   */
  public function block_js ($name)
  {
    return $this->use_resource('js', $name, true);
  }

  /**
   * Block a CSS stylesheet. The same rules apply with this as with JS.
   */
  public function block_css ($name)
  {
    return $this->use_resource('css', $name, true);
  }

  /**
   * Add resource paths.
   * 
   * Uses `update_resource_type_prop()` to set the `path` property.
   * Determines operation to use to modify array based on arguments passed.
   * 
   * @param string $type - The resource type we're adding paths to.
   * @param string|array $paths - One or more paths to add.
   * @param int $offset (Optional, default: `0`) Offset to add paths at.
   * 
   * If this is set to `-1` then we will _push_ the values to the end.
   * 
   * @param int $remove (Optional, default: `0`) Remove this many items.
   * 
   * If this is set to `-1` then we will replace ALL existing paths.
   * 
   * @return void
   */
  public function add_resource_paths (
    string $type, 
    string|array $paths, 
    int $offset=0, 
    int $remove=0,
  )
  {
    if (!is_array($paths)) $paths = [$paths];

    if ($remove === -1)
    { // Replacing not splicing.
      $setValue = $paths;
    }
    elseif ($offset === -1 && $remove === 0)
    { // Gonna use push instead of splice.
      $setValue = ['$push'=>$paths];
    }
    else
    { // Anything else we're using splice.
      $setValue = ['$splice'=>[$offset, $remove, ...$paths]];
    }
    
    $this->update_resource_type_prop($type, 'path', $setValue);
  }

  /**
   * Update a type definition property.
   * 
   * @param string $type The type name.
   * @param string $key The type def property.
   * @param mixed $value A value to assign.
   * 
   * If the type def is an array (such as `path` or `exts`), then this
   * should either be a simple flat array of values to set, or a special
   * array operation, which is defined as an associative array with a single
   * named property defining which operation to use.
   * 
   * Splice:
   * 
   * ```js
   * {
   *   "path":
   *   {
   *     "$splice": [offset, remove, value1, ...]
   *   }
   * }
   * ```
   * 
   * Push:
   * 
   * ```js
   * {
   *   "exts":
   *   {
   *     "$push": [value1, ...]
   *   }
   * }
   * 
   * @return void 
   */
  public function update_resource_type_prop(
    string $type,
    string $key,
    mixed  $value,
  )
  {
    if (!isset($this->resource_types[$type]))
    { // Create a new type definition.
      $this->resource_types[$type] = [];
    }

    // Get the type definition rules.
    $tdef = self::RESOURCES_TYPEDEF_PROPS[$key] ?? null;

    if (isset($tdef))
    { // The type definition has defined rules. Follow them.
      if ($tdef[0] === 'array')
      { // Array values are special and support magic operations.
        if (is_array($value) 
          && isset($value['$splice']) 
          && is_array($value['$splice'])
          && array_is_list($value['$splice'])
          && count($value['$splice']) >= 3)
        { // Splice values into an array.
          $splice = $value['$splice'];
          $offset = intval($splice[0]);
          $remove = intval($splice[1]);
          $value  = array_slice($splice, $offset);
  
          if (isset($this->resource_types[$type][$key])
            && is_array($this->resource_types[$type][$key]))
          { // Splice away!
            array_splice($this->resource_types[$type][$key], $offset, $remove, $value);
            return;
          }
        }
        elseif (is_array($value) && isset($value['$push']))
        { // Push values to end of array.
          $pushVals = $value['$push'];
          if (!is_array($pushVals)) $pushVals = [$pushVals];
          if (isset($this->resource_types[$type][$key]) 
            && is_array($this->resource_types[$type][$key]))
          { // Push onwards!
            array_push($this->resource_types[$type][$key], ...$pushVals);
            return;
          }
        }
        elseif (!is_array($value))
        { // Force the value to be an array.
          $value = [$value];
        }
      }
      else
      { // Not an array, do a quick type check.
        $vtype = get_debug_type($value);
        if (!in_array($vtype, $tdef))
        {
          error_log("Invalid '$key' value: ".serialize($value));
          return;
        }
      }
    }

    // Setting the property directly.
    $this->resource_types[$type][$key] = $value;
  }

  /**
   * Load a resource configuration.
   * 
   * @param string|array $config - A Lum config name, or actual config data.
   * 
   * The data _should_ have sub-sections for each _type_ of data.
   * The pre-defined types in the library are `js`, `css,` and `font`.
   * Other types may be defined by overriding the ``
   * 
   * The configuration data supports a few different properties:
   * 
   * - `path` (`string[]`) An array of local paths to find resource files in.
   * - `pathOffset` (`int`) The `$offset` arg for `add_resource_paths()`.
   *   The default is `0` which adds to the start.
   * - `pathReplace` (`int`) The `$remove` arg for `add_resource_paths()`.
   *   If `pathOffset` is `0`, `pathReplace` defaults to `-1`;
   *   if `pathOffset` is anything else, `pathReplace` defaults to `0`.
   * 
   * @param null|string $type (Optional) A specific config type to load.
   * 
   * If NOT specified, every key in the $config will be assumed to be the name
   * of a resource type, and the value must be the config settings to add.
   * 
   * @param string $comment (Optional) Prefix for comment properties.
   *
   * Default if not passed here or overridden in the config is `--`.
   * 
   * @return void 
   * @throws Exception An invalid Lum config name was specified.
   */
  public function load_resource_config (
    string|array $config, 
    ?string $type=null,
    string $comment='--',
  )
  {
    if (is_string($config))
    {
      $core = \Lum\Core::getInstance();
      $cname = $config;
      $config = $core->conf[$cname];
      if (!is_array($config))
      {
        throw new \Exception("Invalid config '$cname'");
      }
    }

    if (isset($config['comment']) && is_string($config['comment']))
    {
      $comment = $config['comment'];
    }

    if (isset($type))
    { // A single type was specified as part of the function call.
      if (isset($config[$type]))
      { // A named sub-section of the config was found for this type.
        $config = $config[$type];
      }
      
      $this->update_resource_config($type, $config, $comment);
    }
    else
    { // Add each non-comment key in the config as a type.
      $clen = strlen($comment);

      foreach ($config as $type => $subconf)
      {
        if (!is_array($subconf))
        {
          error_log("Invalid '$type' type definition: ".serialize($subconf));
          continue;
        }

        if (substr($type, 0, $clen) === $comment) continue; // Skip comments.

        $this->update_resource_config($type, $subconf, $comment);
      }
    }

  } // load_resource_config()

  protected function update_resource_config (string $type, array $config, string $comment)
  {
    foreach ($config as $key => $val)
    {
      if (isset(self::RESOURCES_TYPEDEF_EXTS[$key]) && is_array($val))
      { // It's an extension field.
        $rprop = self::RESOURCES_TYPEDEF_EXTS[$key];
        $this->update_resource_ext_prop($rprop, $type, $val, $comment);
      }
      else
      { // It's a regular typedef field.
        $this->update_resource_type_prop($type, $key, $val);
      }
    }
  } // update_resource_config()

  protected function update_resource_ext_prop(
    string $prop,
    string $type, 
    array $data, 
    string $comment)
  {
    $clen = strlen($comment);

    if (!isset($this->$prop[$type]))
    { // Add a type def.
      $this->$prop[$type] = [];
    }

    foreach ($data as $key => $val)
    {
      if (substr($key, 0, $clen) === $comment) continue; // Skip comments.

      $this->$prop[$type][$key] = $val;
    }

  } // update_resource_property()

}
