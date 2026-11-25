<?php

namespace Drupal\prodepem_solicitudes_rtm\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\taxonomy\Entity\Term;
use Drupal\webform\Entity\WebformSubmission;
use Dompdf\Dompdf;
use Dompdf\Options;
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
   * Reporte ASPU - Boletas de Rifa (campos: c_c, numero_de_rifa_1, numero_de_rifa_2).
   */
  public function reporteAspuRifas(Request $request) {
    return $this->generarVistaReporte('aspu', 'rifas', $request);
  }

  /**
   * Reporte ASPU - Conteo y consolidado de condiciones de salud.
   */
  public function reporteAspuCondicionesSalud(Request $request) {
    $search = trim((string) $request->query->get('search', ''));
    $fecha_desde = trim((string) $request->query->get('fecha_desde', ''));
    $fecha_hasta = trim((string) $request->query->get('fecha_hasta', ''));

    $items = $this->obtenerRegistrosFiltrados('aspu', 'vida', $search, $fecha_desde, $fecha_hasta);
    $stats = $this->calcularEstadisticasCondicionesSalud($items);

    $export_params = array_filter([
      'search' => $search,
      'fecha_desde' => $fecha_desde,
      'fecha_hasta' => $fecha_hasta,
    ]);
    $export_url = Url::fromRoute('prodepem_solicitudes_rtm.reportes_equidad_exportar', ['reporte' => 'aspu-condiciones-salud'], ['query' => $export_params])->toString();
    $pdf_url = Url::fromRoute('prodepem_solicitudes_rtm.exportar_pdf_condiciones_salud', [], ['query' => $export_params])->toString();
    $back_url = Url::fromRoute('prodepem_solicitudes_rtm.reporte_aspu')->toString();

    // URLs para redirección a listados filtrados y exportación XLS directa por card:
    $base_query = array_filter([
      'search' => $search,
      'fecha_desde' => $fecha_desde,
      'fecha_hasta' => $fecha_hasta,
    ]);

    $card_urls = [
      'total' => Url::fromRoute('prodepem_solicitudes_rtm.reporte_aspu', [], ['query' => $base_query])->toString(),
      'con_condiciones' => Url::fromRoute('prodepem_solicitudes_rtm.reporte_aspu', [], ['query' => array_merge($base_query, ['filtro_salud' => 'con_condiciones'])])->toString(),
      'sin_condiciones' => Url::fromRoute('prodepem_solicitudes_rtm.reporte_aspu', [], ['query' => array_merge($base_query, ['filtro_salud' => 'sin_condiciones'])])->toString(),
      'tarifa_12000' => Url::fromRoute('prodepem_solicitudes_rtm.reporte_aspu', [], ['query' => array_merge($base_query, ['tarifa' => '12000'])])->toString(),
      'tarifa_10000' => Url::fromRoute('prodepem_solicitudes_rtm.reporte_aspu', [], ['query' => array_merge($base_query, ['tarifa' => '10000'])])->toString(),
      'tarifa_8000' => Url::fromRoute('prodepem_solicitudes_rtm.reporte_aspu', [], ['query' => array_merge($base_query, ['tarifa' => '8000'])])->toString(),
      'mayor_71' => Url::fromRoute('prodepem_solicitudes_rtm.reporte_aspu', [], ['query' => array_merge($base_query, ['tarifa' => 'mayor_71'])])->toString(),
      'sobrepeso' => Url::fromRoute('prodepem_solicitudes_rtm.reporte_aspu', [], ['query' => array_merge($base_query, ['condicion' => 'sobrepeso'])])->toString(),
      'obesidad_imc30' => Url::fromRoute('prodepem_solicitudes_rtm.reporte_aspu', [], ['query' => array_merge($base_query, ['tarifa' => '12000'])])->toString(),
    ];

    $card_export_urls = [
      'total' => Url::fromRoute('prodepem_solicitudes_rtm.reportes_equidad_exportar', ['reporte' => 'aspu'], ['query' => $base_query])->toString(),
      'con_condiciones' => Url::fromRoute('prodepem_solicitudes_rtm.reportes_equidad_exportar', ['reporte' => 'aspu'], ['query' => array_merge($base_query, ['filtro_salud' => 'con_condiciones'])])->toString(),
      'sin_condiciones' => Url::fromRoute('prodepem_solicitudes_rtm.reportes_equidad_exportar', ['reporte' => 'aspu'], ['query' => array_merge($base_query, ['filtro_salud' => 'sin_condiciones'])])->toString(),
      'tarifa_12000' => Url::fromRoute('prodepem_solicitudes_rtm.reportes_equidad_exportar', ['reporte' => 'aspu'], ['query' => array_merge($base_query, ['tarifa' => '12000'])])->toString(),
      'tarifa_10000' => Url::fromRoute('prodepem_solicitudes_rtm.reportes_equidad_exportar', ['reporte' => 'aspu'], ['query' => array_merge($base_query, ['tarifa' => '10000'])])->toString(),
      'tarifa_8000' => Url::fromRoute('prodepem_solicitudes_rtm.reportes_equidad_exportar', ['reporte' => 'aspu'], ['query' => array_merge($base_query, ['tarifa' => '8000'])])->toString(),
      'mayor_71' => Url::fromRoute('prodepem_solicitudes_rtm.reportes_equidad_exportar', ['reporte' => 'aspu'], ['query' => array_merge($base_query, ['tarifa' => 'mayor_71'])])->toString(),
      'sobrepeso' => Url::fromRoute('prodepem_solicitudes_rtm.reportes_equidad_exportar', ['reporte' => 'aspu'], ['query' => array_merge($base_query, ['condicion' => 'sobrepeso'])])->toString(),
      'obesidad_imc30' => Url::fromRoute('prodepem_solicitudes_rtm.reportes_equidad_exportar', ['reporte' => 'aspu'], ['query' => array_merge($base_query, ['tarifa' => '12000'])])->toString(),
    ];

    foreach ($stats['conteo_preguntas'] as $key => &$p) {
      $p['url_listado'] = Url::fromRoute('prodepem_solicitudes_rtm.reporte_aspu', [], ['query' => array_merge($base_query, ['condicion' => $key])])->toString();
      $p['url_export'] = Url::fromRoute('prodepem_solicitudes_rtm.reportes_equidad_exportar', ['reporte' => 'aspu'], ['query' => array_merge($base_query, ['condicion' => $key])])->toString();
    }
    unset($p);

    return [
      '#theme' => 'prodepem_equidad_reporte_condiciones_salud',
      '#stats' => $stats,
      '#total' => $stats['total_solicitudes'],
      '#filters' => [
        'search' => $search,
        'fecha_desde' => $fecha_desde,
        'fecha_hasta' => $fecha_hasta,
      ],
      '#export_url' => $export_url,
      '#pdf_url' => $pdf_url,
      '#back_url' => $back_url,
      '#card_urls' => $card_urls,
      '#card_export_urls' => $card_export_urls,
      '#attached' => [
        'library' => [
          'prodepem_solicitudes_rtm/reportes_equidad',
        ],
      ],
      '#title' => $this->t('Reporte ASPU - Conteo de Condiciones de Salud'),
    ];
  }

  /**
   * Reporte PRODEPEM - Solicitudes de Seguro de Vida.
   */
  public function reporteProdepem(Request $request) {
    return $this->generarVistaReporte('prodepem', 'vida', $request);
  }

  /**
   * Reporte PRODEPEM - Datos Complementarios (Seguros adicionales y Rifa).
   */
  public function reporteProdepemAdicionales(Request $request) {
    return $this->generarVistaReporte('prodepem', 'adicionales', $request);
  }

  /**
   * Reporte PRODEPEM - Boletas de Rifa.
   */
  public function reporteProdepemRifas(Request $request) {
    return $this->generarVistaReporte('prodepem', 'rifas', $request);
  }

  /**
   * Reporte de Boletas de Rifa (compatibilidad con ruta anterior).
   */
  public function reporteRifas(Request $request) {
    return $this->generarVistaReporte('aspu', 'rifas', $request);
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
    $tarifa = trim((string) $request->query->get('tarifa', ''));
    $filtro_salud = trim((string) $request->query->get('filtro_salud', ''));
    $condicion = trim((string) $request->query->get('condicion', ''));

    $filtros_extra = array_filter([
      'tarifa' => $tarifa,
      'filtro_salud' => $filtro_salud,
      'condicion' => $condicion,
    ]);

    $items = $this->obtenerRegistrosFiltrados($tomador, $tipo, $search, $fecha_desde, $fecha_hasta, $filtros_extra);

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
        'title' => 'Reporte ASPU - Datos Complementarios',
        'desc' => 'Datos complementarios comerciales: ampliación de amparo de vida, interés en seguros (SOAT, todo riesgo, hogar, adicionales), placa y rifa (ASPU).',
        'badge' => 'ASPU (Datos Complementarios)',
      ],
      'aspu_rifas' => [
        'title' => 'Reporte ASPU - Boletas de Rifa',
        'desc' => 'Reporte con campos: c_c, numero_de_rifa_1 y numero_de_rifa_2 para solicitudes de ASPU.',
        'badge' => 'ASPU • RIFAS',
      ],
      'prodepem_vida' => [
        'title' => 'Reporte PRODEPEM - Seguro de Vida',
        'desc' => 'Solicitudes de declaración de asegurabilidad registradas a través de formulario_equidad_seguros_prode (PRODEPEM).',
        'badge' => 'PRODEPEM (Tomador)',
      ],
      'prodepem_adicionales' => [
        'title' => 'Reporte PRODEPEM - Datos Complementarios',
        'desc' => 'Datos complementarios comerciales: ampliación de amparo de vida, interés en seguros (SOAT, todo riesgo, hogar, adicionales), placa y rifa (PRODEPEM).',
        'badge' => 'PRODEPEM (Datos Complementarios)',
      ],
      'prodepem_rifas' => [
        'title' => 'Reporte PRODEPEM - Boletas de Rifa',
        'desc' => 'Reporte con campos: c_c, numero_de_rifa_1 y numero_de_rifa_2 para solicitudes de PRODEPEM.',
        'badge' => 'PRODEPEM • RIFAS',
      ],
      'foneducor_adicionales' => [
        'title' => 'Reporte FONEDUCOR - Datos Complementarios',
        'desc' => 'Datos complementarios comerciales: ampliación de amparo de vida, interés en seguros (SOAT, todo riesgo, hogar, adicionales), placa y rifa (FONEDUCOR).',
        'badge' => 'FONEDUCOR (Datos Complementarios)',
      ],
    ];

    $cfg_key = $tomador . '_' . $tipo;
    $info = $titles[$cfg_key] ?? [
      'title' => 'Reporte Equidad Seguros',
      'desc' => 'Listado de solicitudes',
      'badge' => strtoupper($tomador),
    ];

    // Label descriptivo para el filtro de condición específico:
    $filtro_condicion_label = '';
    if (!empty($condicion)) {
      if ($condicion === 'mayor_71') {
        $filtro_condicion_label = 'Personas con más de 71 años';
      }
      elseif ($condicion === 'sobrepeso') {
        $filtro_condicion_label = 'Sobrepeso (declarado o registrado)';
      }
      elseif ($condicion === 'obesidad_imc30') {
        $filtro_condicion_label = 'Obesidad con IMC > 30';
      }
      elseif ($condicion === 'hipertension_arterial') {
        $filtro_condicion_label = 'Hipertensión arterial';
      }
      else {
        $def_salud = self::getDefinicionCamposSalud();
        $filtro_condicion_label = $def_salud[$condicion]['titulo'] ?? $condicion;
      }
    }

    // URL para descarga XLS conservando los filtros activos.
    if ($tipo === 'rifas') {
      $export_key = ($tomador === 'prodepem') ? 'prodepem-rifas' : 'aspu-rifas';
    } elseif ($tipo === 'adicionales') {
      $export_key = $tomador . '-adicionales';
    } else {
      $export_key = $tomador;
    }
    $export_params = array_filter([
      'search' => $search,
      'fecha_desde' => $fecha_desde,
      'fecha_hasta' => $fecha_hasta,
      'tarifa' => $tarifa,
      'filtro_salud' => $filtro_salud,
      'condicion' => $condicion,
    ]);
    $export_url = Url::fromRoute('prodepem_solicitudes_rtm.reportes_equidad_exportar', ['reporte' => $export_key], ['query' => $export_params])->toString();

    // Paginación: 10 registros por página.
    $limit = 10;
    $total = count($items);
    $total_pages = max(1, (int) ceil($total / $limit));
    $page = max(1, (int) $request->query->get('page', 1));
    if ($page > $total_pages) {
      $page = $total_pages;
    }
    $offset = ($page - 1) * $limit;
    $paged_items = array_slice($items, $offset, $limit);
    $item_from = $total > 0 ? $offset + 1 : 0;
    $item_to = min($offset + $limit, $total);

    $base_query = array_filter([
      'search' => $search,
      'fecha_desde' => $fecha_desde,
      'fecha_hasta' => $fecha_hasta,
      'tarifa' => $tarifa,
      'filtro_salud' => $filtro_salud,
      'condicion' => $condicion,
    ]);

    $build_page_url = function ($p) use ($base_query) {
      $q = $base_query;
      if ($p > 1) {
        $q['page'] = $p;
      }
      return Url::fromRoute('<current>', [], ['query' => $q])->toString();
    };

    $page_items = [];
    if ($total_pages <= 7) {
      for ($i = 1; $i <= $total_pages; $i++) {
        $page_items[] = [
          'number' => $i,
          'url' => $build_page_url($i),
          'is_active' => ($i === $page),
          'is_ellipsis' => FALSE,
        ];
      }
    }
    else {
      // Página 1 siempre visible.
      $page_items[] = [
        'number' => 1,
        'url' => $build_page_url(1),
        'is_active' => (1 === $page),
        'is_ellipsis' => FALSE,
      ];

      $start = max(2, $page - 2);
      $end = min($total_pages - 1, $page + 2);

      if ($page <= 4) {
        $start = 2;
        $end = 5;
      }
      if ($page >= $total_pages - 3) {
        $start = $total_pages - 4;
        $end = $total_pages - 1;
      }

      if ($start > 2) {
        $page_items[] = [
          'number' => '...',
          'url' => '',
          'is_active' => FALSE,
          'is_ellipsis' => TRUE,
        ];
      }

      for ($i = $start; $i <= $end; $i++) {
        $page_items[] = [
          'number' => $i,
          'url' => $build_page_url($i),
          'is_active' => ($i === $page),
          'is_ellipsis' => FALSE,
        ];
      }

      if ($end < $total_pages - 1) {
        $page_items[] = [
          'number' => '...',
          'url' => '',
          'is_active' => FALSE,
          'is_ellipsis' => TRUE,
        ];
      }

      // Última página siempre visible.
      $page_items[] = [
        'number' => $total_pages,
        'url' => $build_page_url($total_pages),
        'is_active' => ($total_pages === $page),
        'is_ellipsis' => FALSE,
      ];
    }

    $pagination = [
      'current_page' => $page,
      'total_pages' => $total_pages,
      'total_items' => $total,
      'limit' => $limit,
      'from' => $item_from,
      'to' => $item_to,
      'first_url' => $page > 1 ? $build_page_url(1) : NULL,
      'prev_url' => $page > 1 ? $build_page_url($page - 1) : NULL,
      'next_url' => $page < $total_pages ? $build_page_url($page + 1) : NULL,
      'last_url' => $page < $total_pages ? $build_page_url($total_pages) : NULL,
      'pages' => $page_items,
    ];

    return [
      '#theme' => 'prodepem_equidad_reporte',
      '#reporte_key' => $export_key,
      '#reporte_title' => $info['title'],
      '#reporte_desc' => $info['desc'],
      '#tomador_badge' => $info['badge'],
      '#tipo_reporte' => $tipo,
      '#items' => $paged_items,
      '#total' => $total,
      '#pagination' => $pagination,
      '#filters' => [
        'search' => $search,
        'fecha_desde' => $fecha_desde,
        'fecha_hasta' => $fecha_hasta,
        'tarifa' => $tarifa,
        'filtro_salud' => $filtro_salud,
        'condicion' => $condicion,
      ],
      '#filtro_condicion_label' => $filtro_condicion_label,
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
    if (!$submission || !in_array($submission->getWebform()->id(), ['formulario_equidad_seguros', 'formulario_equidad_seguros_prode'], TRUE)) {
      return [
        '#markup' => $this->t('No se encontró la sumisión @sid del formulario de Equidad Seguros.', ['@sid' => $sid]),
      ];
    }

    $raw = $submission->getData();
    $detalles = $this->estructurarDetalleCompleto($submission, $raw);

    $pdf_url = Url::fromRoute('prodepem_solicitudes_rtm.generar_pdf_seguro_vida', ['sid' => $sid])->toString();

    // Determinar URL de retorno según el tomador / webform.
    if ($submission->getWebform()->id() === 'formulario_equidad_seguros_prode') {
      $back_route = 'prodepem_solicitudes_rtm.reporte_prodepem';
    }
    else {
      $selector = (string) ($raw['selector_tomador'] ?? '2');
      $tomador_text = mb_strtolower((string) ($raw['tomador'] ?? ''));
      $is_foneducor = ($selector === '1' || strpos($tomador_text, 'fondo') !== FALSE || strpos($tomador_text, 'cordoba') !== FALSE || strpos($tomador_text, 'foneducor') !== FALSE);
      $back_route = $is_foneducor ? 'prodepem_solicitudes_rtm.reporte_foneducor' : 'prodepem_solicitudes_rtm.reporte_aspu';
    }
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
   * Exporta a PDF el conteo de registros por cada condición y consolidado por tarifa.
   */
  public function exportarPdfCondicionesSalud(Request $request) {
    try {
      $search = trim((string) $request->query->get('search', ''));
      $fecha_desde = trim((string) $request->query->get('fecha_desde', ''));
      $fecha_hasta = trim((string) $request->query->get('fecha_hasta', ''));

      $items = $this->obtenerRegistrosFiltrados('aspu', 'vida', $search, $fecha_desde, $fecha_hasta);
      $stats = $this->calcularEstadisticasCondicionesSalud($items);

      $module_path = \Drupal::service('extension.list.module')->getPath('prodepem_solicitudes_rtm');

      $logo_uri = NULL;
      $equidad_logo_jpg = \Drupal::root() . '/' . $module_path . '/images/la_equidad_logo.jpg';
      $equidad_logo_png = \Drupal::root() . '/' . $module_path . '/images/la_equidad_logo.png';
      if (file_exists($equidad_logo_jpg)) {
        $logo_uri = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($equidad_logo_jpg));
      }
      elseif (file_exists($equidad_logo_png)) {
        $logo_uri = 'data:image/png;base64,' . base64_encode(file_get_contents($equidad_logo_png));
      }

      $vigilado_uri = NULL;
      $vigilado_jpg = \Drupal::root() . '/' . $module_path . '/images/vigilado_superfinanciera.jpg';
      $vigilado_png = \Drupal::root() . '/' . $module_path . '/images/vigilado_superfinanciera.png';
      if (file_exists($vigilado_jpg)) {
        $vigilado_uri = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($vigilado_jpg));
      }
      elseif (file_exists($vigilado_png)) {
        $vigilado_uri = 'data:image/png;base64,' . base64_encode(file_get_contents($vigilado_png));
      }

      $render_array = [
        '#theme' => 'prodepem_equidad_reporte_condiciones_salud_pdf',
        '#stats' => $stats,
        '#total' => $stats['total_solicitudes'],
        '#filters' => [
          'search' => $search,
          'fecha_desde' => $fecha_desde,
          'fecha_hasta' => $fecha_hasta,
        ],
        '#date' => date('d/m/Y H:i:s'),
        '#logo_path' => $logo_uri,
        '#vigilado_path' => $vigilado_uri,
      ];

      $html = (string) \Drupal::service('renderer')->renderPlain($render_array);

      $options = new Options();
      $options->set('isHtml5ParserEnabled', TRUE);
      $options->set('isRemoteEnabled', TRUE);
      $dompdf = new Dompdf($options);
      $dompdf->loadHtml($html, 'UTF-8');
      $dompdf->setPaper('letter', 'portrait');
      $dompdf->render();

      $output = $dompdf->output();
      $filename = 'reporte_aspu_condiciones_salud_' . date('Ymd_His') . '.pdf';

      $response = new Response($output);
      $disposition = $response->headers->makeDisposition(
        ResponseHeaderBag::DISPOSITION_ATTACHMENT,
        $filename
      );
      $response->headers->set('Content-Type', 'application/pdf');
      $response->headers->set('Content-Disposition', $disposition);
      $response->headers->set('Pragma', 'no-cache');
      $response->headers->set('Expires', '0');

      return $response;
    }
    catch (\Exception $e) {
      \Drupal::logger('prodepem_solicitudes_rtm')->error('Error exportando PDF de condiciones de salud: @msg', ['@msg' => $e->getMessage()]);
      return [
        '#markup' => $this->t('Error al generar el PDF: @message', ['@message' => $e->getMessage()]),
      ];
    }
  }

  /**
   * Exportación de registros en formato XLS (Excel XML Spreadsheet).
   */
  public function exportarXls($reporte, Request $request) {
    if ($reporte === 'aspu-condiciones-salud' || $reporte === 'condiciones-salud') {
      $search = trim((string) $request->query->get('search', ''));
      $fecha_desde = trim((string) $request->query->get('fecha_desde', ''));
      $fecha_hasta = trim((string) $request->query->get('fecha_hasta', ''));

      $items = $this->obtenerRegistrosFiltrados('aspu', 'vida', $search, $fecha_desde, $fecha_hasta);
      $stats = $this->calcularEstadisticasCondicionesSalud($items);

      $filename = 'reporte_aspu_condiciones_salud_' . date('Ymd_His') . '.xls';
      $xml = $this->construirXmlExcelCondicionesSalud($stats);

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

    if ($reporte === 'rifas' || $reporte === 'aspu-rifas') {
      $tomador = 'aspu';
      $tipo = 'rifas';
    }
    elseif ($reporte === 'prodepem-rifas') {
      $tomador = 'prodepem';
      $tipo = 'rifas';
    }
    elseif (strpos($reporte, 'prodepem') !== FALSE) {
      $tomador = 'prodepem';
      $tipo = (strpos($reporte, 'adicionales') !== FALSE) ? 'adicionales' : 'vida';
    }
    elseif (strpos($reporte, 'aspu') !== FALSE) {
      $tomador = 'aspu';
      $tipo = (strpos($reporte, 'adicionales') !== FALSE) ? 'adicionales' : 'vida';
    }
    else {
      $tomador = 'foneducor';
      $tipo = (strpos($reporte, 'adicionales') !== FALSE) ? 'adicionales' : 'vida';
    }

    $search = trim((string) $request->query->get('search', ''));
    $fecha_desde = trim((string) $request->query->get('fecha_desde', ''));
    $fecha_hasta = trim((string) $request->query->get('fecha_hasta', ''));
    $tarifa = trim((string) $request->query->get('tarifa', ''));
    $filtro_salud = trim((string) $request->query->get('filtro_salud', ''));
    $condicion = trim((string) $request->query->get('condicion', ''));

    $filtros_extra = array_filter([
      'tarifa' => $tarifa,
      'filtro_salud' => $filtro_salud,
      'condicion' => $condicion,
    ]);

    $items = $this->obtenerRegistrosFiltrados($tomador, $tipo, $search, $fecha_desde, $fecha_hasta, $filtros_extra);

    if ($tipo === 'rifas') {
      $filename = 'reporte_' . $tomador . '_boletas_rifa_' . date('Ymd_His') . '.xls';
    }
    else {
      $nombre_tomador = ($tomador === 'prodepem') ? 'PRODEPEM' : (($tomador === 'aspu') ? 'ASPU' : 'FONEDUCOR');
      $tipo_nombre = ($tipo === 'adicionales') ? 'Datos_Complementarios' : 'Seguro_Vida';

      $sufijo_filtro = '';
      if (!empty($tarifa)) {
        $sufijo_filtro = '_tarifa_' . $tarifa;
      }
      elseif (!empty($filtro_salud)) {
        $sufijo_filtro = '_' . $filtro_salud;
      }
      elseif (!empty($condicion)) {
        $sufijo_filtro = '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $condicion);
      }

      $filename = 'reporte_' . strtolower($nombre_tomador) . '_' . $tipo_nombre . $sufijo_filtro . '_' . date('Ymd_His') . '.xls';
    }

    $xml = $this->construirXmlExcel($items, $tomador, $tipo, $filtros_extra);

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
  protected function obtenerRegistrosFiltrados($tomador, $tipo, $search = '', $fecha_desde = '', $fecha_hasta = '', array $filtros_extra = []) {
    $webform_id = ($tomador === 'prodepem') ? 'formulario_equidad_seguros_prode' : 'formulario_equidad_seguros';
    $storage = \Drupal::entityTypeManager()->getStorage('webform_submission');
    $query = $storage->getQuery()
      ->condition('webform_id', $webform_id)
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

      if ($tomador === 'prodepem') {
        // En formulario_equidad_seguros_prode todas las sumisiones corresponden a PRODEPEM.
      }
      else {
        // Validación del tomador para formulario_equidad_seguros (ASPU o FONEDUCOR):
        $selector = (string) ($raw['selector_tomador'] ?? '');
        $tomador_text = mb_strtolower((string) ($raw['tomador'] ?? ''));

        $is_aspu = ($selector === '2' || strpos($tomador_text, 'aspu') !== FALSE || strpos($tomador_text, 'sindical') !== FALSE || $selector === '');
        $is_foneducor = ($selector === '1' || strpos($tomador_text, 'fondo') !== FALSE || strpos($tomador_text, 'cordoba') !== FALSE || strpos($tomador_text, 'docente') !== FALSE || strpos($tomador_text, 'foneducor') !== FALSE);

        if ($tomador === 'aspu' && !$is_aspu) {
          continue;
        }
        if ($tomador === 'foneducor' && !$is_foneducor) {
          continue;
        }
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
          $item['c_c'] ?? '',
          $item['tipo_documento'],
          $item['fecha_nacimiento'] ?? '',
          $item['edad'] ?? '',
          $item['peso'] ?? '',
          $item['estatura'] ?? '',
          $item['imc'] ?? '',
          $item['numero_de_rifa_1'] ?? '',
          $item['numero_de_rifa_2'] ?? '',
          $item['email'],
          $item['telefono'],
          $item['ciudad'],
          $item['ocupacion'],
          $item['cargo'],
          $item['condiciones_salud_texto'] ?? '',
          $item['explicacion_condiciones'] ?? '',
          $item['valor_condicion_salud'] ?? '',
          $item['esta_interesado_en_ampliar_el_amparo_de_vida'],
          $item['por_que'],
          $item['interesado_soat'],
          $item['interesado_todo_riesgo'],
          $item['interesado_hogar'],
          $item['interesado_tecnicomecanica'] ?? '',
          $item['recibir_mensajes_vencimiento'] ?? '',
          $item['interesado_salud'] ?? '',
          $item['seguros_adicionales'],
          $item['placa'],
          $item['numero_de_rifa_1'],
          $item['numero_de_rifa_2'],
        ]));

        if (strpos($corpus, $search_lower) === FALSE) {
          continue;
        }
      }

      // Filtros específicos adicionales (Tarifa, Salud, Condición):
      if (!empty($filtros_extra['tarifa'])) {
        if ($filtros_extra['tarifa'] === 'mayor_71') {
          if (empty($item['es_mayor_71'])) {
            continue;
          }
        }
        else {
          // Si el filtro es de tarifa regular (12000, 10000 u 8000), se excluyen personas con más de 71 años:
          if (!empty($item['es_mayor_71'])) {
            continue;
          }
          $tarifa_filtro = (int) $filtros_extra['tarifa'];
          if ((int) ($item['valor_condicion_salud'] ?? 8000) !== $tarifa_filtro) {
            continue;
          }
        }
      }

      if (!empty($filtros_extra['filtro_salud'])) {
        $tiene_condiciones = (!empty($item['condiciones_marcadas']) && ($item['condiciones_salud_texto'] ?? '') !== 'Ninguna');
        if ($filtros_extra['filtro_salud'] === 'con_condiciones' && !$tiene_condiciones) {
          continue;
        }
        if ($filtros_extra['filtro_salud'] === 'sin_condiciones' && $tiene_condiciones) {
          continue;
        }
      }

      if (!empty($filtros_extra['condicion'])) {
        $cond_req = $filtros_extra['condicion'];
        $texto_salud = ($item['condiciones_salud_texto'] ?? '') . ' ' . ($item['explicacion_condiciones'] ?? '');
        $texto_norm = self::normalizarTextoParaBusqueda($texto_salud);
        $keys_marcadas = $item['condiciones_marcadas_keys'] ?? [];

        if ($cond_req === 'mayor_71') {
          if (empty($item['es_mayor_71'])) {
            continue;
          }
        }
        elseif ($cond_req === 'sobrepeso') {
          if (!empty($item['es_mayor_71']) || strpos($texto_norm, 'sobrepeso') === FALSE) {
            continue;
          }
        }
        elseif ($cond_req === 'obesidad_imc30') {
          if (!empty($item['es_mayor_71'])) {
            continue;
          }
          $imc_num = 0.0;
          if (!empty($item['imc'])) {
            $imc_clean = str_replace(',', '.', trim((string) $item['imc']));
            if (is_numeric($imc_clean)) {
              $imc_num = (float) $imc_clean;
            }
          }
          if (strpos($texto_norm, 'obesidad') === FALSE || $imc_num <= 30.0) {
            continue;
          }
        }
        elseif ($cond_req === 'hipertension_arterial') {
          if (
            strpos($texto_norm, 'hipertension') === FALSE &&
            strpos($texto_norm, 'presion arterial') === FALSE &&
            !in_array('hipertension_arterial', $keys_marcadas, TRUE)
          ) {
            continue;
          }
        }
        else {
          $def_salud = self::getDefinicionCamposSalud();
          $titulo_def = $def_salud[$cond_req]['titulo'] ?? '';
          $titulos_marcados = $item['condiciones_marcadas'] ?? [];

          if (!in_array($cond_req, $keys_marcadas, TRUE) && !in_array($titulo_def, $titulos_marcados, TRUE)) {
            continue;
          }
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

    // Apellido asegurado principal:
    $apellido = trim((string) ($raw['apellido_asegurado_principal'] ?? ''));

    // Nombre del Asegurado:
    $nombre = $raw['nombre_completo']
      ?? $raw['asegurado_principal']
      ?? $raw['nombre_del_asegurado']
      ?? $raw['nombre_asegurado']
      ?? $raw['asegurado']
      ?? $raw['nombres_y_apellidos']
      ?? $raw['nombre_y_apellidos']
      ?? '';
    if (!empty($nombre)) {
      if (!empty($apellido)) {
        $nombre = trim($nombre . ' ' . $apellido);
      }
    }
    else {
      $nombres = $raw['nombres'] ?? $raw['nombre'] ?? trim(($raw['primer_nombre'] ?? '') . ' ' . ($raw['segundo_nombre'] ?? ''));
      $apellidos = !empty($apellido) ? $apellido : ($raw['apellidos'] ?? $raw['primer_apellido'] ?? trim(($raw['primer_apellido'] ?? '') . ' ' . ($raw['segundo_apellido'] ?? '')));
      $nombre = trim($nombres . ' ' . $apellidos);
    }
    if (empty($nombre)) {
      foreach ($raw as $k => $v) {
        if (is_string($v) && !empty(trim($v)) && !in_array($k, ['tomador', 'empresa', 'direccion_empresa', 'email', 'correo_electronico']) && stripos($k, 'beneficiar') === FALSE && stripos($k, 'tomador') === FALSE && stripos($k, 'apellido') === FALSE) {
          if (stripos($k, 'nombre') !== FALSE || stripos($k, 'asegurad') !== FALSE) {
            $nombre = trim($v);
            if (!empty($apellido)) {
              $nombre = trim($nombre . ' ' . $apellido);
            }
            break;
          }
        }
      }
    }
    if (empty($nombre) && $sub->getOwnerId()) {
      $nombre = $sub->getOwner()->getDisplayName();
    }

    // Documento del asegurado (campo c_c):
    $num_doc = $raw['c_c']
      ?? $raw['numero_de_documento']
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

    // Beneficiarios resumen (usar tabla_de_beneficiarios, o tabla_de_beneficiarios_2 si está vacía):
    $beneficiarios = self::obtenerBeneficiariosNormalizados($raw);
    $beneficiarios_count = count($beneficiarios);
    $beneficiarios_nombres = [];
    foreach ($beneficiarios as $b) {
      if (!empty($b['nombre'])) {
        $beneficiarios_nombres[] = $b['nombre'];
      }
    }

    // Cuestionario de salud (campos marcados con true):
    $condiciones_marcadas_titulos = [];
    $condiciones_marcadas_keys = [];
    $salud_def = self::getDefinicionCamposSalud();
    $webform = $sub->getWebform();

    foreach ($salud_def as $campo_key => $info) {
      $titulo = $info['titulo'];
      if ($webform) {
        $elem = $webform->getElement($campo_key);
        if (!empty($elem['#title'])) {
          $titulo = trim(strip_tags((string) $elem['#title']));
        }
      }

      $val = NULL;
      foreach ($info['aliases'] as $alias) {
        if (isset($raw[$alias]) && $raw[$alias] !== '' && $raw[$alias] !== NULL) {
          $val = $raw[$alias];
          break;
        }
      }

      $is_true = FALSE;
      if (is_bool($val)) {
        $is_true = $val === TRUE;
      }
      elseif (is_numeric($val)) {
        $is_true = (int) $val === 1;
      }
      elseif (is_string($val)) {
        $v = mb_strtolower(trim($val));
        $is_true = in_array($v, ['1', 'true', 'si', 'sí', 'yes', 'on', 'checked'], TRUE);
      }

      if ($is_true) {
        $condiciones_marcadas_titulos[] = $titulo;
        $condiciones_marcadas_keys[] = $campo_key;
      }
    }

    $salud_positivas = count($condiciones_marcadas_titulos);
    $condiciones_salud_texto = !empty($condiciones_marcadas_titulos)
      ? implode(', ', $condiciones_marcadas_titulos)
      : 'Ninguna';

    // Fecha de nacimiento y edad calculada:
    $fecha_nac_raw = $raw['fecha_de_nacimiento']
      ?? $raw['fecha_nacimiento']
      ?? $raw['f_nacimiento']
      ?? $raw['nacimiento']
      ?? '';
    if (empty($fecha_nac_raw)) {
      foreach ($raw as $k => $v) {
        if (is_string($v) && !empty(trim($v)) && stripos($k, 'nacimiento') !== FALSE && stripos($k, 'lugar') === FALSE) {
          $fecha_nac_raw = trim($v);
          break;
        }
      }
    }
    $fecha_nacimiento = self::formatearFecha($fecha_nac_raw);
    $edad = self::calcularEdad($fecha_nac_raw);
    if ($edad === '') {
      $candidatos_edad = ['edad', 'edad_asegurado', 'edad_anos', 'anos_cumplidos'];
      foreach ($candidatos_edad as $cand_edad) {
        if (isset($raw[$cand_edad]) && is_numeric($raw[$cand_edad]) && (int) $raw[$cand_edad] > 0) {
          $edad = (string) (int) $raw[$cand_edad];
          break;
        }
      }
    }
    $edad_int = ($edad !== '' && is_numeric($edad)) ? (int) $edad : null;
    $es_mayor_71 = ($edad_int !== null && $edad_int > 71);

    // Peso, estatura e IMC (Índice de Masa Corporal):
    $peso = trim((string) ($raw['peso'] ?? $raw['peso_kg'] ?? ''));
    if (empty($peso)) {
      foreach ($raw as $k => $v) {
        if ((is_string($v) || is_numeric($v)) && !empty(trim((string) $v)) && stripos($k, 'peso') !== FALSE && stripos($k, 'pesos') === FALSE) {
          $peso = trim((string) $v);
          break;
        }
      }
    }

    $estatura = trim((string) ($raw['estatura'] ?? $raw['estatura_cm'] ?? $raw['altura'] ?? ''));
    if (empty($estatura)) {
      foreach ($raw as $k => $v) {
        if ((is_string($v) || is_numeric($v)) && !empty(trim((string) $v)) && (stripos($k, 'estatura') !== FALSE || stripos($k, 'altura') !== FALSE)) {
          $estatura = trim((string) $v);
          break;
        }
      }
    }

    $imc = self::calcularImc($peso, $estatura);

    // Explicación de condiciones de salud:
    $explicacion_condiciones = trim((string) (
      $raw['en_caso_de_haber_marcado_alguna_de_las_condiciones_anteriores_o']
      ?? $raw['explicacion_salud']
      ?? $raw['explicacion_condiciones_salud']
      ?? $raw['favor_explicar_detalladamente']
      ?? ''
    ));

    // Valor numérico según condiciones de salud e IMC:
    $valor_condicion_salud = self::calcularValorCondicionSalud($condiciones_salud_texto, $explicacion_condiciones, $imc);

    // Helper para normalizar respuestas Si/No/Valores booleanos:
    $normalizar_sino = function ($val) {
      if (is_null($val) || $val === '' || (is_array($val) && empty($val))) {
        return '';
      }
      if (is_bool($val)) {
        return $val ? 'SÍ' : 'NO';
      }
      if (is_array($val)) {
        $has_yes = FALSE;
        $has_no = FALSE;
        foreach ($val as $sub_k => $sub_v) {
          if ($sub_v && $sub_v !== '0') {
            $has_yes = TRUE;
          }
          elseif ($sub_v === '0' || $sub_v === FALSE) {
            $has_no = TRUE;
          }
        }
        if ($has_yes) {
          return 'SÍ';
        }
        if ($has_no) {
          return 'NO';
        }
        return '';
      }
      $s = mb_strtolower(trim((string) $val));
      if (in_array($s, ['1', 'si', 'sí', 'true', 'yes', 'on', 'checked'])) {
        return 'SÍ';
      }
      if (in_array($s, ['0', 'no', 'false', 'off', 'unchecked'])) {
        return 'NO';
      }
      return (string) $val;
    };

    // DATOS COMPLEMENTARIOS:
    // Placa del vehículo (necesaria para heurística de seguros vehiculares):
    $placa = strtoupper(trim((string) ($raw['placa'] ?? $raw['placas'] ?? $raw['numero_placa'] ?? $raw['placa_vehiculo'] ?? '')));
    if (empty($placa)) {
      foreach ($raw as $k => $v) {
        if (is_string($v) && !empty(trim($v)) && stripos($k, 'placa') !== FALSE) {
          $placa = strtoupper(trim($v));
          break;
        }
      }
    }

    // 1. esta_interesado_en_ampliar_el_amparo_de_vida y por_que:
    $ampliar_amparo_val = $raw['esta_interesado_en_ampliar_el_amparo_de_vida']
      ?? $raw['ampliar_el_amparo_de_vida']
      ?? $raw['ampliar_amparo_de_vida']
      ?? $raw['ampliar_amparo']
      ?? '';
    $esta_interesado_ampliar_vida = $normalizar_sino($ampliar_amparo_val);
    $por_que = trim((string) ($raw['por_que'] ?? $raw['porque'] ?? ''));

    // 2. interesado_soat:
    $candidatos_soat = [
      'interesado_prodepem_soat',
      'esta_interesado_en_soat',
      'esta_interesado_en_el_soat',
      'esta_interesado_soat',
      'interesado_en_soat',
      'interesado_en_el_soat',
      'interesado_soat',
      'interes_soat',
      'interes_en_soat',
      'interes_en_el_soat',
      'desea_soat',
      'desea_adquirir_soat',
      'esta_interesado_en_adquirir_soat',
      'esta_interesado_en_adquirir_el_soat',
      'adquirir_soat',
      'seguro_soat',
      'seguros_soat',
      'poliza_soat',
      'soat_vehiculo',
      'vehiculo_soat',
      'soat_interes',
      'soat',
      'SOAT',
    ];

    $interesado_soat_val = NULL;
    foreach ($candidatos_soat as $cand) {
      if (isset($raw[$cand]) && $raw[$cand] !== '' && $raw[$cand] !== NULL) {
        $interesado_soat_val = $raw[$cand];
        break;
      }
    }

    // Si aún no se encontró, buscar dinámicamente en los elementos del Webform por título o clave que contenga 'soat':
    if (($interesado_soat_val === NULL || $interesado_soat_val === '') && $webform) {
      $elementos_webform = $webform->getElementsDecodedAndFlattened();
      if (is_array($elementos_webform)) {
        foreach ($elementos_webform as $elem_k => $elem_def) {
          $k_low = mb_strtolower((string) $elem_k);
          $titulo_low = mb_strtolower((string) ($elem_def['#title'] ?? ''));
          if (strpos($k_low, 'soat') !== FALSE || strpos($titulo_low, 'soat') !== FALSE) {
            if (strpos($k_low, 'tipo') !== FALSE || strpos($k_low, 'clase') !== FALSE) {
              continue;
            }
            if (isset($raw[$elem_k]) && $raw[$elem_k] !== '' && $raw[$elem_k] !== NULL) {
              $interesado_soat_val = $raw[$elem_k];
              break;
            }
          }
        }
      }
    }

    // Si aún no se encontró, buscar en cualquier clave de $raw que contenga 'soat':
    if ($interesado_soat_val === NULL || $interesado_soat_val === '') {
      foreach ($raw as $k => $v) {
        $k_lower = mb_strtolower(trim((string) $k));
        if (strpos($k_lower, 'soat') !== FALSE) {
          if (strpos($k_lower, 'tipo') !== FALSE || strpos($k_lower, 'clase') !== FALSE) {
            continue;
          }
          if ($v !== '' && $v !== NULL) {
            $interesado_soat_val = $v;
            break;
          }
        }
      }
    }

    // Revisar si en campos tipo array/checkboxes se seleccionó SOAT:
    if ($interesado_soat_val === NULL || $interesado_soat_val === '') {
      foreach ($raw as $k => $v) {
        if (is_array($v)) {
          foreach ($v as $sub_k => $sub_v) {
            $sub_k_lower = mb_strtolower((string) $sub_k);
            $sub_v_lower = mb_strtolower((string) (is_string($sub_v) ? $sub_v : ''));
            if (strpos($sub_k_lower, 'soat') !== FALSE || strpos($sub_v_lower, 'soat') !== FALSE) {
              if ($sub_v && $sub_v !== '0') {
                $interesado_soat_val = 'SÍ';
                break 2;
              }
              elseif ($sub_v === '0' || $sub_v === FALSE) {
                $interesado_soat_val = 'NO';
              }
            }
          }
        }
        elseif (is_string($v) && stripos($v, 'soat') !== FALSE) {
          $interesado_soat_val = 'SÍ';
          break;
        }
      }
    }

    $interesado_soat = $normalizar_sino($interesado_soat_val);

    // 3. interesado_todo_riesgo (validar que se utilice prioritariamente interesado_todo_riesgo):
    $interesado_todo_riesgo_val = $raw['interesado_todo_riesgo'] ?? NULL;
    if ($interesado_todo_riesgo_val === NULL || $interesado_todo_riesgo_val === '') {
      $candidatos_todo_riesgo = [
        'interesado_todo_riesgo',
        'interesado_prodepem_todo_riesgo',
        'esta_interesado_en_todo_riesgo',
        'esta_interesado_en_el_todo_riesgo',
        'esta_interesado_todo_riesgo',
        'interesado_en_todo_riesgo',
        'interesado_en_el_todo_riesgo',
        'interes_todo_riesgo',
        'interes_en_todo_riesgo',
        'seguro_todo_riesgo',
        'seguros_todo_riesgo',
        'poliza_todo_riesgo',
        'todo_riesgo',
      ];
      foreach ($candidatos_todo_riesgo as $cand) {
        if (isset($raw[$cand]) && $raw[$cand] !== '' && $raw[$cand] !== NULL) {
          $interesado_todo_riesgo_val = $raw[$cand];
          break;
        }
      }
    }
    if ($interesado_todo_riesgo_val === NULL || $interesado_todo_riesgo_val === '') {
      foreach ($raw as $k => $v) {
        $k_lower = mb_strtolower(trim((string) $k));
        if (strpos($k_lower, 'todo_riesgo') !== FALSE || strpos($k_lower, 'todo riesgo') !== FALSE) {
          if ($v !== '' && $v !== NULL) {
            $interesado_todo_riesgo_val = $v;
            break;
          }
        }
      }
    }
    $interesado_todo_riesgo = $normalizar_sino($interesado_todo_riesgo_val);

    // 4. interesado_hogar:
    $interesado_hogar_val = $raw['interesado_hogar'] ?? NULL;
    if ($interesado_hogar_val === NULL || $interesado_hogar_val === '') {
      $candidatos_hogar = [
        'interesado_hogar',
        'interesado_prodepem_hogar',
        'esta_interesado_en_hogar',
        'esta_interesado_en_el_hogar',
        'esta_interesado_hogar',
        'interesado_en_hogar',
        'interesado_en_el_hogar',
        'interes_hogar',
        'interes_en_hogar',
        'seguro_hogar',
        'seguros_hogar',
        'poliza_hogar',
        'hogar',
      ];
      foreach ($candidatos_hogar as $cand) {
        if (isset($raw[$cand]) && $raw[$cand] !== '' && $raw[$cand] !== NULL) {
          $interesado_hogar_val = $raw[$cand];
          break;
        }
      }
    }
    if ($interesado_hogar_val === NULL || $interesado_hogar_val === '') {
      foreach ($raw as $k => $v) {
        $k_lower = mb_strtolower(trim((string) $k));
        if (strpos($k_lower, 'hogar') !== FALSE) {
          if ($v !== '' && $v !== NULL) {
            $interesado_hogar_val = $v;
            break;
          }
        }
      }
    }
    $interesado_hogar = $normalizar_sino($interesado_hogar_val);

    // 5. interesado_tecnicomecanica (campo: interesado_prodepem_tecnicomecanica):
    $candidatos_tecnicomecanica = [
      'interesado_prodepem_tecnicomecanica',
      'interesado_tecnicomecanica',
      'esta_interesado_en_tecnicomecanica',
      'esta_interesado_en_la_tecnicomecanica',
      'interesado_en_tecnicomecanica',
      'interes_tecnicomecanica',
      'tecnicomecanica',
      'tecnomecanica',
      'revision_tecnicomecanica',
      'rtm',
    ];
    $interesado_tecnicomecanica_val = $raw['interesado_prodepem_tecnicomecanica'] ?? NULL;
    if ($interesado_tecnicomecanica_val === NULL || $interesado_tecnicomecanica_val === '') {
      foreach ($candidatos_tecnicomecanica as $cand) {
        if (isset($raw[$cand]) && $raw[$cand] !== '' && $raw[$cand] !== NULL) {
          $interesado_tecnicomecanica_val = $raw[$cand];
          break;
        }
      }
    }
    if (($interesado_tecnicomecanica_val === NULL || $interesado_tecnicomecanica_val === '') && $webform) {
      $elementos_webform = $webform->getElementsDecodedAndFlattened();
      if (is_array($elementos_webform)) {
        foreach ($elementos_webform as $elem_k => $elem_def) {
          $k_low = mb_strtolower((string) $elem_k);
          $titulo_low = mb_strtolower((string) ($elem_def['#title'] ?? ''));
          if (strpos($k_low, 'tecnicomecanica') !== FALSE || strpos($k_low, 'tecnomecanica') !== FALSE || strpos($titulo_low, 'tecnicomecanica') !== FALSE || strpos($titulo_low, 'tecnomecanica') !== FALSE) {
            if (isset($raw[$elem_k]) && $raw[$elem_k] !== '' && $raw[$elem_k] !== NULL) {
              $interesado_tecnicomecanica_val = $raw[$elem_k];
              break;
            }
          }
        }
      }
    }
    if ($interesado_tecnicomecanica_val === NULL || $interesado_tecnicomecanica_val === '') {
      foreach ($raw as $k => $v) {
        $k_lower = mb_strtolower(trim((string) $k));
        if (strpos($k_lower, 'tecnicomecanica') !== FALSE || strpos($k_lower, 'tecnomecanica') !== FALSE) {
          if ($v !== '' && $v !== NULL) {
            $interesado_tecnicomecanica_val = $v;
            break;
          }
        }
      }
    }
    $interesado_tecnicomecanica = $normalizar_sino($interesado_tecnicomecanica_val);
    if (empty($interesado_tecnicomecanica) && (!empty($interesado_soat) || !empty($interesado_todo_riesgo) || !empty($interesado_hogar) || !empty($esta_interesado_ampliar_vida))) {
      $interesado_tecnicomecanica = 'NO';
    }

    // 6. recibir_mensajes_vencimiento (campo: le_interesaria_recibir_mensaje_de_vencimiento_de_soat_y_tecnome):
    $candidatos_mensajes = [
      'le_interesaria_recibir_mensaje_de_vencimiento_de_soat_y_tecnome',
      'recibir_mensaje_de_vencimiento_de_soat_y_tecnome',
      'le_interesaria_recibir_mensaje_de_vencimiento',
      'recibir_mensaje_de_vencimiento',
      'recibir_mensajes_vencimiento',
      'mensaje_vencimiento_soat_tecnome',
      'mensajes_vencimiento',
      'recibir_mensajes',
    ];
    $recibir_mensajes_val = $raw['le_interesaria_recibir_mensaje_de_vencimiento_de_soat_y_tecnome'] ?? NULL;
    if ($recibir_mensajes_val === NULL || $recibir_mensajes_val === '') {
      foreach ($candidatos_mensajes as $cand) {
        if (isset($raw[$cand]) && $raw[$cand] !== '' && $raw[$cand] !== NULL) {
          $recibir_mensajes_val = $raw[$cand];
          break;
        }
      }
    }
    if (($recibir_mensajes_val === NULL || $recibir_mensajes_val === '') && $webform) {
      $elementos_webform = $webform->getElementsDecodedAndFlattened();
      if (is_array($elementos_webform)) {
        foreach ($elementos_webform as $elem_k => $elem_def) {
          $k_low = mb_strtolower((string) $elem_k);
          $titulo_low = mb_strtolower((string) ($elem_def['#title'] ?? ''));
          if (strpos($k_low, 'vencimiento') !== FALSE || strpos($titulo_low, 'vencimiento') !== FALSE) {
            if (isset($raw[$elem_k]) && $raw[$elem_k] !== '' && $raw[$elem_k] !== NULL) {
              $recibir_mensajes_val = $raw[$elem_k];
              break;
            }
          }
        }
      }
    }
    if ($recibir_mensajes_val === NULL || $recibir_mensajes_val === '') {
      foreach ($raw as $k => $v) {
        $k_lower = mb_strtolower(trim((string) $k));
        if (strpos($k_lower, 'vencimiento') !== FALSE || (strpos($k_lower, 'mensaje') !== FALSE && strpos($k_lower, 'soat') !== FALSE)) {
          if ($v !== '' && $v !== NULL) {
            $recibir_mensajes_val = $v;
            break;
          }
        }
      }
    }
    $recibir_mensajes_vencimiento = $normalizar_sino($recibir_mensajes_val);
    if (empty($recibir_mensajes_vencimiento) && (!empty($interesado_soat) || !empty($interesado_todo_riesgo) || !empty($interesado_hogar) || !empty($esta_interesado_ampliar_vida))) {
      $recibir_mensajes_vencimiento = 'NO';
    }

    // Heurística de respaldo para SOAT:
    // 1. Si no hay valor explícito pero el solicitante registró placa de vehículo,
    //    indica interés en seguro vehicular (SOAT):
    if (empty($interesado_soat) && !empty($placa)) {
      $interesado_soat = 'SÍ';
    }
    // 2. Si el solicitante completó la sección de complementarios (respondió ampliar amparo, todo riesgo u hogar)
    //    y no se registró interés en SOAT ni placa, normalizar a 'NO' en lugar de dejarlo vacío '-':
    elseif (empty($interesado_soat) && (!empty($interesado_todo_riesgo) || !empty($interesado_hogar) || !empty($esta_interesado_ampliar_vida))) {
      $interesado_soat = 'NO';
    }

    // 6.1. interesado_salud:
    $candidatos_salud = [
      'interesado_salud',
      'interesado_prodepem_salud',
      'esta_interesado_en_salud',
      'esta_interesado_en_el_seguro_de_salud',
      'esta_interesado_salud',
      'interesado_en_salud',
      'interes_salud',
      'interes_en_salud',
      'seguro_salud',
      'seguros_salud',
      'poliza_salud',
    ];
    $interesado_salud_val = $raw['interesado_salud'] ?? NULL;
    if ($interesado_salud_val === NULL || $interesado_salud_val === '') {
      foreach ($candidatos_salud as $cand) {
        if (isset($raw[$cand]) && $raw[$cand] !== '' && $raw[$cand] !== NULL) {
          $interesado_salud_val = $raw[$cand];
          break;
        }
      }
    }
    if (($interesado_salud_val === NULL || $interesado_salud_val === '') && $webform) {
      $elementos_webform = $webform->getElementsDecodedAndFlattened();
      if (is_array($elementos_webform)) {
        foreach ($elementos_webform as $elem_k => $elem_def) {
          $k_low = mb_strtolower((string) $elem_k);
          $titulo_low = mb_strtolower((string) ($elem_def['#title'] ?? ''));
          $es_pregunta_cuestionario = (
            preg_match('/^salud_\d+$/', $k_low) ||
            strpos($k_low, 'condicion') !== FALSE ||
            strpos($k_low, 'explicacion') !== FALSE ||
            strpos($k_low, 'valor') !== FALSE
          );
          if (!$es_pregunta_cuestionario && (
            (strpos($k_low, 'salud') !== FALSE && (strpos($k_low, 'interes') !== FALSE || strpos($k_low, 'seguro') !== FALSE || strpos($k_low, 'poliza') !== FALSE)) ||
            (strpos($titulo_low, 'salud') !== FALSE && (strpos($titulo_low, 'interes') !== FALSE || strpos($titulo_low, 'seguro') !== FALSE))
          )) {
            if (isset($raw[$elem_k]) && $raw[$elem_k] !== '' && $raw[$elem_k] !== NULL) {
              $interesado_salud_val = $raw[$elem_k];
              break;
            }
          }
        }
      }
    }
    if ($interesado_salud_val === NULL || $interesado_salud_val === '') {
      foreach ($raw as $k => $v) {
        $k_lower = mb_strtolower(trim((string) $k));
        if (
          strpos($k_lower, 'salud') !== FALSE &&
          (strpos($k_lower, 'interes') !== FALSE || strpos($k_lower, 'seguro') !== FALSE || strpos($k_lower, 'poliza') !== FALSE) &&
          strpos($k_lower, 'condicion') === FALSE &&
          strpos($k_lower, 'explicacion') === FALSE &&
          strpos($k_lower, 'valor') === FALSE
        ) {
          if ($v !== '' && $v !== NULL) {
            $interesado_salud_val = $v;
            break;
          }
        }
      }
    }
    $interesado_salud = $normalizar_sino($interesado_salud_val);
    if (empty($interesado_salud) && (!empty($interesado_soat) || !empty($interesado_todo_riesgo) || !empty($interesado_hogar) || !empty($esta_interesado_ampliar_vida))) {
      $interesado_salud = 'NO';
    }

    // 7. seguros_adicionales:
    $seguros_adicionales = '';
    $raw_seg_adic = $raw['seguros_adicionales'] ?? $raw['seguros_de_interes'] ?? [];
    if (is_array($raw_seg_adic)) {
      $seg_list = [];
      foreach ($raw_seg_adic as $sub_k => $sub_v) {
        if ($sub_v && $sub_v !== '0') {
          $val_to_add = is_string($sub_k) && !is_numeric($sub_k) ? $sub_k : $sub_v;
          if (is_string($val_to_add)) {
            $val_low = mb_strtolower(trim($val_to_add));
            if (!in_array($val_low, ['soat', 'todo_riesgo', 'hogar', 'tecnicomecanica', 'tecnomecanica', 'vencimiento', 'no', '0', 'ninguno', 'ninguna'])) {
              $seg_list[] = $val_to_add;
            }
          }
        }
      }
      $seguros_adicionales = implode(', ', array_unique($seg_list));
    }
    elseif (is_string($raw_seg_adic)) {
      $s_trim = trim($raw_seg_adic);
      if (!in_array(mb_strtolower($s_trim), ['no', '0', 'ninguno', 'ninguna', 'ningun', 'n/a', 'none', 'false', 'off'])) {
        $seguros_adicionales = $s_trim;
      }
    }

    if (empty($seguros_adicionales)) {
      $seg_list = [];
      foreach ($raw as $k => $v) {
        $k_lower = mb_strtolower($k);
        if (
          in_array($k_lower, [
            'esta_interesado_en_ampliar_el_amparo_de_vida', 'por_que', 'porque',
            'interesado_prodepem_soat', 'interesado_soat', 'esta_interesado_en_soat', 'esta_interesado_en_el_soat', 'esta_interesado_soat', 'interesado_en_soat', 'interesado_en_el_soat', 'interes_soat', 'desea_soat', 'seguro_soat', 'soat',
            'interesado_todo_riesgo', 'interesado_prodepem_todo_riesgo', 'esta_interesado_en_todo_riesgo', 'interesado_en_todo_riesgo', 'interes_todo_riesgo', 'seguro_todo_riesgo', 'todo_riesgo',
            'interesado_prodepem_hogar', 'interesado_hogar', 'esta_interesado_en_hogar', 'interesado_en_hogar', 'interes_hogar', 'seguro_hogar', 'hogar',
            'interesado_prodepem_tecnicomecanica', 'interesado_tecnicomecanica', 'esta_interesado_en_tecnicomecanica', 'tecnicomecanica', 'tecnomecanica',
            'interesado_salud', 'interesado_prodepem_salud', 'esta_interesado_en_salud', 'interesado_en_salud', 'interes_salud', 'seguro_salud',
            'le_interesaria_recibir_mensaje_de_vencimiento_de_soat_y_tecnome', 'recibir_mensaje_de_vencimiento_de_soat_y_tecnome', 'recibir_mensajes_vencimiento',
            'placa', 'placas', 'numero_placa', 'placa_vehiculo',
            'numero_de_rifa_1', 'numero_de_rifa_2', 'numero_de_rifa', 'numero_rifa', 'rifa', 'boleta'
          ], TRUE) ||
          strpos($k_lower, 'soat') !== FALSE ||
          strpos($k_lower, 'tecnicomecanica') !== FALSE ||
          strpos($k_lower, 'tecnomecanica') !== FALSE ||
          strpos($k_lower, 'vencimiento') !== FALSE
        ) {
          continue;
        }
        if (strpos($k_lower, 'interes') !== FALSE || strpos($k_lower, 'adicional') !== FALSE) {
          if (is_array($v)) {
            foreach ($v as $sub_k => $sub_v) {
              if ($sub_v && $sub_v !== '0') {
                $seg_list[] = is_string($sub_k) && !is_numeric($sub_k) ? $sub_k : $sub_v;
              }
            }
          }
          elseif (is_string($v) && !empty($v) && !in_array(mb_strtolower(trim($v)), ['si', '1', 'no', '0', 'sí', 'ninguno', 'ninguna'])) {
            $seg_list[] = $v;
          }
        }
      }
      $seguros_adicionales = implode(', ', array_unique($seg_list));
    }

    // 8. numero_de_rifa_1 y numero_de_rifa_2:
    $numero_de_rifa_1 = trim((string) ($raw['numero_de_rifa_1'] ?? $raw['numero_rifa_1'] ?? $raw['rifa_1'] ?? $raw['boleta_1'] ?? ''));
    $numero_de_rifa_2 = trim((string) ($raw['numero_de_rifa_2'] ?? $raw['numero_rifa_2'] ?? $raw['rifa_2'] ?? $raw['boleta_2'] ?? ''));
    if (empty($numero_de_rifa_1)) {
      $rifa_gen = trim((string) ($raw['numero_de_rifa'] ?? $raw['numero_rifa'] ?? $raw['rifa'] ?? $raw['boleta'] ?? ''));
      if (!empty($rifa_gen)) {
        $numero_de_rifa_1 = $rifa_gen;
      }
    }

    // Otros campos fuera del formulario original:
    $otros_adicionales = [];
    $keys_originales = [
      'tomador', 'nit_tomador', 'c_c_nit', 'direccion_tomador', 'direccion', 'ciudad_tomador', 'ciudad', 'telefono_tomador', 'telefono',
      'selector_tomador', 'nombre_completo', 'nombre', 'nombres', 'apellidos', 'primer_apellido', 'segundo_apellido', 'primer_nombre', 'segundo_nombre',
      'asegurado_principal', 'apellido_asegurado_principal', 'nombre_del_asegurado', 'nombre_asegurado', 'asegurado', 'nombres_y_apellidos', 'nombre_y_apellidos',
      'tipo_de_documento', 'tipo_documento', 'numero_de_documento', 'numero_documento', 'documento', 'c_c', 'cedula', 'identificacion', 'numero_de_identificacion', 'numero_identificacion',
      'email', 'correo_electronico', 'correo', 'telefono_asegurado', 'celular', 'numero_contacto',
      'direccion_residencia', 'lugar_residencia', 'trabaja_actualmente', 'trabaja_usted_actualmente',
      'ocupacion', 'cargo', 'fecha_de_nacimiento', 'fecha_nacimiento', 'f_nacimiento', 'nacimiento', 'estado_civil',
      'valor_asegurado_solicitado', 'total_valor_asegurado', 'valor_asegurado', 'plan',
      'beneficiarios', 'tabla_beneficiarios', 'tabla_de_beneficiarios', 'tabla_de_beneficiarios_2', 'beneficiario', 'beneficiarios_tabla', 'datos_beneficiarios', 'designacion_beneficiarios', 'designacion_de_beneficiarios', 'beneficiarios_del_seguro', 'datos_de_los_beneficiarios', 'escriba_el_nombre_de_los_beneficiarios_de_este_seguro_y_su_respectivo_porcentaje', 'escriba_el_nombre_de_los_beneficiarios',
      'peso', 'peso_kg', 'estatura', 'estatura_cm', 'altura', 'edad', 'imc', 'valor_condicion_salud', 'explicacion_salud', 'explicacion_condiciones_salud', 'en_caso_de_haber_marcado_alguna_de_las_condiciones_anteriores_o', 'favor_explicar_detalladamente',
      'esta_interesado_en_ampliar_el_amparo_de_vida', 'por_que', 'porque',
      'interesado_prodepem_soat', 'interesado_soat', 'esta_interesado_en_soat', 'esta_interesado_en_el_soat', 'esta_interesado_soat', 'interesado_en_soat', 'interesado_en_el_soat', 'interes_soat', 'interes_en_soat', 'desea_soat', 'seguro_soat', 'soat', 'SOAT',
      'interesado_todo_riesgo', 'interesado_prodepem_todo_riesgo', 'esta_interesado_en_todo_riesgo', 'interesado_en_todo_riesgo', 'interes_todo_riesgo', 'seguro_todo_riesgo', 'todo_riesgo',
      'interesado_prodepem_hogar', 'interesado_hogar', 'esta_interesado_en_hogar', 'interesado_en_hogar', 'interes_hogar', 'seguro_hogar', 'hogar',
      'interesado_prodepem_tecnicomecanica', 'interesado_tecnicomecanica', 'esta_interesado_en_tecnicomecanica', 'tecnicomecanica', 'tecnomecanica',
      'interesado_salud', 'interesado_prodepem_salud', 'esta_interesado_en_salud', 'interesado_en_salud', 'interes_salud', 'seguro_salud',
      'le_interesaria_recibir_mensaje_de_vencimiento_de_soat_y_tecnome', 'recibir_mensaje_de_vencimiento_de_soat_y_tecnome', 'recibir_mensajes_vencimiento',
      'seguros_adicionales', 'placa', 'placas', 'numero_placa', 'placa_vehiculo', 'numero_de_rifa_1', 'numero_de_rifa_2', 'numero_de_rifa', 'numero_rifa', 'rifa', 'boleta',
    ];
    for ($i = 1; $i <= 14; $i++) {
      $keys_originales[] = 'salud_' . $i;
    }

    foreach ($raw as $k => $v) {
      $k_lower = mb_strtolower((string) $k);
      if (
        in_array($k, $keys_originales, TRUE) ||
        strpos($k_lower, 'salud') !== FALSE ||
        strpos($k_lower, 'interes') !== FALSE ||
        strpos($k_lower, 'rifa') !== FALSE ||
        strpos($k_lower, 'sorteo') !== FALSE ||
        strpos($k_lower, 'boleta') !== FALSE ||
        strpos($k_lower, 'beneficiar') !== FALSE ||
        strpos($k_lower, 'asegurad') !== FALSE ||
        strpos($k_lower, 'tomador') !== FALSE ||
        strpos($k_lower, 'documento') !== FALSE ||
        strpos($k_lower, 'cedula') !== FALSE
      ) {
        continue;
      }
      if (is_string($v) && !empty(trim($v))) {
        $otros_adicionales[$k] = trim($v);
      }
    }

    $otros_adicionales_texto = !empty($otros_adicionales)
      ? implode(', ', array_map(function ($k, $v) {
          return ucwords(str_replace('_', ' ', $k)) . ': ' . $v;
        }, array_keys($otros_adicionales), $otros_adicionales))
      : '';

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
      'c_c' => !empty($raw['c_c']) ? $raw['c_c'] : $num_doc,
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
      'fecha_nacimiento' => $fecha_nacimiento,
      'fecha_nacimiento_raw' => $fecha_nac_raw,
      'edad' => $edad,
      'es_mayor_71' => $es_mayor_71,
      'peso' => $peso,
      'estatura' => $estatura,
      'imc' => $imc,
      'condiciones_marcadas' => $condiciones_marcadas_titulos,
      'condiciones_marcadas_keys' => $condiciones_marcadas_keys,
      'condiciones_salud_texto' => $condiciones_salud_texto,
      'condiciones_salud_count' => $salud_positivas,
      'explicacion_condiciones' => $explicacion_condiciones,
      'valor_condicion_salud' => $valor_condicion_salud,
      // Datos complementarios:
      'esta_interesado_en_ampliar_el_amparo_de_vida' => $esta_interesado_ampliar_vida,
      'por_que' => $por_que,
      'interesado_soat' => $interesado_soat,
      'interesado_todo_riesgo' => $interesado_todo_riesgo,
      'interesado_hogar' => $interesado_hogar,
      'interesado_tecnicomecanica' => $interesado_tecnicomecanica,
      'interesado_salud' => $interesado_salud,
      'recibir_mensajes_vencimiento' => $recibir_mensajes_vencimiento,
      'seguros_adicionales' => $seguros_adicionales,
      'seguros_adicionales_texto' => $seguros_adicionales,
      'placa' => $placa,
      'numero_de_rifa_1' => $numero_de_rifa_1,
      'numero_de_rifa_2' => $numero_de_rifa_2,
      'interes_otros' => (!empty($seguros_adicionales) || $interesado_soat === 'SÍ' || $interesado_todo_riesgo === 'SÍ' || $interesado_hogar === 'SÍ' || $interesado_tecnicomecanica === 'SÍ' || $interesado_salud === 'SÍ' || $recibir_mensajes_vencimiento === 'SÍ') ? 'SÍ' : 'NO',
      'participa_rifa' => (!empty($numero_de_rifa_1) || !empty($numero_de_rifa_2)) ? 'SÍ' : 'NO',
      'rifa_texto' => trim($numero_de_rifa_1 . (!empty($numero_de_rifa_2) ? ' / ' . $numero_de_rifa_2 : '')),
      'otros_adicionales' => $otros_adicionales_texto,
      'otros_adicionales_lista' => $otros_adicionales,
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
    $webform_id = $sub->getWebform()->id();
    $selector = (string) ($raw['selector_tomador'] ?? '2');
    $tomador_text = mb_strtolower((string) ($raw['tomador'] ?? ''));

    if ($webform_id === 'formulario_equidad_seguros_prode' || strpos($tomador_text, 'prodepem') !== FALSE) {
      $tomador_nombre = 'PRODEPEM S.A.S';
      $tomador_nit = '900.582.164-1';
      $tomador_dir = 'Cra 6 # 77-305';
      $tomador_ciudad = 'Montería';
      $tomador_tel = '3242560489';
    }
    else {
      $is_foneducor = ($selector === '1' || strpos($tomador_text, 'fondo') !== FALSE || strpos($tomador_text, 'cordoba') !== FALSE || strpos($tomador_text, 'foneducor') !== FALSE);
      $is_aspu = ($selector === '2' || strpos($tomador_text, 'aspu') !== FALSE || strpos($tomador_text, 'sindical') !== FALSE || !$is_foneducor);

      $tomador_nombre = $is_aspu ? 'Asociación Sindical de Profesores Universitarios - ASPU' : 'Fondo de empleado de docente de la Universidad de Córdoba';
      $tomador_nit = $is_aspu ? '830001998' : '900834726';
      $tomador_dir = $is_aspu ? 'Cra 6 # 77-305' : 'Cra 6 # 76-103';
      $tomador_ciudad = 'Montería';
      $tomador_tel = $is_aspu ? '3242560489' : '3014619585';
    }

    // Beneficiarios completos (usar tabla_de_beneficiarios, o tabla_de_beneficiarios_2 si está vacía):
    $bene_list = self::obtenerBeneficiariosNormalizados($raw);

    // Salud completa (14 preguntas del cuestionario de salud)
    $salud_def = self::getDefinicionCamposSalud();
    $salud_respuestas = [];
    $num = 1;
    $webform = $sub->getWebform();
    foreach ($salud_def as $campo_key => $info) {
      $titulo = $info['titulo'];
      if ($webform) {
        $elem = $webform->getElement($campo_key);
        if (!empty($elem['#title'])) {
          $titulo = trim(strip_tags((string) $elem['#title']));
        }
      }
      $val = NULL;
      foreach ($info['aliases'] as $alias) {
        if (isset($raw[$alias]) && $raw[$alias] !== '' && $raw[$alias] !== NULL) {
          $val = $raw[$alias];
          break;
        }
      }
      $is_true = FALSE;
      if (is_bool($val)) {
        $is_true = $val === TRUE;
      }
      elseif (is_numeric($val)) {
        $is_true = (int) $val === 1;
      }
      elseif (is_string($val)) {
        $v = mb_strtolower(trim($val));
        $is_true = in_array($v, ['1', 'true', 'si', 'sí', 'yes', 'on', 'checked'], TRUE);
      }

      $salud_respuestas[] = [
        'num' => $num++,
        'pregunta' => $titulo,
        'respuesta' => $is_true ? 'SÍ' : 'NO',
      ];
    }

    return [
      'tomador' => [
        'nombre' => !empty($raw['tomador']) ? $raw['tomador'] : $tomador_nombre,
        'nit' => !empty($raw['c_c_nit']) ? $raw['c_c_nit'] : (!empty($raw['nit_tomador']) ? $raw['nit_tomador'] : $tomador_nit),
        'direccion' => !empty($raw['direccion']) ? $raw['direccion'] : (!empty($raw['direccion_tomador']) ? $raw['direccion_tomador'] : $tomador_dir),
        'ciudad' => !empty($raw['ciudad']) ? $raw['ciudad'] : (!empty($raw['ciudad_tomador']) ? $raw['ciudad_tomador'] : $tomador_ciudad),
        'telefono' => !empty($raw['telefono']) ? $raw['telefono'] : (!empty($raw['telefono_tomador']) ? $raw['telefono_tomador'] : $tomador_tel),
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
        'fecha_nacimiento' => !empty($item['fecha_nacimiento']) ? $item['fecha_nacimiento'] : ($raw['fecha_de_nacimiento'] ?? $raw['fecha_nacimiento'] ?? ''),
        'edad' => $item['edad'],
        'es_mayor_71' => $item['es_mayor_71'] ?? FALSE,
        'estado_civil' => $raw['estado_civil'] ?? '',
        'valor_solicitado' => $item['valor_asegurado'],
        'total_asegurado' => $item['total_asegurado'],
      ],
      'adicionales' => [
        'esta_interesado_en_ampliar_el_amparo_de_vida' => $item['esta_interesado_en_ampliar_el_amparo_de_vida'],
        'por_que' => $item['por_que'],
        'interesado_soat' => $item['interesado_soat'],
        'interesado_todo_riesgo' => $item['interesado_todo_riesgo'],
        'interesado_hogar' => $item['interesado_hogar'],
        'interesado_tecnicomecanica' => $item['interesado_tecnicomecanica'],
        'interesado_salud' => $item['interesado_salud'],
        'recibir_mensajes_vencimiento' => $item['recibir_mensajes_vencimiento'],
        'seguros_adicionales' => $item['seguros_adicionales'],
        'placa' => $item['placa'],
        'numero_de_rifa_1' => $item['numero_de_rifa_1'],
        'numero_de_rifa_2' => $item['numero_de_rifa_2'],
        'interes_otros' => $item['interes_otros'],
        'participa_rifa' => $item['participa_rifa'],
        'rifa_detalle' => $item['rifa_texto'],
        'otros_campos' => $item['otros_adicionales'],
        'otros_campos_lista' => $item['otros_adicionales_lista'],
      ],
      'beneficiarios' => $bene_list,
      'salud' => [
        'peso' => $item['peso'],
        'estatura' => $item['estatura'],
        'imc' => $item['imc'],
        'valor_condicion_salud' => $item['valor_condicion_salud'],
        'respuestas' => $salud_respuestas,
        'explicacion' => !empty($item['explicacion_condiciones']) ? $item['explicacion_condiciones'] : 'Ninguna condición declarada.',
      ],
    ];
  }

  /**
   * Construye el documento Excel XML Spreadsheet para descarga.
   */
  protected function construirXmlExcel(array $items, $tomador, $tipo, array $filtros_extra = []) {
    $nombre_tomador = ($tomador === 'prodepem') ? 'PRODEPEM' : (($tomador === 'aspu') ? 'ASPU' : 'FONEDUCOR');
    if ($tipo === 'rifas') {
      $titulo_hoja = 'Boletas Rifa ' . $nombre_tomador;
    }
    elseif ($tipo === 'adicionales') {
      $titulo_hoja = 'Datos Complementarios ' . $nombre_tomador;
    }
    else {
      $titulo_hoja = 'Seguro Vida ' . $nombre_tomador;
    }

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

    $colspan_total = ($tipo === 'vida') ? '18' : ($tipo === 'rifas' ? '3' : '16');

    $meta_filtro = '';
    if (!empty($filtros_extra['tarifa'])) {
      if ($filtros_extra['tarifa'] === 'mayor_71') {
        $meta_filtro = ' | Filtro: Personas con más de 71 años';
      }
      else {
        $meta_filtro = ' | Filtro Tarifa: $' . number_format((int) $filtros_extra['tarifa'], 0, ',', '.');
      }
    }
    elseif (!empty($filtros_extra['filtro_salud'])) {
      $meta_filtro = ' | Filtro Salud: ' . ($filtros_extra['filtro_salud'] === 'con_condiciones' ? 'Con Condiciones Médicas' : 'Sin Condiciones (Sanos)');
    }
    elseif (!empty($filtros_extra['condicion'])) {
      $def_salud = self::getDefinicionCamposSalud();
      $nom_cond = $def_salud[$filtros_extra['condicion']]['titulo'] ?? $filtros_extra['condicion'];
      $meta_filtro = ' | Filtro Condición: ' . $nom_cond;
    }

    echo '<table border="1">' . "\n";
    echo '<tr><td colspan="' . $colspan_total . '" class="header-title">REPORTE DE SOLICITUDES DE EQUIDAD SEGUROS - ' . htmlspecialchars($nombre_tomador) . ' (' . ($tipo === 'adicionales' ? 'DATOS COMPLEMENTARIOS' : 'SEGURO VIDA') . ')</td></tr>' . "\n";
    echo '<tr><td colspan="' . $colspan_total . '" class="header-meta">Generado el: ' . date('d/m/Y H:i:s') . ' | Total Registros: ' . count($items) . htmlspecialchars($meta_filtro) . '</td></tr>' . "\n";
    echo '<tr></tr>' . "\n";

    if ($tipo === 'rifas') {
      echo '<table border="1">' . "\n";
      echo '<tr><td colspan="3" class="header-title">REPORTE DE BOLETAS DE RIFA</td></tr>' . "\n";
      echo '<tr><td colspan="3" class="header-meta">Generado el: ' . date('d/m/Y H:i:s') . ' | Total Registros: ' . count($items) . '</td></tr>' . "\n";
      echo '<tr></tr>' . "\n";
      echo '<tr>' . "\n";
      echo '  <th>c_c</th>' . "\n";
      echo '  <th>numero_de_rifa_1</th>' . "\n";
      echo '  <th>numero_de_rifa_2</th>' . "\n";
      echo '</tr>' . "\n";

      foreach ($items as $it) {
        $cc_val = !empty($it['c_c']) ? $it['c_c'] : (!empty($it['documento']) ? $it['documento'] : '');
        echo '<tr>' . "\n";
        echo '  <td class="text-center">' . htmlspecialchars((string) $cc_val) . '</td>' . "\n";
        echo '  <td class="text-center">' . htmlspecialchars((string) ($it['numero_de_rifa_1'] ?? '')) . '</td>' . "\n";
        echo '  <td class="text-center">' . htmlspecialchars((string) ($it['numero_de_rifa_2'] ?? '')) . '</td>' . "\n";
        echo '</tr>' . "\n";
      }
      echo '</table></body></html>' . "\n";
      return ob_get_clean();
    }
    elseif ($tipo === 'vida') {
      echo '<tr>' . "\n";
      echo '  <th>SID</th>' . "\n";
      echo '  <th>FECHA</th>' . "\n";
      echo '  <th>ASEGURADO PRINCIPAL</th>' . "\n";
      echo '  <th>TIPO DOC</th>' . "\n";
      echo '  <th>N° DOCUMENTO</th>' . "\n";
      echo '  <th>FECHA DE NACIMIENTO</th>' . "\n";
      echo '  <th>EDAD (AÑOS)</th>' . "\n";
      echo '  <th>CORREO ELECTRÓNICO</th>' . "\n";
      echo '  <th>TELÉFONO</th>' . "\n";
      echo '  <th>OCUPACIÓN / CARGO</th>' . "\n";
      echo '  <th>VALOR SOLICITADO</th>' . "\n";
      echo '  <th>BENEFICIARIOS</th>' . "\n";
      echo '  <th>PESO</th>' . "\n";
      echo '  <th>ESTATURA</th>' . "\n";
      echo '  <th>ÍNDICE DE MASA CORPORAL (IMC)</th>' . "\n";
      echo '  <th>CONDICIONES DE SALUD (MARCADAS)</th>' . "\n";
      echo '  <th>VALOR CONDICIÓN SALUD</th>' . "\n";
      echo '  <th>EXPLICACIÓN CONDICIONES</th>' . "\n";
      echo '</tr>' . "\n";

      foreach ($items as $it) {
        echo '<tr>' . "\n";
        echo '  <td class="text-center">' . htmlspecialchars((string) $it['sid']) . '</td>' . "\n";
        echo '  <td class="text-center">' . htmlspecialchars((string) $it['fecha']) . '</td>' . "\n";
        echo '  <td>' . htmlspecialchars((string) $it['nombre']) . '</td>' . "\n";
        echo '  <td class="text-center">' . htmlspecialchars((string) $it['tipo_documento']) . '</td>' . "\n";
        echo '  <td class="text-center">' . htmlspecialchars((string) $it['documento']) . '</td>' . "\n";
        echo '  <td class="text-center">' . htmlspecialchars((string) ($it['fecha_nacimiento'] ?: '-')) . '</td>' . "\n";
        echo '  <td class="text-center">' . htmlspecialchars((string) ($it['edad'] !== '' ? $it['edad'] : '-')) . '</td>' . "\n";
        echo '  <td>' . htmlspecialchars((string) $it['email']) . '</td>' . "\n";
        echo '  <td class="text-center">' . htmlspecialchars((string) $it['telefono']) . '</td>' . "\n";
        echo '  <td>' . htmlspecialchars(trim($it['ocupacion'] . ' - ' . $it['cargo'], ' -')) . '</td>' . "\n";
        echo '  <td class="text-right">' . htmlspecialchars((string) $it['valor_asegurado']) . '</td>' . "\n";
        echo '  <td>' . htmlspecialchars((string) ($it['beneficiarios_nombres'] ?: 'Sin beneficiarios')) . '</td>' . "\n";
        echo '  <td class="text-center">' . htmlspecialchars((string) ($it['peso'] ?: '-')) . '</td>' . "\n";
        echo '  <td class="text-center">' . htmlspecialchars((string) ($it['estatura'] ?: '-')) . '</td>' . "\n";
        echo '  <td class="text-center">' . htmlspecialchars((string) ($it['imc'] ?: '-')) . '</td>' . "\n";
        echo '  <td>' . htmlspecialchars((string) ($it['condiciones_salud_texto'] ?: 'Ninguna')) . '</td>' . "\n";
        echo '  <td class="text-right" style="mso-number-format:\'0\';">' . htmlspecialchars((string) ($it['valor_condicion_salud'] ?? 8000)) . '</td>' . "\n";
        echo '  <td>' . htmlspecialchars((string) ($it['explicacion_condiciones'] ?: '-')) . '</td>' . "\n";
        echo '</tr>' . "\n";
      }
    }
    else {
      // Tipo adicionales (datos complementarios)
      echo '<tr>' . "\n";
      echo '  <th>SID</th>' . "\n";
      echo '  <th>FECHA</th>' . "\n";
      echo '  <th>SOLICITANTE</th>' . "\n";
      echo '  <th>TIPO DOC</th>' . "\n";
      echo '  <th>N° DOCUMENTO</th>' . "\n";
      echo '  <th>TELÉFONO</th>' . "\n";
      echo '  <th>ESTÁ INTERESADO EN AMPLIAR EL AMPARO DE VIDA / POR QUÉ</th>' . "\n";
      echo '  <th>INTERESADO SOAT</th>' . "\n";
      echo '  <th>INTERESADO TODO RIESGO</th>' . "\n";
      echo '  <th>INTERESADO HOGAR</th>' . "\n";
      echo '  <th>INTERESADO TÉCNICO MECÁNICA</th>' . "\n";
      echo '  <th>MENSAJES VENCIMIENTO SOAT/RTM</th>' . "\n";
      echo '  <th>SEGUROS ADICIONALES</th>' . "\n";
      echo '  <th>PLACA</th>' . "\n";
      echo '  <th>NÚMERO DE RIFA 1</th>' . "\n";
      echo '  <th>NÚMERO DE RIFA 2</th>' . "\n";
      echo '</tr>' . "\n";

      foreach ($items as $it) {
        $ampliar_texto = $it['esta_interesado_en_ampliar_el_amparo_de_vida'] ?: '-';
        if (!empty($it['por_que'])) {
          $ampliar_texto = ($it['esta_interesado_en_ampliar_el_amparo_de_vida'] ? $it['esta_interesado_en_ampliar_el_amparo_de_vida'] . ' - ' : '') . 'Razón: ' . $it['por_que'];
        }

        echo '<tr>' . "\n";
        echo '  <td class="text-center">' . htmlspecialchars((string) $it['sid']) . '</td>' . "\n";
        echo '  <td class="text-center">' . htmlspecialchars((string) $it['fecha']) . '</td>' . "\n";
        echo '  <td>' . htmlspecialchars((string) $it['nombre']) . '</td>' . "\n";
        echo '  <td class="text-center">' . htmlspecialchars((string) $it['tipo_documento']) . '</td>' . "\n";
        echo '  <td class="text-center">' . htmlspecialchars((string) $it['documento']) . '</td>' . "\n";
        echo '  <td class="text-center">' . htmlspecialchars((string) $it['telefono']) . '</td>' . "\n";
        echo '  <td>' . htmlspecialchars((string) $ampliar_texto) . '</td>' . "\n";
        echo '  <td class="text-center">' . htmlspecialchars((string) ($it['interesado_soat'] ?: '-')) . '</td>' . "\n";
        echo '  <td class="text-center">' . htmlspecialchars((string) ($it['interesado_todo_riesgo'] ?: '-')) . '</td>' . "\n";
        echo '  <td class="text-center">' . htmlspecialchars((string) ($it['interesado_hogar'] ?: '-')) . '</td>' . "\n";
        echo '  <td class="text-center">' . htmlspecialchars((string) ($it['interesado_tecnicomecanica'] ?: '-')) . '</td>' . "\n";
        echo '  <td class="text-center">' . htmlspecialchars((string) ($it['recibir_mensajes_vencimiento'] ?: '-')) . '</td>' . "\n";
        echo '  <td>' . htmlspecialchars((string) ($it['seguros_adicionales'] ?: '-')) . '</td>' . "\n";
        echo '  <td class="text-center">' . htmlspecialchars((string) ($it['placa'] ?: '-')) . '</td>' . "\n";
        echo '  <td class="text-center">' . htmlspecialchars((string) ($it['numero_de_rifa_1'] ?: '-')) . '</td>' . "\n";
        echo '  <td class="text-center">' . htmlspecialchars((string) ($it['numero_de_rifa_2'] ?: '-')) . '</td>' . "\n";
        echo '</tr>' . "\n";
      }
    }
    echo '</table>' . "\n";
    echo '</body>' . "\n";
    echo '</html>' . "\n";

    return ob_get_clean();
  }

  /**
   * Intenta parsear una cadena de fecha a un objeto DateTime con hora 00:00:00.
   *
   * @param string|null $fecha_str
   *   Cadena de fecha a parsear.
   *
   * @return \DateTime|null
   *   Objeto DateTime o NULL si la fecha no es válida.
   */
  public static function parsearFecha($fecha_str): ?\DateTime {
    if (empty($fecha_str)) {
      return NULL;
    }
    $fecha_str = trim((string) $fecha_str);
    $formatos = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d', 'Y-m-d H:i:s', 'd/m/Y H:i:s'];
    foreach ($formatos as $fmt) {
      $d = \DateTime::createFromFormat($fmt, $fecha_str);
      if ($d && $d->format($fmt) === $fecha_str) {
        $d->setTime(0, 0, 0);
        return $d;
      }
    }
    $ts = strtotime(str_replace('/', '-', $fecha_str));
    if ($ts !== FALSE && $ts > 0) {
      $d = new \DateTime();
      $d->setTimestamp($ts);
      $d->setTime(0, 0, 0);
      return $d;
    }
    return NULL;
  }

  /**
   * Normaliza y formatea una fecha al formato estándar d/m/Y.
   *
   * @param string|null $fecha_str
   *   Cadena de fecha.
   *
   * @return string
   *   Fecha en formato d/m/Y o la cadena original si no se pudo parsear.
   */
  public static function formatearFecha($fecha_str): string {
    $d = self::parsearFecha($fecha_str);
    return $d ? $d->format('d/m/Y') : trim((string) $fecha_str);
  }

  /**
   * Calcula la edad en años cumplidos a partir de la fecha de nacimiento.
   *
   * @param string|null $fecha_str
   *   Fecha de nacimiento.
   *
   * @return string
   *   Edad en años (entero como string) o cadena vacía si no se puede calcular.
   */
  public static function calcularEdad($fecha_str): string {
    $d = self::parsearFecha($fecha_str);
    if (!$d) {
      return '';
    }
    $hoy = new \DateTime('today');
    if ($d > $hoy) {
      return '';
    }
    $diff = $d->diff($hoy);
    return (string) $diff->y;
  }

  /**
   * Calcula el Índice de Masa Corporal (IMC = peso_kg / (estatura_m)^2).
   *
   * Soporta estatura en centímetros (ej: 170) o en metros (ej: 1.70).
   * Soporta peso en kilogramos con decimales y texto de unidades (ej: 75, 75.5, 75 kg).
   *
   * @param mixed $peso_raw
   *   Peso ingresado.
   * @param mixed $estatura_raw
   *   Estatura / altura ingresada.
   *
   * @return string
   *   IMC formateado a 2 decimales (ej: '24.22') o cadena vacía si no es calculable.
   */
  public static function calcularImc($peso_raw, $estatura_raw): string {
    if (empty($peso_raw) || empty($estatura_raw)) {
      return '';
    }

    $limpiar_numero = function ($val) {
      $val = str_replace(',', '.', trim((string) $val));
      if (preg_match('/[0-9]+(?:\.[0-9]+)?/', $val, $matches)) {
        return (float) $matches[0];
      }
      return 0.0;
    };

    $peso = $limpiar_numero($peso_raw);
    $estatura = $limpiar_numero($estatura_raw);

    if ($peso <= 0.0 || $estatura <= 0.0) {
      return '';
    }

    // Si la estatura fue ingresada en centímetros (ej: 140 - 250 cm), convertir a metros:
    if ($estatura > 3.0) {
      $estatura = $estatura / 100.0;
    }

    // Rango fisiológico admisible en metros (0.5 m a 2.5 m)
    if ($estatura < 0.5 || $estatura > 2.5) {
      return '';
    }

    $imc = $peso / ($estatura * $estatura);

    return number_format($imc, 2, '.', '');
  }

  /**
   * Normaliza una cadena de texto para comparaciones (minúsculas, sin tildes).
   *
   * @param string|null $texto
   *   Cadena de texto.
   *
   * @return string
   *   Cadena normalizada.
   */
  public static function normalizarTextoParaBusqueda($texto): string {
    if (empty($texto)) {
      return '';
    }
    $texto = function_exists('mb_strtolower') ? mb_strtolower(trim((string) $texto), 'UTF-8') : strtolower(trim((string) $texto));
    $tildes = [
      'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
      'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u',
      'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
      'ñ' => 'n', 'Ñ' => 'n',
    ];
    return strtr($texto, $tildes);
  }

  /**
   * Calcula el valor numérico según condiciones de salud e índice de masa corporal (IMC).
   *
   * Reglas de negocio:
   * 1. Si en condiciones de salud contiene 'hipertensión', 'presión arterial' o 'sobrepeso' -> 10000.
   * 2. En otro caso, si contiene 'obesidad' y el IMC es mayor a 30 -> 12000.
   * 3. Para las que no cumplen con las condiciones mencionadas -> 8000.
   *
   * @param string|null $condiciones_salud_texto
   *   Texto de las condiciones de salud declaradas.
   * @param string|null $explicacion_condiciones
   *   Texto explicativo de condiciones de salud.
   * @param mixed $imc
   *   Índice de masa corporal calculado.
   *
   * @return int
   *   Valor numérico correspondiente (10000, 12000 u 8000).
   */
  public static function calcularValorCondicionSalud($condiciones_salud_texto, $explicacion_condiciones, $imc): int {
    $texto_completo = self::normalizarTextoParaBusqueda(
      ($condiciones_salud_texto ?? '') . ' ' . ($explicacion_condiciones ?? '')
    );

    // Regla 1: contiene 'hipertensión', 'presion arterial' o 'sobrepeso' -> 10000.
    if (
      strpos($texto_completo, 'hipertension') !== FALSE ||
      strpos($texto_completo, 'presion arterial') !== FALSE ||
      strpos($texto_completo, 'sobrepeso') !== FALSE
    ) {
      return 10000;
    }

    // Regla 2: en otro caso, contiene 'obesidad' y el IMC está por encima de 30 -> 12000.
    $imc_num = 0.0;
    if (!empty($imc)) {
      $imc_clean = str_replace(',', '.', trim((string) $imc));
      if (is_numeric($imc_clean)) {
        $imc_num = (float) $imc_clean;
      }
    }

    if (strpos($texto_completo, 'obesidad') !== FALSE && $imc_num > 30.0) {
      return 12000;
    }

    // Regla 3: para las que no cumplen con las condiciones mencionadas -> 8000.
    return 8000;
  }

  /**
   * Definición centralizada de los 14 campos del cuestionario de salud.
   *
   * @return array
   *   Arreglo de campos de salud con títulos por defecto y alias.
   */
  public static function getDefinicionCamposSalud(): array {
    return [
      'afecciones_cardiovasculares' => [
        'titulo' => 'Afecciones cardiovasculares',
        'aliases' => ['afecciones_cardiovasculares', 'salud_1'],
      ],
      'afecciones_cerebrovasculares' => [
        'titulo' => 'Afecciones cerebrovasculares',
        'aliases' => ['afecciones_cerebrovasculares', 'salud_2'],
      ],
      'durante_los_ultimos_4_anos_ha_sido_diagnosticado_o_tratado_por' => [
        'titulo' => '¿Durante los últimos 4 años ha sido diagnosticado o tratado por alguna enfermedad oncológica?',
        'aliases' => ['durante_los_ultimos_4_anos_ha_sido_diagnosticado_o_tratado_por', 'enfermedad_oncologica', 'salud_6'],
      ],
      'diabetes' => [
        'titulo' => 'Diabetes',
        'aliases' => ['diabetes', 'salud_5'],
      ],
      'vih_positivo_sida' => [
        'titulo' => 'VIH positivo / SIDA',
        'aliases' => ['vih_positivo_sida', 'vih_sida', 'salud_9'],
      ],
      'insuficiencia_renal_cronica' => [
        'titulo' => 'Insuficiencia renal crónica',
        'aliases' => ['insuficiencia_renal_cronica', 'insuficiencia_renal', 'salud_8'],
      ],
      'epoc_enfermedad_pulmonar_obstructiva_cronica' => [
        'titulo' => 'EPOC (Enfermedad pulmonar obstructiva crónica)',
        'aliases' => ['epoc_enfermedad_pulmonar_obstructiva_cronica', 'epoc', 'salud_7'],
      ],
      'cirugia' => [
        'titulo' => 'Cirugía',
        'aliases' => ['cirugia', 'salud_4'],
      ],
      'alcoholismo' => [
        'titulo' => 'Alcoholismo',
        'aliases' => ['alcoholismo', 'salud_3'],
      ],
      'tabaquismo_drogadiccion' => [
        'titulo' => 'Tabaquismo / Drogadicción',
        'aliases' => ['tabaquismo_drogadiccion', 'salud_10'],
      ],
      'hipertension_arterial' => [
        'titulo' => 'Hipertensión arterial',
        'aliases' => ['hipertension_arterial', 'hipertension', 'salud_11'],
      ],
      'enfermedades_congenitas' => [
        'titulo' => 'Enfermedades congénitas',
        'aliases' => ['enfermedades_congenitas', 'salud_12'],
      ],
      'enfermedades_hematologicas' => [
        'titulo' => 'Enfermedades hematológicas',
        'aliases' => ['enfermedades_hematologicas', 'salud_13'],
      ],
      'enfermedades_del_colageno_enfermedad_que_afecta_el_sistema_autoi' => [
        'titulo' => 'Enfermedades del colágeno (sistema autoinmune)',
        'aliases' => ['enfermedades_del_colageno_enfermedad_que_afecta_el_sistema_autoi', 'enfermedades_colageno', 'salud_14'],
      ],
    ];
  }

  public static function obtenerBeneficiariosNormalizados(array $raw): array {
    $candidatos = [];

    // Prioridad 1: tabla_de_beneficiarios.
    if (isset($raw['tabla_de_beneficiarios'])) {
      $candidatos[] = $raw['tabla_de_beneficiarios'];
    }

    // Prioridad 2: tabla_de_beneficiarios_2 (se utiliza si tabla_de_beneficiarios está vacía).
    if (isset($raw['tabla_de_beneficiarios_2'])) {
      $candidatos[] = $raw['tabla_de_beneficiarios_2'];
    }

    // Prioridad 3: Claves de respaldo para compatibilidad histórica.
    $claves_respaldo = [
      'beneficiarios',
      'tabla_beneficiarios',
      'beneficiario',
      'beneficiarios_tabla',
      'datos_beneficiarios',
      'designacion_beneficiarios',
      'designacion_de_beneficiarios',
      'beneficiarios_del_seguro',
      'datos_de_los_beneficiarios',
      'escriba_el_nombre_de_los_beneficiarios_de_este_seguro_y_su_respectivo_porcentaje',
      'escriba_el_nombre_de_los_beneficiarios',
    ];

    foreach ($claves_respaldo as $clave) {
      if (isset($raw[$clave]) && !in_array($clave, ['tabla_de_beneficiarios', 'tabla_de_beneficiarios_2'], TRUE)) {
        $candidatos[] = $raw[$clave];
      }
    }

    // Cualquier otra clave que contenga 'beneficiar' no evaluada previamente.
    foreach ($raw as $k => $v) {
      if (stripos($k, 'beneficiar') !== FALSE && !in_array($k, array_merge(['tabla_de_beneficiarios', 'tabla_de_beneficiarios_2'], $claves_respaldo), TRUE)) {
        $candidatos[] = $v;
      }
    }

    // Helper para resolver etiquetas de términos de taxonomía si vienen como ID numérico.
    $resolve_term = function ($value) {
      if (!empty($value) && is_numeric($value)) {
        $term = Term::load((int) $value);
        if ($term) {
          return $term->label();
        }
      }
      return (string) $value;
    };

    // Evaluar candidatos en orden estricto de prioridad.
    foreach ($candidatos as $candidate) {
      if (empty($candidate)) {
        continue;
      }

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
        $tipo_doc_label = !empty($tipo_doc) ? $resolve_term($tipo_doc) : 'C.C.';

        // Número de documento.
        $num_doc = '';
        foreach ($item as $ik => $iv) {
          $ik_lower = mb_strtolower((string) $ik);
          if ((strpos($ik_lower, 'numero') !== FALSE || strpos($ik_lower, 'no_') !== FALSE || strpos($ik_lower, 'num') !== FALSE || strpos($ik_lower, 'cedula') !== FALSE || strpos($ik_lower, 'identifica') !== FALSE || $ik_lower === 'documento') && strpos($ik_lower, 'tipo') === FALSE) {
            if (is_string($iv) || is_numeric($iv)) {
              $num_doc = trim((string) $iv);
              break;
            }
          }
        }

        // Parentesco.
        $parentesco = '';
        foreach ($item as $ik => $iv) {
          $ik_lower = mb_strtolower((string) $ik);
          if (strpos($ik_lower, 'parentesco') !== FALSE || strpos($ik_lower, 'vinculo') !== FALSE || strpos($ik_lower, 'relacion') !== FALSE) {
            $parentesco = trim((string) $iv);
            break;
          }
        }
        $parentesco_label = !empty($parentesco) ? $resolve_term($parentesco) : 'N/A';

        // Edad.
        $edad = '';
        foreach ($item as $ik => $iv) {
          $ik_lower = mb_strtolower((string) $ik);
          if (strpos($ik_lower, 'edad') !== FALSE || strpos($ik_lower, 'anos') !== FALSE || strpos($ik_lower, 'años') !== FALSE) {
            $edad = trim((string) $iv);
            break;
          }
        }

        // Porcentaje.
        $porcentaje = '';
        foreach ($item as $ik => $iv) {
          $ik_lower = mb_strtolower((string) $ik);
          if (strpos($ik_lower, 'porcent') !== FALSE || strpos($ik_lower, 'particip') !== FALSE || $ik_lower === '%') {
            $porcentaje = trim((string) $iv);
            break;
          }
        }
        if (!empty($porcentaje) && is_numeric($porcentaje)) {
          $porcentaje .= '%';
        }

        // Un beneficiario solo es válido si cuenta con al menos un nombre O número de documento.
        if (!empty($nombre_ben) || !empty($num_doc)) {
          $parsed_list[] = [
            'nombre' => $nombre_ben,
            'nombre_y_apellido' => $nombre_ben,
            'tipo_doc' => $tipo_doc_label,
            'tipo_de_documento' => $tipo_doc_label,
            'num_doc' => $num_doc,
            'numero_de_documento' => $num_doc,
            'parentesco' => $parentesco_label,
            'edad' => $edad,
            'porcentaje' => $porcentaje,
          ];
        }
      }

      // Si este candidato produjo al menos un beneficiario válido, se retorna de inmediato.
      if (!empty($parsed_list)) {
        return array_values($parsed_list);
      }
    }

    return [];
  }

  /**
   * Calcula el consolidado estadístico de condiciones de salud y tarifas.
   *
   * @param array $items
   *   Arreglo de registros normalizados (extraerDatosRegistro).
   *
   * @return array
   *   Arreglo estadístico estructurado para la vista y exportación.
   */
  public function calcularEstadisticasCondicionesSalud(array $items): array {
    $total = count($items);
    $def = self::getDefinicionCamposSalud();

    $conteo_preguntas = [];
    foreach ($def as $key => $info) {
      $conteo_preguntas[$key] = [
        'titulo' => $info['titulo'],
        'cantidad' => 0,
        'porcentaje' => 0.0,
      ];
    }

    $conteo_sobrepeso = 0;
    $conteo_obesidad_total = 0;
    $conteo_obesidad_imc30 = 0;
    $conteo_hipertension = 0;
    $conteo_sin_condiciones = 0;
    $conteo_con_condiciones = 0;
    $conteo_mayores_71 = 0;

    $tarifas = [
      12000 => ['cantidad' => 0, 'porcentaje' => 0.0],
      10000 => ['cantidad' => 0, 'porcentaje' => 0.0],
      8000  => ['cantidad' => 0, 'porcentaje' => 0.0],
    ];

    foreach ($items as $item) {
      $keys_marcadas = $item['condiciones_marcadas_keys'] ?? [];
      $titulos_marcados = $item['condiciones_marcadas'] ?? [];

      // Conteo de las 14 preguntas individuales del cuestionario.
      foreach ($def as $key => $info) {
        if (in_array($key, $keys_marcadas, TRUE) || in_array($info['titulo'], $titulos_marcados, TRUE)) {
          $conteo_preguntas[$key]['cantidad']++;
        }
      }

      // Conteo con o sin condiciones generales declaradas.
      $texto_salud = $item['condiciones_salud_texto'] ?? 'Ninguna';
      $explicacion = $item['explicacion_condiciones'] ?? '';
      $texto_busqueda = self::normalizarTextoParaBusqueda($texto_salud . ' ' . $explicacion);

      if (empty($titulos_marcados) || $texto_salud === 'Ninguna') {
        $conteo_sin_condiciones++;
      }
      else {
        $conteo_con_condiciones++;
      }

      // Hipertensión arterial / Presión arterial.
      if (
        strpos($texto_busqueda, 'hipertension') !== FALSE ||
        strpos($texto_busqueda, 'presion arterial') !== FALSE ||
        in_array('hipertension_arterial', $keys_marcadas, TRUE)
      ) {
        $conteo_hipertension++;
      }

      // Regla de edad: más de 71 años.
      $edad_num = (isset($item['edad']) && is_numeric($item['edad'])) ? (int) $item['edad'] : null;
      $es_mayor_71 = (!empty($item['es_mayor_71']) || ($edad_num !== null && $edad_num > 71));

      // Si tiene más de 71 años se contabiliza en su propio card y se excluye de los cards
      // de obesidad ($12.000), sobrepeso ($10.000) y base ($8.000):
      if ($es_mayor_71) {
        $conteo_mayores_71++;
      }
      else {
        // Sobrepeso (excluye mayores de 71 años).
        if (strpos($texto_busqueda, 'sobrepeso') !== FALSE) {
          $conteo_sobrepeso++;
        }

        // Obesidad (excluye mayores de 71 años).
        $imc_num = 0.0;
        if (!empty($item['imc'])) {
          $imc_clean = str_replace(',', '.', trim((string) $item['imc']));
          if (is_numeric($imc_clean)) {
            $imc_num = (float) $imc_clean;
          }
        }

        if (strpos($texto_busqueda, 'obesidad') !== FALSE) {
          $conteo_obesidad_total++;
          if ($imc_num > 30.0) {
            $conteo_obesidad_imc30++;
          }
        }

        // Consolidado por tarifa (12000, 10000 u 8000, excluye mayores de 71 años).
        $tarifa_val = (int) ($item['valor_condicion_salud'] ?? 8000);
        if (!isset($tarifas[$tarifa_val])) {
          $tarifa_val = 8000;
        }
        $tarifas[$tarifa_val]['cantidad']++;
      }
    }

    $calc_pct = function($cant) use ($total) {
      return $total > 0 ? round(($cant / $total) * 100, 1) : 0.0;
    };

    foreach ($conteo_preguntas as $key => &$p) {
      $p['porcentaje'] = $calc_pct($p['cantidad']);
    }
    unset($p);

    foreach ($tarifas as $val => &$t) {
      $t['porcentaje'] = $calc_pct($t['cantidad']);
    }
    unset($t);

    return [
      'total_solicitudes' => $total,
      'conteo_preguntas' => $conteo_preguntas,
      'conteo_con_condiciones' => $conteo_con_condiciones,
      'porcentaje_con_condiciones' => $calc_pct($conteo_con_condiciones),
      'conteo_sin_condiciones' => $conteo_sin_condiciones,
      'porcentaje_sin_condiciones' => $calc_pct($conteo_sin_condiciones),
      'conteo_mayores_71' => [
        'cantidad' => $conteo_mayores_71,
        'porcentaje' => $calc_pct($conteo_mayores_71),
      ],
      'conteo_sobrepeso' => [
        'cantidad' => $conteo_sobrepeso,
        'porcentaje' => $calc_pct($conteo_sobrepeso),
      ],
      'conteo_obesidad_total' => [
        'cantidad' => $conteo_obesidad_total,
        'porcentaje' => $calc_pct($conteo_obesidad_total),
      ],
      'conteo_obesidad_imc30' => [
        'cantidad' => $conteo_obesidad_imc30,
        'porcentaje' => $calc_pct($conteo_obesidad_imc30),
      ],
      'conteo_hipertension' => [
        'cantidad' => $conteo_hipertension,
        'porcentaje' => $calc_pct($conteo_hipertension),
      ],
      'tarifas' => $tarifas,
    ];
  }

  /**
   * Construye el documento Excel XML para el reporte estadístico de condiciones de salud.
   */
  protected function construirXmlExcelCondicionesSalud(array $stats): string {
    ob_start();
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">' . "\n";
    echo '<head>' . "\n";
    echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />' . "\n";
    echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Condiciones de Salud</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->' . "\n";
    echo '<style>' . "\n";
    echo '  .header-title { font-size: 14pt; font-weight: bold; background-color: #991b1b; color: #ffffff; text-align: center; }' . "\n";
    echo '  .header-subtitle { font-size: 11pt; font-weight: bold; background-color: #4338ca; color: #ffffff; }' . "\n";
    echo '  .header-meta { font-size: 10pt; color: #555555; background-color: #fef2f2; }' . "\n";
    echo '  th { background-color: #374151; color: #ffffff; font-weight: bold; padding: 6px; border: 0.5pt solid #cccccc; text-align: center; font-size: 9.5pt; }' . "\n";
    echo '  td { padding: 5px; border: 0.5pt solid #cccccc; font-size: 9pt; }' . "\n";
    echo '  .text-center { text-align: center; }' . "\n";
    echo '  .text-right { text-align: right; }' . "\n";
    echo '  .total-row { font-weight: bold; background-color: #f3f4f6; }' . "\n";
    echo '  .badge-12000 { background-color: #fee2e2; color: #991b1b; font-weight: bold; }' . "\n";
    echo '  .badge-10000 { background-color: #fef9c3; color: #854d0e; font-weight: bold; }' . "\n";
    echo '  .badge-8000 { background-color: #ecfdf5; color: #047857; font-weight: bold; }' . "\n";
    echo '</style>' . "\n";
    echo '</head>' . "\n";
    echo '<body>' . "\n";

    echo '<table border="1">' . "\n";
    echo '<tr><td colspan="5" class="header-title">REPORTE ESTADÍSTICO DE CONDICIONES DE SALUD - ASPU</td></tr>' . "\n";
    echo '<tr><td colspan="5" class="header-meta">Generado el: ' . date('d/m/Y H:i:s') . ' | Total Solicitudes Evaluadas: ' . $stats['total_solicitudes'] . ' | Con Condiciones: ' . $stats['conteo_con_condiciones'] . ' (' . $stats['porcentaje_con_condiciones'] . '%) | Sin Condiciones: ' . $stats['conteo_sin_condiciones'] . ' (' . $stats['porcentaje_sin_condiciones'] . '%)</td></tr>' . "\n";
    echo '<tr></tr>' . "\n";

    // TABLA 1: CONTEO POR CONDICIÓN
    echo '<tr><td colspan="5" class="header-subtitle">1. CONTEO DE REGISTROS POR CADA CONDICIÓN DE SALUD</td></tr>' . "\n";
    echo '<tr>' . "\n";
    echo '  <th style="width: 40px;">#</th>' . "\n";
    echo '  <th style="width: 380px;">CONDICIÓN DE SALUD / CUESTIONARIO</th>' . "\n";
    echo '  <th style="width: 130px;">REGISTROS (CANTIDAD)</th>' . "\n";
    echo '  <th style="width: 120px;">PORCENTAJE (%)</th>' . "\n";
    echo '  <th style="width: 140px;">TARIFA ASOCIADA</th>' . "\n";
    echo '</tr>' . "\n";

    $i = 1;
    foreach ($stats['conteo_preguntas'] as $key => $p) {
      $tarifa_label = ($key === 'hipertension_arterial') ? '$10.000' : '$8.000 / $10.000';
      $tarifa_class = ($key === 'hipertension_arterial') ? 'badge-10000' : '';
      echo '<tr>' . "\n";
      echo '  <td class="text-center">' . $i . '</td>' . "\n";
      echo '  <td>' . htmlspecialchars($p['titulo']) . '</td>' . "\n";
      echo '  <td class="text-right" style="mso-number-format:\'0\';">' . $p['cantidad'] . '</td>' . "\n";
      echo '  <td class="text-right">' . $p['porcentaje'] . '%</td>' . "\n";
      echo '  <td class="text-center ' . $tarifa_class . '">' . $tarifa_label . '</td>' . "\n";
      echo '</tr>' . "\n";
      $i++;
    }

    // Sobrepeso
    echo '<tr>' . "\n";
    echo '  <td class="text-center">' . $i++ . '</td>' . "\n";
    echo '  <td>Sobrepeso (declarado o registrado)</td>' . "\n";
    echo '  <td class="text-right" style="mso-number-format:\'0\';">' . $stats['conteo_sobrepeso']['cantidad'] . '</td>' . "\n";
    echo '  <td class="text-right">' . $stats['conteo_sobrepeso']['porcentaje'] . '%</td>' . "\n";
    echo '  <td class="text-center badge-10000">$10.000</td>' . "\n";
    echo '</tr>' . "\n";

    // Obesidad IMC > 30
    echo '<tr>' . "\n";
    echo '  <td class="text-center">' . $i++ . '</td>' . "\n";
    echo '  <td>Obesidad con IMC > 30</td>' . "\n";
    echo '  <td class="text-right" style="mso-number-format:\'0\';">' . $stats['conteo_obesidad_imc30']['cantidad'] . '</td>' . "\n";
    echo '  <td class="text-right">' . $stats['conteo_obesidad_imc30']['porcentaje'] . '%</td>' . "\n";
    echo '  <td class="text-center badge-12000">$12.000</td>' . "\n";
    echo '</tr>' . "\n";

    // Personas con más de 71 años
    echo '<tr>' . "\n";
    echo '  <td class="text-center">' . $i++ . '</td>' . "\n";
    echo '  <td>Personas con más de 71 años</td>' . "\n";
    echo '  <td class="text-right" style="mso-number-format:\'0\';">' . $stats['conteo_mayores_71']['cantidad'] . '</td>' . "\n";
    echo '  <td class="text-right">' . $stats['conteo_mayores_71']['porcentaje'] . '%</td>' . "\n";
    echo '  <td class="text-center">> 71 años</td>' . "\n";
    echo '</tr>' . "\n";

    // Sin condiciones
    echo '<tr>' . "\n";
    echo '  <td class="text-center">' . $i++ . '</td>' . "\n";
    echo '  <td>Sin condiciones declaradas (Ninguna)</td>' . "\n";
    echo '  <td class="text-right" style="mso-number-format:\'0\';">' . $stats['conteo_sin_condiciones'] . '</td>' . "\n";
    echo '  <td class="text-right">' . $stats['porcentaje_sin_condiciones'] . '%</td>' . "\n";
    echo '  <td class="text-center badge-8000">$8.000</td>' . "\n";
    echo '</tr>' . "\n";

    echo '<tr></tr>' . "\n";

    // TABLA 2: CONSOLIDADO POR TARIFA
    echo '<tr><td colspan="4" class="header-subtitle">2. CONSOLIDADO POR TARIFA (VALOR CONDICIÓN SALUD)</td></tr>' . "\n";
    echo '<tr>' . "\n";
    echo '  <th style="width: 140px;">VALOR ($)</th>' . "\n";
    echo '  <th style="width: 380px;">CRITERIO / CONDICIÓN DE SALUD ASIGNADA</th>' . "\n";
    echo '  <th style="width: 130px;">REGISTROS (CANTIDAD)</th>' . "\n";
    echo '  <th style="width: 120px;">PORCENTAJE (%)</th>' . "\n";
    echo '</tr>' . "\n";

    // Tarifa 12000
    echo '<tr>' . "\n";
    echo '  <td class="text-center badge-12000">$12.000</td>' . "\n";
    echo '  <td>Obesidad con IMC > 30</td>' . "\n";
    echo '  <td class="text-right" style="mso-number-format:\'0\';">' . $stats['tarifas'][12000]['cantidad'] . '</td>' . "\n";
    echo '  <td class="text-right">' . $stats['tarifas'][12000]['porcentaje'] . '%</td>' . "\n";
    echo '</tr>' . "\n";

    // Tarifa 10000
    echo '<tr>' . "\n";
    echo '  <td class="text-center badge-10000">$10.000</td>' . "\n";
    echo '  <td>Hipertensión arterial / Presión arterial / Sobrepeso</td>' . "\n";
    echo '  <td class="text-right" style="mso-number-format:\'0\';">' . $stats['tarifas'][10000]['cantidad'] . '</td>' . "\n";
    echo '  <td class="text-right">' . $stats['tarifas'][10000]['porcentaje'] . '%</td>' . "\n";
    echo '</tr>' . "\n";

    // Tarifa 8000
    echo '<tr>' . "\n";
    echo '  <td class="text-center badge-8000">$8.000</td>' . "\n";
    echo '  <td>Tarifa Base (No cumplen condiciones anteriores / Sanos)</td>' . "\n";
    echo '  <td class="text-right" style="mso-number-format:\'0\';">' . $stats['tarifas'][8000]['cantidad'] . '</td>' . "\n";
    echo '  <td class="text-right">' . $stats['tarifas'][8000]['porcentaje'] . '%</td>' . "\n";
    echo '</tr>' . "\n";

    // Más de 71 años
    echo '<tr>' . "\n";
    echo '  <td class="text-center">> 71 años</td>' . "\n";
    echo '  <td>Personas con más de 71 años (Excluidas de tarifas base, sobrepeso y obesidad)</td>' . "\n";
    echo '  <td class="text-right" style="mso-number-format:\'0\';">' . $stats['conteo_mayores_71']['cantidad'] . '</td>' . "\n";
    echo '  <td class="text-right">' . $stats['conteo_mayores_71']['porcentaje'] . '%</td>' . "\n";
    echo '</tr>' . "\n";

    // Total
    echo '<tr class="total-row">' . "\n";
    echo '  <td colspan="2" class="text-right">TOTAL SOLICITUDES EVALUADAS:</td>' . "\n";
    echo '  <td class="text-right" style="mso-number-format:\'0\';">' . $stats['total_solicitudes'] . '</td>' . "\n";
    echo '  <td class="text-right">100.0%</td>' . "\n";
    echo '</tr>' . "\n";

    echo '</table>' . "\n";
    echo '</body>' . "\n";
    echo '</html>' . "\n";

    return ob_get_clean();
  }

  protected function obtenerEstadisticas() {
    $storage = \Drupal::entityTypeManager()->getStorage('webform_submission');

    // Estadísticas ASPU (formulario_equidad_seguros)
    $sids_aspu = $storage->getQuery()
      ->condition('webform_id', 'formulario_equidad_seguros')
      ->accessCheck(FALSE)
      ->execute();

    $aspu_count = 0;
    $rifas_aspu = 0;
    if (!empty($sids_aspu)) {
      $subs_aspu = $storage->loadMultiple($sids_aspu);
      foreach ($subs_aspu as $sub) {
        $raw = $sub->getData();
        $selector = (string) ($raw['selector_tomador'] ?? '');
        $tomador_text = mb_strtolower((string) ($raw['tomador'] ?? ''));
        $is_aspu = ($selector === '2' || strpos($tomador_text, 'aspu') !== FALSE || strpos($tomador_text, 'sindical') !== FALSE || $selector === '');
        if ($is_aspu) {
          $aspu_count++;
          $r1 = $raw['numero_de_rifa_1'] ?? $raw['numero_rifa_1'] ?? '';
          $r2 = $raw['numero_de_rifa_2'] ?? $raw['numero_rifa_2'] ?? '';
          if (!empty($r1) || !empty($r2)) {
            $rifas_aspu++;
          }
        }
      }
    }

    // Estadísticas PRODEPEM (formulario_equidad_seguros_prode)
    $sids_prode = $storage->getQuery()
      ->condition('webform_id', 'formulario_equidad_seguros_prode')
      ->accessCheck(FALSE)
      ->execute();

    $prode_count = 0;
    $rifas_prode = 0;
    if (!empty($sids_prode)) {
      $subs_prode = $storage->loadMultiple($sids_prode);
      foreach ($subs_prode as $sub) {
        $raw = $sub->getData();
        $prode_count++;
        $r1 = $raw['numero_de_rifa_1'] ?? $raw['numero_rifa_1'] ?? '';
        $r2 = $raw['numero_de_rifa_2'] ?? $raw['numero_rifa_2'] ?? '';
        if (!empty($r1) || !empty($r2)) {
          $rifas_prode++;
        }
      }
    }

    return [
      'total' => $aspu_count + $prode_count,
      'aspu' => $aspu_count,
      'rifas_aspu' => $rifas_aspu,
      'prodepem' => $prode_count,
      'rifas_prodepem' => $rifas_prode,
      'rifas' => $rifas_aspu + $rifas_prode,
    ];
  }

}
