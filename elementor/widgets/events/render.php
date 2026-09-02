<?php
$settings = $this->get_settings_for_display();
$fields = [
  'fetch_all',
  'fetch_limit',
  'external_event_new_tab',
  'event_title',
  'event_category',
  'only_static_events',
  'no_results_found_text',
];
$filtered_settings = array_intersect_key($settings, array_flip(array_merge($fields, get_carousel_fields())));
$unique_id = uniqid();
?>

<div id="eyeon-events-<?= $unique_id ?>" class="eyeon-events eyeon-loader">
  <div class="eyeon-wrapper eyeon-hide">
    <?php if( $settings['categories_filters'] === 'show' ) : ?>
    <div class="categories">
      <select id="categories-dropdown-<?= $unique_id ?>" class="show-on-mob"></select>
      <ul id="categories-<?= $unique_id ?>" class="hide-on-mob"></ul>
    </div>
    <?php endif; ?>

    <?php
    $classes = '';
    if ($settings['view_mode']==='carousel' ) {
      $classes .= ' owl-carousel owl-carousel-'.$unique_id.' owl-theme';
      if($settings['carousel_navigation']==='show') {
        $classes .= ' owl-nav-show';
      }
      if($settings['carousel_dots']==='show') {
        $classes .= ' owl-dots-show';
      }
    } else {
      $classes .= ' grid-view';
    }
    ?>
    <div id="events-list-<?= $unique_id ?>" class="events-list <?= $classes ?>"></div>
  </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
  const settings = <?= json_encode($filtered_settings) ?>;

  const wpTimezone = `<?= wp_timezone_string() ?>`;
  
  const eyeonEvents = $('#eyeon-events-<?= $unique_id ?>');
  const categoryList = $('#categories-<?= $unique_id ?>');
  const categoryDropdownList = $('#categories-dropdown-<?= $unique_id ?>');
  const eventsList = $('#events-list-<?= $unique_id ?>');

  let events = [];
  let categories = [];

  const event_category = parseInt(settings.event_category);

  /**
   * Resolve "now" as calendar date + wall-clock time in an IANA timezone.
   * Intl resolves DST from the zone database at this instant — no offset math.
   */
  function getCenterNow(timezone) {
    const tz = timezone || wpTimezone || 'UTC';
    try {
      const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: tz,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
      }).formatToParts(new Date());

      const get = (type) => {
        const part = parts.find((p) => p.type === type);
        return part ? part.value : '00';
      };

      // en-CA hour12:false can emit "24" for midnight — normalize to "00"
      let hour = get('hour');
      if (hour === '24') hour = '00';

      return {
        date: `${get('year')}-${get('month')}-${get('day')}`,
        time: `${hour}:${get('minute')}:${get('second')}`,
      };
    } catch (e) {
      // Invalid IANA zone (or fixed-offset WP string) — fall back to UTC calendar date
      const now = new Date();
      return {
        date: moment.utc(now).format('YYYY-MM-DD'),
        time: moment.utc(now).format('HH:mm:ss'),
      };
    }
  }

  function eventHasRecurrence(event) {
    return event.event_type === 'recurring';
  }

  function normalizeTime(time) {
    if (!time) return null;
    const trimmed = String(time).trim();
    if (!trimmed) return null;
    // HH:mm → HH:mm:00 for lexicographic compare with HH:mm:ss
    if (/^\d{1,2}:\d{2}$/.test(trimmed)) return trimmed + ':00';
    return trimmed;
  }

  /**
   * True when an occurrence on `occDate` is still upcoming relative to center-local now.
   * All-day events count as upcoming for the whole calendar day.
   */
  function isOccurrenceUpcoming(occDate, endTime, today, nowTime, isAllDay) {
    if (occDate > today) return true;
    if (occDate < today) return false;
    if (isAllDay) return true;
    const end = normalizeTime(endTime) || '23:59:59';
    return end >= nowTime;
  }

  function occurrenceCalendarDate(occ) {
    return moment.utc(occ).format('YYYY-MM-DD');
  }

  function calendarDate(value) {
    if (!value) return null;
    const match = String(value).trim().match(/^(\d{4}-\d{2}-\d{2})/);
    return match ? match[1] : null;
  }

  function calendarDateToUtcMs(dateStr) {
    if (!dateStr || typeof dateStr !== 'string') return NaN;
    const trimmed = dateStr.trim();
    if (!/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) return NaN;
    const [y, m, d] = trimmed.split('-').map(Number);
    return Date.UTC(y, m - 1, d);
  }

  /**
   * Build the list of date slots used for list-page display modes.
   * Each slot: { date: 'YYYY-MM-DD', start_time, end_time }
   */
  function buildEventDateSlots(event) {
    const centerTz = event.center_timezone || wpTimezone;
    const now = getCenterNow(centerTz);
    const today = now.date;
    const isAllDay = !!event.is_all_day_event;
    const startTime = event.start_time || null;
    const endTime = event.end_time || null;

    if (event.event_type === 'custom' && event.custom_dates && event.custom_dates.length > 0) {
      return (event.custom_dates || [])
        .filter((cd) => cd.date && cd.date !== '')
        .sort((a, b) => a.date.localeCompare(b.date))
        .map((cd) => ({
          date: calendarDate(cd.date) || cd.date,
          start_time: cd.start_time || startTime,
          end_time: cd.end_time || endTime,
        }));
    }

    if (eventHasRecurrence(event)) {
      try {
        const rule = rrule.RRule.fromString(event.repeat_rrule);
        // Bound window: 2 days before today → 365 days ahead (UTC midnight anchors)
        const windowStart = new Date(Date.UTC(
          Number(today.slice(0, 4)),
          Number(today.slice(5, 7)) - 1,
          Number(today.slice(8, 10)) - 2
        ));
        const windowEnd = new Date(Date.UTC(
          Number(today.slice(0, 4)),
          Number(today.slice(5, 7)) - 1,
          Number(today.slice(8, 10)) + 365
        ));
        const occurrences = rule.between(windowStart, windowEnd);
        const slots = [];
        const seen = {};
        occurrences.forEach(function (occ) {
          const dateStr = occurrenceCalendarDate(occ);
          if (seen[dateStr]) return;
          const startBound = calendarDate(event.start_date);
          const endBound = calendarDate(event.end_date);
          if (startBound && dateStr < startBound) return;
          if (endBound && dateStr > endBound) return;
          seen[dateStr] = true;
          slots.push({
            date: dateStr,
            start_time: startTime,
            end_time: endTime,
          });
        });
        return slots;
      } catch (e) {
        // fall through to single-slot
      }
    }

    // one-time / ongoing / fallback: single slot from start_date
    if (event.start_date) {
      return [{
        date: event.start_date,
        start_time: startTime,
        end_time: endTime,
      }];
    }
    return [];
  }

  function findUpcomingSlot(slots, event) {
    const centerTz = event.center_timezone || wpTimezone;
    const now = getCenterNow(centerTz);
    const isAllDay = !!event.is_all_day_event;
    return slots.find((slot) =>
      isOccurrenceUpcoming(slot.date, slot.end_time, now.date, now.time, isAllDay)
    ) || null;
  }

  function isOnetimeUpcoming(event, today, nowTime) {
    const start = calendarDate(event.start_date);
    const end = calendarDate(event.end_date) || start;
    if (start && start > today) return true;
    return isOccurrenceUpcoming(end, event.end_time, today, nowTime, !!event.is_all_day_event);
  }

  /**
   * Same public-live contract as web-app-backend isEventPubliclyLive.
   * ongoing never expires; custom uses remaining slots; recurring uses RRULE.
   */
  function isEventPubliclyLive(event) {
    if (event.event_type === 'ongoing') return true;

    const centerTz = event.center_timezone || wpTimezone;
    const now = getCenterNow(centerTz);

    if (event.event_type === 'custom') {
      const slots = buildEventDateSlots(event);
      if (slots.length === 0) {
        return isOnetimeUpcoming(event, now.date, now.time);
      }
      return !!findUpcomingSlot(slots, event);
    }

    if (eventHasRecurrence(event)) {
      const slots = buildEventDateSlots(event);
      return !!findUpcomingSlot(slots, event);
    }

    return isOnetimeUpcoming(event, now.date, now.time);
  }

  /**
   * Shared list-page date/time display resolver for every event type.
   * Modes: hide | dateRange | show | upcoming | allUpcoming | allDates
   */
  function getListPageDisplay(event) {
    const dateDisplay = event.list_page_date_display;
    const showTimeSetting = event.list_page_time_display === 'show' && !event.is_all_day_event;

    if (!dateDisplay || dateDisplay === 'hide') {
      return { showDate: false, showTime: false, dateText: null, timeStart: null, timeEnd: null };
    }

    if (dateDisplay === 'dateRange') {
      const startFmt = eyeonFormatDate(event.start_date);
      const endFmt = eyeonFormatDate(event.end_date);
      let dateText = startFmt;
      if (endFmt && event.end_date && event.end_date !== event.start_date) {
        dateText = `${startFmt} - ${endFmt}`;
      }
      return {
        showDate: !!startFmt,
        showTime: showTimeSetting && !!(event.start_time && event.end_time),
        dateText: dateText || null,
        timeStart: event.start_time || null,
        timeEnd: event.end_time || null,
      };
    }

    if (dateDisplay === 'show') {
      const startFmt = eyeonFormatDate(event.start_date);
      let dateText = startFmt;
      if (event.end_date && event.end_date !== event.start_date) {
        const endFmt = eyeonFormatDate(event.end_date);
        if (endFmt) dateText = `${startFmt} - ${endFmt}`;
      }
      return {
        showDate: !!startFmt,
        showTime: showTimeSetting && !!(event.start_time && event.end_time),
        dateText: dateText || null,
        timeStart: event.start_time || null,
        timeEnd: event.end_time || null,
      };
    }

    const slots = event._dateSlots || [];
    const centerTz = event.center_timezone || wpTimezone;
    const now = getCenterNow(centerTz);
    const isAllDay = !!event.is_all_day_event;

    let displaySlots = [];
    if (dateDisplay === 'allDates') {
      displaySlots = slots;
    } else if (dateDisplay === 'allUpcoming') {
      displaySlots = slots.filter((slot) =>
        isOccurrenceUpcoming(slot.date, slot.end_time, now.date, now.time, isAllDay)
      );
    } else {
      // upcoming (default for any other mode)
      const upcoming = findUpcomingSlot(slots, event);
      displaySlots = upcoming ? [upcoming] : [];
    }

    if (displaySlots.length === 0) {
      // Fall back to start_date so the card still shows something meaningful
      if (event.start_date) {
        displaySlots = [{
          date: event.start_date,
          start_time: event.start_time,
          end_time: event.end_time,
        }];
      } else {
        return { showDate: false, showTime: false, dateText: null, timeStart: null, timeEnd: null };
      }
    }

    const first = displaySlots[0];
    const extraCount = Math.max(0, displaySlots.length - 1);
    let dateText = eyeonFormatDate(first.date);
    if (extraCount > 0) {
      dateText += ` (+${extraCount})`;
    }

    const timeStart = showTimeSetting && first.start_time ? first.start_time : null;
    const timeEnd = showTimeSetting && first.end_time ? first.end_time : null;

    return {
      showDate: !!dateText,
      showTime: !!(timeStart && timeEnd),
      dateText,
      timeStart,
      timeEnd,
    };
  }

  function fetch_events(force_refresh = false) {
    $.ajax({
      url: EYEON.ajaxurl+'?api=<?= MCD_API_EVENTS ?>',
      data: {
        action: 'eyeon_api_request',
        nonce: EYEON.nonce,
        apiUrl: "<?= MCD_API_EVENTS ?>",
        paginated_data: true,
        force_refresh: force_refresh
      },
      method: "POST",
      dataType: 'json',
      xhrFields: {
        withCredentials: true
      },
      success: function (response) {
        parse_events(response);
      }
    });
  }

  function parse_events(response) {
    if (response.items) {
      let allEvents = response.items.filter(isEventPubliclyLive);
      
      if (settings.only_static_events === 'yes') {
        allEvents = allEvents.filter(function(event) {
          return event.event_type === 'ongoing';
        });
      }

      if (event_category > 0) {
        allEvents = allEvents.filter(function(event) {
          if (!event.category) return false;
          return event.category && event.category.id === event_category;
        });
      }
      
      if (settings.fetch_all !== 'yes' && settings.fetch_limit > 0) {
        allEvents = allEvents.slice(0, settings.fetch_limit);
      }
      
      events = allEvents;
      setup_events();
    }
  }

  function setup_events() {
    <?php if( $settings['categories_filters'] === 'show' ) : ?>
      setup_categories();
    <?php endif; ?>

    events = events.map(parseAndFindUpcoming);

    events.sort(function (a, b) {
      if (a.event_type === 'ongoing' && b.event_type !== 'ongoing') return 1;
      if (a.event_type !== 'ongoing' && b.event_type === 'ongoing') return -1;

      const ta = calendarDateToUtcMs(a.upcoming_date);
      const tb = calendarDateToUtcMs(b.upcoming_date);
      if (!isNaN(ta) && !isNaN(tb)) {
        return ta - tb;
      } else if (!isNaN(ta)) {
        return -1;
      } else if (!isNaN(tb)) {
        return 1;
      }

      return 0;
    });

    render_events();
  }

  function setup_categories() {
    let fetchedCategories = [];
    events.forEach(item => {
      item.categories = [];
      if(item.category) item.categories.push(item.category);

      item.categories.forEach(category => {
        if( !(fetchedCategories.some(cat => cat.id === category.id)) ) {
          fetchedCategories.push({
            id: category.id,
            name: category.title,
          });
        }
      });
    });

    fetchedCategories = fetchedCategories.sort(function (a, b) {
      var nameA = a.name.toUpperCase();
      var nameB = b.name.toUpperCase();
      if (nameA < nameB) return -1;
      if (nameA > nameB) return 1;
      return 0;
    });

    categories = [{id: 0, name: 'All'}].concat(fetchedCategories);

    categoryList.html('');
    categoryDropdownList.html('');

    categories.forEach(category => {
      categoryList.append(`
        <li data-value="${category.id}" class="${category.id===0?'active':''}">${category.name}</li>
      `);
      categoryDropdownList.append(`
        <option value="${category.id}">${category.name}</option>
      `);
    });
  }
  
  function parseAndFindUpcoming(event) {
    const slots = buildEventDateSlots(event);
    event._dateSlots = slots;

    const upcoming = findUpcomingSlot(slots, event);
    // Do NOT clamp to today — report the event's own start_date when nothing is upcoming
    // (matches single-event page semantics)
    event.upcoming_date = upcoming
      ? upcoming.date
      : (event.start_date || null);

    if (upcoming) {
      event.upcoming_custom_time = {
        date: upcoming.date,
        start_time: upcoming.start_time,
        end_time: upcoming.end_time,
      };
    }

    event.datesStr = eyeonFormatDate(event.upcoming_date);
    event.formatted_start_date = eyeonFormatDate(event.start_date);
    event.formatted_end_date = eyeonFormatDate(event.end_date);
    return event;
  }

  function render_events() {
    eyeonEvents.removeClass('eyeon-loader').find('.eyeon-wrapper').removeClass('eyeon-hide');
    eyeonEvents.find('.no-items-found').remove();
    eventsList.html('');

    if( events.length > 0 ) {
      events.forEach(event => {
        const display = getListPageDisplay(event);
        const showDate = display.showDate;
        const showTime = display.showTime;
        const dateHtml = showDate && display.dateText ? `<span>${display.dateText}</span>` : '';
        const timeStartVal = display.timeStart;
        const timeEndVal = display.timeEnd;

        const eventItem = $(`
          <a href="${event.event_url?event.event_url:`<?= mcd_single_page_url('mycenterevent') ?>${event.slug}`}" class="event event-${event.id}" ${(event.event_url && settings.external_event_new_tab)?'target="_blank"':''}>
            <div class="image">
              <img src="${event.media?.url}" alt="${event.title}" />
            </div>
            <div class="event-content">
              ${ settings.event_title ? `<h3 class="event-title">${event.title}</h3>` : '' }
              ${ (showDate || showTime) ? `
                <div class="metadata">
                  ${ showDate ? `
                    <div class="date">
                      <i class="far fa-calendar"></i>
                      ${dateHtml}
                    </div>
                  `: '' }
                  ${ showTime ? `
                    <div class="time">
                      <i class="far fa-clock"></i>
                      <span>${eyeonFormatTime(timeStartVal)} - ${eyeonFormatTime(timeEndVal)}</span>
                    </div>
                  ` : '' }
                </div>
              `: '' }
            </div>
          </a>
        `);
        eventsList.append(eventItem);
      });
    } else {
      eyeonEvents.find('.eyeon-wrapper').addClass('eyeon-hide');
      if(eyeonEvents.find('.no-items-found').length === 0) {
        eyeonEvents.append(`
          <div class="no-items-found">${settings.no_results_found_text}</div>
        `);
      }
    }
    
    if( events.length > 0 && elementorFrontend.config.environmentMode.edit && eyeonEvents.find('.no-items-found').length === 0) {
      eyeonEvents.append(`
        <div class="no-items-found">${settings.no_results_found_text}</div>
      `);
    }

    <?php include(MCD_PLUGIN_PATH.'elementor/widgets/common/carousel/setup-js.php'); ?>
  }
  function filterByCategory(categoryId = 0) {
    eventsList.find('.event').addClass('eyeon-hide');
    events.forEach(item => {
      if (categoryId == 0 || item.categories.some(cat => cat.id == categoryId)) {
        eventsList.find('.event.event-'+item.id).removeClass('eyeon-hide');
      }
    });
  }

  // Event listeners for filter
  categoryList.on('click', 'li', function() {
    categoryList.find('li.active').removeClass('active');
    $(this).addClass('active');
    const selectedCategoryId = parseInt($(this).attr('data-value'));

    categoryDropdownList.val(selectedCategoryId);
    filterByCategory(selectedCategoryId);
  });

  categoryDropdownList.on('change', function() {
    const selectedCategoryId = parseInt($(this).val());

    // change categories list selection
    categoryList.find('li.active').removeClass('active');
    categoryList.find('li[data-value="'+selectedCategoryId+'"]').addClass('active');

    filterByCategory(selectedCategoryId);
  });

  const cachedEvents = <?= get_eyeon_api_cache_data(MCD_API_EVENTS) ?>;
  if (cachedEvents) {
    parse_events(cachedEvents);
  }
  fetch_events(true);
});
</script>
