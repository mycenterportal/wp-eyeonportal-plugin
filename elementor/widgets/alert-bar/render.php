<?php
$unique_id = uniqid();
?>

<div id="eyeon-alert-bar-<?= $unique_id ?>" class="eyeon-alert-bar eyeon-loader">
  <div class="eyeon-wrapper eyeon-hide"></div>
</div>

<script type="text/javascript">
  jQuery(document).ready(function($) {
    const isEditMode = <?= $is_edit_mode ? 'true' : 'false' ?>;
    const eyeonAlertBar = $('#eyeon-alert-bar-<?= $unique_id ?>');
    const wrapper = eyeonAlertBar.find('.eyeon-wrapper');

    function getTodayDateString() {
      const wpTimezone = `<?= wp_timezone_string() ?>`;
      const today = new Date();
      const localToday = new Date(today.toLocaleString('en-US', { timeZone: wpTimezone }));
      const year = localToday.getFullYear();
      const month = String(localToday.getMonth() + 1).padStart(2, '0');
      const day = String(localToday.getDate()).padStart(2, '0');
      return `${year}-${month}-${day}`;
    }

    function hasAlertBarContent(alertBar) {
      if (!alertBar || typeof alertBar !== 'object') {
        return false;
      }

      const content = (alertBar.content || '').trim();
      return content.length > 0;
    }

    function createAlertBarMessage(content, options = {}) {
      const $message = $('<div class="alert-bar-message"></div>');

      if (options.isPlaceholder) {
        $message.addClass('alert-bar-message--placeholder');

        if (options.label) {
          $message.append(`<span class="alert-bar-message__label">${options.label}</span>`);
        }
      }

      $message.append($('<span class="alert-bar-message__text"></span>').text(content || ''));
      return $message;
    }

    function isAlertBarActive(alertBar) {
      if (!alertBar || !alertBar.start_date || !alertBar.end_date || !hasAlertBarContent(alertBar)) {
        return false;
      }

      const today = getTodayDateString();
      return today >= alertBar.start_date && today <= alertBar.end_date;
    }

    function formatDateRange(alertBar) {
      if (!alertBar || !alertBar.start_date || !alertBar.end_date) {
        return '';
      }

      return `${alertBar.start_date} to ${alertBar.end_date}`;
    }

    function renderAlertBar(alertBar) {
      eyeonAlertBar.removeClass('eyeon-loader');
      wrapper.removeClass('eyeon-hide').html('');

      if (isAlertBarActive(alertBar)) {
        wrapper.append(createAlertBarMessage(alertBar.content));
        return;
      }

      if (isEditMode) {
        const dateRange = formatDateRange(alertBar);
        const previewContent = hasAlertBarContent(alertBar)
          ? alertBar.content
          : 'Configure alert bar content in EyeOn Portal → Center Info → Alert Bar.';

        wrapper.append(createAlertBarMessage(previewContent, {
          isPlaceholder: true,
          label: `Alert Bar Preview (not visible on live site outside active dates${dateRange ? `: ${dateRange}` : ''})`,
        }));
        return;
      }

      wrapper.addClass('eyeon-hide').html('');
    }

    function fetch_alert_bar(force_refresh = false) {
      $.ajax({
        url: EYEON.ajaxurl + '?api=<?= MCD_API_CENTER_INFO ?>',
        data: {
          action: 'eyeon_api_request',
          nonce: EYEON.nonce,
          apiUrl: "<?= MCD_API_CENTER_INFO ?>",
          force_refresh: force_refresh
        },
        method: 'POST',
        dataType: 'json',
        xhrFields: {
          withCredentials: true
        },
        success: function(response) {
          renderAlertBar(response && response.alert_bar ? response.alert_bar : null);
        },
        error: function() {
          eyeonAlertBar.removeClass('eyeon-loader');

          if (isEditMode) {
            wrapper.removeClass('eyeon-hide').append(createAlertBarMessage(
              'Unable to load alert bar data. Check the EyeOn API token settings.',
              {
                isPlaceholder: true,
                label: 'Alert Bar Preview',
              }
            ));
          }
        }
      });
    }

    const cachedData = <?= get_eyeon_api_cache_data(MCD_API_CENTER_INFO) ?>;
    if (cachedData && cachedData.alert_bar) {
      renderAlertBar(cachedData.alert_bar);
    }

    fetch_alert_bar(true);
  });
</script>
