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
  public function getQuote(Request $request, string $id = null)
  {
    $unauthorized = $this->quoteApiService->checkAccess($request);

    if ($unauthorized) {
      return $unauthorized;
    }

    $query = $this->quoteApiService->useQuery();

    if ($id && $query) {
      $query->condition('nid', $id);
    }

    if ($query) {
      $query
        ->sort('created', 'DESC')
        ->range(0, 1);
    }

    return $this->quoteApiService->parseContent($query, TRUE);
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
  public function getQuotes(Request $request): JsonResponse
  {
    $unauthorized = $this->quoteApiService->checkAccess($request);

    if ($unauthorized) {
      return $unauthorized;
    }

    // Filter from the additional Person Taxonomy name value or ID
    $person = $request->query->get('person');
    $sortBy = $request->query->get('sortBy', 'date');
    $sortOrder = $request->query->get('sortOrder', 'ASC');

    $query = $this->quoteApiService->useQuery();

    if (!$query) {
      return new JsonResponse(['Unable to use getQuotes from undefinde query'], 400);
    }

    // Apply additional filtering by `people` Taxonomy name or ID from:
    if ($person) {
      if (is_numeric($person)) {
        $query->condition('field_person', (int) $person);
      } else {
        // Get the expected Taxonomy id from the given name
        $term_ids = $this->quoteApiService->filterByTarget($person);

        if (empty($term_ids)) {
          return new JsonResponse(['error' => 'Unable to find any quote from ' . $person], 404);
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

    return $this->quoteApiService->parseContent($query);
  }

  public function getPeople()
  {
    return $this->quoteApiService->parseTaxonomy();
  }

  public function setQuote(Request $request)
  {
    $data = json_decode($request->getContent(), TRUE);
  }
}
