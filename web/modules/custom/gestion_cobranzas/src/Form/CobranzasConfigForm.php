<?php

namespace Drupal\gestion_cobranzas\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure gestion_cobranzas settings.
 */
class CobranzasConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gestion_cobranzas_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['gestion_cobranzas.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('gestion_cobranzas.settings');

    $form['mensaje_notificacion'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Mensaje de notificación'),
      '#default_value' => $config->get('mensaje_notificacion'),
      '#description' => $this->t('El mensaje para la notificación de cuotas vencidas. Use [mes] como comodín para el nombre del mes.'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('gestion_cobranzas.settings')
      ->set('mensaje_notificacion', $form_state->getValue('mensaje_notificacion'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
