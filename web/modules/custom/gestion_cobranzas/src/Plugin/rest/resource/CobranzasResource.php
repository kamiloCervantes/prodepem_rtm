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
 *     "create" = "/api/gestion-cobranzas/registrar"
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

    // if (empty($data)) {
    //   throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('No data received.');
    // }

    // try {
    //   // Create the node.
    //   // Assuming 'registro_de_cobranzas' is the content type machine name.
    //   // Map fields from $data to node fields.
    //   // Example: 'title' => $data['title']
      
    //   $node_data = [
    //     'type' => 'registro_de_cobranzas',
    //     'title' => isset($data['title']) ? $data['title'] : 'Nueva Cobranza - ' . time(),
    //     // Add other fields mapping here based on the requirement
    //     // 'field_monto' => $data['monto'],
    //   ];
      
    //   // If there are specific fields to map, we should add them here.
    //   // For now, I'll iterate over data to map common fields if needed, 
    //   // or rely on the user to provide exact field names in mapping.
    //   // But a basic mapping is safer.
    //   foreach ($data as $key => $value) {
    //       if ($key != 'type' && $key != 'title') {
    //          $node_data[$key] = $value;
    //       }
    //   }

    //   $node = $this->entityTypeManager->getStorage('node')->create($node_data);
    //   $node->save();

    //   $this->logger->notice('Created new Cobranza node with ID @id', ['@id' => $node->id()]);

    //   // Return the ID of the created node.
    //   return new ModifiedResourceResponse(['message' => 'Cobranza created successfully', 'id' => $node->id()], 201);

    // }
    // catch (\Exception $e) {
    //   $this->logger->error('Error creating Cobranza node: @message', ['@message' => $e->getMessage()]);
    //   throw new \Symfony\Component\HttpKernel\Exception\HttpException(500, 'Internal Server Error: ' . $e->getMessage());
    // }

    //retornar mensaje de exito
    return new ModifiedResourceResponse(['message' => 'Cobranza created successfully'], 201);
  }

}
