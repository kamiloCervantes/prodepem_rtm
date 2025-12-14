<?php

namespace Drupal\prodepem_solicitudes_rtm\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\taxonomy\Entity\Term;

/**
 * Returns responses for Prodepem Solicitudes RTM routes.
 */
class AjaxController extends ControllerBase {

  /**
   * Returns details of a CDA taxonomy term.
   *
   * @param int $tid
   *   The term ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function getCdaDetails($tid) {
    $term = Term::load($tid);
    $data = [];

    if ($term && $term->bundle() === 'cdas') {
      // Get field_ciudad label.
      $ciudad_label = '';
      if (!$term->get('field_ciudad')->isEmpty()) {
        /** @var \Drupal\taxonomy\Entity\Term $ciudad_term */
        $ciudad_term = $term->get('field_ciudad')->entity;
        if ($ciudad_term) {
          $ciudad_label = $ciudad_term->label();
        }
      }

      // Get field_departamento label.
      $departamento_label = '';
      if (!$term->get('field_departamento')->isEmpty()) {
        /** @var \Drupal\taxonomy\Entity\Term $departamento_term */
        $departamento_term = $term->get('field_departamento')->entity;
        if ($departamento_term) {
          $departamento_label = $departamento_term->label();
        }
      }

      // Get field_direccion value.
      $direccion = '';
      if (!$term->get('field_direccion')->isEmpty()) {
        $direccion = $term->get('field_direccion')->value;
      }

      // Get field_telefono value.
      $telefono = '';
      if (!$term->get('field_telefono')->isEmpty()) {
        $telefono = $term->get('field_telefono')->value;
      }

      // Get description processed value.
      $description = '';
      if (!$term->get('description')->isEmpty()) {
        $description = $term->get('description')->processed;
      }

      $data = [
        'name' => $term->label(),
        'field_ciudad' => $ciudad_label,
        'field_departamento' => $departamento_label,
        'field_direccion' => $direccion,
        'field_telefono' => $telefono,
        'description' => $description,
      ];
    }

    return new JsonResponse($data);
  }

}
