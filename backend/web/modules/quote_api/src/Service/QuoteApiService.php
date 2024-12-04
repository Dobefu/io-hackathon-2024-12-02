<?php

declare(strict_types=1);

namespace Drupal\quote_api\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
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
  private $nodeStorage;
  private $taxonomyStorage;

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

    $this->taxonomyStorage = $this->entityTypeManager->getStorage('taxonomy_term');
    $this->nodeStorage = $this->entityTypeManager->getStorage('node');
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

  /**
   * Constructs the additional Taxonomy query to get any Quote with the given
   * target value.
   *
   * @param string $value
   *  The optional target value.
   *
   * @return mixed \Drupal\Core\Entity\Query\QueryInterface
   *  Returns the optional Query interface.
   */
  private function createTaxonomyQuery(string $value = '', string $taxonomyType = 'people')
  {
    $query = $this->taxonomyStorage->getQuery();

    if ($value) {
      $query
        ->condition('name', $value, 'LIKE');
    }

    $query
      ->condition('vid', $taxonomyType)
      ->accessCheck(FALSE);

    return $query;
  }

  /**
   * Helper method to get term IDs from a target as plain text, HTML entity
   * or Base64 encoded string.
   *
   * @param string $target
   *   The target string (could be a plain text, HTML entity, or Base64).
   *
   * @return array
   *   Array of term IDs found for the target.
   */
  public function filterByTarget(string $target = '', string $type = 'people')
  {
    $term_ids = $this->createTaxonomyQuery($target, $type)->execute();

    if (empty($term_ids)) {
      // If no term found, try to decode the target as HTML entities
      $decoded_target = html_entity_decode($target, ENT_QUOTES, 'UTF-8');
      $term_ids = $this->createTaxonomyQuery($decoded_target, $type)->execute();

      // Search with successfull decoded Base64 value only:
      if (empty($term_ids)) {
        $decoded_target = base64_decode($target, true);

        if ($decoded_target !== false) {
          $term_ids = $this->createTaxonomyQuery($decoded_target, $type)->execute();
        }
      }
    }

    return $term_ids;
  }

  public function useQuery(string $type = 'quote'): QueryInterface
  {
    $query = $this->nodeStorage->getQuery()
      ->condition('type', $type)
      ->condition('status', 1)
      ->accessCheck(FALSE);

    return $query;
  }

  public function parseContent(QueryInterface $query): JsonResponse | null
  {
    $entry = $query->execute();

    /** @var \Drupal\node\Entity\Node[] $nodes */
    $nodes = $this->nodeStorage->loadMultiple($entry);

    $response = [];

    if ($nodes && !empty($nodes)) {
      foreach ($nodes as $node) {
        $person = $node->get('field_person');

        $response[] = [
          'body' => $node->get('body')->value,
          'id' => $node->id(),
          'person' => $person->entity?->getName(),
          'title' => $node->getTitle(),
        ];
      }
    }

    if (!count($response)) {
      return new JsonResponse(['error' => 'Quote not found'], 404);
    }

    return new JsonResponse($response);
  }

  public function parseTaxonomy(): JsonResponse
  {
    $query = $this->createTaxonomyQuery();
    $vids = $query->execute();

    /** @var \Drupal\node\Entity\Taxonomy[] $taxonomies */
    $taxonomies = $this->taxonomyStorage->loadMultiple($vids);

    $response = [];

    if ($taxonomies && !empty($taxonomies)) {
      foreach ($taxonomies as $key => $value) {
        $response[] = [
          'id' => $key,
          'name' => $value->getName(),
        ];
      }
    }

    if (!count($response)) {
      return new JsonResponse(['error' => 'Quote not found'], 404);
    }

    return new JsonResponse($response);
  }
}
