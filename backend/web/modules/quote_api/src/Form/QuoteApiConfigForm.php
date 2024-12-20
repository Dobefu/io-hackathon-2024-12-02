<?php

namespace Drupal\quote_api\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class QuoteApiConfigForm extends ConfigFormBase
{
  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames()
  {
    return ['quote_api.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'quote_api_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $config = $this->config('quote_api.settings');

    $form['api_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Secret'),
      '#default_value' => $config->get('api_secret'),
      '#required' => TRUE,
    ];

    // Generate the Argon2 hash of the API secret and display it as a read-only field
    $apiToken = $config->get('api_token') ?: '';
    $form['api_token'] = [
      '#type' => 'textarea',
      '#title' => $this->t('API Token'),
      '#default_value' => $apiToken,
      '#readonly' => TRUE,
      '#description' => $this->t('Argon2 Generated Api Token generated from the current API Secret.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    // Generate the Argon2 hash of the API secret
    $apiSecret = $form_state->getValue('api_secret');
    $this
      ->config('quote_api.settings')
      ->set('api_secret', $apiSecret)
      ->save();

    if ($apiSecret) {
      $argon2_hash = password_hash($apiSecret, 'argon2id', [
        'memory_cost' => 256,
        'time_cost' => 1,
        'threads' => 1
      ]);

      $this
        ->config('quote_api.settings')
        ->set('api_token', base64_encode($argon2_hash))
        ->save();
    }

    parent::submitForm($form, $form_state);
  }
}
