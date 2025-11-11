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
  // public function handleAutocomplete(Request $request) {
  //   $results = [];
  //   $input = $request->query->get('q');
  //   if (!$input) {
  //     return new JsonResponse($results);
  //   }
  //   $keyword = Xss::filter($input);
  //   $api = \Drupal::service('rep.api_connector');
  //   $unit_list = $api->listByKeyword('unit',$keyword,10,0);
  //   $obj = json_decode($unit_list);
  //   $units = [];
  //   if ($obj->isSuccessful) {
  //     $units = $obj->body;
  //   }
  //   foreach ($units as $unit) {
  //     $results[] = [
  //       'value' => $unit->label . ' [' . $unit->uri . ']',
  //       'label' => $unit->label,
  //     ];
  //   }
  //   return new JsonResponse($results);
  // }

  // New Method using Instances End-Point
//   public function handleAutocomplete(Request $request) {
//     $results = [];
//     $input = $request->query->get('q');
//     if (!$input) {
//       return new JsonResponse($results);
//     }
//     $keyword = Xss::filter($input);
//     $api = \Drupal::service('rep.api_connector');
//     $unit_list = $api->listInstancesByKeyword($keyword);
//     $obj = json_decode($unit_list);
//     $units = [];
//     if ($obj->isSuccessful) {
//       $units = $obj->body;
//     }
//     foreach ($units as $unit) {
//       $results[] = [
//         'value' => $unit->label . ' [' . $unit->uri . ']',
//         'label' => $unit->label,
//         'description' => $unit->description != '' ? $unit->description : $unit->label
//       ];
//     }
//     return new JsonResponse($results);
//   }

  public function handleAutocomplete(Request $request) {
    $results = [];
    $input = $request->query->get('q');

    if (!$input) {
      return new JsonResponse($results);
    }

    $keyword = Xss::filter($input);

    /** @var \Drupal\rep\ApiConnector $api */
    $api = \Drupal::service('rep.api_connector');
    $unit_list = $api->listInstancesByKeyword($keyword);
    $obj = json_decode($unit_list);

    if (!empty($obj->isSuccessful) && !empty($obj->body)) {
      foreach ($obj->body as $unit) {
        $label = $unit->label ?? '';
        $description = $unit->description != '' ? $unit->description : 'Olá';
        $uri = $unit->uri ?? '';

        // Value used in the textfield (compatible with Utils::uriFromAutocomplete()).
        $value = $label . ' [' . $uri . ']';

        $results[] = [
          'value' => $value,
          'label' => $label,
          'description' => $description,
        ];
      }
    }

    return new JsonResponse($results);
  }

}
