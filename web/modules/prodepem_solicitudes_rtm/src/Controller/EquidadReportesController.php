<?php

namespace Drupal\prodepem_solicitudes_rtm\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\taxonomy\Entity\Term;
use Drupal\webform\Entity\WebformSubmission;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Controller for Reportes de Equidad Seguros (ASPU & FONEDUCOR).
 */
class EquidadReportesController extends ControllerBase {

  /**
   * Vista general / Landing de la sección Reportes de Equidad Seguros.
   */
  public function index() {
    $stats = $this->obtenerEstadisticas();

    return [
      '#theme' => 'prodepem_equidad_reportes_index',
      '#stats' => $stats,
      '#attached' => [
        'library' => [
          'prodepem_solicitudes_rtm/reportes_equidad',
        ],
      ],
      '#title' => $this->t('Reportes de Equidad Seguros'),
    ];
  }

  /**
   * Reporte 1: ASPU - Solicitudes de Seguro de Vida.
   */
  public function reporteAspu(Request $request) {
    return $this->generarVistaReporte('aspu', 'vida', $request);
  }

  /**
   * Reporte 2: FONEDUCOR - Solicitudes de Seguro de Vida.
   */
  public function reporteFoneducor(Request $request) {
    return $this->generarVistaReporte('foneducor', 'vida', $request);
  }

  /**
   * Reporte 3: ASPU - Datos Adicionales (Seguros adicionales y Rifa).
   */
  public function reporteAspuAdicionales(Request $request) {
    return $this->generarVistaReporte('aspu', 'adicionales', $request);
  }

  /**
   * Reporte 4: FONEDUCOR - Datos Adicionales (Seguros adicionales y Rifa).
   */
  public function reporteFoneducorAdicionales(Request $request) {
    return $this->generarVistaReporte('foneducor', 'adicionales', $request);
  }

  /**
   * Genera el render array para cualquiera de los 4 reportes con filtros y buscador.
   */
  protected function generarVistaReporte($tomador, $tipo, Request $request) {
    $search = trim((string) $request->query->get('search', ''));
    $fecha_desde = trim((string) $request->query->get('fecha_desde', ''));
    $fecha_hasta = trim((string) $request->query->get('fecha_hasta', ''));

    $items = $this->obtenerRegistrosFiltrados($tomador, $tipo, $search, $fecha_desde, $fecha_hasta);

    $titles = [
      'aspu_vida' => [
        'title' => 'Reporte ASPU - Seguro de Vida',
        'desc' => 'Solicitudes de declaración de asegurabilidad del tomador ASPU (Asociación Sindical de Profesores Universitarios).',
        'badge' => 'ASPU (Tomador)',
      ],
      'foneducor_vida' => [
        'title' => 'Reporte FONEDUCOR - Seguro de Vida',
        'desc' => 'Solicitudes de declaración de asegurabilidad del tomador FONEDUCOR (Fondo de Empleados Docentes Universidad de Córdoba).',
        'badge' => 'FONEDUCOR (Tomador)',
      ],
      'aspu_adicionales' => [
        'title' => 'Reporte ASPU - Seguros Adicionales y Rifa',
        'desc' => 'Datos complementarios comerciales fuera del formato original: interés en otros productos de seguro y participación en rifa (ASPU).',
        'badge' => 'ASPU (Adicionales & Rifa)',
      ],
      'foneducor_adicionales' => [
        'title' => 'Reporte FONEDUCOR - Seguros Adicionales y Rifa',
        'desc' => 'Datos complementarios comerciales fuera del formato original: interés en otros productos de seguro y participación en rifa (FONEDUCOR).',
        'badge' => 'FONEDUCOR (Adicionales & Rifa)',
      ],
    ];

    $cfg_key = $tomador . '_' . $tipo;
    $info = $titles[$cfg_key] ?? [
      'title' => 'Reporte Equidad Seguros',
      'desc' => 'Listado de solicitudes',
      'badge' => strtoupper($tomador),
    ];

    // URL para descarga XLS conservando los filtros activos.
    $export_key = ($tipo === 'adicionales') ? ($tomador . '-adicionales') : $tomador;
    $export_params = array_filter([
      'search' => $search,
      'fecha_desde' => $fecha_desde,
      'fecha_hasta' => $fecha_hasta,
    ]);
    $export_url = Url::fromRoute('prodepem_solicitudes_rtm.reportes_equidad_exportar', ['reporte' => $export_key], ['query' => $export_params])->toString();

    return [
      '#theme' => 'prodepem_equidad_reporte',
      '#reporte_key' => $export_key,
      '#reporte_title' => $info['title'],
      '#reporte_desc' => $info['desc'],
      '#tomador_badge' => $info['badge'],
      '#tipo_reporte' => $tipo,
      '#items' => $items,
      '#total' => count($items),
      '#filters' => [
        'search' => $search,
        'fecha_desde' => $fecha_desde,
        'fecha_hasta' => $fecha_hasta,
      ],
      '#export_url' => $export_url,
      '#attached' => [
        'library' => [
          'prodepem_solicitudes_rtm/reportes_equidad',
        ],
      ],
      '#title' => $this->t($info['title']),
    ];
  }

  /**
   * Vista completa del detalle de una sumisión.
   */
  public function verDetalleSubmission($sid) {
    $submission = WebformSubmission::load($sid);
    if (!$submission || $submission->getWebform()->id() !== 'formulario_equidad_seguros') {
      return [
        '#markup' => $this->t('No se encontró la sumisión @sid del formulario de Equidad Seguros.', ['@sid' => $sid]),
      ];
    }

    $raw = $submission->getData();
    $detalles = $this->estructurarDetalleCompleto($submission, $raw);

    $pdf_url = Url::fromRoute('prodepem_solicitudes_rtm.generar_pdf_seguro_vida', ['sid' => $sid])->toString();

    // Determinar URL de retorno según el tomador.
    $selector = (string) ($raw['selector_tomador'] ?? '');
    $back_route = ($selector === '2') ? 'prodepem_solicitudes_rtm.reporte_aspu' : 'prodepem_solicitudes_rtm.reporte_foneducor';
    $back_url = Url::fromRoute($back_route)->toString();

    return [
      '#theme' => 'prodepem_equidad_submission_detalle',
      '#sid' => $sid,
      '#serial' => $submission->serial->value ?? $sid,
      '#created' => date('d/m/Y H:i', $submission->getCreatedTime()),
      '#tomador_data' => $detalles['tomador'],
      '#asegurado_data' => $detalles['asegurado'],
      '#adicionales_data' => $detalles['adicionales'],
      '#beneficiarios' => $detalles['beneficiarios'],
      '#salud_data' => $detalles['salud'],
      '#pdf_url' => $pdf_url,
      '#back_url' => $back_url,
      '#raw_keys' => array_keys($raw),
      '#attached' => [
        'library' => [
          'prodepem_solicitudes_rtm/reportes_equidad',
        ],
      ],
      '#title' => $this->t('Detalle de Solicitud #@serial (SID: @sid)', [
        '@serial' => $submission->serial->value ?? $sid,
        '@sid' => $sid,
      ]),
    ];
  }

  /**
   * Exportación de registros en formato XLS (Excel XML Spreadsheet).
   */
  public function exportarXls($reporte, Request $request) {
    $tomador = (strpos($reporte, 'aspu') !== FALSE) ? 'aspu' : 'foneducor';
    $tipo = (strpos($reporte, 'adicionales') !== FALSE) ? 'adicionales' : 'vida';

    $search = trim((string) $request->query->get('search', ''));
    $fecha_desde = trim((string) $request->query->get('fecha_desde', ''));
    $fecha_hasta = trim((string) $request->query->get('fecha_hasta', ''));

    $items = $this->obtenerRegistrosFiltrados($tomador, $tipo, $search, $fecha_desde, $fecha_hasta);

    $nombre_tomador = ($tomador === 'aspu') ? 'ASPU' : 'FONEDUCOR';
    $tipo_nombre = ($tipo === 'adicionales') ? 'Seguros_Adicionales_y_Rifa' : 'Seguro_Vida';
    $filename = 'reporte_' . strtolower($nombre_tomador) . '_' . $tipo_nombre . '_' . date('Ymd_His') . '.xls';

    $xml = $this->construirXmlExcel($items, $tomador, $tipo);

    $response = new Response($xml);
    $response->headers->set('Content-Type', 'application/vnd.ms-excel; charset=UTF-8');
    $disposition = $response->headers->makeDisposition(
      ResponseHeaderBag::DISPOSITION_ATTACHMENT,
      $filename
    );
    $response->headers->set('Content-Disposition', $disposition);
    $response->headers->set('Pragma', 'no-cache');
    $response->headers->set('Expires', '0');

    return $response;
  }

  /**
   * Obtiene y normaliza los registros aplicando filtros.
   */
  protected function obtenerRegistrosFiltrados($tomador, $tipo, $search = '', $fecha_desde = '', $fecha_hasta = '') {
    $storage = \Drupal::entityTypeManager()->getStorage('webform_submission');
    $query = $storage->getQuery()
      ->condition('webform_id', 'formulario_equidad_seguros')
      ->accessCheck(FALSE)
      ->sort('created', 'DESC');

    if (!empty($fecha_desde)) {
      $ts_desde = strtotime($fecha_desde . ' 00:00:00');
      if ($ts_desde) {
        $query->condition('created', $ts_desde, '>=');
      }
    }
    if (!empty($fecha_hasta)) {
      $ts_hasta = strtotime($fecha_hasta . ' 23:59:59');
      if ($ts_hasta) {
        $query->condition('created', $ts_hasta, '<=');
      }
    }

    $sids = $query->execute();
    if (empty($sids)) {
      return [];
    }

    $submissions = $storage->loadMultiple($sids);
    $resultados = [];

    foreach ($submissions as $sub) {
      $raw = $sub->getData();

      // Validación del tomador:
      // ASPU: selector_tomador == 2 o texto 'aspu'
      // FONEDUCOR: selector_tomador == 1 o texto 'cordoba'/'docente'/'foneducor'
      $selector = (string) ($raw['selector_tomador'] ?? '');
      $tomador_text = mb_strtolower((string) ($raw['tomador'] ?? ''));

      $is_aspu = ($selector === '2' || strpos($tomador_text, 'aspu') !== FALSE || strpos($tomador_text, 'sindical') !== FALSE);
      $is_foneducor = ($selector === '1' || strpos($tomador_text, 'fondo') !== FALSE || strpos($tomador_text, 'cordoba') !== FALSE || strpos($tomador_text, 'docente') !== FALSE || strpos($tomador_text, 'foneducor') !== FALSE);

      if ($tomador === 'aspu' && !$is_aspu) {
        continue;
      }
      if ($tomador === 'foneducor' && !$is_foneducor) {
        continue;
      }

      $item = $this->extraerDatosRegistro($sub, $raw);

      // Filtro de búsqueda por texto:
      if (!empty($search)) {
        $search_lower = mb_strtolower($search);
        $corpus = mb_strtolower(implode(' ', [
          $item['sid'],
          $item['serial'],
          $item['nombre'],
          $item['documento'],
          $item['tipo_documento'],
          $item['email'],
          $item['telefono'],
          $item['ciudad'],
          $item['ocupacion'],
          $item['cargo'],
          $item['seguros_adicionales_texto'],
          $item['rifa_texto'],
        ]));

        if (strpos($corpus, $search_lower) === FALSE) {
          continue;
        }
      }

      $resultados[] = $item;
    }

    return $resultados;
  }

  /**
   * Extrae y normaliza los datos de un registro de sumisión.
   */
  protected function extraerDatosRegistro(WebformSubmission $sub, array $raw) {
    $sid = $sub->id();
    $serial = $sub->serial->value ?? $sid;
    $fecha = date('d/m/Y H:i', $sub->getCreatedTime());

    // Nombre del Asegurado:
    $nombre = $raw['nombre_completo']
      ?? $raw['asegurado_principal']
      ?? $raw['nombre_del_asegurado']
      ?? $raw['nombre_asegurado']
      ?? $raw['asegurado']
      ?? $raw['nombres_y_apellidos']
      ?? $raw['nombre_y_apellidos']
      ?? '';
    if (empty($nombre)) {
      $nombres = $raw['nombres'] ?? $raw['nombre'] ?? trim(($raw['primer_nombre'] ?? '') . ' ' . ($raw['segundo_nombre'] ?? ''));
      $apellidos = $raw['apellidos'] ?? $raw['primer_apellido'] ?? trim(($raw['primer_apellido'] ?? '') . ' ' . ($raw['segundo_apellido'] ?? ''));
      $nombre = trim($nombres . ' ' . $apellidos);
    }
    if (empty($nombre)) {
      foreach ($raw as $k => $v) {
        if (is_string($v) && !empty(trim($v)) && !in_array($k, ['tomador', 'empresa', 'direccion_empresa', 'email', 'correo_electronico']) && stripos($k, 'beneficiar') === FALSE && stripos($k, 'tomador') === FALSE) {
          if (stripos($k, 'nombre') !== FALSE || stripos($k, 'asegurad') !== FALSE) {
            $nombre = trim($v);
            break;
          }
        }
      }
    }
    if (empty($nombre) && $sub->getOwnerId()) {
      $nombre = $sub->getOwner()->getDisplayName();
    }

    // Documento:
    $num_doc = $raw['numero_de_documento']
      ?? $raw['numero_documento']
      ?? $raw['documento']
      ?? $raw['numero_de_identificacion']
      ?? $raw['numero_identificacion']
      ?? $raw['identificacion']
      ?? $raw['cedula']
      ?? '';
    if (empty($num_doc)) {
      foreach ($raw as $k => $v) {
        if ((is_string($v) || is_numeric($v)) && !empty($v) && stripos($k, 'beneficiar') === FALSE && stripos($k, 'tomador') === FALSE && stripos($k, 'nit') === FALSE) {
          if (stripos($k, 'documento') !== FALSE || stripos($k, 'cedula') !== FALSE || stripos($k, 'identifica') !== FALSE) {
            $num_doc = (string) $v;
            break;
          }
        }
      }
    }

    $tipo_doc = $raw['tipo_de_documento'] ?? $raw['tipo_documento'] ?? 'C.C.';
    if (is_numeric($tipo_doc)) {
      $term = Term::load((int) $tipo_doc);
      if ($term) {
        $tipo_doc = $term->label();
      }
    }

    // Contacto y Laborales:
    $email = $raw['email'] ?? $raw['correo_electronico'] ?? '';
    $telefono = $raw['telefono'] ?? $raw['celular'] ?? $raw['telefono_contacto'] ?? '';
    $ciudad = $raw['ciudad'] ?? $raw['ciudad_residencia'] ?? '';
    if (is_numeric($ciudad)) {
      $t_ciudad = Term::load((int) $ciudad);
      if ($t_ciudad) {
        $ciudad = $t_ciudad->label();
      }
    }
    $ocupacion = $raw['ocupacion'] ?? '';
    if (is_numeric($ocupacion)) {
      $t_ocu = Term::load((int) $ocupacion);
      if ($t_ocu) {
        $ocupacion = $t_ocu->label();
      }
    }
    $cargo = $raw['cargo'] ?? '';
    $valor_asegurado = $raw['valor_asegurado_solicitado'] ?? $raw['valor_asegurado'] ?? '';
    $total_asegurado = $raw['total_valor_asegurado'] ?? $valor_asegurado;

    // Beneficiarios resumen:
    $beneficiarios_count = 0;
    $beneficiarios_nombres = [];
    $raw_bene = $raw['beneficiarios'] ?? $raw['tabla_beneficiarios'] ?? [];
    if (is_array($raw_bene)) {
      foreach ($raw_bene as $b) {
        if (is_array($b) && (!empty($b['nombre']) || !empty($b['nombre_y_apellido']) || !empty($b['numero_de_documento']))) {
          $beneficiarios_count++;
          $b_name = $b['nombre_y_apellido'] ?? $b['nombre'] ?? '';
          if (!empty($b_name)) {
            $beneficiarios_nombres[] = $b_name;
          }
        }
      }
    }

    // Salud resumen:
    $salud_positivas = 0;
    for ($i = 1; $i <= 14; $i++) {
      $val = $raw['salud_' . $i] ?? '';
      if (in_array(mb_strtolower(trim((string) $val)), ['si', '1', 'true', 'sí'])) {
        $salud_positivas++;
      }
    }

    // SEGUROS ADICIONALES (fuera del formulario PDF):
    $seguros_adicionales = [];
    $interes_otros = '';
    foreach ($raw as $k => $v) {
      $k_lower = mb_strtolower($k);
      if (strpos($k_lower, 'interes') !== FALSE || strpos($k_lower, 'adicional') !== FALSE) {
        if (is_array($v)) {
          foreach ($v as $sub_k => $sub_v) {
            if ($sub_v && $sub_v !== '0') {
              $seguros_adicionales[] = is_string($sub_k) ? $sub_k : $sub_v;
            }
          }
        }
        elseif (is_string($v) && !empty($v)) {
          if (in_array(mb_strtolower(trim($v)), ['si', '1', 'sí'])) {
            $interes_otros = 'SÍ';
          }
          elseif (in_array(mb_strtolower(trim($v)), ['no', '0'])) {
            $interes_otros = 'NO';
          }
          else {
            $seguros_adicionales[] = $v;
          }
        }
      }
    }
    if (empty($interes_otros)) {
      $interes_otros = !empty($seguros_adicionales) ? 'SÍ' : 'NO';
    }
    $seguros_adicionales_texto = implode(', ', array_unique($seguros_adicionales));

    // RIFA / SORTEO (fuera del formulario PDF):
    $rifa_val = '';
    $participa_rifa = '';
    foreach ($raw as $k => $v) {
      $k_lower = mb_strtolower($k);
      if (strpos($k_lower, 'rifa') !== FALSE || strpos($k_lower, 'sorteo') !== FALSE || strpos($k_lower, 'boleta') !== FALSE) {
        if (is_string($v) || is_numeric($v)) {
          $val_str = trim((string) $v);
          if (in_array(mb_strtolower($val_str), ['si', '1', 'sí'])) {
            $participa_rifa = 'SÍ';
          }
          elseif (in_array(mb_strtolower($val_str), ['no', '0'])) {
            $participa_rifa = 'NO';
          }
          else {
            $rifa_val = $val_str;
          }
        }
      }
    }
    if (empty($participa_rifa)) {
      $participa_rifa = !empty($rifa_val) ? 'SÍ' : 'NO';
    }
    $rifa_texto = !empty($rifa_val) ? ('Boleta/N°: ' . $rifa_val) : ($participa_rifa === 'SÍ' ? 'Participa' : 'No participa');

    // Otros campos fuera del formulario original:
    $otros_adicionales = [];
    $keys_originales = [
      'tomador', 'nit_tomador', 'c_c_nit', 'direccion_tomador', 'direccion', 'ciudad_tomador', 'ciudad', 'telefono_tomador', 'telefono',
      'selector_tomador', 'nombre_completo', 'nombre', 'nombres', 'apellidos', 'primer_apellido', 'segundo_apellido',
      'tipo_de_documento', 'tipo_documento', 'numero_de_documento', 'numero_documento', 'documento',
      'email', 'correo_electronico', 'trabaja_actualmente', 'trabaja_usted_actualmente',
      'ocupacion', 'cargo', 'fecha_de_nacimiento', 'fecha_nacimiento', 'estado_civil',
      'valor_asegurado_solicitado', 'total_valor_asegurado', 'beneficiarios', 'tabla_beneficiarios',
      'peso', 'estatura', 'explicacion_salud', 'explicacion_condiciones_salud',
    ];
    for ($i = 1; $i <= 14; $i++) {
      $keys_originales[] = 'salud_' . $i;
    }

    foreach ($raw as $k => $v) {
      if (!in_array($k, $keys_originales) && strpos($k, 'salud') === FALSE && strpos($k, 'interes') === FALSE && strpos($k, 'rifa') === FALSE && strpos($k, 'sorteo') === FALSE && strpos($k, 'boleta') === FALSE) {
        if (is_string($v) && !empty(trim($v))) {
          $otros_adicionales[$k] = $v;
        }
      }
    }

    $url_detalle = Url::fromRoute('prodepem_solicitudes_rtm.reportes_equidad_detalle', ['sid' => $sid])->toString();
    $url_pdf = Url::fromRoute('prodepem_solicitudes_rtm.generar_pdf_seguro_vida', ['sid' => $sid])->toString();
    $url_webform = '/admin/structure/webform/manage/formulario_equidad_seguros/submission/' . $sid;

    return [
      'sid' => $sid,
      'serial' => $serial,
      'fecha' => $fecha,
      'nombre' => $nombre,
      'tipo_documento' => $tipo_doc,
      'documento' => $num_doc,
      'email' => $email,
      'telefono' => $telefono,
      'ciudad' => $ciudad,
      'ocupacion' => $ocupacion,
      'cargo' => $cargo,
      'valor_asegurado' => $valor_asegurado,
      'total_asegurado' => $total_asegurado,
      'beneficiarios_count' => $beneficiarios_count,
      'beneficiarios_nombres' => implode(', ', $beneficiarios_nombres),
      'salud_positivas' => $salud_positivas,
      // Datos adicionales:
      'interes_otros' => $interes_otros,
      'seguros_adicionales_texto' => $seguros_adicionales_texto,
      'participa_rifa' => $participa_rifa,
      'rifa_texto' => $rifa_texto,
      'otros_adicionales' => $otros_adicionales,
      // Acciones:
      'url_detalle' => $url_detalle,
      'url_pdf' => $url_pdf,
      'url_webform' => $url_webform,
    ];
  }

  /**
   * Estructura completa de la sumisión para la vista detallada.
   */
  protected function estructurarDetalleCompleto(WebformSubmission $sub, array $raw) {
    $item = $this->extraerDatosRegistro($sub, $raw);

    // Tomador
    $selector = (string) ($raw['selector_tomador'] ?? '');
    $tomador_nombre = ($selector === '2') ? 'Asociación Sindical de Profesores Universitarios - ASPU' : 'Fondo de empleado de docente de la Universidad de Córdoba';
    $tomador_nit = ($selector === '2') ? '830001998' : '900834726';
    $tomador_dir = ($selector === '2') ? 'Cra 6 # 77-305' : 'Cra 6 # 76-103';
    $tomador_ciudad = 'Montería';
    $tomador_tel = ($selector === '2') ? '3242560489' : '3014619585';

    // Beneficiarios completos
    $bene_list = [];
    $raw_bene = $raw['beneficiarios'] ?? $raw['tabla_beneficiarios'] ?? [];
    if (is_array($raw_bene)) {
      foreach ($raw_bene as $b) {
        if (is_array($b)) {
          $bene_list[] = [
            'nombre' => $b['nombre_y_apellido'] ?? $b['nombre'] ?? 'N/A',
            'tipo_doc' => $b['tipo_de_documento'] ?? $b['tipo_documento'] ?? 'C.C.',
            'num_doc' => $b['numero_de_documento'] ?? $b['numero_documento'] ?? 'N/A',
            'parentesco' => $b['parentesco'] ?? 'N/A',
            'edad' => $b['edad'] ?? 'N/A',
            'porcentaje' => $b['porcentaje'] ?? 'N/A',
          ];
        }
      }
    }

    // Salud completa (14 preguntas)
    $salud_preguntas = [
      1 => 'Afecciones cardiovasculares',
      2 => 'Afecciones cerebrovasculares',
      3 => 'Alcoholismo',
      4 => 'Cirugía / Hospitalización previa',
      5 => 'Diabetes',
      6 => 'Enfermedad oncológica (cáncer)',
      7 => 'EPOC / Enfermedad pulmonar obstructiva',
      8 => 'Insuficiencia renal',
      9 => 'Leucemia',
      10 => 'Lupus eritematoso',
      11 => 'Trastornos neurológicos / Psiquiátricos',
      12 => 'VIH / SIDA',
      13 => 'Adicción a drogas / Sustancias psicoactivas',
      14 => 'Enfermedades del colágeno / Autoinmunes',
    ];
    $salud_respuestas = [];
    foreach ($salud_preguntas as $num => $preg) {
      $val = $raw['salud_' . $num] ?? 'no';
      $salud_respuestas[] = [
        'num' => $num,
        'pregunta' => $preg,
        'respuesta' => in_array(mb_strtolower(trim((string) $val)), ['si', '1', 'true', 'sí']) ? 'SÍ' : 'NO',
      ];
    }

    return [
      'tomador' => [
        'nombre' => $raw['tomador'] ?? $tomador_nombre,
        'nit' => $raw['nit_tomador'] ?? $tomador_nit,
        'direccion' => $raw['direccion_tomador'] ?? $tomador_dir,
        'ciudad' => $raw['ciudad_tomador'] ?? $tomador_ciudad,
        'telefono' => $raw['telefono_tomador'] ?? $tomador_tel,
      ],
      'asegurado' => [
        'nombre' => $item['nombre'],
        'tipo_documento' => $item['tipo_documento'],
        'documento' => $item['documento'],
        'email' => $item['email'],
        'telefono' => $item['telefono'],
        'ciudad' => $item['ciudad'],
        'ocupacion' => $item['ocupacion'],
        'cargo' => $item['cargo'],
        'trabaja_actualmente' => $raw['trabaja_actualmente'] ?? 'Si',
        'fecha_nacimiento' => $raw['fecha_de_nacimiento'] ?? $raw['fecha_nacimiento'] ?? '',
        'estado_civil' => $raw['estado_civil'] ?? '',
        'valor_solicitado' => $item['valor_asegurado'],
        'total_asegurado' => $item['total_asegurado'],
      ],
      'adicionales' => [
        'interes_otros' => $item['interes_otros'],
        'seguros_adicionales' => $item['seguros_adicionales_texto'],
        'participa_rifa' => $item['participa_rifa'],
        'rifa_detalle' => $item['rifa_texto'],
        'otros_campos' => $item['otros_adicionales'],
      ],
      'beneficiarios' => $bene_list,
      'salud' => [
        'peso' => $raw['peso'] ?? '',
        'estatura' => $raw['estatura'] ?? '',
        'respuestas' => $salud_respuestas,
        'explicacion' => $raw['explicacion_salud'] ?? $raw['favor_explicar_detalladamente'] ?? 'Ninguna condición declarada.',
      ],
    ];
  }

  /**
   * Construye el documento Excel XML Spreadsheet para descarga.
   */
  protected function construirXmlExcel(array $items, $tomador, $tipo) {
    $nombre_tomador = ($tomador === 'aspu') ? 'ASPU' : 'FONEDUCOR';
    $titulo_hoja = ($tipo === 'adicionales') ? ('Datos Adicionales ' . $nombre_tomador) : ('Seguro Vida ' . $nombre_tomador);

    ob_start();
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">' . "\n";
    echo '<head>' . "\n";
    echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />' . "\n";
    echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>' . htmlspecialchars($titulo_hoja) . '</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->' . "\n";
    echo '<style>' . "\n";
    echo '  .header-title { font-size: 14pt; font-weight: bold; background-color: #1b5e20; color: #ffffff; text-align: center; }' . "\n";
    echo '  .header-meta { font-size: 10pt; color: #555555; background-color: #f1f8e9; }' . "\n";
    echo '  th { background-color: #2e7d32; color: #ffffff; font-weight: bold; padding: 6px; border: 0.5pt solid #cccccc; text-align: center; font-size: 9.5pt; }' . "\n";
    echo '  td { padding: 5px; border: 0.5pt solid #cccccc; font-size: 9pt; mso-number-format:"\\@"; }' . "\n";
    echo '  .text-center { text-align: center; }' . "\n";
    echo '  .text-right { text-align: right; }' . "\n";
    echo '</style>' . "\n";
    echo '</head>' . "\n";
    echo '<body>' . "\n";

    echo '<table border="1">' . "\n";
    echo '<tr><td colspan="' . ($tipo === 'adicionales' ? '9' : '10') . '" class="header-title">REPORTE DE SOLICITUDES DE EQUIDAD SEGUROS - ' . htmlspecialchars($nombre_tomador) . ' (' . strtoupper($tipo) . ')</td></tr>' . "\n";
    echo '<tr><td colspan="' . ($tipo === 'adicionales' ? '9' : '10') . '" class="header-meta">Generado el: ' . date('d/m/Y H:i:s') . ' | Total Registros: ' . count($items) . '</td></tr>' . "\n";
    echo '<tr></tr>' . "\n";

    if ($tipo === 'vida') {
      echo '<tr>' . "\n";
      echo '  <th>SID</th>' . "\n";
      echo '  <th>FECHA</th>' . "\n";
      echo '  <th>ASEGURADO PRINCIPAL</th>' . "\n";
      echo '  <th>TIPO DOC</th>' . "\n";
      echo '  <th>N° DOCUMENTO</th>' . "\n";
      echo '  <th>CORREO ELECTRÓNICO</th>' . "\n";
      echo '  <th>TELÉFONO</th>' . "\n";
      echo '  <th>OCUPACIÓN / CARGO</th>' . "\n";
      echo '  <th>VALOR SOLICITADO</th>' . "\n";
      echo '  <th>BENEFICIARIOS</th>' . "\n";
      echo '</tr>' . "\n";

      foreach ($items as $it) {
        echo '<tr>' . "\n";
        echo '  <td class="text-center">' . htmlspecialchars((string) $it['sid']) . '</td>' . "\n";
        echo '  <td class="text-center">' . htmlspecialchars((string) $it['fecha']) . '</td>' . "\n";
        echo '  <td>' . htmlspecialchars((string) $it['nombre']) . '</td>' . "\n";
        echo '  <td class="text-center">' . htmlspecialchars((string) $it['tipo_documento']) . '</td>' . "\n";
        echo '  <td class="text-center">' . htmlspecialchars((string) $it['documento']) . '</td>' . "\n";
        echo '  <td>' . htmlspecialchars((string) $it['email']) . '</td>' . "\n";
        echo '  <td class="text-center">' . htmlspecialchars((string) $it['telefono']) . '</td>' . "\n";
        echo '  <td>' . htmlspecialchars(trim($it['ocupacion'] . ' - ' . $it['cargo'], ' -')) . '</td>' . "\n";
        echo '  <td class="text-right">' . htmlspecialchars((string) $it['valor_asegurado']) . '</td>' . "\n";
        echo '  <td>' . htmlspecialchars((string) ($it['beneficiarios_nombres'] ?: 'Sin beneficiarios')) . '</td>' . "\n";
        echo '</tr>' . "\n";
      }
    }
    else {
      // Tipo adicionales & rifa
      echo '<tr>' . "\n";
      echo '  <th>SID</th>' . "\n";
      echo '  <th>FECHA</th>' . "\n";
      echo '  <th>SOLICITANTE</th>' . "\n";
      echo '  <th>TIPO DOC</th>' . "\n";
      echo '  <th>N° DOCUMENTO</th>' . "\n";
      echo '  <th>TELÉFONO</th>' . "\n";
      echo '  <th>CORREO</th>' . "\n";
      echo '  <th>SEGUROS ADICIONALES DE INTERÉS</th>' . "\n";
      echo '  <th>PARTICIPA EN RIFA / BOLETA</th>' . "\n";
      echo '</tr>' . "\n";

      foreach ($items as $it) {
        echo '<tr>' . "\n";
        echo '  <td class="text-center">' . htmlspecialchars((string) $it['sid']) . '</td>' . "\n";
        echo '  <td class="text-center">' . htmlspecialchars((string) $it['fecha']) . '</td>' . "\n";
        echo '  <td>' . htmlspecialchars((string) $it['nombre']) . '</td>' . "\n";
        echo '  <td class="text-center">' . htmlspecialchars((string) $it['tipo_documento']) . '</td>' . "\n";
        echo '  <td class="text-center">' . htmlspecialchars((string) $it['documento']) . '</td>' . "\n";
        echo '  <td class="text-center">' . htmlspecialchars((string) $it['telefono']) . '</td>' . "\n";
        echo '  <td>' . htmlspecialchars((string) $it['email']) . '</td>' . "\n";
        echo '  <td>' . htmlspecialchars((string) ($it['seguros_adicionales_texto'] ?: 'Ninguno declarado')) . '</td>' . "\n";
        echo '  <td class="text-center">' . htmlspecialchars((string) $it['rifa_texto']) . '</td>' . "\n";
        echo '</tr>' . "\n";
      }
    }

    echo '</table>' . "\n";
    echo '</body>' . "\n";
    echo '</html>' . "\n";

    return ob_get_clean();
  }

  /**
   * Resumen numérico para el landing de reportes.
   */
  protected function obtenerEstadisticas() {
    $storage = \Drupal::entityTypeManager()->getStorage('webform_submission');
    $query = $storage->getQuery()
      ->condition('webform_id', 'formulario_equidad_seguros')
      ->accessCheck(FALSE);
    $sids = $query->execute();

    $aspu_count = 0;
    $foneducor_count = 0;
    $total = count($sids);

    if (!empty($sids)) {
      $subs = $storage->loadMultiple($sids);
      foreach ($subs as $sub) {
        $raw = $sub->getData();
        $selector = (string) ($raw['selector_tomador'] ?? '');
        $tomador_text = mb_strtolower((string) ($raw['tomador'] ?? ''));

        if ($selector === '2' || strpos($tomador_text, 'aspu') !== FALSE || strpos($tomador_text, 'sindical') !== FALSE) {
          $aspu_count++;
        }
        elseif ($selector === '1' || strpos($tomador_text, 'fondo') !== FALSE || strpos($tomador_text, 'cordoba') !== FALSE || strpos($tomador_text, 'foneducor') !== FALSE) {
          $foneducor_count++;
        }
      }
    }

    return [
      'total' => $total,
      'aspu' => $aspu_count,
      'foneducor' => $foneducor_count,
    ];
  }

}
