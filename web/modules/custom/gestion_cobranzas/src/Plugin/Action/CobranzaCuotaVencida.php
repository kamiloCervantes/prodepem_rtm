<?php

namespace Drupal\gestion_cobranzas\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\webform\Entity\WebformSubmission;

/**
 * Registra un submission en el webform notificaciones_cobranzas.
 *
 * @Action(
 *   id = "gestion_cobranzas_cobranza_cuota_vencida",
 *   label = @Translation("Cobranza cuota vencida"),
 *   type = "node"
 * )
 */
class CobranzaCuotaVencida extends ActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    if (!$entity) {
      return;
    }

    $timestamp = $entity->get('field_fecha_vencimiento_tipado')->value;
    $mes = 'N/A';
    if ($timestamp) {
      $meses = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
        5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
        9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
      ];
      $mes = $meses[(int) date('n', strtotime($timestamp))] ?? 'N/A';
    }

    $config = \Drupal::config('gestion_cobranzas.settings');
    $mensaje_template = $config->get('mensaje_notificacion') ?? 'La cuota de su factura del mes de [mes] ha vencido.';
    $mensaje = str_replace('[mes]', $mes, $mensaje_template);

    $values = [
      'webform_id' => 'notificaciones_cobranzas',
      'data' => [
        'mensaje' => $mensaje,
        'destinatario' => $entity->get('field_id_cliente')->value ?? '',
        'enviado' => 0,
      ],
    ];

    /** @var \Drupal\webform\WebformSubmissionInterface $webform_submission */
    $webform_submission = WebformSubmission::create($values);
    $webform_submission->save();
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    /** @var \Drupal\node\NodeInterface $object */
    return $object->access('update', $account, $return_as_object);
  }

}
