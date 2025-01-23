<?php 

namespace Lum;

function is_subarray(array $a, string $p)
{
  return (isset($a[$p]) && is_array($a[$p]));
}

/**
 * A helper class providing mostly static methods and constants.
 */
class App
{
  const APP_INITED    = '@LumApp@.init';
  const ROUTER_INITED = '@LumApp@.router';
  const NS_INITED     = '@LumApp@.NS';

  /**
   * Default options for init() method.
   * 
   * Any path options may have a `rootPrefix` prepended, such as `'../'`
   * in the case where the webroot is a sub-folder of the app/project root.
   * 
   * This is looked up via static:: so may be overridden in sub-classes.
   */
  const DEFAULT_INIT  =
  [
    'classroot' => 'lib',
    'viewroot'  => 'views',
    'confroot'  => 'conf',
  ];

  const DEFAULT_ROUTER =
  [
    'conf' => 'routes',
  ];

  /**
   * Default options for the setupNamespaces() method.
   */
  const DEFAULT_NS =
  [
    'models'      => 'Models',
    'controllers' => 'Controllers',
    'use_screens' => true,
    'components'  => 'uicomponents',
    'comp_subdir' => 'components',
  ];

  /**
   * Initialize the Lum\Core instance for this app.
   * 
   * This is the first stage in the bootstrap process.
   * It will only perform the actual initialisation process once,
   * after which further calls will just return the Core instance.
   * 
   * @param array $opts Options (optional)
   * @return \Lum\Core
   */
  public static function init(array $opts=[])
  {
    $opts = array_merge(static::DEFAULT_INIT, $opts);
    $root = $opts['rootPrefix'] ?? '';
    $core = Core::getInstance($opts);

    if ($core[self::APP_INITED])
    { // Already initialized
      return $core;
    }

    $core->conf->setDir($root.$opts['confroot']);

    $useDebug = $opts['dbgCore'] ?? $opts['debug'] ?? false;
    if ($useDebug === true)
    { // Old debug file name
      $useDebug = '.debug';
    }

    if (is_string($useDebug))
    {
      try 
      {
        $dbg = $core->debug;
        $dbg->loadConfig($root.$useDebug);
      }
      catch (Exception $e)
      {
        error_log("debug plugin not available");
      }
    }

    $core[self::APP_INITED] = true;
    return $core;
  }

  /**
   * Setup a Lum\Router instance for this app.
   * 
   * This is usually the second stage in the bootstrap.
   * It calls init() to get the Core instance.
   * 
   * Like init() the actual initialisation process is only done
   * the first time this is called, after which it will simply
   * return the router instance.
   * 
   * @param array $ro Options for the Router (optional)
   * @param array $io Options for init() method (optional)
   * @return \Lum\Router
   */
  public static function router(array $ro=[], array $io=[])
  {
    $core = static::init($io);
    if (!$core[self::ROUTER_INITED])
    { // Initialize the core router object.
      $ro = array_merge(static::DEFAULT_ROUTER, $ro);
      $core->router = $ro;
      $rtr = $core->router;
      /** @var Lum\Plugins\Router $rtr */
      $useDebug = $ro['debug'] ?? $io['dbgRouter'] ?? false;
      $root = $conf['rootPrefix'] ?? '';
      if ($useDebug === true)
      {
        try
        {
          $dbg = $core->debug;
          $rtr->useLumDebug($dbg);
        }
        catch (Exception $e)
        {
          error_log("debug plugin not available");
        }
      }
      elseif (is_string($useDebug))
      {
        $rtr->loadDebugConf($root.$useDebug);
      }

      if (is_string($ro['conf']))
      {
        $ck = $ro['conf'];
        $routes = $core->conf[$ck];
        if (isset($routes))
        {
          $rtr->load($routes);
        }
      }

      $core[self::ROUTER_INITED] = true;
    }
    
    return $core->router;
  }

  /**
   * Setup namespaces for models, controllers, and views.
   * 
   * @param string $nsp Base app namespace prefix (mandatory)
   * 
   * Assumes this is the top level, so if you specified 'MyApp' here,
   * then the default models would be in the `\MyApp\Models` namespace.
   * 
   * @param array $no Namespace options (optional)
   * 
   * TODO: document this
   * 
   * @param array $io Options for the init() method (optional)
   * 
   * @return \Lum\Core
   */
  public static function setupNamespaces(string $nsp, array $no=[], array $io=[])
  {
    $core = static::init($io);

    if ($core[self::NS_INITED])
    {
      error_log("cannot initialize namespaces more than once");
      return $core;
    }

    $no = array_merge(static::DEFAULT_NS, $no);

    if (!str_starts_with($nsp, '\\'))
    {
      $nsp = "\\$nsp";
    }
    if (!str_ends_with($nsp, '\\'))
    {
      $nsp = "$nsp\\";
    }

    $core->models->addNS($nsp.$no['models']);
    $core->controllers->addNS($nsp.$no['controllers']);
    if ($no['use_screens'])
    {
      $core->controllers->use_screens();
      $comps = $no['components'];
      if (is_string($comps) && trim($comps) !== '')
      {
        $core->$comps = 'views';
        $uicomps = $core->$comps;
        $uicomps->addDir($core['viewroot'].'/'.$no['comp_subdir']);
      }
    }

    $core[self::NS_INITED] = true;
    return $core;
  }

  /**
   * Calls both setupNamespace() and router()
   * and provides a wrapper to start the routing process.
   * 
   * @param string $nsp See setupNamespace() for details
   * @param array $opts Options
   * 
   * Will look for three sets of named arrays (sub-options),
   * falling back on $opts itself if they aren't found:
   * 
   * - 'init'   → Options for init() method
   * - 'ns'     → Options for setupNamespace() method
   * - 'router' → Options for router() method
   * 
   * @return object
   * 
   * A standard object with three properties,
   * 'core' and 'router' are the Core and Router instances,
   * while 'go' is a function/method that when called will
   * call `router->route()` and return the output.
   * 
   */
  public static function bootstrap(string $nsp, array $opts=[])
  {
    $io = is_subarray($opts, 'init')   ? $opts['init']   : $opts;
    $no = is_subarray($opts, 'ns')     ? $opts['ns']     : $opts;
    $ro = is_subarray($opts, 'router') ? $opts['router'] : $opts;

    $core = static::setupNamespaces($nsp, $no, $io);
    $rtr  = static::router($ro); // no need to pass $io
    $go   = function() use ($rtr)
    { // Start the routing process.
      return $rtr->route();
    };

    return (object)['core' => $core, 'router'=>$rtr, 'go'=>$go];
  }

  /**
   * Get a lum-mailer instance.
   * 
   * This method supports both lum-mailer v2.x or older,
   * as well as lum-mailer v3.x or newer.
   *
   * @param array $opts Options for mailer
   * 
   * Explicitly will check for the 'fields' option if
   * version 2.x or older is found, as that version included
   * the fields as a separate constructor argument.
   * 
   * @return ?object A mailer instance if a class was found,
   * or null otherwise.
   */
  public static function mailer (array $opts=[])
  {
    if (!isset($opts['views']))
    {
      $opts['views'] = 'mail_messages';
    }
    
    if (class_exists('\\Lum\\Mailer\\Manager'))
    { // Mailer v3.x or newer
      /** @disregard P1009 */
      $mailer = new \Lum\Mailer\Manager($opts);
    }
    elseif (class_exists('\\Lum\\Mailer'))
    { // Mailer v2.x or older
      $fields = $opts['fields'] ?? null;
      /** @disregard P1009 */
      $mailer = new \Lum\Mailer($fields, $opts);
    }
    else
    {
      error_log("No lum-mailer library found");
      $mailer = null;
    }

    return $mailer;
  }

}
