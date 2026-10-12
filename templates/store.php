<?php
$mycenterstore = $this->mcd_settings['mycenterstore'];

$global_retailer_links = array();
if( isset($mycenterstore['global_retailer']['links']) && is_array($mycenterstore['global_retailer']['links']) ) {
  $global_retailer_links = $mycenterstore['global_retailer']['links'];
}
$retailer_links = array();
if( isset($mycenterstore['links']) && is_array($mycenterstore['links']) ) {
  $retailer_links = $mycenterstore['links'];
}

$social_links = array_merge($global_retailer_links, $retailer_links);
$social_links_order = array('website', 'twitter', 'facebook', 'linkedin', 'instagram', 'youtube', 'snapchat', 'tiktok', 'tripadvisor', 'pinterest');
$store_url = mcd_single_page_url('mycenterstore');

?>

<?php if( is_array($mycenterstore) ) : ?>

<div id="eyeonstore-single" class="mycenterdeals-wrapper">
	<?php if( isset( $mycenterstore['error'] ) ) : ?>
    <?php if( isset( $mycenterstore['error']['description'] ) ) : ?>
      <div class="mcd-alert"><?= $mycenterstore['error']['description'] ?></div>
    <?php else: ?>
      <div class="mcd-alert"><?= $mycenterstore['error'] ?></div>
    <?php endif; ?>
	<?php else: ?>
		<div class="eyeon-store clearfix">
			<div class="mcd-prev-next-nav">
				<?php if( !empty($this->mcd_settings['stores_listing_page']) ) : ?>
					<a href="<?= get_permalink($this->mcd_settings['stores_listing_page']) ?>" class="item back">Back to Stores</a>
				<?php endif; ?>
			</div>

			<div class="mcd-store-cols">
				<div class="mcd-retailer-image-col">
					<div class="mcd-retailer-image">
						<img src="<?= $mycenterstore['media']['url'] ?>" />
						<?php
						$custom_flags = ( isset( $mycenterstore['custom_flags'] ) && is_array( $mycenterstore['custom_flags'] ) ) ? $mycenterstore['custom_flags'] : array();
						$custom_flags = array_values( array_filter( $custom_flags, function( $flag ) {
							return is_array( $flag ) && ! empty( $flag['name'] );
						} ) );
						?>
						<?php if( ! empty( $custom_flags ) ) : ?>
							<ul class="custom-flags eyeon-hide">
								<?php foreach( $custom_flags as $flag ) : ?>
									<li><?= esc_html( $flag['name'] ) ?></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
				</div>

				<div class="mcd-retailer-details">
					<div class="mcd-retailer-name"><?= $mycenterstore['name'] ?></div>
          
          <div class="eyeon-retailer-details-cols">
            <div class="eyeon-retailer-content">
              <?php
              $retailer_location = get_retailer_location($mycenterstore['location']);
              $retailer_phone = eyeon_format_phone($mycenterstore['retailer_phone']);
              ?>
              <?php if(!empty($retailer_location) || !empty($retailer_phone)) : ?>
                <div class="eyeon-location-and-phone">
                  <?php if( !empty($retailer_location) ) : ?>
                    <div class="mcd-retailer-location"><span class="mcd-label">Location:</span> <?= $retailer_location ?></div>
                  <?php endif; ?>
                  <?php if( !empty($retailer_phone) ) : ?>
                    <div class="mcd-retailer-phone"><span class="mcd-label">Phone:</span> <?= $retailer_phone ?></div>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
          
              <?php
              $global_description = get_editor_output($mycenterstore['global_retailer']['description']);
              $local_description = get_editor_output($mycenterstore['description']);
              ?>
              <?php if( !empty($global_description) || !empty($local_description)) : ?>
                <div class="mcd-retailer-description editor_output">
                  <?php if( !empty($global_description) ) : ?>
                    <div class="global-description">
                      <?= $global_description ?>
                    </div>
                  <?php endif; ?>
                  <?php if( !empty($local_description) ) : ?>
                    <div class="local-description">
                      <p><?= $local_description ?></p>
                    </div>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
              
              <?php if( $this->mcd_settings['stores_single_social_links'] ) : ?>
                <?php
                $social_links_html = '';
                  foreach ($social_links_order as $link) {
                    if( !empty($social_links[$link]) ) {
                      $social_links_html .= '<li class="'.$link.'"><a href="'.$social_links[$link].'" target="_blank">'.$link.'</a></li>';
                    }
                  }
                ?>
                <?php if( !empty($social_links_html) ) : ?>
                <div class="mcd-social-links">
                  <ul class="mcd-social-icons"><?= $social_links_html ?></ul>
                </div>
                <?php endif; ?>
              <?php endif; ?>

              <?php if( !empty($this->mcd_settings['map_page']) && $mycenterstore['mapit'] ) :
                $map_url = get_permalink($this->mcd_settings['map_page']).$mycenterstore['slug'].'?r='.$mycenterstore['id'];
                ?>
                <a href="<?= $map_url ?>" class="eyeon-btn">Map IT</a>
              <?php endif; ?>
            </div>

            <?php if( $mycenterstore['opening_hours'] ) : ?>
            <div class="mcd-retailer-opening-hours">
              <h4>Hours:</h4>
              <div class="hours-sets">
                <?php foreach( eyeon_weekdays() as $key=>$day ) : ?>
                <div class="hours-set">
                  <div class="day"><?= $day ?></div>
                  <div class="time">
                    <?php if( @$mycenterstore['opening_hours'][$key]['isClosed'] ) : ?>
                      Closed
                    <?php else: ?>
                      <?= eyeon_format_time($mycenterstore['opening_hours'][$key]['startTime']) ?> - <?= eyeon_format_time($mycenterstore['opening_hours'][$key]['endTime']) ?>
                    <?php endif; ?>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>
			</div>
		</div>

		<?php include(MCD_PLUGIN_PATH.'templates/store-deals.php') ?>
	<?php endif; ?>
</div>

<?php endif; ?>

