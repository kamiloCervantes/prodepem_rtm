<?php

namespace Drupal\gestion_cobranzas\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "gestion_cobranzas_cobranzas_resource",
 *   label = @Translation("Cobranzas Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/cbr/gestion-cobranzas",
 *     "create" = "/api/cbr/gestion-cobranzas",
 *     "delete" = "/api/cbr/gestion-cobranzas"
 *   }
 * )
 */
class CobranzasResource extends ResourceBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new CobranzasResource object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serializer formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('gestion_cobranzas'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Responds to POST requests.
   *
   * @param array $data
   *   The data containing the node information.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception for invalid data.
   */
  public function post(array $data) {
    // Validate data.

    //agregar logger para ver que se recibe
    $this->logger->notice('Data received: @data', ['@data' => json_encode($data)]);

    if (empty($data)) {
      throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('No data received.');
    }

    try {
      // Create the node.
      // Assuming 'registro_de_cobranzas' is the content type machine name.
      // Map fields from $data to node fields.
      // Example: 'title' => $data['title']
      
      $node_data = [
        'type' => 'item_cobranzas_uma_tipada',
        'title' => 'Cobranza ' . ($data['factura'] ?? time()) . ' - ' . ($data['vencimiento'] ?? ''),
        'field_factura' => $data['factura'] ?? '',
        'field_fecha_emision_tipado' => isset($data['fecha_emision']) ? date('Y-m-d', strtotime(str_replace('/', '-', $data['fecha_emision']))) : NULL,
        'field_fecha_vencimiento_tipado' => isset($data['vencimiento']) ? date('Y-m-d', strtotime(str_replace('/', '-', $data['vencimiento']))) : NULL,
        'field_referencia' => $data['referencia'] ?? '',
        'field_valor_tipado' => $data['valor'] ?? 0,
        'field_mora_tipado' => (int) ($data['mora'] ?? 0),
        'field_dias_vencidos_tipado' => (int) ($data['dias_vencidos'] ?? 0),
        'field_id_cliente' => $data['cliente_id'] ?? '',
        'field_nombre_cliente' => $data['cliente_nombre'] ?? '',
        'field_ciudad' => $data['ciudad'] ?? '',
        'field_direccion' => $data['direccion'] ?? '',
        'field_telefono' => $data['telefono'] ?? '',
        'field_activo' => (bool) ($data['activo'] ?? TRUE),
      ];

      $node = $this->entityTypeManager->getStorage('node')->create($node_data);
      $node->save();

      $this->logger->notice('Created new Cobranza Tipada node with ID @id', ['@id' => $node->id()]);

      // Return the ID of the created node.
      return new ModifiedResourceResponse(['message' => 'Cobranza created successfully', 'id' => $node->id()], 201);

    }
    catch (\Exception $e) {
      $this->logger->error('Error creating Cobranza node: @message', ['@message' => $e->getMessage()]);
      throw new \Symfony\Component\HttpKernel\Exception\HttpException(500, 'Internal Server Error: ' . $e->getMessage());
    }

    //retornar mensaje de exito
    //return new ModifiedResourceResponse(['message' => 'Cobranza created successfully'], 201);
  }

  /**
   * Responds to DELETE requests.
   *
   * Deletes all nodes of type 'item_cobranzas_uma'.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   */
 public function delete()
  {
    try {
      $storage = $this->entityTypeManager->getStorage('node');
      $nids = $storage->getQuery()
        ->condition('type', 'item_cobranzas_uma_tipada')
        ->accessCheck(FALSE)
        ->execute();

      $total_deleted = 0;
      if (!empty($nids)) {
        $chunks = array_chunk($nids, 100);
        foreach ($chunks as $chunk) {
          $nodes = $storage->loadMultiple($chunk);
          $storage->delete($nodes);
          $total_deleted += count($chunk);
        }
        $this->logger->notice('Deleted @count Cobranza Tipada nodes.', ['@count' => $total_deleted]);
        return new ModifiedResourceResponse(['message' => "Deleted $total_deleted Cobranza nodes."], 200);
      }

      return new ModifiedResourceResponse(['message' => 'No Cobranza nodes found to delete.'], 200);

    } catch (\Exception $e) {
      $this->logger->error('Error deleting Cobranza nodes: @message', ['@message' => $e->getMessage()]);
      throw new \Symfony\Component\HttpKernel\Exception\HttpException(500, 'Internal Server Error: ' . $e->getMessage());
    }
  }

  /**
   * Responds to PATCH requests.
   *
   * @param array $data
   *   The data containing update information.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   */
  public function patch(array $data) {
    if (empty($data['modo_update'])) {
      throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Missing modo_update.');
    }

    switch ($data['modo_update']) {
      case 'UPDATE_STATUS':
        return $this->updateStatus($data);

      default:
        throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Invalid modo_update.');
    }
  }

  /**
   * Updates the active status for a specific client.
   */
  protected function updateStatus(array $data) {
    if (empty($data['cliente_id']) || !isset($data['activo'])) {
      throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Missing cliente_id or activo.');
    }

    $activo = (bool) $data['activo'];
    $cliente_id = $data['cliente_id'];

    try {
      $storage = $this->entityTypeManager->getStorage('node');
      $nids = $storage->getQuery()
        ->condition('type', 'item_cobranzas_uma_tipada')
        ->condition('field_id_cliente', $cliente_id)
        ->accessCheck(FALSE)
        ->execute();

      $count = 0;
      if (!empty($nids)) {
        foreach ($storage->loadMultiple($nids) as $node) {
          $node->set('field_activo', $activo);
          $node->save();
          $count++;
        }
      }

      $this->logger->notice('Updated @count nodes for client @client to @status.', [
        '@count' => $count,
        '@client' => $cliente_id,
        '@status' => $activo ? 'TRUE' : 'FALSE',
      ]);

      return new ModifiedResourceResponse([
        'message' => 'Nodes updated successfully',
        'updated_count' => $count
      ], 200);

    } catch (\Exception $e) {
      $this->logger->error('Error updating nodes: @message', ['@message' => $e->getMessage()]);
      throw new \Symfony\Component\HttpKernel\Exception\HttpException(500, 'Internal Server Error');
    }
  }


}

