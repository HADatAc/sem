<?php

namespace Drupal\sem\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Utility\Xss;

/**
 * Class JsonApiEntityController
 * @package Drupal\sem\Controller
 */
class JsonApiEntityController extends ControllerBase{

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
    $entity_list = $api->listByKeyword('entity',$keyword,10,0);
    $obj = json_decode($entity_list);
    $entities = [];
    if ($obj->isSuccessful) {
      $entities = $obj->body;
    }
    foreach ($entities as $entity) {
      $results[] = [
        'value' => $entity->label . ' [' . $entity->uri . ']',
        'label' => $entity->label,
      ];
    }
    return new JsonResponse($results);
  }

}