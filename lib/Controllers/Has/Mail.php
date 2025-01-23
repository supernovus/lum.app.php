<?php

namespace Lum\Controllers\Has;

/**
 * Trait to add a Mail template view with optional language-specific
 * features if we're also using the Messages trait.
 *
 * Define a property called 'email_path' if you want to override the default
 * mailer view folder of 'views/mail'.
 */
trait Mail
{
  protected function __init_mailer_controller ($opts)
  {
    $core = \Lum\Core::getInstance();
    if (isset($core->mail_messages)) { return; } // Sanity check.

    if (property_exists($this, 'email_path'))
    {
      if (is_array($this->email_path))
      {
        $dirs = $this->email_path;
      }
      else
      {
        $dirs = [$this->email_path];
      }
    }
    else
    {
      $dirs = ['views/mail'];
    }

    $lang = $this->get_prop('lang', Null);

    $core->mail_messages = 'views';
    foreach ($dirs as $dir)
    {
      /** @disregard P1006 -> mail_messages is NOT a string */
      if (isset($lang))
        $core->mail_messages->addDir($dir . '/' . $lang);
      /** @disregard P1006 */
      $core->mail_messages->addDir($dir);
    }
  }

  /**
   * Get a Mailer object.
   * 
   * Is now a wrapper of the Lum\App::mailer() static method.
   *
   * @param array|null $fields (Optional) Fields for Mailer.
   * @param array      $opts   (Optional) Options for Mailer.
   * @return ?object A mailer instance (if class was found).
   */
  public function mailer (?array $fields=null, array $opts=[])
  {
    if (isset($fields))
    {
      $opts['fields'] = $fields;
    }
    return \Lum\App::mailer($opts);
  }

}
