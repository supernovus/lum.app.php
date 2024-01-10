<?php

namespace Lum\Controllers;

use Lum\Controllers\Has\{Routes,URL,Uploads,ViewData,Wrappers};

/**
 * A simple abstract controller class for common features used in Web Apps.
 *
 * @uses Has\Routes
 * @uses Has\URL
 * @uses Has\Uploads
 * @uses Has\ViewData
 * @uses Has\Wrappers
 * @uses Has\API\JSON
 */
abstract class Webapp extends Core
{
  use Routes, URL, Uploads, ViewData, Wrappers, Has\API\JSON;
}
