<?php

namespace Lum\Models\Common;

use Lum\Encode\Safe64;

/*
 * TODO: 
 * - send_*_email() need to support 'htmlTemplate' and 'textTemplate'
 * - getting $core->conf->mail defaults should be moved to App::mailer()
 */

trait Users
{
  abstract public function getUser ($identifier, $fieldname=null);
  abstract public function listUsers ($fields=null, $query=null);
  abstract public function newChild ($data=[], $opts=[]);

  protected $login_field = 'email'; // The unique stringy DB field.
  protected $token_field = 'token'; // The user token field.
  protected $hash_field  = 'hash';  // The authentication hash field.
  protected $reset_field = 'reset'; // The reset code field.

  protected $hashType    = 'sha256';   // The default hash algorithm.

  protected $auth_class = "\\Lum\\Auth\\Simple";

  public function hash_type ()
  {
    return $this->hashType;
  }

  public function auth_class ()
  {
    return $this->auth_class;
  }

  public function login_field ()
  {
    return $this->login_field;
  }

  public function token_field ()
  {
    return $this->token_field;
  }

  public function hash_field ()
  {
    return $this->hash_field;
  }

  public function reset_field ()
  {
    return $this->reset_field;
  }

  public function get_auth ($instance=false, $store=false)
  {
    $hash = $this->hashType;
    $class = $this->auth_class;
    $opts = ['hash'=>$hash, 'store'=>$store];
    if ($instance)
      $auth = $class::getInstance($opts);
    else
      $auth = new $class($opts);
    return $auth;
  }

  // Override the default offsetGet function.
  public function offsetGet ($offset): mixed
  {
    return $this->getUser($offset);
  }

  /**
   * Add a new user.
   *
   * @param Array   $rowdef     The row definition.
   * @param String  $password   The raw password for the user.
   * @param Bool    $return     If True, return the new user object.
   *
   * @return Mixed              Results depend on value of $return
   *
   *   The returned value will be False if the login field was not
   *   properly specified in the $rowdef.
   *
   *   If $return is True, then given a correct login field, we will return 
   *   either a User row object, or Null, depending on if the row creation
   *   was successful.
   *
   *   If $return is False, and we have a correct login field, we will simply
   *   return True, regardless of if the row creation succeeded or not.
   */
  public function addUser ($rowdef, $password, $return=False)
  {
    // We allow overriding the login, token, and hash fields.
    $lfield = $this->login_field;
    $tfield = $this->token_field;
    $hfield = $this->hash_field;
    $rfield = $this->reset_field;

    if (is_array($lfield))
    { // Use the first login field for test.
      $lfield = $lfield[0];
    }

    if (!isset($rowdef[$lfield]))
      return False; // The login field is required!

    // Generate a unique token.
    $token = hash($this->hashType, time());
    $rowdef[$tfield] = $token;

    // Generate a unique reset field.
    $reset = uniqid();
    $rowdef[$rfield] = $reset;

    // Generate the password hash.
    $auth = $this->get_auth();
    $hash = $auth->generate_hash($token, $password);
    $rowdef[$hfield] = $hash;

    // Create the user.
    $user = $this->newChild($rowdef);
    $user->save();

    // Return the created user if requested.
    if ($return)
    {
      return $user;
    }

    return True;
  }

  /**
   * Find the first provider of a 'hook' method, and return the provider.
   *
   * The order we look in:
   *
   *   1. $user    (Only if passed as a parameter.)
   *   2. $this    (The Users model itself.)
   *   3. $parent  (The Controller object that loaded this model.)
   *
   * If none of the providers have the hook, we return null.
   *
   * @param string $hook     The hook method we're looking for.
   * @param User $user=null  If set, adds the user to the provider list.
   *
   * @return object|null  The first object with the hook, or null if not found.
   */
  protected function find_hooker ($hook, $user=null)
  {
    if (isset($user) && is_callable([$user, $hook]))
    { // The user has the hook.
      return $user;
    }
    if (method_exists($this, $hook))
    { // We have the hook.
      return $this;
    }
    if (is_callable([$this->parent, $hook]))
    { // The controller has the hook.
      return $this->parent;
    }
  }

  /**
   * Basically the same as find_hooker, but instead of a hook, we're looking
   * for a property of a certain name. If we find it in one of the providers,
   * we return the value of the property.
   */
  protected function find_property ($prop, $user=null)
  {
    if (isset($user) && property_exists($user, $prop) && isset($user->$prop))
    {
      return $user->$prop;
    }
    if (property_exists($this, $prop) && isset($this->$prop))
    {
      return $this->$prop;
    }
    $ctrl = $this->parent;
    if (property_exists($ctrl, $prop) && isset($ctrl->$prop))
    {
      return $ctrl->$prop;
    }
  }

  // Backend method to start the reset process and send an e-mail to the
  // user with the appropriate message. Used to live in the User trait, but
  // I think it's more appropriate to be in here.
  protected function mail_reset_pw ($user, $opts=[])
  {
    // Get our required information.
    $uid  = $user->get_id();
    if (!$uid) return null; // No user? Cannot continue

    // Pre-email check, if it returns false, we fail.
    // You can populate $opts with extended data if required.
    $hooker = $this->find_hooker('pre_email', $user);
    if (isset($hooker))
    {
      if (!$hooker->pre_email($opts))
      { // Cannot continue.
        return False;
      }
    }

    $ctrl = $this->parent;
    $code = $user->resetReset();

    $translate = $opts['translate_subject'] ?? true;

    if ($translate)
    { // Subject uses translation strings.
      $text = $ctrl->get_text();
      $subject = $opts['subject'];
      $subject = $text[$subject];
      $opts['subject'] = $subject;
    }

    // Set up a validation code to send to the user.
    $validInfo = array('uid'=>$uid, 'code'=>$code);
    $validCode = Safe64::encodeData($validInfo);

    // E-mail rules for the Lum mailer.
    $mail_rules = $opts['fields'] ?? $opts['mail_rules'] ?? [];
    $mail_rules['username'] = true;
    $mail_rules['siteurl']  = true;
    $mail_rules['code']     = true;

    // Our mailer options.
    $mail_opts             = $opts['mail_opts'] ?? $opts;
    $mail_opts['views']    = isset($opts['view_loader']) 
      ? $opts['view_loader'] : 'mail_messages';
    $mail_opts['to']       = $user->email;
    $mail_opts['fields']   = $mail_rules;

    if (isset($mail_opts['template']) && !isset($mail_opts['htmlTemplate']))
    {
      $mail_opts['htmlTemplate'] = $mail_opts['template'];
    }

    if (isset($mail_opts['alt_template']) && !isset($mail_opts['textTemplate']))
    {
      $mail_opts['textTemplate'] = $mail_opts['alt_template'];
    }

    if (!isset($mail_opts['handler']))
    { // Look for a handler in the 'email_class' property.
      $mail_opts['handler'] = $this->find_property('email_class', $user);
    }

    // Populate $mail_rules and $mail_opts with further data here.
    $hooker = $this->find_hooker('prep_email_options', $user);
    if (isset($hooker))
    {
      $hooker->prep_email_options($mail_opts, $mail_rules, $opts);
    }

    // Build our mailer object.
    $mailer = \Lum\App::mailer($mail_opts);

    // The message data for the template.
    $mail_data = isset($opts['mail_data']) ? $opts['mail_data'] : [];
    $mail_data['username'] = $user->getName();
    $mail_data['shortname'] = $user->getName(true);
    $mail_data['siteurl']  = $ctrl->url();
    $mail_data['code']     = $validCode;

    // Populate $mail_data, and make any changes to $mailer here.
    $hooker = $this->find_hooker('prep_email_data', $user);
    if (isset($hooker))
    {
      $hooker->prep_email_data($mailer, $mail_data, $opts);
    }

    // Send the message.
    $sent = $mailer->send($mail_data);

    // One last check after sending the message.
    $hooker = $this->find_hooker('post_email', $user);
    if (isset($hooker))
    {
      $hooker->post_email($mailer, $sent, $opts);
    }

    // Return the response from $mailer->send();
    return $sent;
  } // end function mail_reset_pw();

  /**
   * Send a forgot password email using mail_reset_pw()
   *
   * @param User $user   The user we're submitting the request for.
   * @param array $opts  Options for mail_reset_pw(), plus a couple:
   *
   *  'template'  The template to use. Default: 'forgot_password'.
   *  'subject'   The subject to use.  Default: 'subject.forgot'.
   *
   *  'translate_subject'  If true, the subject is a translation string
   *                       key, and the actual subject will be looked up
   *                       using the \Lum\UI\String() instance returned
   *                       by $ctrl->get_text();
   *                       If false, the subject will be used as is.
   *                       Default: true
   *
   * @return mixed  The return from mail_reset_pw() or null if invalid user.
   */
  public function send_forgot_email ($user, $opts=[])
  {
    if (!isset($opts['template'])) $opts['template'] = 'forgot_password';
    if (!isset($opts['subject']))  $opts['subject']  = 'subject.forgot';
    return $this->mail_reset_pw($user, $opts);
  }

  /**
   * Send an activation email.
   *
   * As the activation emails are pretty much identical to forgot password
   * ones, this and send_forgot_email() use the same protected method, 
   * but with different defaults.
   *
   *  'template'  New default: 'activate_account'
   *  'subject'   New default: 'subject.activate'
   *
   * That's it, see send_forgot_email() and mail_reset_pw() for details.
   */
  public function send_activation_email ($user, $opts=[])
  {
    if (!isset($opts['template'])) $opts['template'] = 'activate_account';
    if (!isset($opts['subject']))  $opts['subject']  = 'subject.activate';
    return $this->mail_reset_pw($user, $opts);
  }

}
