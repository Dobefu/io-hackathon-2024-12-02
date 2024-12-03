<?php

namespace Drupal\quote_api\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;


/**
 * Controller for the Quote API.
 */
class QuoteApiController
{
  /**
   * Helper method to get term IDs from a target as plain text, HTML entity
   *  or Base64 encoded string.
   *
   * @param string $target
   *   The target string (could be a plain text, HTML entity, or Base64).
   *
   * @return array
   *   Array of term IDs found for the target.
   */
  private function filterByTarget(string $target)
  {
    $term_ids = $this->queryFromTaxonomyValue($target)->execute();

    if (empty($term_ids)) {
      // If no term found, try to decode the target as HTML entities
      $decoded_target = html_entity_decode($target, ENT_QUOTES, 'UTF-8');
      $term_ids = $this->queryFromTaxonomyValue($decoded_target)->execute();

      // Search with successfull decoded Base64 value only:
      if (empty($term_ids)) {
        $decoded_target = base64_decode($target, true);

        if ($decoded_target !== false) {
          $term_ids = $this->queryFromTaxonomyValue($decoded_target)->execute();
        }
      }
    }

    return $term_ids;
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
  private function queryFromTaxonomyValue(string $value)
  {
    if (!$value) {
      return;
    }

    $query = \Drupal::entityQuery('taxonomy_term')
      ->condition('name', $value, 'LIKE')
      ->condition('vid', 'people')
      ->accessCheck(FALSE);

    return $query;
  }


  /**
   * Returns the available entries from the `quote` content type.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *  The initial HTTP Request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *  Expected JSON Response containing quote data.
   */
  public function getQuotes(Request $request)
  {
    $secret = \Drupal::config('quote_api.settings')->get('api_secret');
    if (!$secret) {
      return new JsonResponse(['error', 'API Endpoint not available'], 500);
    }

    // Implements basic Api token usage via Argon2 that is defined from the
    // expected API secret.
    $token = $request->headers->get('Authorization') ?: $request->query->get('token');

    if (!$token) {
      return new JsonResponse(['error', 'No `token` parameter or `Authorization` header detected from the initial Request!'], 400);
    }

    $hash = base64_decode($token);
    if (!$hash) {
      return new JsonResponse(['error' => 'Unable to process required API token:' . $token], 422);
    }

    if (!password_verify($secret, $hash)) {
      return new JsonResponse(['error' => 'Token rejected: ' . $token], 403);
    }

    // Filter from the additional Person Taxonomy name value or ID
    $target = $request->query->get('target');
    $sortBy = $request->query->get('sortBy', 'date');
    $sortOrder = $request->query->get('sortOrder', 'ASC');

    // Fetch published quotes of content type 'quote'.
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'quote')
      ->condition('status', 1)
      ->accessCheck(FALSE); // Use API key instead, .
    ;

    // Apply additional filtering by `people` Taxonomy name or ID from:
    if ($target) {
      if (is_numeric($target)) {
        $query->condition('field_person', (int) $target);
      } else {
        $decodedTarget = html_entity_decode($target, ENT_QUOTES, 'UTF-8');


        // Get the expected Taxonomy id from the given name
        $term_ids = $term_ids = $this->filterByTarget($target);

        if (empty($term_ids)) {
          return new JsonResponse(['error' => 'Unable to find any quote from ' . $target], 404);
        }

        $query->condition('field_person', reset($term_ids)); // Take the first matching term ID.
      }
    }

    // Implements basic sorting by Name or Date in ascending or descending
    // order:
    switch ($sortBy) {
      case 'name':
        $query->sort('title', $sortOrder);
        break;

      default:
        $query->sort('created', $sortOrder);
        break;
    }

    $nodes = $query->execute();
    $quotes = Node::loadMultiple($nodes);

    $response = [];

    foreach ($quotes as $quote) {
      $person = $quote->get('field_person')->entity;
      $response[] = [
        'id' => $quote->id(),
        'title' => $quote->getTitle(),
        'body' => $quote->get('body')->value,
        'person' => $person ? $person->getName() : null,
      ];
    }

    if (!count($response)) {
      return new JsonResponse(['error' => 'Quotes not found'], 404);
    }

    return new JsonResponse($response);
  }
}
