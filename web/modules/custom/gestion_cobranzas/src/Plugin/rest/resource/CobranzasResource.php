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
 *     "create" = "/api/gestion-cobranzas/registrar",
 *     "delete" = "/api/gestion-cobranzas/eliminar-todo"
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
        'type' => 'item_cobranzas_uma',
        'title' => 'Cobranza ' . ($data['factura'] ?? time()),
        'field_factura' => $data['factura'] ?? '',
        'field_fecha_emision' => isset($data['fecha_emision']) ? str_replace('/', '-', $data['fecha_emision']) : NULL,
        'field_fecha_vencimiento' => isset($data['vencimiento']) ? str_replace('/', '-', $data['vencimiento']) : NULL,
        'field_referencia' => $data['referencia'] ?? '',
        'field_valor' => $data['valor'] ?? 0,
        'field_mora' => $data['mora'] ?? 0,
        'field_dias_vencidos' => $data['dias_vencidos'] ?? 0,
        'field_id_cliente' => $data['cliente_id'] ?? '',
        'field_nombre_cliente' => $data['cliente_nombre'] ?? '',
        'field_ciudad' => $data['ciudad'] ?? '',
        'field_direccion' => $data['direccion'] ?? '',
        'field_telefono' => $data['telefono'] ?? '',
      ];

      $node = $this->entityTypeManager->getStorage('node')->create($node_data);
      $node->save();

      $this->logger->notice('Created new Cobranza node with ID @id', ['@id' => $node->id()]);

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
  public function delete() {
    try {
      $storage = $this->entityTypeManager->getStorage('node');
      $nids = $storage->getQuery()
        ->condition('type', 'item_cobranzas_uma')
        ->accessCheck(FALSE)
        ->execute();

      if (!empty($nids)) {
        $nodes = $storage->loadMultiple($nids);
        $storage->delete($nodes);
        $count = count($nids);
        $this->logger->notice('Deleted @count Cobranza nodes.', ['@count' => $count]);
        return new ModifiedResourceResponse(['message' => "Deleted $count Cobranza nodes."], 200);
      }

      return new ModifiedResourceResponse(['message' => 'No Cobranza nodes found to delete.'], 200);

    }
    catch (\Exception $e) {
      $this->logger->error('Error deleting Cobranza nodes: @message', ['@message' => $e->getMessage()]);
      throw new \Symfony\Component\HttpKernel\Exception\HttpException(500, 'Internal Server Error: ' . $e->getMessage());
    }
  }

}

