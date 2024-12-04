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

    // Defines the API key range in minutes that is used for the timestamp based
    // API secret.
    $apiRange = $config->get('api_range') ?: 15;
    $form['api_range'] = [
      '#type' => 'number',
      '#title' => $this->t('API Range'),
      '#default_value' => $apiRange,
      '#description' => $this->t('Expire the generated key in minutes:'),
      '#required' => TRUE,
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

    $apiRange = $form_state->getValue('api_range');
    $this
      ->config('quote_api.settings')
      ->set('api_range', $apiRange)
      ->save();

    $currentTime = floor(time() / 60);
    $delta = floor($currentTime / ($apiRange * 60)) * ($apiRange * 60);

    if ($apiSecret) {
      $argon2_hash = password_hash($apiSecret . $delta, 'argon2id', [
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
