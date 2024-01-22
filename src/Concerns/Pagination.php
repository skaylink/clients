<?php namespace Skaylink\Clients\Concerns;

use Skaylink\Clients\Concerns\Filter;
use Illuminate\Http\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Closure;
use Exception;
use Error;

trait Pagination
{
  use Filter;

  /**
   * @param  string                           $uri
   * @param  \Illuminate\Http\Request         $request
   * @param  array                            $filter
   * @return \Illuminate\Support\Collection   $collection
   *
   * @todo fix required alphabetic array key
   */
  public function fetch(string $uri, Request $request, array $filter = []): Collection
  {
    $params = $this->getFilterParams($request, $filter);
    return $this->get($uri, array_merge($params, [
      'limit'  => $request->get('limit', 100),
      'page'   => $request->get('page', 1)
    ]));
  }

  /**
   * @param  string                           $uri
   * @param  \Illuminate\Http\Request         $request
   * @param  array                            $filter
   * @return \Illuminate\Support\Collection   $collection
   */
  public function paginate(string $uri, Request $request, array $filter = []): Collection
  {
    try {
      $data     = [];
      $params   = $this->getFilterParams($request, $filter);
      $response = $this->iterate($uri, $params, function($result) use ($data) {
        foreach($result as $collection) {
          $data[] = $collection;
        }
      });
      return collect([
        'code' => $response->getStatusCode(),
        'data' => $data
      ]);
    } catch (Exception | Error $e) {
      return collect([
        'code'    => $e->getCode(),
        'message' => $e->getMessage(),
      ]);
    }
  }
  /**
   * paginate next via query string parameter "page"
   *
   * @param  string                             $uri
   * @param  array                              $params
   * @param  \Closure                           $callback(data, code, message)
   * @return \Illuminate\Http\Client\Response   $response
   * @access private
   */
  private function iterate(string $uri, array $params = [], Closure $callback): Response
  {
    $url      = $this->endpoint($uri);
    $response = Http::accept('application/json')
      ->withToken($this->bearer)
      ->withHeaders($this->headers('get', $url, $params))
      ->get($url, $params);
      jot(['cmapi@pagination:iterate:' => [
        'uri' => $uri,
        'params' => $params
      ]]);
    switch(true) {
      case $response->successful():
        $body = recursive($response->json());
        $callback($body->get('data'));
        if ($meta = optional($body->get('@meta'))->get('pagination')) {
          $page = (int) $meta->get('currentPage');
          while ($page < (int) $meta->get('totalPages')) {
            $page++;
            $this->iterate($uri,
              array_merge($params, ['page' => $page]), $callback);
          }
        }
      case $response->failed():
        jotError(['cmapi@pagination:iterate' => $response->json()]);
        return $response;
    }
    return $response;
  }
}
