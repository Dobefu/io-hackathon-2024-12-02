<?php

declare(strict_types=1);

namespace Drupal\quote_api\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @todo Add class description.
 */
class QuoteApiService
{

  private $readAccess = 'quote_api.access';
  private $configName = 'quote_api.settings';
  private $apiSecret;

  /**
   * Constructs a QuoteApiService object.
   */
  public function __construct(
    private readonly AccountProxyInterface $currentUser,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    $configFactory = $this->configFactory->get($this->configName);

    $this->apiSecret = $configFactory->get('api_secret');
  }


  /**
   * Authentication helper that verifies the current user with the expected
   * module permissions or check the optional API credentials instead.
   *
   * The Request should return a valid JSON response with additional error
   * information while any of the verification fails.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   * @return JsonResponse|null
   */
  public function checkAccess(Request $request): null | JsonResponse
  {
    if (!$this->currentUser) {
      return new JsonResponse(['error' => 'Service not available'], 422);
    }

    // Get the current user and check if the required permissions are available.
    if ($this->currentUser->isAuthenticated()) {
      if (!$this->currentUser->hasPermission($this->readAccess)) {
        return new JsonResponse([
          'error' => 'Access denied, required permissions are not defined for the current user'
        ]);
      }

      return null;
    }

    if (!$this->apiSecret) {
      return new JsonResponse(['error', 'API Endpoint not available'], 500);
    }

    // Implements basic API token usage via Argon2 that is defined from the
    // expected API secret and timestamp based of the API Range configuration.
    $token = $request->headers->get('Authorization') ?: $request->query->get('token');

    if (!$token) {
      return new JsonResponse(['error', 'No `token` parameter or `Authorization` header detected from the initial Request!'], 400);
    }

    $hash = base64_decode($token);
    if (!$hash) {
      return new JsonResponse(['error' => 'Unable to process required API token:' . $token], 422);
    }

    if (!password_verify($this->apiSecret, $hash)) {
      return new JsonResponse(['error' => 'Token rejected or expired: ' . $token], 403);
    }

    return null;
  }
}
