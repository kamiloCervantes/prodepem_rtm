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

  /**
   * Returns a list of terms from the tipos_de_vehiculos taxonomy.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function getTiposVehiculos() {
    $vocabulary = 'tipos_de_vehiculos';
    $query = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->getQuery();
    $tids = $query->condition('vid', $vocabulary)
      ->accessCheck(TRUE)
      ->sort('weight')
      ->execute();

    $terms = Term::loadMultiple($tids);
    $data = [];

    foreach ($terms as $term) {
      $data[] = [
        'tid' => $term->id(),
        'name' => $term->label(),
      ];
    }

    return new JsonResponse($data);
  }



  /**
   * Returns the quote value for a node and a vehicle type.
   *
   * @param int $nid
   *   The node ID.
   * @param string $term_label
   *   The label of the vehicle type term.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function getValorCotizacion($nid, $term_label) {
    $data = [
      'valor' => 0,
      'status' => 'not_found',
    ];

    $node = \Drupal\node\Entity\Node::load($nid);
    if ($node && $node->hasField('field_reglas_de_cotizacion')) {
      $paragraphs = $node->get('field_reglas_de_cotizacion')->referencedEntities();
      foreach ($paragraphs as $paragraph) {
        if ($paragraph->hasField('field_tipo_de_vehiculo') && !$paragraph->get('field_tipo_de_vehiculo')->isEmpty()) {
          $term = $paragraph->get('field_tipo_de_vehiculo')->entity;
          if ($term && $term->label() === $term_label) {
            if ($paragraph->hasField('field_valor_cotizacion')) {
              $data = [
                'valor' => $paragraph->get('field_valor_cotizacion')->value,
                'status' => 'success',
              ];
              break;
            }
          }
        }
      }
    }

    return new JsonResponse($data);
  }

}
