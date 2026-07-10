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

  function getTimezoneDate(date = null) {
    const today = date ? date : new Date();
    return new Date(today.toLocaleString('en-US', { timeZone: wpTimezone }));
  }

  function addMinutesToDate(date, minutes) {
    const newDate = new Date(date.getTime());
    newDate.setTime(newDate.getTime() + minutes * 60 * 1000);
    return newDate;
  }

  function getMinutesBetween(time1, time2) {
    const date1 = new Date(`1970-01-01T${time1}Z`);
    const date2 = new Date(`1970-01-01T${time2}Z`);
    const diffInMs = Math.abs(date2 - date1);
    const diffInMinutes = diffInMs / (1000 * 60);
    return diffInMinutes;
  }

  var todayDate = getTimezoneDate();

  function getTodayDateString() {
    const d = getTimezoneDate();
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  function getCustomListPageDisplay(event) {
    const dateDisplay = event.list_page_date_display;
    const showTimeSetting = event.list_page_time_display === 'show' && !event.is_all_day_event;

    if (!dateDisplay || dateDisplay === 'hide') {
      return { showDate: false, showTime: false, dateText: null, timeStart: null, timeEnd: null };
    }

    const customDates = (event.custom_dates || [])
      .filter(cd => cd.date && cd.date !== '')
      .sort((a, b) => a.date.localeCompare(b.date));

    if (customDates.length === 0) {
      return { showDate: false, showTime: false, dateText: null, timeStart: null, timeEnd: null };
    }

    const today = getTodayDateString();
    let displaySlots = [];
    let extraCount = 0;

    if (dateDisplay === 'allDates') {
      displaySlots = customDates;
      extraCount = customDates.length - 1;
    } else if (dateDisplay === 'allUpcoming') {
      displaySlots = customDates.filter(cd => cd.date >= today);
      extraCount = Math.max(0, displaySlots.length - 1);
    } else if (dateDisplay === 'upcoming') {
      const upcoming = customDates.find(cd => cd.date >= today);
      if (!upcoming) {
        return { showDate: false, showTime: false, dateText: null, timeStart: null, timeEnd: null };
      }
      displaySlots = [upcoming];
      extraCount = 0;
    } else {
      const upcoming = customDates.find(cd => cd.date >= today);
      if (!upcoming) {
        return { showDate: false, showTime: false, dateText: null, timeStart: null, timeEnd: null };
      }
      displaySlots = [upcoming];
      extraCount = 0;
    }

    if (displaySlots.length === 0) {
      return { showDate: false, showTime: false, dateText: null, timeStart: null, timeEnd: null };
    }

    const first = displaySlots[0];
    let dateText = eyeonFormatDate(first.date);
    if (extraCount > 0) {
      dateText += ` (+${extraCount})`;
    }

    const timeStart = showTimeSetting && first.start_time ? first.start_time : null;
    const timeEnd = showTimeSetting && first.end_time ? first.end_time : null;
    const showTime = !!(timeStart && timeEnd);

    return {
      showDate: true,
      showTime,
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
      let allEvents = response.items;
      
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

      if (a.upcoming_date && b.upcoming_date) {
        if (a.upcoming_date > b.upcoming_date) {
          return 1;
        } else if (a.upcoming_date < b.upcoming_date) {
          return -1;
        } else {
          return 0;
        }
      } else if (a.upcoming_date) {
        return -1;
      } else if (b.upcoming_date) {
        return 1;
      }

      // Sort by start_date and start_time
      // var startDateA = getTimezoneDate(new Date(a.start_date + ' ' + (a.is_all_day_event ? '00:00:00' : a.start_time)));
      // var startDateB = getTimezoneDate(new Date(b.start_date + ' ' + (b.is_all_day_event ? '00:00:00' : b.start_time)));

      // if (startDateA > startDateB) return 1;
      // if (startDateA < startDateB) return -1;

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
    var upcomingOccurrence = null;
    var tempStartDate = new Date(event.start_date + ' ' + (event.is_all_day_event ? '00:00:00' : event.start_time));

    if (event.event_type === 'custom' && event.custom_dates && event.custom_dates.length > 0) {
      const validDates = event.custom_dates
        .filter(cd => cd.date && cd.date !== '')
        .sort((a, b) => a.date.localeCompare(b.date))
        .map(cd => ({
          date: new Date(cd.date + ' ' + (cd.start_time || '00:00')),
          start_time: cd.start_time,
          end_time: cd.end_time
        }))
        .sort((a, b) => a.date - b.date);

      const upcomingCustom = validDates.find(cd => cd.date >= todayDate);
      if (upcomingCustom) {
        event.upcoming_date = upcomingCustom.date;
        event.upcoming_custom_time = upcomingCustom;
      } else {
        event.upcoming_date = tempStartDate > todayDate ? tempStartDate : todayDate;
      }
    } else if (event.is_repeat_event && event.repeat_rrule && event.repeat_rrule !== '') {
      var rule = rrule.RRule.fromString(event.repeat_rrule);

      var occurrences = rule.between(
        new Date(todayDate.getTime() - 2 * 24 * 60 * 60 * 1000),
        new Date(todayDate.getTime() + 365 * 24 * 60 * 60 * 1000)
      );

      let event_duration_in_minutes = 0;
      if(!event.is_all_day_event) {
        event_duration_in_minutes = getMinutesBetween(event.end_time, event.start_time);
      } else {
        event_duration_in_minutes = 60*24-1;
      }
      var occurrencesInTimezone = occurrences.map(date => {
        const timezoneOffsetInMinutes = date.getTimezoneOffset();
        return addMinutesToDate(date, timezoneOffsetInMinutes+event_duration_in_minutes)
      });

      upcomingOccurrence = occurrencesInTimezone.find(function (occurrence) {
        return occurrence >= todayDate;
      });

      if (upcomingOccurrence) {
        event.upcoming_date = tempStartDate > upcomingOccurrence ? tempStartDate : upcomingOccurrence;
      } else {
        event.upcoming_date = tempStartDate > todayDate ? tempStartDate : todayDate;
      }
    } else {
      event.upcoming_date = tempStartDate > todayDate ? tempStartDate : todayDate;
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
        let showDate = event.list_page_date_display && event.list_page_date_display !== 'hide';
        let showTime = event.list_page_time_display === 'show' && !event.is_all_day_event;

        let dateHtml = '';
        let timeStartVal = event.start_time;
        let timeEndVal = event.end_time;

        if (event.event_type === 'custom') {
          const display = getCustomListPageDisplay(event);
          showDate = display.showDate;
          showTime = display.showTime;
          if (display.showDate && display.dateText) {
            dateHtml = `<span>${display.dateText}</span>`;
          }
          if (display.showTime) {
            timeStartVal = display.timeStart;
            timeEndVal = display.timeEnd;
          }
        } else {
          if (showDate) {
            if (event.list_page_date_display === 'dateRange') {
              dateHtml = `<span>${event.formatted_start_date} - ${event.formatted_end_date}</span>`;
            } else {
              dateHtml = `<span>${event.datesStr}</span>`;
            }
          }
        }

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