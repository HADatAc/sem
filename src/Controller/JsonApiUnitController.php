<?php

namespace Drupal\sem\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Utility\Xss;

/**
 * Class JsonApiUnitController
 * @package Drupal\sem\Controller
 */
class JsonApiUnitController extends ControllerBase{

  /**
   * @return JsonResponse
   */
  public function handleAutocomplete(Request $request) {
    $results = [];
    $input = $request->query->get('q');
    if (!$input) {
      return new JsonResponse($results);
    }
    $keyword = Xss::filter($input);
    $api = \Drupal::service('rep.api_connector');
    $unit_list = $api->listByKeyword('unit',$keyword,10,0);
    $obj = json_decode($unit_list);
    $units = [];
    if ($obj->isSuccessful) {
      $units = $obj->body;
    }
    foreach ($units as $unit) {
      $results[] = [
        'value' => $unity->label . ' [' . $unity->uri . ']',
        'label' => $unity->label,
      ];
    }
    return new JsonResponse($results);
  }

}