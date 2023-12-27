<?php return [

  /*
  |--------------------------------------------------------------------------
  | API configuration file
  |--------------------------------------------------------------------------
  |
  */
  'version' => env('API_VERSION', 'v1'),
  'store' => [
    'driver' =>  env('API_STORE_DRIVER'),
  ],
  'cmapi' => [
    'endpoint'      => env('CMAPI_ENDPOINT'),
    'checksum'      => env('CMAPI_CHECKSUM_NAME'),
    'token'         => env('CMAPI_TOKEN'),
    'salt'          => env('CMAPI_SALT'),
    'credentials'   => [
      'client_id'     => env('CMAPI_KEY'),
      'client_secret' => env('CMAPI_SECRET'),
      'scope'         => env('CMAPI_SCOPE'),
      'grant_type'    => env('CMAPI_GRANT_TYPE')
    ]
  ]
];
