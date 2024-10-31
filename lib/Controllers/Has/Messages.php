<?php

namespace Lum\Controllers\Has;

/**
 * A Trait that handles translatable text strings, status messages,
 * and adds the HTML template handler for Views (also with translatable text.)
 *
 * Uses the $core->conf->translations configuration structure as a location
 * for the UI strings. Within the ./conf/translations.d/ you should have one
 * folder for each language with a name like 'en.d' for 'en', etc.
 * Within that, you should have one file for each controller, plus one called
 * 'common.json' that will be included on all pages.
 *
 * So if you have a controller called 'test', then the English strings
 * for it would be in:
 *
 * ./conf/translations.d/en.d/test.json
 *
 * You will need to do $core->conf->setDir('./conf') at before loading any
 * controllers to ensure the configuration directory is loaded.
 *
 * If you define a class property called $html_includes, it will be used
 * as the view loader for an $html object pre-installed in the view data.
 * The $html object is an instance of Lum\HTML\Helper.
 */
trait Messages
{
  protected $text;              // Our translation table.
  protected $lang;              // Our language.

  protected $OVERWRITE_JSON = 1; // Overwrite conflicting JSON data.
  protected $MERGE_JSON     = 2; // Merge conflicting JSON data.

  protected function __init_messages_controller ($opts)
  {
    #error_log("__init_messages_controller()");
    $core = \Lum\Core::getInstance();

    if (!isset($this->lang))
    {
      $this->lang = $core['default_lang'];
    }

    $name = $this->name();

    if (isset($opts['text_object']))
    {
      $translations = $opts['text_object'];
    }
    else
    {
      // Let's load our translation table, and page title.
      $trconf = $core->conf->translations;
      $trns   = array($name, 'common');
      $tropts = [];
      if (isset($this->lang))
      {
        $tropts['default'] = $this->lang;
      }
      $useaccept = $core['use_accept_lang'];
      if (isset($useaccept) && is_bool($useaccept))
      {
        $tropts['accept'] = $useaccept;
      }
      $fallback = $core['fallback_lang'];
      if (isset($fallback) && is_string($fallback))
      {
        $tropts['fallback'] = $fallback;
      }
      $translations = new \Lum\UI\Strings($trconf, $trns, $tropts);  
    }
    $this->text = $translations;
    $this->data['text'] = $translations;

    // Register a view helper for use in our views.
    $html_opts = array
    (
      'translate' => $translations,
    );
    if (property_exists($this, 'html_includes') && isset($this->html_includes))
    {
      $html_opts['include'] = $this->html_includes;
    }
    $this->data['html'] = new \Lum\HTML\Helper($html_opts);

    // Okay, first look for the 'title' key. If it's set, typically in the
    // translation table for this namespace, we'll use it.
    if (!isset($this->data['title']))
    {
      $pagetitle = $translations->getStr('title');
      if ($pagetitle == 'title')
      { // If we didn't find the page title, use a default value.
        $pagetitle = ucfirst($name);
      }
      $this->data['title'] = $pagetitle;
    }

  }

  // Return our translation object.
  public function get_text ()
  {
    return $this->text;
  }

  /**
   * Add a 'status_messages' JSON element.
   *
   * Uses $this->text->strArray() to build a map of translations, then
   * uses $this->add_json() to add the element.
   *
   * @param string[] $messages  The message codes to add.
   */
  public function add_status_json (array $messages, string $prefix='',
    array $opts=[], $fieldName='status_messages')
  {
    $jsonMode = isset($opts['ADD_JSON']) 
      ? intval($opts['ADD_JSON'])
      : $this->MERGE_JSON;

    $msgArray = $this->text->strArray($messages, $prefix, $opts);
    $this->add_json($fieldName, $msgArray, $jsonMode);
  }

  /**
   * An easy way to add JSON data to the templates.
   *
   * In your layout, you should have a section that looks like:
   *
   * <?php
   *   if (isset($json) && count($json) > 0)
   *     foreach ($json as $json_name => $json_data)
   *       echo $html->json($json_name, $json_data);
   * ?>
   *
   */
  public function add_json ($name, $data, $mode=0)
  {
    if (isset($this->data['json'], $this->data['json'][$name]))
    { // Existing data found, the mode determines how we handle this.
      if ($mode === $this->OVERWRITE_JSON)
      { // We're not even going to blink, just overwrite it.
        $this->data['json'][$name] = $data;
      }
      elseif ($mode === $this->MERGE_JSON)
      { // Merge using the + operator.
        $this->data['json'][$name] += $data;
      }
      else
      { // No mode selected, reject this.
        error_log("attempt to overwrite '$name' JSON rejected");
        return;
      }
    }
    else
    { // New data, just add it.
      $this->data['json'][$name] = $data;
    }
  }

  /**
   * Change the current language.
   */
  public function set_lang ($lang)
  {
    $this->lang = $lang;
    if (isset($this->text))
    {
      $this->text->default_lang = $lang;
    }
  }
  
}

