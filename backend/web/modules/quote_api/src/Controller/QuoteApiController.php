<?php

namespace Drupal\quote_api\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\node\Entity\Node;

/**
 * Controller for the Quote API.
 */
class QuoteApiController
{

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
    // Fetch published quotes of content type 'quote'.
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'quote')
      ->condition('status', 1)
      ->accessCheck(FALSE); // Use API key instead, .
    ;

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

    $status = count($response) ? 200 : 204;

    return new JsonResponse($response, $status);
  }
}
