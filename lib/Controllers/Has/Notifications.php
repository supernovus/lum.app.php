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
trait Notifications
{
  use Messages;

  protected $notifications;     // The notification library.

  protected function __init_notifications_controller ($opts)
  {
    $notifications = \Lum\UI\Notifications::getInstance(
    [
      'parent' => $this,
      'text'   => $this->text,
    ]);

    $this->notifications = $notifications;
    $this->data['notifications'] = $notifications;
  }

  public function get_notifications ()
  {
    return $this->notifications;
  }

  // An alias for message()
  public function msg ($name, $opts=[])
  {
    return $this->message($name, $opts);
  }

  // Add a message to the stack.
  public function message ($name, $opts=[])
  {
    if (!isset($opts['type']))
      $opts['type'] = 'message';
    return $this->notifications->addMessage($name, $opts);
  }

  /**
   * Store any current messages in the session, so they can be retreived
   * on a redirect.
   */
  public function store_messages ()
  {
    $this->notifications->store();
  }

  // Redirect to another page, and show a message.
  public function redirect_msg ($name, $url=Null, $opts=array())
  {
    $opts['session'] = True;
    $this->message($name, $opts);
    $this->redirect($url, $opts);
  }

  // Go to another page, and show a message.
  public function go_msg ($msg, $page, $params=[], $gopts=[], $mopts=[])
  {
    $mopts['session'] = True;
    $this->message($msg, $mopts);
    $this->go($page, $params, $gopts);
  }

  // Add an error to the stack.
  public function error ($name, $opts=array())
  {
    $opts['type'] = 'error';
    $this->message($name, $opts);
  }

  // Add a dismissable notification to the stack.
  public function notify ($name, $opts=[])
  {
    $opts['type'] = 'notice';
    $this->message($name, $opts);
  }

  // Use this when you want to return the display immediately.
  public function show_error ($name, $opts=array())
  {
    $this->error($name, $opts);
    return $this->display();
  }

  // Use this when you want to redirect to another page, and show the error.
  public function redirect_error ($name, $url=Null, $opts=array())
  {
    $opts['type']  = 'error';
    $this->redirect_msg($name, $url, $opts);
  }

  // Use this when you want to redirect to another page, and show the error.
  public function redirect_warn ($name, $url=Null, $opts=array())
  {
    $opts['type']  = 'warning';
    $this->redirect_msg($name, $url, $opts);
  }

  // Go to another page, showing an error.
  public function go_error ($msg, $page, $params=[], $gopts=[], $mopts=[])
  {
    $mopts['type'] = 'error';
    $this->go_msg($msg, $page, $params, $gopts, $mopts);
  }

  // Go to another page, showing a warning.
  public function go_warn ($msg, $page, $params=[], $gopts=[], $mopts=[])
  {
    $mopts['type'] = 'warning';
    $this->go_msg($msg, $page, $params, $gopts, $mopts);
  }

  // Add a warning to the stack.
  public function warning ($name, $opts=[])
  {
    $opts['type']  = 'warning';
    $this->message($name, $opts);
  }

  // An alias for warning()
  public function warn ($name, $opts=[])
  {
    return $this->warning($name, $opts);
  }

  // Check to see if we have any of a certain class of status  messages.
  public function has_status ($type)
  {
    return $this->notifications->hasStatus($type);
  }

  // Wrapper for the above checking for errors.
  public function has_errors ()
  {
    return $this->has_status('error');
  }

  /**
   * Add all of the strings needed for our current notifications to the
   * 'status_messages' JSON element (using the add_status_json() method.)
   *
   * @return array The notification messages.
   */
  public function add_notification_messages ()
  {
    $codes = [];
    $msgs = $this->notifications->getMessages();
    foreach ($msgs as $msg)
    {
      $msgid = $msg->getMsgId();
      $codes[] = $msgid;
    }
    $this->add_status_json($codes);
    return $msgs;
  }

}

