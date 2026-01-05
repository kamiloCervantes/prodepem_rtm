<?php

namespace Drupal\prodepem_solicitudes_rtm\Controller;

use Drupal\Core\Controller\ControllerBase;

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

}
