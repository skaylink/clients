<?php namespace Skaylink\Concerns;

trait Filter
{
  /**
   * Create base64 encoded JSON
   *
   * @param  array  $params
   * @return string
   * @access public
   * @static
   */
  public static function createFilterHash(array $params = []): string
  {
    return base64_encode(json_encode($params, JSON_UNESCAPED_SLASHES));
  }

  /**
   * Parse requested base64 encoded JSON filter
   *
   * @param  string|null  $filter
   * @return array
   * @access public
   * @static
   */
  public static function parseFilterHash(string $filter = null): array
  {
    $empty = [];
    if (is_null($filter)) return $empty;
    $filter = json_decode(base64_decode($filter), true);
    if (!is_array($filter)) return $empty;
    return $filter;
  }

  /**
   * @param  \Illuminate\Http\Request $request
   * @param  array                    $filters
   * @return array
   * @access protected
   */
  protected function getFilterParams(Request $request, array $filters = []): array
  {
    return [
      'limit' => $request->get('limit', 50),
      'page'  => $request->get('page', 1),
      // the ordering of array keys should be alphabetic because of checksum creation
      'filter' => self::createFilterHash(array_merge($filters, [
        'direction' => $request->get('direction', 'asc'),
        'order'     => $request->get('order', null),
        'search'    => $request->get('search', null)
      ]))
    ];
  }
}
