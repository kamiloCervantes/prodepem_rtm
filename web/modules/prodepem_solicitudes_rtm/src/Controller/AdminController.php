<?php

namespace Drupal\prodepem_solicitudes_rtm\Controller;

use Drupal\Core\Controller\ControllerBase;

use Drupal\webform\Entity\WebformSubmission;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

use Drupal\taxonomy\Entity\Term;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Returns responses for prodepem_solicitudes_rtm routes.
 */
class AdminController extends ControllerBase {

  /**
   * Builds the dashboard page.
   */
  public function dashboard() {
    return [
      '#theme' => 'prodepem_rtm_dashboard',
      '#title' => $this->t('RTM Admin Dashboard'),
    ];
  }

  /**
   * Generates a PDF from a Twig template using webform submission data.
   */
  public function generarPdfDesdePlantilla($sid) {
    \Drupal::logger('prodepem_solicitudes_rtm')->debug('Accediendo a generar PDF para SID: @sid', ['@sid' => $sid]);
    try {
      $webform_submission = WebformSubmission::load($sid);
      if (!$webform_submission) {
        return [
          '#markup' => $this->t('No se encontró el envío del webform con ID: @sid', ['@sid' => $sid]),
        ];
      }

      // 1. Obtener datos de la sumisión
      $data = $webform_submission->getData();
      
      // Resolver etiquetas de taxonomía para mejorar la visualización en el PDF
      $taxonomy_fields = ['cda', 'entidad_convenio', 'departamento', 'ubicacion_cda'];
      foreach ($taxonomy_fields as $field) {
        if (!empty($data[$field])) {
          $term = Term::load($data[$field]);
          if ($term) {
            $data[$field . '_label'] = $term->label();
          }
        }
      }

      // 2. Renderizar la plantilla Twig
      $render_array = [
        '#theme' => 'prodepem_rtm_pdf_template',
        '#data' => $data,
        '#submission' => $webform_submission,
        '#date' => date('d/m/Y'),
      ];

      $html = \Drupal::service('renderer')->renderPlain($render_array);

      // 3. Configurar Dompdf
      $options = new Options();
      $options->set('isHtml5ParserEnabled', true);
      $options->set('isRemoteEnabled', true);
      $dompdf = new Dompdf($options);

      // 4. Cargar HTML y generar PDF
      $dompdf->loadHtml($html);
      $dompdf->setPaper('A4', 'portrait');
      $dompdf->render();

      // 5. Preparar respuesta de descarga
      $output = $dompdf->output();
      $response = new Response($output);
      $disposition = $response->headers->makeDisposition(
        ResponseHeaderBag::DISPOSITION_ATTACHMENT,
        'solicitud_rtm_' . $webform_submission->id() . '.pdf'
      );
      $response->headers->set('Content-Type', 'application/pdf');
      $response->headers->set('Content-Disposition', $disposition);

      return $response;

    } catch (\Exception $e) {
      \Drupal::logger('prodepem_solicitudes_rtm')->error($e->getMessage());
      return [
        '#markup' => $this->t('Error al generar el PDF: @message', ['@message' => $e->getMessage()]),
      ];
    }
  }

}
