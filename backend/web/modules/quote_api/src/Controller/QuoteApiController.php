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
   * Helper method to get term IDs from a target (plain text, HTML entity, or Base64).
   *
   * @param string $target
   *   The target string (could be a plain text, HTML entity, or Base64).
   *
   * @return array
   *   Array of term IDs found for the target.
   */
  private function filterByTarget($target)
  {
    $term_ids = $this->queryFromTaxonomyValue($target)->execute();

    if (empty($term_ids)) {
      // If no term found, try to decode the target as HTML entities
      $decoded_target = html_entity_decode($target, ENT_QUOTES, 'UTF-8');
      $term_ids = $this->queryFromTaxonomyValue($decoded_target)->execute();

      // Search with successfull decoded Base64 value:
      if (empty($term_ids)) {
        $decoded_target = base64_decode($target, true);

        if ($decoded_target !== false) {
          $term_ids = $this->queryFromTaxonomyValue($decoded_target)->execute();
        }
      }
    }

    return $term_ids;
  }


  private function queryFromTaxonomyValue($value)
  {
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

    // Filter from the additional Person Taxonomy name value or ID
    $target = $request->query->get('target');

    // Fetch published quotes of content type 'quote'.
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'quote')
      ->condition('status', 1)
      ->accessCheck(access_check: FALSE); // Use API key instead, .
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

    $status = count($response) ? 200 : 404;

    return new JsonResponse($response);
  }

  // public function filterByPerson()
  // {
  //   return \Drupal::entityQuery('taxonomy_term')
  //     ->condition('name', $target, 'Like')
  //     ->condition('vid', 'people')
  //     ->accessCheck(access_check: False)
  //     ->execute();
  // }
}
