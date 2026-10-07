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
            if ($field === 'cda') {
              if ($term->hasField('field_direccion') && !$term->get('field_direccion')->isEmpty()) {
                $data['cda_direccion'] = $term->get('field_direccion')->value;
              }
              if ($term->hasField('field_telefono') && !$term->get('field_telefono')->isEmpty()) {
                $data['cda_telefono'] = $term->get('field_telefono')->value;
              }
              if ($term->hasField('description') && !$term->get('description')->isEmpty()) {
                $data['detalles_cda'] = $term->getDescription();
              }
            }
          }
        }
      }

      // 2. Renderizar la plantilla Twig
      $logo_uri = NULL;
      $logo_path = \Drupal::root() . '/sites/default/files/2026-01/prodepem.jpg';
      if (file_exists($logo_path)) {
        $logo_data = base64_encode(file_get_contents($logo_path));
        $logo_uri = 'data:image/jpeg;base64,' . $logo_data;
      }
      
      $render_array = [
        '#theme' => 'prodepem_rtm_pdf_template',
        '#data' => $data,
        '#submission' => $webform_submission,
        '#date' => date('d/m/Y'),
        '#logo_path' => $logo_uri,
        '#serial' => $webform_submission->serial->value,
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


  /**
   * Genera el PDF de la Solicitud de Seguro de Vida Grupo a partir de la sumisión de un webform.
   *
   * Valida que la sumisión corresponda exclusivamente a 'formulario_equidad_seguros'
   * y mapea de forma selectiva únicamente los datos pertinentes para el reporte,
   * omitiendo campos internos, códigos OTP y metadatos no requeridos.
   *
   * @param int|string $sid
   *   El ID de la sumisión del webform (sid).
   *
   * @return \Symfony\Component\HttpFoundation\Response|array
   *   Respuesta binaria con el PDF descargable o arreglo de render con mensaje de error.
   */
  public function generarPdfSeguroVida($sid) {
    \Drupal::logger('prodepem_solicitudes_rtm')->debug('Accediendo a generar PDF Seguro de Vida para SID: @sid', ['@sid' => $sid]);
    try {
      $webform_submission = WebformSubmission::load($sid);
      if (!$webform_submission) {
        return [
          '#markup' => $this->t('No se encontró el envío del webform con ID: @sid', ['@sid' => $sid]),
        ];
      }

      // Validar si pertenece estrictamente al webform 'formulario_equidad_seguros'.
      $webform = $webform_submission->getWebform();
      $webform_id = $webform ? $webform->id() : '';
      if ($webform_id !== 'formulario_equidad_seguros') {
        \Drupal::logger('prodepem_solicitudes_rtm')->warning('Intento de generar PDF Seguro de Vida con formulario no válido: @webform (SID: @sid)', [
          '@webform' => $webform_id,
          '@sid' => $sid,
        ]);
        return [
          '#markup' => $this->t('El envío con ID @sid no pertenece al formulario de Equidad Seguros (formulario_equidad_seguros).', [
            '@sid' => $sid,
          ]),
        ];
      }

      // 1. Obtener datos crudos de la sumisión.
      $raw_data = $webform_submission->getData();

      // Función auxiliar para resolver etiquetas de términos de taxonomía si vienen como ID numérico.
      $resolve_term = function ($value) {
        if (!empty($value) && is_numeric($value)) {
          $term = Term::load((int) $value);
          if ($term) {
            return $term->label();
          }
        }
        return $value;
      };

      // Función auxiliar para normalizar respuestas de tipo Sí/No a 'si'/'no'.
      $normalize_yes_no = function ($val) {
        if (is_null($val) || $val === '') {
          return 'no';
        }
        if (is_bool($val)) {
          return $val ? 'si' : 'no';
        }
        $str = mb_strtolower(trim((string) $val));
        if (in_array($str, ['1', 'si', 'sí', 'true', 'yes', 'on'], TRUE)) {
          return 'si';
        }
        return 'no';
      };

      // 2. Filtrar y estructurar ÚNICAMENTE los datos necesarios para el reporte del seguro de vida.
      $data = [];

      // A. Datos del Tomador (mapeo según 'selector_tomador' o valores por defecto institucionales).
      if (isset($raw_data['selector_tomador'])) {
        $selector = (string) $raw_data['selector_tomador'];
        if ($selector === '1') {
          $raw_data['tomador'] = 'Fondo de empleado de docente de la Universidad de Córdoba';
          $raw_data['direccion'] = 'Cra 6 # 76-103';
          $raw_data['direccion_tomador'] = 'Cra 6 # 76-103';
          $raw_data['telefono'] = '3014619585';
          $raw_data['telefono_tomador'] = '3014619585';
          $raw_data['ciudad'] = 'Montería';
          $raw_data['ciudad_tomador'] = 'Montería';
          $raw_data['c_c_nit'] = '900834726';
          $raw_data['nit_tomador'] = '900834726';
        }
        elseif ($selector === '2') {
          $raw_data['tomador'] = 'Asociación Sindical de Profesores Universitarios - ASPU';
          $raw_data['c_c_nit'] = '830001998';
          $raw_data['nit_tomador'] = '830001998';
          $raw_data['direccion'] = 'Cra 6 # 77-305';
          $raw_data['direccion_tomador'] = 'Cra 6 # 77-305';
          $raw_data['ciudad'] = 'Montería';
          $raw_data['ciudad_tomador'] = 'Montería';
          $raw_data['telefono'] = '3242560489';
          $raw_data['telefono_tomador'] = '3242560489';
        }
      }

      $data['tomador'] = !empty($raw_data['tomador']) ? $raw_data['tomador'] : 'PRODEPEM S.A.S';
      $data['nit_tomador'] = !empty($raw_data['nit_tomador']) ? $raw_data['nit_tomador'] : (!empty($raw_data['c_c_nit']) ? $raw_data['c_c_nit'] : '900.582.164-1');
      $data['direccion_tomador'] = !empty($raw_data['direccion_tomador']) ? $raw_data['direccion_tomador'] : (!empty($raw_data['direccion']) ? $raw_data['direccion'] : 'CALLE 64 N 1-43');
      $data['ciudad_tomador'] = !empty($raw_data['ciudad_tomador']) ? $resolve_term($raw_data['ciudad_tomador']) : (!empty($raw_data['ciudad']) ? $resolve_term($raw_data['ciudad']) : 'BOGOTÁ');
      $data['telefono_tomador'] = !empty($raw_data['telefono_tomador']) ? $raw_data['telefono_tomador'] : (!empty($raw_data['telefono']) ? $raw_data['telefono'] : '3118228328');

      // B. Datos del Asegurado.
      $nombre_completo = $raw_data['nombre_completo']
        ?? $raw_data['asegurado_principal']
        ?? $raw_data['nombre_del_asegurado']
        ?? $raw_data['nombre_asegurado']
        ?? $raw_data['asegurado']
        ?? $raw_data['nombres_y_apellidos']
        ?? $raw_data['nombre_y_apellidos']
        ?? $raw_data['nombres_apellidos']
        ?? $raw_data['nombre_y_apellido']
        ?? $raw_data['titular']
        ?? $raw_data['solicitante']
        ?? $raw_data['cliente']
        ?? '';

      if (empty($nombre_completo)) {
        $nombres = $raw_data['nombres'] ?? $raw_data['nombre'] ?? trim(($raw_data['primer_nombre'] ?? '') . ' ' . ($raw_data['segundo_nombre'] ?? ''));
        $apellidos = $raw_data['apellidos'] ?? $raw_data['primer_apellido'] ?? trim(($raw_data['primer_apellido'] ?? '') . ' ' . ($raw_data['segundo_apellido'] ?? ''));
        $nombre_completo = trim($nombres . ' ' . $apellidos);
      }

      // Búsqueda heurística en raw_data por si el campo tiene otra clave que contenga 'nombre' o 'asegurad'.
      if (empty($nombre_completo)) {
        foreach ($raw_data as $k => $v) {
          if (is_string($v) && !empty(trim($v)) && !in_array($k, ['tomador', 'empresa', 'direccion_empresa', 'email', 'correo_electronico']) && stripos($k, 'beneficiar') === FALSE && stripos($k, 'tomador') === FALSE) {
            if (stripos($k, 'nombre') !== FALSE || stripos($k, 'asegurad') !== FALSE) {
              $nombre_completo = trim($v);
              break;
            }
          }
        }
      }

      // En caso de estar autenticado o asociado al usuario de Drupal.
      if (empty($nombre_completo) && $webform_submission->getOwnerId()) {
        $owner = $webform_submission->getOwner();
        if ($owner) {
          $nombre_completo = $owner->getDisplayName();
        }
      }

      $data['nombre_completo'] = $nombre_completo;

      // Número y tipo de documento del asegurado.
      $numero_documento = $raw_data['numero_de_documento']
        ?? $raw_data['numero_documento']
        ?? $raw_data['documento']
        ?? $raw_data['numero_de_identificacion']
        ?? $raw_data['numero_identificacion']
        ?? $raw_data['identificacion']
        ?? $raw_data['cedula']
        ?? $raw_data['cedula_de_ciudadania']
        ?? $raw_data['no_documento']
        ?? $raw_data['no_de_documento']
        ?? $raw_data['documento_asegurado']
        ?? '';

      if (empty($numero_documento)) {
        foreach ($raw_data as $k => $v) {
          if ((is_string($v) || is_numeric($v)) && !empty($v) && stripos($k, 'beneficiar') === FALSE && stripos($k, 'tomador') === FALSE && stripos($k, 'nit') === FALSE) {
            if (stripos($k, 'documento') !== FALSE || stripos($k, 'cedula') !== FALSE || stripos($k, 'identifica') !== FALSE) {
              $numero_documento = (string) $v;
              break;
            }
          }
        }
      }
      $data['numero_de_documento'] = $numero_documento;

      $tipo_doc = $raw_data['tipo_de_documento'] ?? $raw_data['tipo_documento'] ?? $raw_data['tipo_doc'] ?? 'C.C.';
      $data['tipo_de_documento'] = $resolve_term($tipo_doc);
      $data['email'] = $raw_data['email'] ?? $raw_data['correo_electronico'] ?? '';
      $data['fecha_de_nacimiento'] = $raw_data['fecha_de_nacimiento'] ?? $raw_data['fecha_nacimiento'] ?? '';
      $data['trabaja_actualmente'] = $raw_data['trabaja_actualmente'] ?? $raw_data['trabaja_usted_actualmente'] ?? 'Si';
      $data['ocupacion'] = $resolve_term($raw_data['ocupacion'] ?? '');
      $data['cargo'] = $raw_data['cargo'] ?? '';
      $data['empresa'] = $raw_data['empresa'] ?? '';
      $data['direccion_empresa'] = $raw_data['direccion_empresa'] ?? '';
      $data['telefono_empresa'] = $raw_data['telefono_empresa'] ?? '';
      $data['ciudad'] = $resolve_term($raw_data['ciudad'] ?? $raw_data['ciudad_residencia'] ?? 'Bogotá D.C.');
      $data['estado_civil'] = $resolve_term($raw_data['estado_civil'] ?? '');
      $data['peso'] = $raw_data['peso'] ?? $raw_data['peso_kg'] ?? '';
      $data['estatura'] = $raw_data['estatura'] ?? $raw_data['estatura_cm'] ?? '';

      // C. Producto y Plan.
      $data['producto'] = $resolve_term($raw_data['producto'] ?? $raw_data['tipo_producto'] ?? 'Vida Grupo');
      $data['plan'] = $resolve_term($raw_data['plan'] ?? '');
      $data['valor_asegurado_solicitado'] = $raw_data['valor_asegurado_solicitado'] ?? $raw_data['valor_asegurado'] ?? '';
      $data['total_valor_asegurado'] = $raw_data['total_valor_asegurado'] ?? $data['valor_asegurado_solicitado'];

      // D. Beneficiarios (soporte exhaustivo para composites, múltiples y detección dinámica).
      $data['beneficiarios'] = [];
      $candidate_lists = [];

      // 1. Buscar cualquier clave que contenga 'beneficiar'.
      foreach ($raw_data as $k => $v) {
        if (stripos($k, 'beneficiar') !== FALSE && !empty($v)) {
          $candidate_lists[] = $v;
        }
      }

      // 2. Claves comunes conocidas de tablas o campos compuestos de beneficiarios.
      $common_bene_keys = [
        'beneficiarios',
        'tabla_beneficiarios',
        'beneficiario',
        'beneficiarios_tabla',
        'tabla_de_beneficiarios',
        'datos_beneficiarios',
        'designacion_beneficiarios',
        'designacion_de_beneficiarios',
        'beneficiarios_del_seguro',
        'datos_de_los_beneficiarios',
        'escriba_el_nombre_de_los_beneficiarios_de_este_seguro_y_su_respectivo_porcentaje',
        'escriba_el_nombre_de_los_beneficiarios',
      ];
      foreach ($common_bene_keys as $k) {
        if (isset($raw_data[$k]) && !empty($raw_data[$k])) {
          $candidate_lists[] = $raw_data[$k];
        }
      }

      // 3. Buscar cualquier arreglo cuyos elementos tengan estructura de beneficiarios (porcentaje, parentesco, etc.).
      foreach ($raw_data as $k => $v) {
        if (is_array($v) && !empty($v)) {
          $sample = reset($v);
          if (is_array($sample)) {
            $sample_keys = array_map('mb_strtolower', array_keys($sample));
            $found_signals = 0;
            foreach ($sample_keys as $sk) {
              if (strpos($sk, 'parentesco') !== FALSE || strpos($sk, 'porcent') !== FALSE || strpos($sk, 'edad') !== FALSE || strpos($sk, 'document') !== FALSE) {
                $found_signals++;
              }
            }
            if ($found_signals >= 2) {
              $candidate_lists[] = $v;
            }
          }
        }
      }

      // Procesar candidatos hasta obtener registros válidos.
      foreach ($candidate_lists as $candidate) {
        if (is_string($candidate)) {
          $decoded = json_decode($candidate, TRUE);
          if (is_array($decoded)) {
            $candidate = $decoded;
          }
        }
        if (!is_array($candidate) || empty($candidate)) {
          continue;
        }

        $parsed_list = [];
        foreach ($candidate as $item) {
          if (!is_array($item)) {
            continue;
          }

          // Nombre del beneficiario.
          $nombre_ben = '';
          foreach ($item as $ik => $iv) {
            if (is_string($iv) && !empty(trim($iv))) {
              $ik_lower = mb_strtolower((string) $ik);
              if ($ik_lower === 'nombre_y_apellido' || $ik_lower === 'nombre_completo' || $ik_lower === 'nombre' || $ik_lower === 'nombres' || $ik_lower === 'nombres_y_apellidos' || strpos($ik_lower, 'nombre') !== FALSE) {
                $nombre_ben = trim($iv);
                break;
              }
            }
          }

          // Tipo de documento.
          $tipo_doc = '';
          foreach ($item as $ik => $iv) {
            $ik_lower = mb_strtolower((string) $ik);
            if (strpos($ik_lower, 'tipo') !== FALSE || $ik_lower === 'documento' || strpos($ik_lower, 'tipo_doc') !== FALSE) {
              if (is_string($iv) || is_numeric($iv)) {
                $tipo_doc = (string) $iv;
                break;
              }
            }
          }
          if (empty($tipo_doc)) {
            $tipo_doc = 'C.C.';
          }

          // Número de documento.
          $num_doc = '';
          foreach ($item as $ik => $iv) {
            $ik_lower = mb_strtolower((string) $ik);
            if ((strpos($ik_lower, 'numero') !== FALSE || strpos($ik_lower, 'no_') !== FALSE || strpos($ik_lower, 'num') !== FALSE || strpos($ik_lower, 'cedula') !== FALSE || strpos($ik_lower, 'identifica') !== FALSE || $ik_lower === 'documento') && strpos($ik_lower, 'tipo') === FALSE) {
              if (is_string($iv) || is_numeric($iv)) {
                $num_doc = (string) $iv;
                break;
              }
            }
          }

          // Parentesco.
          $parentesco = '';
          foreach ($item as $ik => $iv) {
            $ik_lower = mb_strtolower((string) $ik);
            if (strpos($ik_lower, 'parentesco') !== FALSE || strpos($ik_lower, 'vinculo') !== FALSE || strpos($ik_lower, 'relacion') !== FALSE) {
              $parentesco = (string) $iv;
              break;
            }
          }

          // Edad.
          $edad = '';
          foreach ($item as $ik => $iv) {
            $ik_lower = mb_strtolower((string) $ik);
            if (strpos($ik_lower, 'edad') !== FALSE || strpos($ik_lower, 'anos') !== FALSE || strpos($ik_lower, 'años') !== FALSE) {
              $edad = (string) $iv;
              break;
            }
          }

          // Porcentaje.
          $porcentaje = '';
          foreach ($item as $ik => $iv) {
            $ik_lower = mb_strtolower((string) $ik);
            if (strpos($ik_lower, 'porcent') !== FALSE || strpos($ik_lower, 'particip') !== FALSE || $ik_lower === '%') {
              $porcentaje = (string) $iv;
              break;
            }
          }
          if (!empty($porcentaje) && is_numeric($porcentaje)) {
            $porcentaje .= '%';
          }

          // Si la fila cuenta con al menos uno de los atributos relevantes.
          if (!empty($nombre_ben) || !empty($num_doc) || !empty($parentesco) || !empty($porcentaje)) {
            $parsed_list[] = [
              'nombre_y_apellido' => $nombre_ben,
              'tipo_de_documento' => $resolve_term($tipo_doc),
              'numero_de_documento' => $num_doc,
              'parentesco' => $resolve_term($parentesco),
              'edad' => $edad,
              'porcentaje' => $porcentaje,
            ];
          }
        }

        if (!empty($parsed_list)) {
          $data['beneficiarios'] = array_values($parsed_list);
          break;
        }
      }

      \Drupal::logger('prodepem_solicitudes_rtm')->info('Beneficiarios procesados para SID @sid: @count registros encontrados.', [
        '@sid' => $sid,
        '@count' => count($data['beneficiarios']),
      ]);

      // E. Cuestionario de Salud (1 a 14 preguntas).
      $salud_map = [
        1 => ['afecciones_cardiovasculares', 'salud_1'],
        2 => ['afecciones_cerebrovasculares', 'salud_2'],
        3 => ['alcoholismo', 'salud_3'],
        4 => ['cirugia', 'salud_4'],
        5 => ['diabetes', 'salud_5'],
        6 => ['enfermedad_oncologica', 'salud_6'],
        7 => ['epoc', 'salud_7'],
        8 => ['insuficiencia_renal', 'salud_8'],
        9 => ['vih_sida', 'salud_9'],
        10 => ['tabaquismo_drogadiccion', 'salud_10'],
        11 => ['hipertension', 'salud_11'],
        12 => ['enfermedades_congenitas', 'salud_12'],
        13 => ['enfermedades_hematologicas', 'salud_13'],
        14 => ['enfermedades_colageno', 'salud_14'],
      ];

      foreach ($salud_map as $num => $keys) {
        $found_val = NULL;
        foreach ($keys as $key) {
          if (isset($raw_data[$key])) {
            $found_val = $raw_data[$key];
            break;
          }
        }
        $normalized_status = $normalize_yes_no($found_val);
        $data['salud_' . $num] = $normalized_status;
        foreach ($keys as $key) {
          $data[$key] = $normalized_status;
        }
      }
      // Explicación de salud en caso de haber marcado condiciones médicas.
      $explicacion = $raw_data['explicacion_salud']
        ?? $raw_data['explicacion_condiciones_salud']
        ?? $raw_data['favor_explicar_detalladamente']
        ?? $raw_data['explicar_detalladamente']
        ?? $raw_data['explicacion']
        ?? $raw_data['explicacion_enfermedad']
        ?? $raw_data['observaciones_salud']
        ?? $raw_data['observaciones']
        ?? $raw_data['detalle_salud']
        ?? $raw_data['descripcion_salud']
        ?? $raw_data['aclaracion_salud']
        ?? $raw_data['enfermedad_explicacion']
        ?? '';

      if (empty($explicacion)) {
        foreach ($raw_data as $k => $v) {
          if (is_string($v) && !empty(trim($v))) {
            $k_lower = mb_strtolower((string) $k);
            if (strpos($k_lower, 'explica') !== FALSE || strpos($k_lower, 'detall') !== FALSE || strpos($k_lower, 'observaci') !== FALSE || (strpos($k_lower, 'salud') !== FALSE && strlen($v) > 3 && !in_array(mb_strtolower(trim($v)), ['si', 'no', '0', '1', 'true', 'false']))) {
              $explicacion = trim($v);
              break;
            }
          }
        }
      }
      $data['explicacion_salud'] = $explicacion;

      // F. Fechas y Firmas.
      $created_time = $webform_submission->getCreatedTime() ?: time();
      $meses = [
        '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
        '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
        '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre',
      ];
      $mes_num = date('m', $created_time);
      $data['dia_firma'] = $raw_data['dia_firma'] ?? date('d', $created_time);
      $data['mes_firma'] = $raw_data['mes_firma'] ?? ($meses[$mes_num] ?? date('F', $created_time));
      $data['anio_firma'] = $raw_data['anio_firma'] ?? date('Y', $created_time);
      $data['ciudad_firma'] = $raw_data['ciudad_firma'] ?? $data['ciudad'];
      $data['fecha_solicitud'] = date('d/m/Y', $created_time);

      // 3. Renderizar la plantilla Twig.
      $logo_uri = NULL;
      $module_path = \Drupal::service('extension.list.module')->getPath('prodepem_solicitudes_rtm');
      $equidad_logo_jpg = \Drupal::root() . '/' . $module_path . '/images/la_equidad_logo.jpg';
      $equidad_logo_png = \Drupal::root() . '/' . $module_path . '/images/la_equidad_logo.png';
      if (file_exists($equidad_logo_jpg)) {
        $logo_data = base64_encode(file_get_contents($equidad_logo_jpg));
        $logo_uri = 'data:image/jpeg;base64,' . $logo_data;
      }
      elseif (file_exists($equidad_logo_png)) {
        $logo_data = base64_encode(file_get_contents($equidad_logo_png));
        $logo_uri = 'data:image/png;base64,' . $logo_data;
      }
      else {
        $prodepem_logo = \Drupal::root() . '/sites/default/files/2026-01/prodepem.jpg';
        if (file_exists($prodepem_logo)) {
          $logo_data = base64_encode(file_get_contents($prodepem_logo));
          $logo_uri = 'data:image/jpeg;base64,' . $logo_data;
        }
      }

      // Franja vertical Vigilado Superintendencia Financiera.
      $vigilado_uri = NULL;
      $vigilado_jpg = \Drupal::root() . '/' . $module_path . '/images/vigilado_superfinanciera.jpg';
      $vigilado_png = \Drupal::root() . '/' . $module_path . '/images/vigilado_superfinanciera.png';
      if (file_exists($vigilado_jpg)) {
        $vigilado_data = base64_encode(file_get_contents($vigilado_jpg));
        $vigilado_uri = 'data:image/jpeg;base64,' . $vigilado_data;
      }
      elseif (file_exists($vigilado_png)) {
        $vigilado_data = base64_encode(file_get_contents($vigilado_png));
        $vigilado_uri = 'data:image/png;base64,' . $vigilado_data;
      }

      $serial = $webform_submission->serial->value ?? $webform_submission->id();

      $render_array = [
        '#theme' => 'prodepem_solicitud_seguro_vida_pdf_template',
        '#data' => $data,
        '#submission' => $webform_submission,
        '#date' => date('d/m/Y'),
        '#logo_path' => $logo_uri,
        '#vigilado_path' => $vigilado_uri,
        '#serial' => $serial,
      ];

      $html = \Drupal::service('renderer')->renderPlain($render_array);

      // 4. Configurar Dompdf.
      $options = new Options();
      $options->set('isHtml5ParserEnabled', true);
      $options->set('isRemoteEnabled', true);
      $dompdf = new Dompdf($options);

      // 5. Cargar HTML y generar PDF en tamaño Carta (Letter).
      $dompdf->loadHtml($html);
      $dompdf->setPaper('letter', 'portrait');
      $dompdf->render();

      // 6. Preparar respuesta de descarga.
      $output = $dompdf->output();
      $response = new Response($output);
      $disposition = $response->headers->makeDisposition(
        ResponseHeaderBag::DISPOSITION_ATTACHMENT,
        'solicitud_seguro_vida_' . $serial . '.pdf'
      );
      $response->headers->set('Content-Type', 'application/pdf');
      $response->headers->set('Content-Disposition', $disposition);

      return $response;

    } catch (\Exception $e) {
      \Drupal::logger('prodepem_solicitudes_rtm')->error('Error en generarPdfSeguroVida: ' . $e->getMessage());
      return [
        '#markup' => $this->t('Error al generar el PDF de Seguro de Vida: @message', ['@message' => $e->getMessage()]),
      ];
    }
  }

}
