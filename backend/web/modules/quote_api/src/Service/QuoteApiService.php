<?php

declare(strict_types=1);

namespace Drupal\quote_api\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

const CONTENT_TYPE = 'people';

/**
 * @todo Add class description.
 */
class QuoteApiService
{

  private $globalAccess = 'quote_api.access';
  private $readAccess = 'quote_api.read';
  private $writeAccess = 'quote_api.write';
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
   *
   * @return null|Symfony\Component\HttpFoundation\JsonResponse
   */
  public function checkAccess(Request $request, bool $write = false): null | JsonResponse
  {
    if (!$this->currentUser) {
      return new JsonResponse(['error' => 'Service not available'], 422);
    }

    // Get the current user and check if the required permissions are available.
    if ($this->currentUser->isAuthenticated()) {
      if (!$this->currentUser->hasPermission($write ? $this->writeAccess : $this->readAccess) || !$this->currentUser->hasPermission($this->globalAccess)) {
        return new JsonResponse([
          'error' => 'Access denied, required permissions are not defined for the current user'
        ]);
      }

      return null;
    }

    if (!$this->apiSecret) {
      return new JsonResponse(['error' => 'API Endpoint not available'], 500);
    }

    // Implements basic API token usage via Argon2 that is defined from the
    // expected API secret and timestamp based of the API Range configuration.
    $token = $request->headers->get('Authorization') ?: $request->query->get('token');

    if (!$token) {
      return new JsonResponse(['error' => 'No `token` parameter or `Authorization` header detected from the initial Request!'], 400);
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
   * Creates a new taxonomy from the compatible target value.
   *
   * @param string $target
   *  Create or use the existing person from the given value.
   * @param string $taxonomy
   *  The given context of the actual taxonomy.
   *
   * @return string|Symfony\Component\HttpFoundation\JsonResponse
   */
  public function createTaxonomyEntity(string $target = '', $taxonomy = CONTENT_TYPE): string | JsonResponse
  {
    $result = $this->filterByTarget($target);

    if (!empty($result)) {
      return reset($result);
    }

    $term = $this->taxonomyStorage->create([
      'vid' => $taxonomy,
      'name' => $target,
    ]);

    if (!$term) {
      return new JsonResponse(['error' => 'Unable to create new term: ' . $target]);
    }

    try {
      $term->save();
      return $term->id();
    } catch (\Exception $exception) {
      // Log the error and return NULL.
      if ($exception) {
        return new JsonResponse(['error' => $exception->getMessage()]);
      }
    }

    return new JsonResponse(['error' => 'Term not created: ' . $target]);
  }

  /**
   * Creates a new Quote from the required title and people taxonomy id.
   *
   * @param string $title
   *  The required Quote title.
   * @param string $body
   *  The optional Quote body.
   * @param string $taxonmy
   *  The required taxonomy ID value.
   *
   * @return Symfony\Component\HttpFoundation\JsonResponse
   */
  public function createQuoteEntity(string $title, string $body = '', string $taxonomy = '')
  {
    if (!$title || !$taxonomy) {
      return new JsonResponse(['error' => 'Unable to create new quote with undefined taxonomy...'], 400);
    }

    // Ignore the API key requirement since we expect a valid user.
    if (!$this->currentUser->hasPermission($this->globalAccess) || !$this->currentUser->hasPermission($this->writeAccess)) {
      return new JsonResponse(['error' => 'Request rejected, verified user can only write new Quotes!'], 403);
    }

    try {
      $node = $this->nodeStorage->create([
        'type' => 'quote',
        'title' => $title,
        'body' => [
          'value' => $body,
          'format' => 'basic_html',
        ],
        'field_person' => [
          'target_id' => $taxonomy,
        ],
        'status' => 1,
        'uid' => $this->currentUser->id(),

      ]);

      $node->save();
    } catch (\Exception $exception) {
      return new JsonResponse((['error' => $exception->getMessage()]));
    }

    return new JsonResponse([
      'title' => $title,
      'body' => $body,
      'success' => $node ? TRUE : FALSE
    ]);
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
  private function createTaxonomyQuery(string $value = '', string $taxonomyType = CONTENT_TYPE)
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
   * Helper function to escape the defined string value.
   */
  public function escapeValue(string | null $value)
  {
    return $value ? Html::escape($value) : '';
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
  public function filterByTarget(string $target = '', string $type = CONTENT_TYPE)
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

  /**
   * Helper function to create the base Query interface to use within the
   * module context.
   *
   * @param string $type
   *   Defines the new query from the given content type.
   *
   * @return Drupal\Core\Entity\Query\QueryInterface
   */
  public function useQuery(string $type = 'quote'): QueryInterface
  {
    $query = $this->nodeStorage->getQuery()
      ->condition('type', $type)
      ->condition('status', 1)
      ->accessCheck(FALSE);

    return $query;
  }

  public function parseContent(QueryInterface $query, bool $singular = false): JsonResponse | null
  {
    $entry = $query->execute();

    /** @var \Drupal\node\Entity\Node[] $nodes */
    $nodes = $this->nodeStorage->loadMultiple($entry);

    $response = [];

    if ($nodes && !empty($nodes)) {
      foreach ($nodes as $node) {
        $person = $node->get('field_person');

        $response[] = [
          'id' => $node->id(),
          'person' => $person->entity?->getName(),
          'title' => $node->getTitle(),
        ];
      }
    }

    if (!count($response)) {
      return new JsonResponse(['error' => 'Quote not found'], 404);
    }

    return new JsonResponse(count($response) === 1 && $singular ? $response[0] : $response);
  }

  /**
   * Returns the published taxonomies from the defined taxonomy type.
   *
   * @param string $type
   *  Creates a new base Taxonomy query from the given $type.
   *
   * @return Symfony\Component\HttpFoundation\JsonResponse
   */
  public function parseTaxonomy($type = CONTENT_TYPE): JsonResponse
  {
    $query = $this->createTaxonomyQuery('', $type);
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
