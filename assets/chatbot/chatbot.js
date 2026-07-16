(function ($) {
  'use strict';

  if (typeof EYEON_CHATBOT === 'undefined') {
    return;
  }

  var STORAGE_TTL_MS = 24 * 60 * 60 * 1000;
  var MAX_STORED_MESSAGES = 50;
  var API_HISTORY_LIMIT = 6;

  var history = [];
  var isOpen = false;
  var isSending = false;
  var sessionNonce = null;
  var pendingNonceRequest = null;

  var $root = $('#eyeon-chatbot-root');
  var $launcher = $('#eyeon-chatbot-launcher');
  var $panel = $('#eyeon-chatbot-panel');
  var $messages = $('#eyeon-chatbot-messages');
  var $form = $('#eyeon-chatbot-form');
  var $input = $('#eyeon-chatbot-input');
  var $send = $('#eyeon-chatbot-send');
  var $close = $('#eyeon-chatbot-close');
  var mobileMediaQuery = window.matchMedia('(max-width: 480px)');
  var viewportListenersBound = false;
  var touchMoveBlockBound = false;
  var lockedScrollY = 0;

  function isMobileLayout() {
    return mobileMediaQuery.matches;
  }

  function onDocumentTouchMove(e) {
    if (!isOpen || !isMobileLayout()) {
      return;
    }

    if ($(e.target).closest('#eyeon-chatbot-messages, #eyeon-chatbot-input, #eyeon-chatbot-form').length) {
      return;
    }

    e.preventDefault();
  }

  function bindTouchScrollLock() {
    if (touchMoveBlockBound) {
      return;
    }

    document.addEventListener('touchmove', onDocumentTouchMove, { passive: false });
    touchMoveBlockBound = true;
  }

  function unbindTouchScrollLock() {
    if (!touchMoveBlockBound) {
      return;
    }

    document.removeEventListener('touchmove', onDocumentTouchMove, { passive: false });
    touchMoveBlockBound = false;
  }

  function lockBodyScroll() {
    lockedScrollY = window.scrollY || window.pageYOffset || 0;
    $('html, body').addClass('eyeon-chatbot-mobile-open');
    $('body').css({
      position: 'fixed',
      top: -lockedScrollY + 'px',
      left: '0',
      right: '0',
      width: '100%',
    });
    bindTouchScrollLock();
  }

  function unlockBodyScroll() {
    $('html, body').removeClass('eyeon-chatbot-mobile-open');
    $('body').css({ position: '', top: '', left: '', right: '', width: '' });
    window.scrollTo(0, lockedScrollY);
    unbindTouchScrollLock();
  }

  function bindViewportListeners() {
    if (viewportListenersBound || !window.visualViewport) {
      return;
    }

    window.visualViewport.addEventListener('resize', syncMobileViewport);
    window.visualViewport.addEventListener('scroll', syncMobileViewport);
    viewportListenersBound = true;
  }

  function unbindViewportListeners() {
    if (!viewportListenersBound || !window.visualViewport) {
      return;
    }

    window.visualViewport.removeEventListener('resize', syncMobileViewport);
    window.visualViewport.removeEventListener('scroll', syncMobileViewport);
    viewportListenersBound = false;
  }

  function resetMobileViewportStyles() {
    $root.css({ top: '', bottom: '', left: '', right: '', width: '', height: '', transform: '' });
    $panel.css({ top: '', left: '', right: '', bottom: '', height: '', width: '' });
    $root.removeClass('eyeon-chatbot--keyboard-open');
  }

  function syncMobileViewport() {
    if (!isMobileLayout()) {
      resetMobileViewportStyles();
      return;
    }

    var viewport = window.visualViewport;
    if (!viewport) {
      return;
    }

    var launcherHeight = $launcher.outerHeight() || 56;
    var edgeOffset = 16;
    var position = EYEON_CHATBOT.position === 'bottom-left' ? 'bottom-left' : 'bottom-right';
    var keyboardOpen = window.innerHeight - viewport.height - viewport.offsetTop > 80;

    if (isOpen) {
      $root.css({
        top: '0',
        left: '0',
        right: '0',
        bottom: '0',
        width: '100%',
        height: '100%',
        transform: 'none',
      });

      if (keyboardOpen) {
        $panel.css({
          top: viewport.offsetTop + 'px',
          left: '0',
          right: '0',
          bottom: 'auto',
          width: '100%',
          height: viewport.height + 'px',
        });
      } else {
        $panel.css({
          top: '0',
          left: '0',
          right: '0',
          bottom: '0',
          width: '100%',
          height: '100%',
        });
      }

      $root.toggleClass('eyeon-chatbot--keyboard-open', keyboardOpen);

      if (keyboardOpen) {
        window.requestAnimationFrame(function () {
          $messages.scrollTop($messages[0].scrollHeight);
        });
      }

      return;
    }

    $panel.css({ top: '', left: '', right: '', bottom: '', height: '', width: '' });

    var launcherTop = viewport.offsetTop + viewport.height - launcherHeight - edgeOffset;
    var launcherStyles = {
      top: launcherTop + 'px',
      bottom: 'auto',
      transform: 'none',
    };

    if (position === 'bottom-left') {
      launcherStyles.left = viewport.offsetLeft + edgeOffset + 'px';
      launcherStyles.right = 'auto';
    } else {
      launcherStyles.left =
        viewport.offsetLeft + viewport.width - ($launcher.outerWidth() || launcherHeight) - edgeOffset + 'px';
      launcherStyles.right = 'auto';
    }

    $root.css(launcherStyles);
  }

  function setMobileOpenState(open) {
    $root.toggleClass('eyeon-chatbot--open', open);

    if (open && isMobileLayout()) {
      lockBodyScroll();
      bindViewportListeners();
      syncMobileViewport();
      return;
    }

    if (!open) {
      unlockBodyScroll();
      resetMobileViewportStyles();
      if (isMobileLayout()) {
        syncMobileViewport();
      } else {
        unbindViewportListeners();
      }
    }
  }

  function storageKey() {
    var centerId = EYEON_CHATBOT.centerId || window.location.hostname || 'default';
    return 'eyeon_chatbot_v1_' + centerId;
  }

  function visitorStorageKey() {
    return storageKey() + '_visitor';
  }

  function getOrCreateVisitorId() {
    try {
      if (!window.localStorage) {
        return getSessionVisitorId();
      }
      var existing = localStorage.getItem(visitorStorageKey());
      if (existing) {
        return existing;
      }

      var id = createVisitorId();
      localStorage.setItem(visitorStorageKey(), id);
      return id;
    } catch (e) {
      return getSessionVisitorId();
    }
  }

  var sessionVisitorId = '';

  function createVisitorId() {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
      var r = Math.random() * 16 | 0;
      var v = c === 'x' ? r : (r & 0x3 | 0x8);
      return v.toString(16);
    });
  }

  function getSessionVisitorId() {
    if (!sessionVisitorId) {
      sessionVisitorId = createVisitorId();
    }
    return sessionVisitorId;
  }

  function panelStorageKey() {
    return storageKey() + '_panel';
  }

  function persistPanelState() {
    try {
      if (!window.localStorage) {
        return;
      }
      localStorage.setItem(panelStorageKey(), isOpen ? 'open' : 'closed');
    } catch (e) {
      // Ignore quota / private mode errors.
    }
  }

  function loadPanelState() {
    try {
      if (!window.localStorage) {
        closePanel();
        return;
      }

      if (localStorage.getItem(panelStorageKey()) === 'open') {
        openPanel();
        return;
      }
    } catch (e) {}

    closePanel();
  }

  function persistHistory() {
    try {
      if (!window.localStorage) {
        return;
      }
      var payload = {
        expiresAt: Date.now() + STORAGE_TTL_MS,
        messages: history.slice(-MAX_STORED_MESSAGES),
      };
      localStorage.setItem(storageKey(), JSON.stringify(payload));
    } catch (e) {
      // Ignore quota / private mode errors.
    }
  }

  function clearStoredHistory() {
    try {
      if (window.localStorage) {
        localStorage.removeItem(storageKey());
      }
    } catch (e) {}
  }

  function loadStoredHistory() {
    try {
      if (!window.localStorage) {
        return;
      }
      var raw = localStorage.getItem(storageKey());
      if (!raw) {
        return;
      }

      var payload = JSON.parse(raw);
      if (!payload || !payload.expiresAt || Date.now() > payload.expiresAt) {
        clearStoredHistory();
        return;
      }

      if (!Array.isArray(payload.messages)) {
        clearStoredHistory();
        return;
      }

      history = payload.messages
        .filter(function (item) {
          return (
            item &&
            (item.role === 'user' || item.role === 'assistant') &&
            typeof item.content === 'string' &&
            item.content.trim() !== ''
          );
        })
        .slice(-MAX_STORED_MESSAGES);

      history.forEach(function (item) {
        appendMessage(item.role, item.content);
      });
    } catch (e) {
      clearStoredHistory();
    }
  }

  function buildPhoneTelHref(formattedPhone) {
    var digits = String(formattedPhone || '').replace(/\D/g, '');
    if (digits.length === 10) {
      return 'tel:+1' + digits;
    }
    if (digits.length === 11 && digits.charAt(0) === '1') {
      return 'tel:+' + digits;
    }
    return digits ? 'tel:' + digits : '';
  }

  function appendRichText($container, text) {
    var itemPattern = /\[([^\]]+)\]\((deal|store|event|career|news):([^)]+)\)/g;
    var phonePattern = /\b(\d{3}\.\d{3}\.\d{4})\b/g;
    var tokens = [];
    var match;

    while ((match = itemPattern.exec(text)) !== null) {
      tokens.push({
        start: match.index,
        end: match.index + match[0].length,
        kind: 'item',
        label: match[1],
        itemType: match[2],
        slug: match[3],
      });
    }

    while ((match = phonePattern.exec(text)) !== null) {
      tokens.push({
        start: match.index,
        end: match.index + match[0].length,
        kind: 'phone',
        phone: match[1],
      });
    }

    tokens.sort(function (a, b) {
      return a.start - b.start;
    });

    var cursor = 0;
    tokens.forEach(function (token) {
      if (token.start < cursor) {
        return;
      }
      if (token.start > cursor) {
        $container.append(document.createTextNode(text.slice(cursor, token.start)));
      }
      if (token.kind === 'item') {
        var href = buildItemUrl(token.itemType, token.slug);
        if (href) {
          $container.append(
            $('<a></a>')
              .addClass('eyeon-chatbot__message-link')
              .attr('href', href)
              .attr('target', '_blank')
              .attr('rel', 'noopener noreferrer')
              .text(token.label)
          );
        } else {
          $container.append(document.createTextNode(token.label));
        }
      } else if (token.kind === 'phone') {
        var telHref = buildPhoneTelHref(token.phone);
        if (telHref) {
          $container.append(
            $('<a></a>')
              .addClass('eyeon-chatbot__message-link eyeon-chatbot__message-link--phone')
              .attr('href', telHref)
              .text(token.phone)
          );
        } else {
          $container.append(document.createTextNode(token.phone));
        }
      }
      cursor = token.end;
    });

    if (cursor < text.length) {
      $container.append(document.createTextNode(text.slice(cursor)));
    }
  }

  function buildItemUrl(type, slug) {
    var bases = EYEON_CHATBOT.linkBases || {};
    var base = bases[type];
    if (!base || !slug) {
      return '';
    }
    return base + encodeURIComponent(slug);
  }

  var UNORDERED_LIST_PATTERN = /^[-*+]\s+(.+)$/;
  var ORDERED_LIST_PATTERN = /^\d+\.\s+(.+)$/;

  function parseListLine(line) {
    var trimmed = $.trim(line);
    if (!trimmed) {
      return null;
    }

    var unordered = trimmed.match(UNORDERED_LIST_PATTERN);
    if (unordered) {
      return { type: 'ul', content: unordered[1] };
    }

    var ordered = trimmed.match(ORDERED_LIST_PATTERN);
    if (ordered) {
      return { type: 'ol', content: ordered[1] };
    }

    return null;
  }

  function appendParagraphLines($container, lines) {
    if (!lines.length) {
      return;
    }

    var $paragraph = $('<p class="eyeon-chatbot__paragraph"></p>');
    lines.forEach(function (line, index) {
      if (index > 0) {
        $paragraph.append('<br>');
      }
      appendRichText($paragraph, line);
    });
    $container.append($paragraph);
  }

  function appendListBlock($container, type, items) {
    var tag = type === 'ol' ? 'ol' : 'ul';
    var listClass =
      type === 'ol'
        ? 'eyeon-chatbot__list eyeon-chatbot__list--ordered'
        : 'eyeon-chatbot__list eyeon-chatbot__list--unordered';
    var $list = $('<' + tag + '></' + tag + '>').addClass(listClass);

    items.forEach(function (item) {
      var $item = $('<li></li>');
      appendRichText($item, item);
      $list.append($item);
    });

    $container.append($list);
  }

  function renderAssistantMessage(text) {
    var $msg = $('<div class="eyeon-chatbot__message eyeon-chatbot__message--assistant"></div>');
    var lines = String(text || '').split('\n');
    var $body = $('<div class="eyeon-chatbot__message-body"></div>');
    var index = 0;

    while (index < lines.length) {
      var trimmed = $.trim(lines[index]);
      if (!trimmed) {
        index++;
        continue;
      }

      var listLine = parseListLine(trimmed);
      if (listLine) {
        var listType = listLine.type;
        var items = [];

        while (index < lines.length) {
          var current = parseListLine($.trim(lines[index]));
          if (!current || current.type !== listType) {
            break;
          }
          items.push(current.content);
          index++;
        }

        appendListBlock($body, listType, items);
        continue;
      }

      var paragraphLines = [];
      while (index < lines.length) {
        var lineTrimmed = $.trim(lines[index]);
        if (!lineTrimmed) {
          break;
        }
        if (parseListLine(lineTrimmed)) {
          break;
        }
        paragraphLines.push(lineTrimmed);
        index++;
      }

      appendParagraphLines($body, paragraphLines);
    }

    if ($body.contents().length || $body.text()) {
      $msg.append($body);
    }
    if (!$msg.children().length) {
      $msg.text(text);
    }
    return $msg;
  }

  function appendMessage(role, text) {
    var cls = role === 'user' ? 'eyeon-chatbot__message--user' : 'eyeon-chatbot__message--assistant';
    var $msg =
      role === 'assistant'
        ? renderAssistantMessage(text)
        : $('<div class="eyeon-chatbot__message ' + cls + '"></div>').text(text);
    $messages.append($msg);
    $messages.scrollTop($messages[0].scrollHeight);
    return $msg;
  }

  function showWelcome() {
    if ($messages.children().length === 0 && EYEON_CHATBOT.welcomeMessage) {
      appendMessage('assistant', EYEON_CHATBOT.welcomeMessage);
    }
  }

  function setTyping(show) {
    $('#eyeon-chatbot-typing').remove();
    if (show) {
      $messages.append(
        '<div id="eyeon-chatbot-typing" class="eyeon-chatbot__message eyeon-chatbot__message--typing">Typing...</div>'
      );
      $messages.scrollTop($messages[0].scrollHeight);
    }
  }

  function fetchNonce(forceRefresh) {
    if (forceRefresh) {
      sessionNonce = null;
    }

    if (sessionNonce) {
      return $.when(sessionNonce);
    }

    if (pendingNonceRequest) {
      return pendingNonceRequest;
    }

    pendingNonceRequest = $.ajax({
      url: EYEON_CHATBOT.ajaxurl,
      method: 'POST',
      dataType: 'json',
      cache: false,
      data: {
        action: 'eyeon_chat_nonce',
      },
    })
      .then(function (response) {
        if (response && response.success && response.data && response.data.nonce) {
          sessionNonce = response.data.nonce;
          return sessionNonce;
        }

        return $.Deferred().reject().promise();
      })
      .always(function () {
        pendingNonceRequest = null;
      });

    return pendingNonceRequest;
  }

  function isNonceAuthError(xhr) {
    return (
      xhr &&
      xhr.status === 403 &&
      xhr.responseJSON &&
      xhr.responseJSON.data &&
      xhr.responseJSON.data.msg &&
      xhr.responseJSON.data.msg.indexOf('not authorized') !== -1
    );
  }

  function postChatRequest(message, nonce) {
    return $.ajax({
      url: EYEON_CHATBOT.ajaxurl,
      method: 'POST',
      dataType: 'json',
      cache: false,
      data: {
        action: 'eyeon_chat_request',
        nonce: nonce,
        message: message,
        history_json: JSON.stringify(trimHistoryForApi().slice(0, -1)),
        visitor_id: getOrCreateVisitorId(),
      },
    });
  }

  function openPanel() {
    isOpen = true;
    $panel.prop('hidden', false);
    setMobileOpenState(true);
    showWelcome();
    persistPanelState();
    fetchNonce(false);
    $input.trigger('focus');
    if (isMobileLayout()) {
      window.requestAnimationFrame(syncMobileViewport);
    }
  }

  function closePanel() {
    isOpen = false;
    $panel.prop('hidden', true);
    setMobileOpenState(false);
    persistPanelState();
  }

  function trimHistoryForApi() {
    return history.slice(-API_HISTORY_LIMIT);
  }

  function capStoredHistory() {
    if (history.length > MAX_STORED_MESSAGES) {
      history = history.slice(-MAX_STORED_MESSAGES);
    }
  }

  function deliverAssistantReply(response) {
    setTyping(false);
    var reply =
      response && response.success && response.data && response.data.reply
        ? response.data.reply
        : EYEON_CHATBOT.offlineMessage;
    appendMessage('assistant', reply);
    history.push({ role: 'assistant', content: reply });
    capStoredHistory();
    persistHistory();
  }

  function deliverAssistantError(xhr) {
    setTyping(false);
    var msg = EYEON_CHATBOT.offlineMessage;
    if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.msg) {
      msg = xhr.responseJSON.data.msg;
    }
    appendMessage('assistant', msg);
    history.push({ role: 'assistant', content: msg });
    capStoredHistory();
    persistHistory();
  }

  function finishSend() {
    isSending = false;
    $send.prop('disabled', false);
    $input.trigger('focus');
  }

  function sendMessage(message) {
    if (!message || isSending) {
      return;
    }

    isSending = true;
    $send.prop('disabled', true);
    appendMessage('user', message);
    history.push({ role: 'user', content: message });
    capStoredHistory();
    persistHistory();
    setTyping(true);

    function trySend(isRetry) {
      fetchNonce(isRetry)
        .then(function (nonce) {
          return postChatRequest(message, nonce);
        })
        .done(function (response) {
          deliverAssistantReply(response);
          finishSend();
        })
        .fail(function (xhr) {
          if (!isRetry && isNonceAuthError(xhr)) {
            trySend(true);
            return;
          }

          deliverAssistantError(xhr);
          finishSend();
        });
    }

    trySend(false);
  }

  $launcher.on('click', function () {
    if (isOpen) {
      closePanel();
    } else {
      openPanel();
    }
  });

  $close.on('click', function () {
    closePanel();
  });

  $form.on('submit', function (e) {
    e.preventDefault();
    var message = $.trim($input.val());
    if (!message) {
      return;
    }
    $input.val('');
    sendMessage(message);
  });

  $input.on('focus', function () {
    if (!isMobileLayout() || !isOpen) {
      return;
    }

    window.setTimeout(function () {
      syncMobileViewport();
      $messages.scrollTop($messages[0].scrollHeight);
    }, 300);
  });

  if (typeof mobileMediaQuery.addEventListener === 'function') {
    mobileMediaQuery.addEventListener('change', function () {
      if (isMobileLayout()) {
        bindViewportListeners();
        syncMobileViewport();
        if (isOpen) {
          lockBodyScroll();
        }
        return;
      }

      unlockBodyScroll();
      resetMobileViewportStyles();
      unbindViewportListeners();
    });
  } else if (typeof mobileMediaQuery.addListener === 'function') {
    mobileMediaQuery.addListener(function () {
      if (isMobileLayout()) {
        bindViewportListeners();
        syncMobileViewport();
        if (isOpen) {
          lockBodyScroll();
        }
        return;
      }

      unlockBodyScroll();
      resetMobileViewportStyles();
      unbindViewportListeners();
    });
  }

  if (isMobileLayout()) {
    bindViewportListeners();
    syncMobileViewport();
  }

  loadStoredHistory();
  loadPanelState();
})(jQuery);
