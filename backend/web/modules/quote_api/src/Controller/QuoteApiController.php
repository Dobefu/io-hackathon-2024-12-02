<?php

namespace Drupal\quote_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\Core\Entity\Query\QueryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\node\Entity\Node;

use Drupal\quote_api\Service\QuoteApiService;

/**
 * Controller for the Quote API.
 */
class QuoteApiController extends ControllerBase
{
  public function __construct(protected readonly QuoteApiService $quoteApiService) {}

  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('quote_api.base')
    );
  }

  /**
   * Returns the latest quote.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *  Expected JSON Response containing a single quote.
   */
  public function getQuote(Request $request)
  {
    $unauthorized = $this->quoteApiService->checkAccess($request);
    if ($unauthorized) {
      return $unauthorized;
    }

    // Create the base query to get published 'quote' nodes.
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'quote')
      ->condition('status', 1)
      ->accessCheck(FALSE)
      ->sort('created', 'DESC')
      ->range(0, 1);

    return $this->sendResponse($query);
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
    $unauthorized = $this->quoteApiService->checkAccess($request);

    if ($unauthorized) {
      return $unauthorized;
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

    return $this->quoteApiService->parseQuery($query);
  }
}
