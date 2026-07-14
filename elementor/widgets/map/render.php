<?php
$settings = $this->get_settings_for_display();
$filtered_settings = array_intersect_key($settings, array_flip([
  'map_height',
]));
$unique_id = uniqid();

$center = eyeon_get_center();

$mapboxProps = array(
  'config' => Array(
    'CENTER_ID' => intval($center['id']),
    'ROLE' => 'WP_SITE',
    'IMAGE_PROXY_URL' => site_url().'/index.php?eyeonmedia=',
  ),
  'webApiURI' => rest_url('eyeon-portal/map'),
);

$selected_store_id = (isset($_GET['r']) && !empty(['r'])) ? $_GET['r'] : null;
if($selected_store_id) {
  $mapboxProps['config']['SELECTED_RETAILER_ID'] = intval($selected_store_id);
}
?>

<div id="eyeon-map-<?= $unique_id ?>" class="eyeon-map">
  <div class="eyeon-wrapper">
      <div id="root"></div>
    </div>
  </div>
</div>

<script type="text/javascript">
(function() {
  var mapId = 'eyeon-map-<?= $unique_id ?>';
  var rootElement = document.querySelector('#' + mapId + ' #root');
  
  if (!rootElement) return;
  
  // Base props from PHP
  var baseProps = <?= json_encode($mapboxProps) ?>;
  
  // Get cached map API response from database
  var cachedMapApiResponse = <?= json_encode(get_option(THREEJS_MAP_API_RESPONSE_KEY, null)) ?>;
  
  // Create the callback function that saves map API response to WordPress
  var onNewMapApiResponse = function(mapApiResponse) {
    // Send the map API response to WordPress to save in wp_options
    if (typeof jQuery !== 'undefined' && typeof EYEON !== 'undefined') {
      jQuery.ajax({
        url: EYEON.ajaxurl,
        type: 'POST',
        data: {
          action: 'eyeon_save_map_response',
          mapResponse: JSON.stringify(mapApiResponse)
        },
        success: function(response) {
          if (response.success) {
            // console.log('Map API response saved successfully');
          } else {
            console.error('Failed to save map API response:', response.data);
          }
        },
        error: function(xhr, status, error) {
          console.error('AJAX error saving map API response:', error);
        }
      });
    } else {
      console.error('jQuery or EYEON object not available');
    }
  };
  
  // Create complete AppProps object with callback function and cached data
  // This is the single object that includes everything the React component needs
  var appProps = {
    config: baseProps.config || {},
    webApiURI: baseProps.webApiURI || '',
    cachedMapApiResponse: cachedMapApiResponse,
    onNewMapApiResponse: onNewMapApiResponse
  };
  
  // Attach the complete props object to the root element
  // The React component should read from: element.eyeonMapProps
  rootElement.eyeonMapProps = appProps;
  
  // Dispatch event to notify that props are ready
  var event = new CustomEvent('eyeon:map:props:ready', {
    detail: {
      mapId: mapId,
      props: appProps,
      element: rootElement
    }
  });
  rootElement.dispatchEvent(event);
})();
</script>
