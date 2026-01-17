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
    $id = \Drupal::request()->query->get('id');

    //recibir user param via url
    $user = \Drupal::request()->query->get('user');

    // Obtener el registro más reciente del webform formulario_touch
    // que tenga como valor en el campo celular el valor de $user pero sin los dos primeros números
    $stripped_user = substr($user, 2);
    $submission_data = [];

    if (!empty($stripped_user)) {
      // Usar query de base de datos directa ya que entityQuery no soporta 'data.' en este entorno
      $query = \Drupal::database()->select('webform_submission_data', 'wsd');
      $query->join('webform_submission', 'ws', 'ws.sid = wsd.sid');
      $query->fields('wsd', ['sid']);
      $query->condition('wsd.webform_id', 'formulario_touch');
      $query->condition('wsd.name', 'celular');
      $query->condition('wsd.value', $stripped_user);
      $query->orderBy('ws.created', 'DESC');
      $query->range(0, 1);
      
      $sid = $query->execute()->fetchField();

      if ($sid) {
        $submission = \Drupal\webform\Entity\WebformSubmission::load($sid);
        $all_data = $submission->getData();
        $submission_data = [
          'nombre' => $all_data['nombre'] ?? '',
          'apellidos' => $all_data['apellidos'] ?? '',
        ];
      }
    }
    
    //obtener los datos del post con id igual a $id y retornar todos los valores de sus campos
    $post = Node::load($id);
    
    if (!$post) {
      return new ResourceResponse(['error' => 'Node not found'], 404);
    }

    $response = [
      'data' => $post->toArray(),
      'submission' => $submission_data,
    ];
    $resource_response = new ResourceResponse($response, 200);
    $resource_response->addCacheableDependency($post);
    $resource_response->getCacheableMetadata()->addCacheContexts(['url.query_args:id', 'url.query_args:user']);
    return $resource_response;
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

    $id = $data['id'] ?? NULL;
    $field_name = $data['field'] ?? NULL;
    $value = $data['value'] ?? NULL;

    if (!$id || !$field_name) {
      throw new BadRequestHttpException('Missing required data: id or field.');
    }

    $node = Node::load($id);
    if (!$node) {
      return new ResourceResponse(['error' => 'Node not found'], 404);
    }

    if (!$node->hasField($field_name)) {
      return new ResourceResponse(['error' => "Field $field_name not found in node"], 400);
    }

    $node->set($field_name, $value);
    $node->save();

    $response = [
      'message' => 'Node updated successfully',
      'id' => $id,
      'field' => $field_name,
      'value' => $value,
    ];
    return new ResourceResponse($response, 200);
  }

}
