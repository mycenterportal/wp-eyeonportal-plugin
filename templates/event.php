<?php
$mycenterevent = $this->mcd_settings['mycenterevent'];
$formatted_start_time = eyeon_format_time($mycenterevent['start_time']);
$formatted_end_time = eyeon_format_time($mycenterevent['end_time']);

$date_display = isset($mycenterevent['single_page_date_display']) ? $mycenterevent['single_page_date_display'] : null;
$time_display = isset($mycenterevent['single_page_time_display']) ? $mycenterevent['single_page_time_display'] : null;
$event_type = isset($mycenterevent['event_type']) ? $mycenterevent['event_type'] : 'onetime';
$custom_dates = isset($mycenterevent['custom_dates']) ? eyeon_sort_custom_dates($mycenterevent['custom_dates']) : [];
$show_time = ($time_display === 'show' && !$mycenterevent['is_all_day_event']);

$event_dates_list = [];

if ($date_display === 'upcoming') {
  if ($event_type === 'recurring' && !empty($mycenterevent['repeat_rrule'])) {
    $occurrences = eyeon_get_rrule_occurrences($mycenterevent['repeat_rrule'], true);
    if (!empty($occurrences)) {
      $event_dates_list[] = [
        'date' => eyeon_rrule_occurrence_calendar_date($occurrences[0]),
        'start_time' => $mycenterevent['start_time'],
        'end_time' => $mycenterevent['end_time'],
      ];
    } else {
      $event_dates_list[] = [
        'date' => $mycenterevent['start_date'],
        'start_time' => $mycenterevent['start_time'],
        'end_time' => $mycenterevent['end_time'],
      ];
    }
  } elseif ($event_type === 'custom') {
    $upcoming = eyeon_get_upcoming_custom_date($custom_dates);
    if ($upcoming) {
      $event_dates_list[] = $upcoming;
    } else {
      $event_dates_list[] = [
        'date' => $mycenterevent['start_date'],
        'start_time' => $mycenterevent['start_time'],
        'end_time' => $mycenterevent['end_time'],
      ];
    }
  } else {
    $event_dates_list[] = [
      'date' => $mycenterevent['start_date'],
      'start_time' => $mycenterevent['start_time'],
      'end_time' => $mycenterevent['end_time'],
    ];
  }
} elseif ($date_display === 'dateRange') {
  $event_dates_list = 'range';
} elseif ($date_display === 'show') {
  $event_dates_list = 'show';
} elseif ($date_display === 'allUpcoming') {
  if ($event_type === 'recurring' && !empty($mycenterevent['repeat_rrule'])) {
    $occurrences = eyeon_get_rrule_occurrences($mycenterevent['repeat_rrule'], true);
    foreach ($occurrences as $occ) {
      $event_dates_list[] = [
        'date' => eyeon_rrule_occurrence_calendar_date($occ),
        'start_time' => $mycenterevent['start_time'],
        'end_time' => $mycenterevent['end_time'],
      ];
    }
  } elseif ($event_type === 'custom') {
    $now = new DateTime('now', wp_timezone());
    $today = $now->format('Y-m-d');
    foreach ($custom_dates as $cd) {
      if (!empty($cd['date']) && $cd['date'] >= $today) {
        $event_dates_list[] = $cd;
      }
    }
  }
  if (empty($event_dates_list)) {
    $event_dates_list[] = [
      'date' => $mycenterevent['start_date'],
      'start_time' => $mycenterevent['start_time'],
      'end_time' => $mycenterevent['end_time'],
    ];
  }
} elseif ($date_display === 'allDates') {
  if ($event_type === 'recurring' && !empty($mycenterevent['repeat_rrule'])) {
    $occurrences = eyeon_get_rrule_occurrences($mycenterevent['repeat_rrule'], false);
    foreach ($occurrences as $occ) {
      $event_dates_list[] = [
        'date' => eyeon_rrule_occurrence_calendar_date($occ),
        'start_time' => $mycenterevent['start_time'],
        'end_time' => $mycenterevent['end_time'],
      ];
    }
  } elseif ($event_type === 'custom') {
    foreach ($custom_dates as $cd) {
      if (!empty($cd['date'])) {
        $event_dates_list[] = $cd;
      }
    }
  }
  if (empty($event_dates_list)) {
    $event_dates_list[] = [
      'date' => $mycenterevent['start_date'],
      'start_time' => $mycenterevent['start_time'],
      'end_time' => $mycenterevent['end_time'],
    ];
  }
}

$event_url = mcd_single_page_url('mycenterevent');
$prev_url = '';
$next_url = '';

if( isset($mycenterevent['prev']) ) {
	$prev_url = $event_url.$mycenterevent['prev']['slug'];
}
if( isset($mycenterevent['next']) ) {
	$next_url = $event_url.$mycenterevent['next']['slug'];
}
?>

<?php if( is_array($mycenterevent) ) : ?>

<div id="eyeonevent-single" class="mycenterdeals-wrapper">
	<?php if( isset( $mycenterevent['error'] ) ) : ?>
		<?php if( isset( $mycenterevent['error']['description'] ) ) : ?>
      <div class="mcd-alert"><?= $mycenterevent['error']['description'] ?></div>
    <?php else: ?>
      <div class="mcd-alert"><?= $mycenterevent['error'] ?></div>
    <?php endif; ?>
	<?php else: ?>
		<div class="eyeon-event clearfix">
			<div class="mcd-prev-next-nav">
				<?php if( !empty($this->mcd_settings['events_listing_page']) ) : ?>
					<a href="<?= get_permalink($this->mcd_settings['events_listing_page']) ?>" class="item back">Back to Events</a>
				<?php endif; ?>
				<a <?= (!empty($prev_url)?'href="'.$prev_url.'"':'') ?> class="item prev hide <?= (empty($prev_url)?'disabled':'') ?>"><i class="fas fa-chevron-left"></i><span>Prev</span></a>
				<a <?= (!empty($next_url)?'href="'.$next_url.'"':'') ?> class="item next hide <?= (empty($next_url)?'disabled':'') ?>"><span>Next</span><i class="fas fa-chevron-right"></i></a>
			</div>

			<div class="mcd-event-cols">
				<div class="mcd-event-image-col">
					<div class="mcd-event-image">
						<img src="<?= $mycenterevent['media']['url'] ?>" />
					</div>

          <?php if( isset($mycenterevent['image_gallery']) && count($mycenterevent['image_gallery'])>0 ) : ?>
            <div class="event-media-gallery hide">
              <?php foreach( $mycenterevent['image_gallery'] as $image ) : ?>
                <div class="image">
                  <img src="<?= $image['media']['url'] ?>" />
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

				<div class="mcd-event-details-col">
					<div class="mcd-event-name"><?= $mycenterevent['title'] ?></div>

          <?php if( $date_display && $date_display !== 'hide' ) : ?>
            <div class="mcd-event-date-time">
              <?php if( $event_dates_list === 'range' ) : ?>
                <span class="mcd-event-dates"><i class="far fa-calendar-alt"></i>&nbsp;<?= eyeon_format_date($mycenterevent['start_date']) ?> - <?= eyeon_format_date($mycenterevent['end_date']) ?></span>
                <?php if( $show_time ) : ?>
                  <span class="mcd-event-times"><i class="far fa-clock"></i>&nbsp;<?= $formatted_start_time ?> - <?= $formatted_end_time ?></span>
                <?php endif; ?>
              <?php elseif( $event_dates_list === 'show' ) : ?>
                <?php
                  $show_date_str = eyeon_format_date($mycenterevent['start_date']);
                  if ($mycenterevent['end_date'] && $mycenterevent['start_date'] !== $mycenterevent['end_date']) {
                    $show_date_str .= ' - ' . eyeon_format_date($mycenterevent['end_date']);
                  }
                ?>
                <span class="mcd-event-dates"><i class="far fa-calendar-alt"></i>&nbsp;<?= $show_date_str ?></span>
                <?php if( $show_time ) : ?>
                  <span class="mcd-event-times"><i class="far fa-clock"></i>&nbsp;<?= $formatted_start_time ?> - <?= $formatted_end_time ?></span>
                <?php endif; ?>
              <?php elseif( is_array($event_dates_list) && count($event_dates_list) === 1 ) : ?>
                <span class="mcd-event-dates"><i class="far fa-calendar-alt"></i>&nbsp;<?= eyeon_format_date($event_dates_list[0]['date']) ?></span>
                <?php if( $show_time && !empty($event_dates_list[0]['start_time']) ) : ?>
                  <span class="mcd-event-times"><i class="far fa-clock"></i>&nbsp;<?= eyeon_format_time($event_dates_list[0]['start_time']) ?> - <?= eyeon_format_time($event_dates_list[0]['end_time']) ?></span>
                <?php endif; ?>
              <?php elseif( is_array($event_dates_list) && count($event_dates_list) > 1 ) : ?>
                <ul class="event-dates-list">
                  <?php foreach( $event_dates_list as $d ) : ?>
                    <li>
                      <span class="mcd-event-dates"><i class="far fa-calendar-alt"></i>&nbsp;<?= eyeon_format_date($d['date']) ?></span>
                      <?php if( $show_time && !empty($d['start_time']) ) : ?>
                        <span class="mcd-event-times"><i class="far fa-clock"></i>&nbsp;<?= eyeon_format_time($d['start_time']) ?> - <?= eyeon_format_time($d['end_time']) ?></span>
                      <?php endif; ?>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>
          <?php endif; ?>
					
          <div class="mcd-event-description editor_output"><?= get_editor_output($mycenterevent['description']) ?></div>

					<?php if( $this->mcd_settings['events_single_add_to_calendar'] ) : ?>
					<div class="mcd-event-add-to-calendar">
						<div title="Add to Calendar" class="addeventatc">
							<span>Add to Calendar</span>
							<span class="date_format">DD/MM/YYYY</span>
							<span class="start"><?= date('d/m/Y', strtotime($mycenterevent['start_date'])) ?> <?= (!empty($formatted_start_time)?$formatted_start_time:'12:00 am') ?></span>
							<span class="end"><?= date('d/m/Y', strtotime($mycenterevent['end_date'])) ?> <?= (!empty($formatted_end_time)?$formatted_end_time:'11:59 pm') ?></span>
							<?php if( $mycenterevent['is_all_day_event'] ) : ?>
                <span class="all_day_event">true</span>
							<?php endif; ?>
							<?php if( $mycenterevent['is_repeat_event'] ) : ?>
                <span class="recurring"><?= $mycenterevent['repeat_rrule'] ?></span>
							<?php endif; ?>
							<span class="title"><?= $mycenterevent['title'] ?></span>
							<span class="description"><?= $mycenterevent['description'] ?></span>
							<span class="location"><?= $mycenterevent['center']['name'] ?></span>
						</div>
					</div>
					<?php endif; ?>

					<?php if( $this->mcd_settings['events_single_social_share'] ) : ?>
					<div class="mcd-event-share clearfix">
            <span class="mcd-share-title mcd-label">Share</span>
						<ul class="mcd-social-icons">
							<li class="twitter"><a href="http://twitter.com/share?text=<?= urlencode($mycenterevent['title']) ?>&url=<?= get_current_url() ?>" target="_blank">Twitter</a></li>
							<li class="facebook"><a href="http://www.facebook.com/sharer.php?u=<?= get_current_url() ?>&quote=<?= urlencode($mycenterevent['title']) ?>" target="_blank">Facebook</a></li>
							<li class="email"><a href="mailto:?subject=<?= $mycenterevent['title'] ?>&body=Hi,%0D%0A%0D%0AEvent Details - <?= urlencode(get_current_url()) ?>%0D%0A%0D%0A<?= $mycenterevent['title'] ?>%0D%0A%0D%0A<?= urlencode($mycenterevent['description']) ?>%0D%0A%0D%0A<?= eyeon_format_date($mycenterevent['start_date']) ?>%0D%0A<?= $formatted_start_time ?> - <?= $formatted_end_time ?>%0D%0A%0D%0ACenter Location: <?= $mycenterevent['center']['name'] ?>%0D%0A%0D%0A">Email</a></li>
						</ul>
					</div>
					<?php endif; ?>
				</div>
			</div>
		</div>

	<?php endif; ?>	
</div>

<?php endif; ?>

<script type="text/javascript">
window.addeventasync = function(){
    addeventatc.settings({
        appleical  : {show:true, text:"Apple Calendar"},
        google     : {show:true, text:"Google Calendar"},
        outlook    : {show:false, text:"Outlook"},
        outlookcom : {show:false, text:"Outlook.com <em>(online)</em>"},
        yahoo      : {show:false, text:"Yahoo <em>(online)</em>"}
    });
};
</script>

