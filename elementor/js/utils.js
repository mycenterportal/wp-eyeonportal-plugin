function eyeonFormatTime(timeString) {
  if (timeString) {
    const [hours, minutes] = timeString.split(':');
    const ampm = hours >= 12 ? 'pm' : 'am';
    const formattedHours = (hours % 12) || 12; // If it's 0, set it to 12
    const formattedTime = `${formattedHours}:${minutes}${ampm}`;
    return formattedTime;
  }
  return timeString;
}

function eyeonFormatDate(dateInput = null) {
  if (!dateInput) return moment().format('MMM D, YYYY');
  if (typeof dateInput === 'string') {
    const trimmed = dateInput.trim();
    if (/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) {
      return moment.utc(trimmed, 'YYYY-MM-DD').format('MMM D, YYYY');
    }
  }
  return moment.utc(dateInput).format('MMM D, YYYY');
}

function getResponsiveBreakpoints() {
  var breakpoints = [];
  if (window.elementorFrontend && window.elementorFrontend.config && window.elementorFrontend.config.breakpoints) {
    breakpoints = window.elementorFrontend.config.breakpoints;
  }
  return breakpoints;
}

function getDayByDate(dateString, type = 'short') {
  if (type === null || type === undefined) type = 'long';
  const dateObj = new Date(dateString);
  const options = { weekday: type, timeZone: 'UTC' };
  const dayOfWeek = dateObj.toLocaleDateString('en-US', options);
  return dayOfWeek;
}