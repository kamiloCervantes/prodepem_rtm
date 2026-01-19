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


   /**
   * Returns a list of terms from the metodos_de_pago taxonomy.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function getMetodosDePago() {
    $vocabulary = 'metodos_de_pago';
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
   * Returns data based on payment method and node ID.
   *
   * @param int $nid
   *   The node ID.
   * @param string $metodo_pago
   *   The name of the payment method term.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function getDatosMetodoPago($nid, $metodo_pago) {
    $data = [];
    $node = \Drupal\node\Entity\Node::load($nid);

    //quitar espacios de metodopago
    $metodo_pago = str_replace(' ', '', $metodo_pago);
    //uppercase de metodopago
    $metodo_pago = strtoupper($metodo_pago);


    if ($node) {
      switch ($metodo_pago) {
        case 'EFECTIVO':
          if ($node->hasField('field_puntos_de_venta')) {
            $paragraphs = $node->get('field_puntos_de_venta')->referencedEntities();
            foreach ($paragraphs as $paragraph) {
              $data[] = $this->getEntityData($paragraph);
            }
          }
          break;

        case 'QR':
          if ($node->hasField('field_qr')) {
            $data['field_qr'] = $this->getFieldValue($node, 'field_qr');
          }
          if ($node->hasField('field_cuentas_bancarias')) {
            $paragraphs = $node->get('field_cuentas_bancarias')->referencedEntities();
            foreach ($paragraphs as $paragraph) {
              $data['field_cuentas_bancarias'][] = $this->getEntityData($paragraph);
            }
          }
          break;

        case 'BRE':
          if ($node->hasField('field_enlace_bre')) {
            $data['field_enlace_bre'] = $this->getFieldValue($node, 'field_enlace_bre');
          }
          break;

        case 'CONVENIOS':
          if ($node->hasField('field_convenios')) {
            $paragraphs = $node->get('field_convenios')->referencedEntities();
            foreach ($paragraphs as $paragraph) {
              $data['field_convenios'][] = $this->getEntityData($paragraph);
            }
          }
          break;

        case 'SISTECRÉDITO':
          if ($node->hasField('field_enlace_asesor_sistecredito')) {
            $data['field_enlace_asesor_sistecredito'] = $this->getFieldValue($node, 'field_enlace_asesor_sistecredito');
          }
          break;

        case 'CORRESPONSALBANCARIO':
          if ($node->hasField('field_cuentas_bancarias')) {
            $paragraphs = $node->get('field_cuentas_bancarias')->referencedEntities();
            foreach ($paragraphs as $paragraph) {
              $data['field_cuentas_bancarias'][] = $this->getEntityData($paragraph);
            }
          }
          break;
      }
    }

    return new JsonResponse($data);
  }

  /**
   * Helper to get all field values from an entity.
   */
  private function getEntityData($entity) {
    $data = [];
    foreach ($entity->getFieldDefinitions() as $field_name => $definition) {
      if ($definition->getFieldStorageDefinition()->isBaseField()) {
        continue;
      }
      if (!$entity->get($field_name)->isEmpty()) {
        $data[$field_name] = $this->getFieldValue($entity, $field_name);
      }
    }
    return $data;
  }

  /**
   * Helper to get field value handle different field types.
   */
  private function getFieldValue($entity, $field_name) {
    $field = $entity->get($field_name);
    $values = [];

    foreach ($field as $item) {
      $val = $item->getValue();
      if (isset($val['target_id'])) {
        // Handle referenced entities (taxonomy terms, media, etc.)
        $referenced_entity = $item->entity;
        if ($referenced_entity) {
          if ($referenced_entity->getEntityTypeId() === 'taxonomy_term') {
            $values[] = [
              'tid' => $referenced_entity->id(),
              'name' => $referenced_entity->label(),
            ];
          } elseif ($referenced_entity->getEntityTypeId() === 'file') {
             $values[] = [
              'url' => \Drupal::service('file_url_generator')->generateAbsoluteString($referenced_entity->getFileUri()),
              'name' => $referenced_entity->getFilename(),
            ];
          } elseif ($referenced_entity->getEntityTypeId() === 'media') {
             // Basic media support
             $values[] = [
               'mid' => $referenced_entity->id(),
               'name' => $referenced_entity->label(),
             ];
          } else {
            $values[] = $referenced_entity->id();
          }
        }
      } elseif (isset($val['value'])) {
        $values[] = $val['value'];
      } elseif (isset($val['uri'])) {
        $values[] = $val['uri'];
      } else {
        $values[] = $val;
      }
    }

    return count($values) === 1 ? reset($values) : $values;
  }

}
