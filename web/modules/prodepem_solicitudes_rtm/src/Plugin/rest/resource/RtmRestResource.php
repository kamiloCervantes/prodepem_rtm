<?php

namespace Drupal\prodepem_solicitudes_rtm\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Drupal\node\Entity\Node;

/**
 * Provides a resource to get and post RTM data.
 *
 * @RestResource(
 *   id = "rtm_rest_resource",
 *   label = @Translation("RTM REST Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/rtm/resource",
 *     "create" = "/api/rtm/resource"
 *   }
 * )
 */
class RtmRestResource extends ResourceBase {

  /**
   * Responds to GET requests.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The HTTP response object.
   */
  public function get() {
    //recibir id del post via url
    $id = $this->getRequest()->query->get('id');
    
    //obtener los datos del post con id igual a $id y retornar todos los valores de sus campos
    $post = Node::load($id);
    $response = [
      'data' => $post->toArray(),
    ];
    return new ResourceResponse($response, 200);
  }

  /**
   * Responds to POST requests.
   *
   * @param array $data
   *   The post data.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The HTTP response object.
   */
  public function post(array $data) {
    if (empty($data)) {
      throw new BadRequestHttpException('No data received.');
    }

    $response = [
      'message' => 'Hello from RTM REST Resource POST method!',
      'received_data' => $data,
    ];
    return new ResourceResponse($response, 201);
  }

}
