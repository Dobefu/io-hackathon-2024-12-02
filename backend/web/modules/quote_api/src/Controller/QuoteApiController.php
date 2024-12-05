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
   * Deletes the existing quote from the expected Request parameters.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *  Expected JSON Response containing the status of the Request operation.
   */
  public function deleteQuote(Request $request)
  {
    $unauthorized = $this->quoteApiService->checkAccess($request);

    if ($unauthorized) {
      return $unauthorized;
    }

    $id = $request->query->get('id');

    if (!is_numeric($id)) {
      return new JsonResponse(['error' => 'Unable to delete undefined Quote: ' . $id]);
    }

    return $this->quoteApiService->deleteQuote($id);
  }

  /**
   * Returns the latest quote.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *  Expected JSON Response containing a single quote.
   */
  public function getQuote(Request $request, string $id = null)
  {
    $unauthorized = $this->quoteApiService->checkAccess($request);

    if ($unauthorized) {
      return $unauthorized;
    }

    /** @var QueryInterface */
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
    $field = 'field_person';

    /** @var QueryInterface */
    $query = $this->quoteApiService->useQuery();

    if (!$query) {
      return new JsonResponse(['Unable to use getQuotes from undefinde query'], 400);
    }

    // Apply additional filtering by `people` Taxonomy name or ID from:
    if ($person) {
      if (is_numeric($person)) {
        $query->condition($field, (int) $person);
      } else {
        // Get the expected Taxonomy id from the given name
        $term_ids = $this->quoteApiService->filterByTarget($person);

        if (empty($term_ids)) {
          return new JsonResponse(['error' => 'Unable to find any Quote from:' . $person], 404);
        }

        $query->condition($field, reset($term_ids)); // Take the first matching term ID.
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


  /**
   * Returns all existing People taxonomy entries.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *  Expected JSON Response containing all existing People taxonomies.
   */
  public function getPeople()
  {
    return $this->quoteApiService->parseTaxonomy();
  }

  public function searchQuote(Request $request)
  {
    $title = $request->query->get('title');

    if (!$title) {
      return $this->getQuotes($request);
    }

    /** @var QueryInterface */
    $query = $this->quoteApiService->useQuery();

    if (!$query) {
      return new JsonResponse(['error' => 'Unable to search quote:' . $title]);
    }

    $query
      ->condition('title', '%' . $title . '%', 'LIKE');

    return $this->quoteApiService->parseContent($query);
  }


  /**
   * Returns all existing People taxonomy entries.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *  The initial HTTP Request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *  Expected JSON Response Object.
   */
  public function setQuote(Request $request)
  {
    $unauthorized = $this->quoteApiService->checkAccess($request, TRUE);

    if ($unauthorized) {
      return $unauthorized;
    }

    $title = $request->query->get('title');
    $body = $this->quoteApiService->escapeValue($request->query->get('body'));
    $person = $this->quoteApiService->escapeValue($request->query->get('person'), TRUE);

    if (empty($title)) {
      return new JsonResponse(['error' => 'Cannot create new Quote from missing field: title'], 400);
    }

    if (empty($person)) {
      return new JsonResponse(['error' => 'Cannot create new Quote from missing field: person.'], 400);
    }

    $taxonomy = $this->quoteApiService->createTaxonomyEntity($person);

    if ($taxonomy instanceof JsonResponse) {
      return $taxonomy;
    }

    $quote = $this->quoteApiService->createQuoteEntity($title, $body || '', $taxonomy, $person);

    if ($quote instanceof JsonResponse) {
      return $quote;
    }

    return
      new JsonResponse(['error' => 'Quote not defined from: ' . $title], 500);
  }
}
