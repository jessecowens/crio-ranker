<?php

const PER_PAGE = 100;
const PAGE = 2;
const URL = 'https://api.wordpress.org/themes/info/1.2/';
const REASONABLE_TIME = 5;
const DATASET_SIZE = 3;
const TARGET_THEME = 'crio';

function get_api_response( $request ) {
  $curl_options = array(
    CURLOPT_URL => URL . (strpos(URL, '?') === FALSE ? '?' : ''). http_build_query($request),
    CURLOPT_HEADER => 0,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => REASONABLE_TIME,
  );
  $curl_connection = curl_init();
  curl_setopt_array($curl_connection, $curl_options);

  if ( ! $api_response = curl_exec($curl_connection) ) {
    trigger_error(curl_error($curl_connection));
  }
  curl_close($curl_connection);
  $array_response = json_decode( $api_response, true );

  return $array_response;
}

//Set up parameters for theme list request
$params = array(
  'action' => 'query_themes',
  'request' => array(
    'browse' => 'popular',
    'per_page' => PER_PAGE,
    'page' => PAGE,
    'fields' => array(
      'active_installs' => true,
      'description' => false,
      'screenshot_url' => false,
      'requires' => false,
      'requires_php' => false,
      'author' => false,
      'preview_url' => false,
    ),
  ),
);

$array_response = get_api_response( $params );

$themes_list = $array_response['themes'];

$theme_location = array_search( TARGET_THEME, array_column($themes_list, 'slug' ) );
if ( ! $theme_location ) {
  exit( 'Target theme ' . TARGET_THEME . ' not found in dataset. Adjust per_page and page constants.');
}

$theme_rank = ( ( (int)PER_PAGE * ( (int)PAGE -1 ) ) + (int)$theme_location );

//Slice array down to chosen dataset size
$themes_list = array_slice( $themes_list, ($theme_location - DATASET_SIZE), ((DATASET_SIZE * 2) + 1 ), true );

foreach ( $themes_list as $key => $theme ) {
  $params = array(
    'action' => 'theme_information',
    'request' => array(
      'slug' => $theme['slug'],
      'fields' => array(
        'creation_date' => true,
      ),
    ),
  );
  $array_response = get_api_response( $params );

  $themes_list[$key]['creation_time'] = $array_response['creation_time'];
  echo 'Theme ' . $theme['slug'] . ' fetched.';
  sleep( REASONABLE_TIME );
}

$csvfile = fopen( TARGET_THEME . "-ranks.csv", 'w' );
fputcsv( $csvfile, array(
                          'slug',
                          'rank',
                          'min installs',
                          'max installs',
                          'creation date',
                          'days active',
                          'min popularity',
                          'max popularity',
                        )
 );

foreach ( $themes_list as $key => $theme ) {
    $slug = $theme['slug'];
    $rank = ( ( (int)PER_PAGE * ( (int)PAGE -1 ) ) + (int)$key );
    $active_installs = (int)$theme['active_installs'];
    if ( $active_installs < 1000 ) {
      $max_installs = $active_installs + 99;
    } else if ( $active_installs < 10000 ) {
      $max_installs = $active_installs + 999;
    } else if ( $active_installs < 100000 ) {
      $max_installs = $active_installs + 9999;
    } else if ( $active_installs < 1000000 ) {
      $max_installs = $active_installs + 99999;
    } else {
      $max_installs = $active_installs + 999999;
    }
    $creation_date = $theme['creation_time'];

    $now = date_create('now', new DateTimeZone('UTC') );
    $date = date_create_from_format('Y-m-d H:i:s', $creation_date, new DateTimeZone('UTC') );
    $interval = date_diff($now, $date);

    $days_active = $interval->format('%a');

    $min_popularity = ( (float)$active_installs / (float)$days_active );
    $max_popularity = ( (float)$max_installs / (float)$days_active );

    fputcsv( $csvfile, array(
                              $slug,
                              $rank,
                              $active_installs,
                              $max_installs,
                              $creation_date,
                              $days_active,
                              $min_popularity,
                              $max_popularity,
                            )
    );
}

fclose($csvfile);
