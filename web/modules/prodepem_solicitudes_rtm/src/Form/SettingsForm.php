<?php

namespace Drupal\prodepem_solicitudes_rtm\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure RTM settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'prodepem_solicitudes_rtm_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['prodepem_solicitudes_rtm.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('prodepem_solicitudes_rtm.settings');

    $form['softseguros_auth'] = [
      '#type' => 'details',
      '#title' => $this->t('Autenticación SoftSeguros'),
      '#open' => TRUE,
    ];

    $form['softseguros_auth']['usuario_softseguros'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Usuario SoftSeguros'),
      '#default_value' => $config->get('usuario_softseguros'),
    ];

    $form['softseguros_auth']['clave_softseguros'] = [
      '#type' => 'password',
      '#title' => $this->t('Clave SoftSeguros'),
      '#default_value' => $config->get('clave_softseguros'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('prodepem_solicitudes_rtm.settings')
      ->set('usuario_softseguros', $form_state->getValue('usuario_softseguros'))
      ->set('clave_softseguros', $form_state->getValue('clave_softseguros'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
