<?php

namespace Drupal\sem\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\sir\Controller\UtilsController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class InitializationController extends ControllerBase{

  public function index() {

    $root_url = \Drupal::request()->getBaseUrl();
    $redirect = new RedirectResponse($root_url . '/sem/list/entity/_/1/12');
  
    return $redirect;

  }

}
