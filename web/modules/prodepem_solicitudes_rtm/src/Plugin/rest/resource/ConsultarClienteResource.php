<?php

namespace Drupal\prodepem_solicitudes_rtm\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a resource to query client information from an external API.
 *
 * @RestResource(
 *   id = "prodepem_solicitudes_rtm_consultar_cliente",
 *   label = @Translation("Consultar Cliente Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/rtm/consultar-cliente"
 *   }
 * )
 */
class ConsultarClienteResource extends ResourceBase {

  /**
   * Responds to GET requests.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The HTTP response object.
   */
  public function get() {
    $cliente_id = \Drupal::request()->query->get('cliente_id');

    if (empty($cliente_id)) {
      throw new BadRequestHttpException('Falta el parámetro cliente_id.');
    }

    try {
      $token = $this->getSoftSegurosToken();
      if (!$token) {
        return new ResourceResponse(['error' => 'No se pudo obtener el token de autenticación.'], 401);
      }

      $client_data = $this->querySoftSegurosClient($token, $cliente_id);
    }
    catch (\Exception $e) {
      \Drupal::logger('prodepem_solicitudes_rtm')->error($e->getMessage());
      return new ResourceResponse(['error' => 'Error de conexión con SoftSeguros: ' . $e->getMessage()], 500);
    }

    $response = [
      'cliente_id' => $cliente_id,
      'status' => 'success',
      'data' => $client_data,
    ];

    $resource_response = new ResourceResponse($response, 200);
    $resource_response->getCacheableMetadata()->addCacheContexts(['url.query_args:cliente_id']);
    
    return $resource_response;
  }

  /**
   * Consulta los datos del cliente en SoftSeguros por documento.
   *
   * @param string $token
   *   El token de autenticación.
   * @param string $documento
   *   El número de documento del cliente.
   *
   * @return array
   *   Los datos del cliente devueltos por la API.
   */
  protected function querySoftSegurosClient($token, $documento) {
    $client = \Drupal::httpClient();
    $url = 'https://app.softseguros.com/api/cliente/listar_cliente_por_documento/';

    $response = $client->get($url, [
      'headers' => [
        'Authorization' => 'Token ' . $token,
        'Accept' => 'application/json',
      ],
      'form_params' => [
        'numero_documento' => $documento,
      ],
    ]);

    if ($response->getStatusCode() === 200) {
      return json_decode($response->getBody(), TRUE);
    }

    throw new \Exception('La API de SoftSeguros respondió con código ' . $response->getStatusCode());
  }

  /**
   * Obtiene el token de autenticación de SoftSeguros.
   *
   * @return string|null
   *   El token o NULL si falla.
   */
  protected function getSoftSegurosToken() {
    $config = \Drupal::config('prodepem_solicitudes_rtm.settings');
    $username = $config->get('usuario_softseguros');
    $password = $config->get('clave_softseguros');

    if (empty($username) || empty($password)) {
      \Drupal::logger('prodepem_solicitudes_rtm')->error('Credenciales de SoftSeguros no configuradas.');
      return NULL;
    }

    $client = \Drupal::httpClient();
    $url = 'https://app.softseguros.com/api-token-auth/';

    $response = $client->post($url, [
      'json' => [
        'username' => $username,
        'password' => $password,
      ],
    ]);

    if ($response->getStatusCode() === 200) {
      $data = json_decode($response->getBody(), TRUE);
      return $data['token'] ?? NULL;
    }

    return NULL;
  }

}
